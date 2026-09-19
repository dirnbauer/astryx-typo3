#!/usr/bin/env node
/**
 * Is the vendored Astryx pin still the newest upstream RELEASE?
 *
 *   npm run check:upstream
 *
 * Exit 0: the pin is current. Exit 1: a newer release exists. Exit 2: the
 * check could not be made (no network, an unreadable pin) — which is a
 * different thing from "you are behind" and says so.
 *
 * Run it by hand, or from a scheduled CI job. Deliberately NOT a test: a unit
 * suite that reaches the network fails on a train, and a gate that fails for
 * reasons unrelated to the change in front of it is a gate people learn to
 * ignore.
 *
 * Released tags only
 * ------------------
 * Two sources are asked, because they can disagree and the disagreement is
 * the interesting part: the git tags say what upstream has cut, and npm's
 * `latest` dist-tag says what upstream has published. A canary version
 * published to npm is not a release and is ignored here, as is a commit on
 * `main` that no tag points at — see Documentation/Developer/UpstreamSync.rst
 * for why, and for how a main-only commit is checked before it is considered.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const REPO = 'facebook/astryx';
const PACKAGE = '@astryxdesign/core';
const NOTICES = path.join(EXT_ROOT, 'THIRD_PARTY_NOTICES.md');

/** `1.2.3` → [1, 2, 3]. A pre-release suffix makes it not a release. */
function release(version) {
  const match = /^v?(\d+)\.(\d+)\.(\d+)$/.exec(String(version).trim());
  return match === null ? null : [Number(match[1]), Number(match[2]), Number(match[3])];
}

function newer(a, b) {
  for (let i = 0; i < 3; i++) {
    if (a[i] !== b[i]) return a[i] > b[i];
  }
  return false;
}

function fail(reason) {
  console.error(`Could not check the Astryx pin: ${reason}`);
  process.exit(2);
}

// ---------------------------------------------------------------- the pin

if (!fs.existsSync(NOTICES)) fail(`${path.relative(EXT_ROOT, NOTICES)} is missing`);
const pinned = /Pinned release:\s*`v?([\d.]+)`/.exec(fs.readFileSync(NOTICES, 'utf8'));
if (pinned === null) fail('THIRD_PARTY_NOTICES.md has no "Pinned release:" line');
const pin = release(pinned[1]);
if (pin === null) fail(`the pinned release "${pinned[1]}" is not a plain x.y.z version`);

// ------------------------------------------------------------ the sources

/** The newest release tag upstream has cut. */
function newestTag() {
  // `git ls-remote`, not the GitHub API: the API refuses anonymous callers
  // after sixty requests an hour, which is how a check nobody can run starts.
  const listing = execFileSync(
    'git',
    ['ls-remote', '--tags', '--refs', `https://github.com/${REPO}.git`],
    {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']},
  );

  const versions = listing
    .split('\n')
    .map(line => /refs\/tags\/(\S+)$/.exec(line.trim())?.[1])
    .map(tag => (tag === undefined ? null : release(tag)))
    .filter(version => version !== null);

  return versions.sort((a, b) => (newer(a, b) ? -1 : 1))[0] ?? null;
}

/** What upstream has published to npm under the `latest` dist-tag. */
async function newestPublished() {
  const response = await fetch(`https://registry.npmjs.org/${encodeURIComponent(PACKAGE)}`, {
    headers: {Accept: 'application/vnd.npm.install-v1+json'},
  });
  if (!response.ok) throw new Error(`registry.npmjs.org answered ${response.status}`);

  return release((await response.json())['dist-tags']?.latest ?? '');
}

let tag = null;
let published = null;
try {
  tag = newestTag();
  published = await newestPublished();
} catch (error) {
  fail(String(error.message ?? error));
}

// ------------------------------------------------------------------ verdict

const show = version => (version === null ? 'unknown' : `v${version.join('.')}`);
const behind = [
  tag !== null && newer(tag, pin) ? `tag ${show(tag)}` : null,
  published !== null && newer(published, pin) ? `npm latest ${show(published)}` : null,
].filter(Boolean);

if (behind.length > 0) {
  console.error(
    `Astryx has moved: pinned ${show(pin)}, upstream has ${behind.join(' and ')}. `
    + 'Refresh with: node Build/Scripts/fetch-astryx-manifest.mjs --tag <tag>',
  );
  process.exit(1);
}

console.log(`Astryx pin ${show(pin)} is current — newest tag ${show(tag)}, npm latest ${show(published)}.`);
