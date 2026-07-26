#!/usr/bin/env node
/**
 * Generate the theme's demo imagery from Build/Data/image-manifest.json.
 *
 * Two providers, chosen per pool rather than globally:
 *
 *   openai  — GPT Image 2. Cheaper per picture, and the photography and
 *             portraits carry no lettering, so its weakness does not bite.
 *   gemini  — Gemini's image model. Costs more per picture but renders text in
 *             an image reliably, which is the whole job for the logo wordmarks.
 *
 * Costs real money, so it never runs by accident: without --apply it prints
 * what it would generate and what that would cost, and writes nothing. Files
 * that already exist are skipped, so a re-run only fills gaps — regenerate one
 * deliberately with --force --only=<id>.
 *
 *   node Build/Scripts/generate-images.mjs                    plan and price it
 *   node Build/Scripts/generate-images.mjs --apply            generate what is missing
 *   node Build/Scripts/generate-images.mjs --apply --pool=logo
 *   node Build/Scripts/generate-images.mjs --apply --only=p03 --force
 *
 * Keys are read from the environment, never stored here:
 *   OPENAI_API_KEY  — in this lab: ddev exec vendor/bin/typo3 vault:retrieve openai_api_key
 *   GEMINI_API_KEY  — Google AI Studio key; billed to the project behind it
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const MANIFEST = path.join(EXT_ROOT, 'Build/Data/image-manifest.json');
const OUT_ROOT = path.join(EXT_ROOT, 'Resources/Public/Images');

/**
 * Published per-image prices, for the estimate only — the bill is whatever the
 * provider charges. Update when the rate cards move.
 */
const RATE = {openai: 0.05, gemini: 0.134};

const args = process.argv.slice(2);
const apply = args.includes('--apply');
const force = args.includes('--force');
const onlyPool = (args.find(a => a.startsWith('--pool=')) ?? '').split('=')[1] || null;
const onlyId = (args.find(a => a.startsWith('--only=')) ?? '').split('=')[1] || null;

const manifest = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'));

/** Every image the manifest asks for, flattened with its pool's settings. */
const jobs = [];
for (const [poolName, pool] of Object.entries(manifest.pools)) {
  if (onlyPool && onlyPool !== poolName) continue;
  for (const image of pool.images) {
    if (onlyId && onlyId !== image.id) continue;
    const style = manifest.style[pool.styleKey ?? 'shared'] ?? manifest.style.shared;
    const format = pool.format ?? 'jpeg';
    jobs.push({
      pool: poolName,
      provider: pool.provider,
      size: pool.size,
      format,
      id: image.id,
      prompt: `${style}\n\n${image.prompt}`,
      file: path.join(OUT_ROOT, poolName, `${image.id}.${format === 'jpeg' ? 'jpg' : format}`),
    });
  }
}

const pending = jobs.filter(job => force || !fs.existsSync(job.file));
const byProvider = {};
for (const job of pending) {
  byProvider[job.provider] = (byProvider[job.provider] ?? 0) + 1;
}

console.log(`manifest: ${jobs.length} images across ${Object.keys(manifest.pools).length} pools`);
console.log(`missing:  ${pending.length}`);
for (const [provider, count] of Object.entries(byProvider)) {
  console.log(`  ${provider.padEnd(7)} ${String(count).padStart(3)} × ~$${RATE[provider]} ≈ $${(count * RATE[provider]).toFixed(2)}`);
}
const total = Object.entries(byProvider).reduce((sum, [p, c]) => sum + c * RATE[p], 0);
console.log(`estimated total: ~$${total.toFixed(2)}`);

if (!apply) {
  console.log('\nNothing written. Re-run with --apply to generate.');
  process.exit(0);
}
if (pending.length === 0) {
  console.log('\nNothing to do.');
  process.exit(0);
}

const keys = {openai: process.env.OPENAI_API_KEY, gemini: process.env.GEMINI_API_KEY};
const missingKeys = [...new Set(pending.map(j => j.provider))].filter(p => !keys[p]);
if (missingKeys.length > 0) {
  console.error(`\nMissing ${missingKeys.map(p => `${p.toUpperCase()}_API_KEY`).join(' and ')}.`);
  console.error('Set it in the environment, or narrow the run with --pool=.');
  process.exit(1);
}

/*
 * Check the key looks like a key before spending the run on it.
 *
 * These are usually piped in from a secret store, and anything that store
 * prints alongside the value — a PHP warning, a trailing newline — travels with
 * it and produces an unsendable header. Without this guard the run discovers
 * that once per image, fifty times, and reports fifty failures for one cause.
 */
for (const provider of new Set(pending.map(j => j.provider))) {
  const key = keys[provider];
  if (/\s/.test(key) || key.length < 20) {
    console.error(`\n${provider.toUpperCase()}_API_KEY does not look like a key: ${key.length} characters${/\s/.test(key) ? ', contains whitespace' : ''}.`);
    console.error('Something was probably captured alongside it — a warning line, or a newline.');
    process.exit(1);
  }
}

/** @returns {Promise<Buffer>} the PNG bytes */
async function generateOpenAi(job) {
  const response = await fetch('https://api.openai.com/v1/images/generations', {
    method: 'POST',
    headers: {Authorization: `Bearer ${keys.openai}`, 'Content-Type': 'application/json'},
    body: JSON.stringify({model: 'gpt-image-2', prompt: job.prompt, size: job.size, n: 1}),
  });
  if (!response.ok) throw new Error(`${response.status} ${(await response.text()).slice(0, 200)}`);
  const data = await response.json();
  return Buffer.from(data.data[0].b64_json, 'base64');
}

async function generateGemini(job) {
  const model = process.env.GEMINI_IMAGE_MODEL ?? 'gemini-3-pro-image-preview';
  const response = await fetch(
    `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent`,
    {
      method: 'POST',
      headers: {'x-goog-api-key': keys.gemini, 'Content-Type': 'application/json'},
      body: JSON.stringify({contents: [{parts: [{text: job.prompt}]}]}),
    },
  );
  if (!response.ok) throw new Error(`${response.status} ${(await response.text()).slice(0, 200)}`);
  const data = await response.json();
  const part = (data.candidates?.[0]?.content?.parts ?? []).find(p => p.inlineData?.data);
  if (!part) throw new Error('no image in response');
  return Buffer.from(part.inlineData.data, 'base64');
}

/**
 * Both APIs return PNG. For a photograph that is roughly ten times the bytes of
 * a good JPEG for no visible gain, and these are committed to a repository, so
 * the conversion happens before anything is written rather than as a cleanup
 * pass somebody forgets to run. Logos stay PNG: flat colour and hard edges are
 * what PNG is for, and JPEG rings around lettering.
 */
function encode(bytes, job) {
  const recipe = job.format === 'jpeg'
    ? ['png:-', '-strip', '-interlace', 'Plane', '-quality', '82', 'jpg:-']
    // A flat wordmark uses a handful of colours; leaving it as a full-depth PNG
    // stores a megabyte of nothing. Quantising keeps the edges crisp and takes
    // roughly nine tenths of the bytes away.
    : ['png:-', '-strip', '-colors', '64', 'png:-'];

  return execFileSync('magick', recipe, {input: bytes, maxBuffer: 64 * 1024 * 1024});
}

let written = 0;
const failed = [];

for (const [index, job] of pending.entries()) {
  process.stdout.write(`[${index + 1}/${pending.length}] ${job.pool}/${job.id} … `);
  try {
    const raw = job.provider === 'gemini' ? await generateGemini(job) : await generateOpenAi(job);
    const bytes = encode(raw, job);
    fs.mkdirSync(path.dirname(job.file), {recursive: true});
    fs.writeFileSync(job.file, bytes);
    written++;
    console.log(`${(bytes.length / 1024).toFixed(0)} kB`);
  } catch (error) {
    failed.push({id: job.id, message: String(error.message ?? error)});
    console.log(`FAILED — ${String(error.message ?? error).slice(0, 120)}`);
  }
}

console.log(`\nwrote ${written} image(s) into Resources/Public/Images/`);
if (failed.length > 0) {
  console.log(`${failed.length} failed; re-running fills only the gaps.`);
  process.exit(1);
}
