#!/usr/bin/env node
/**
 * Look at every element the way a reviewer would, and write down what is wrong.
 *
 *   node Build/Scripts/design-review.mjs --harness
 *   node Build/Scripts/design-review.mjs --base-url=https://lab.ddev.site --urls=Build/Data/design-review-urls.json
 *   node Build/Scripts/design-review.mjs --base-url=… --only=hero-split-media --viewports=390,1440
 *
 * Output goes to Build/Reports/design-review/<date>/ (git-ignored): a PNG per
 * page per viewport per scheme, report.json, and report.md.
 *
 * Two modes, because they answer different questions
 * --------------------------------------------------
 *   --base-url   the real thing. Element-library preview URLs from a running
 *                TYPO3 (/?type=<previewTypeNum>&elPreview=<uid>&cHash=…), so
 *                what is measured is real content in real markup: axe sees the
 *                actual heading order, the actual alt text, the actual labels.
 *                Needs the lab.
 *   --harness    a static page this script builds from
 *                Build/Data/component-contract.json and the built CSS, with
 *                every component rendered in every declared modifier. It cannot
 *                see content problems - there is no content - but it measures
 *                everything the STYLESHEET decides: the type scale, spacing,
 *                radii, focus rings, hover and active states, reduced motion,
 *                and RTL. It runs anywhere, including CI, with no database.
 *
 * The probes are the same in both modes, so a finding means the same thing
 * either way.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import * as http from 'node:http';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

// --------------------------------------------------------------------- args

function parseArgs(argv) {
  const args = {
    harness: false,
    baseUrl: null,
    urls: null,
    only: null,
    viewports: [390, 768, 1440],
    schemes: ['light', 'dark'],
    theme: 'neutral',
    limit: Infinity,
  };
  for (const arg of argv) {
    const [flag, raw] = arg.includes('=') ? [arg.slice(0, arg.indexOf('=')), arg.slice(arg.indexOf('=') + 1)] : [arg, null];
    switch (flag) {
      case '--harness': args.harness = true; break;
      case '--base-url': args.baseUrl = raw; break;
      case '--urls': args.urls = raw; break;
      case '--only': args.only = raw.split(',').map(s => s.trim()); break;
      case '--viewports': args.viewports = raw.split(',').map(Number); break;
      case '--schemes': args.schemes = raw.split(','); break;
      case '--theme': args.theme = raw; break;
      case '--limit': args.limit = Number(raw); break;
      default:
        console.error(`Unknown argument: ${arg}`);
        process.exit(1);
    }
  }
  if (!args.harness && !args.baseUrl) {
    console.error(
      'Give it something to look at: --harness for the static component harness, '
      + 'or --base-url=<origin> --urls=<file> for element-library previews.',
    );
    process.exit(1);
  }
  return args;
}

const args = parseArgs(process.argv.slice(2));

// ------------------------------------------------------------------ tokens

const tokens = JSON.parse(fs.readFileSync(path.join(EXT_ROOT, 'Build/astryx/tokens.json'), 'utf8'));
const contract = JSON.parse(fs.readFileSync(path.join(EXT_ROOT, 'Build/Data/component-contract.json'), 'utf8'));

const rem = value => {
  const match = /^([\d.]+)rem$/.exec(String(value).trim());
  return match ? Number(match[1]) * 16 : null;
};
const px = value => {
  const match = /^([\d.]+)px$/.exec(String(value).trim());
  return match ? Number(match[1]) : null;
};

/**
 * The token NAMES the probe resolves in the page.
 *
 * Names rather than values, because a theme overrides them: measuring against
 * the base defaults would report every themed radius and every themed type step
 * as a violation. The page knows what the active theme resolved them to.
 */
const TOKEN_NAMES = {
  typeScale: [
    ...Object.keys(tokens.defaults.textSize),
    ...Object.keys(tokens.defaults.typeScale).filter(name => name.endsWith('-size')),
  ],
  spacing: Object.keys(tokens.defaults.spacing),
  radius: Object.keys(tokens.defaults.radius).filter(name => name !== '--radius-full'),
};

/**
 * Components whose whole point is a horizontal axis, so RTL is not cosmetic:
 * an inline start that stays on the left in Arabic is a broken component.
 * The probe carries its own copy because it runs inside the page.
 */
const RTL_SENSITIVE = ['carousel', 'breadcrumbs', 'stepper', 'split', 'toolbar'];

// ----------------------------------------------------------------- harness

/**
 * Build one static page carrying every component in every declared modifier.
 *
 * The markup is generated from the contract rather than from the Fluid
 * components on purpose: this page exists to measure what the STYLESHEET does
 * with a root class and a data attribute, and generating it from the same
 * contract the components render means a probe failure points at the CSS rather
 * than at a disagreement between two generators.
 */
function buildHarness() {
  const sections = [];

  for (const [key, component] of Object.entries(contract.components)) {
    const cases = [['(default)', {}]];
    for (const [token, {attribute, value}] of Object.entries(component.modifiers)) {
      cases.push([token, {[`data-${attribute}`]: value}]);
    }

    const rendered = cases.map(([label, attributes]) => {
      const attrs = Object.entries(attributes).map(([name, value]) => ` ${name}="${value}"`).join('');
      return `<div class="probe" data-probe="${key}" data-case="${label}">
        <span class="probe__label" data-harness-chrome>${label}</span>
        <div class="${component.rootClass}"${attrs}>
          <p class="astryx-text" data-type="body">Sample copy with <a class="astryx-link" href="#">a link inside a sentence</a>, because a bare standalone link is not the shape axe's target-size rule is written for.</p>
          <button class="astryx-button" type="button" data-variant="primary" data-size="md">A button</button>
        </div>
      </div>`;
    }).join('\n');

    sections.push(`<section class="probe-group" data-component="${key}" data-layer="${component.layer}">
      <h2 class="astryx-heading" data-level="2">${key}</h2>
      ${rendered}
    </section>`);
  }

  return `<!doctype html>
<html lang="en" class="__SCHEME__">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Astryx component harness</title>
  <link rel="stylesheet" href="/css/astryx-theme.css">
  <link rel="stylesheet" href="/css/astryx-components.css">
  <link rel="stylesheet" href="/css/astryx.css">
  <style>
    .probe-group { padding: 1rem 0; }
    .probe { padding: 0.5rem 0; }
    .probe__label { display: block; font-size: 11px; opacity: 0.6; }
  </style>
</head>
<body data-astryx-theme="${args.theme}">
  <main class="astryx-layout" data-size="lg">
    ${sections.join('\n')}
  </main>
</body>
</html>`;
}

/** Serve the harness and the built CSS, so the browser sees real @font-face and layers. */
function serveHarness() {
  const html = buildHarness();
  const cssDir = path.join(EXT_ROOT, 'Resources/Public/Css');

  const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/' || url.pathname === '/index.html') {
      const scheme = url.searchParams.get('scheme') === 'dark' ? 'dark' : 'light';
      response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
      response.end(html.replace('__SCHEME__', scheme));
      return;
    }
    if (url.pathname.startsWith('/css/')) {
      const file = path.join(cssDir, url.pathname.slice('/css/'.length));
      if (file.startsWith(cssDir) && fs.existsSync(file)) {
        response.writeHead(200, {'Content-Type': 'text/css; charset=utf-8'});
        response.end(fs.readFileSync(file));
        return;
      }
    }
    if (url.pathname.startsWith('/files/')) {
      const file = path.join(cssDir, url.pathname.slice(1));
      if (file.startsWith(cssDir) && fs.existsSync(file)) {
        response.writeHead(200, {'Content-Type': 'font/woff2'});
        response.end(fs.readFileSync(file));
        return;
      }
    }
    response.writeHead(404);
    response.end('not found');
  });

  return new Promise(resolve => {
    server.listen(0, '127.0.0.1', () => resolve({server, port: server.address().port}));
  });
}

// ------------------------------------------------------------------ probes

/**
 * Every probe runs in the page. They are written as one function so the page is
 * only walked once per viewport/scheme, which matters at 250 x 3 x 2.
 */
const PROBE_SOURCE = ({tokenNames, tolerance}) => {
  const findings = [];

  /*
   * The allowed values are read out of the PAGE, not out of tokens.json.
   * A theme overrides --radius-element and --font-size-base, so the base
   * defaults would call every themed value a violation — which is how the first
   * run produced 6424 "radius" findings for a radius that was correct.
   */
  const root = getComputedStyle(document.body);
  const resolve = names => {
    const values = [];
    for (const name of names) {
      const raw = root.getPropertyValue(name).trim();
      if (!raw) continue;
      const probe = document.createElement('div');
      probe.style.position = 'absolute';
      probe.style.visibility = 'hidden';
      probe.style.width = raw;
      document.body.append(probe);
      const px = parseFloat(getComputedStyle(probe).width);
      probe.remove();
      if (Number.isFinite(px)) values.push(px);
    }
    return values;
  };

  const typeScale = resolve(tokenNames.typeScale);
  const spacing = resolve(tokenNames.spacing);
  const radii = resolve(tokenNames.radius);
  const record = (rule, element, detail) => {
    findings.push({
      rule,
      selector: element ? (element.tagName.toLowerCase()
        + (element.className && typeof element.className === 'string'
          ? '.' + element.className.trim().split(/\s+/).slice(0, 2).join('.')
          : '')) : null,
      detail,
    });
  };
  const near = (value, allowed) => allowed.some(candidate => Math.abs(candidate - value) <= tolerance);

  /*
   * The harness's own scaffolding is not part of the design system, and a probe
   * that measures its own labels reports nothing useful.
   */
  const elements = [...document.querySelectorAll('body *')].filter(element => {
    if (element.closest('[data-harness-chrome]')) return false;
    const style = getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden';
  });

  for (const element of elements) {
    const style = getComputedStyle(element);
    const hasOwnText = [...element.childNodes].some(
      node => node.nodeType === 3 && node.textContent.trim() !== '',
    );

    /*
     * Only elements that DECIDE their own size are measured. A <span> inside a
     * display heading inherits 3.2rem and is not making a type-scale decision;
     * measuring it reports the parent's value twice and, in a harness that
     * nests sample content inside every component, hundreds of times.
     */
    const parentStyle = element.parentElement ? getComputedStyle(element.parentElement) : null;
    const setsOwnType = parentStyle === null
      || style.fontSize !== parentStyle.fontSize
      || style.lineHeight !== parentStyle.lineHeight;

    // --- the type scale ---------------------------------------------------
    if (hasOwnText && setsOwnType) {
      const fontSize = parseFloat(style.fontSize);
      if (!near(fontSize, typeScale)) {
        record('type-scale', element, `font-size ${fontSize}px is not in the scale`);
      }
      const lineHeight = style.lineHeight === 'normal' ? null : parseFloat(style.lineHeight);
      if (lineHeight !== null && lineHeight < fontSize * 1.1) {
        record('line-height', element, `line-height ${lineHeight}px is under 1.1x the font size`);
      }
    }

    // --- spacing ----------------------------------------------------------
    for (const side of ['Top', 'Right', 'Bottom', 'Left']) {
      for (const box of ['padding', 'margin']) {
        // An auto margin is a centring instruction, not a measurement.
        if (box === 'margin' && element.style[`margin${side}`] === 'auto') continue;
        const computedAuto = getComputedStyle(element).getPropertyValue(`margin-${side.toLowerCase()}`);
        if (box === 'margin' && computedAuto === 'auto') continue;
        const value = parseFloat(style[`${box}${side}`]);
        if (!value || value < 0) continue;
        // Centring inside a container resolves to a large, meaningless number.
        if (box === 'margin' && value > 64) continue;
        /*
         * Above about 40px the value is almost always a fluid clamp() — section
         * rhythm that is meant to interpolate with the viewport and therefore
         * lands on a fraction. Below it, a value that is neither a token nor a
         * multiple of four is a hand-typed number.
         */
        if (value > 40) continue;
        if (!near(value, spacing) && value % 4 !== 0) {
          record('spacing', element, `${box}-${side.toLowerCase()} ${value}px is neither a token nor a multiple of 4`);
        }
      }
    }

    // --- radii ------------------------------------------------------------
    for (const corner of ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft']) {
      const raw = style[`border${corner}Radius`];
      if (!raw || raw === '0px') continue;
      if (raw.includes('%') || parseFloat(raw) >= 999) continue; // a pill
      const value = parseFloat(raw);
      if (!near(value, radii)) {
        record('radius', element, `border-radius ${raw} is not a --radius-* token`);
      }
    }
  }

  // --- focus ---------------------------------------------------------------
  const focusable = [...document.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select, textarea, summary, [tabindex]:not([tabindex="-1"])',
  )].slice(0, 60);

  for (const element of focusable) {
    const before = getComputedStyle(element);
    const resting = `${before.outlineWidth}|${before.outlineStyle}|${before.boxShadow}`;
    element.focus();
    const after = getComputedStyle(element);
    const focused = `${after.outlineWidth}|${after.outlineStyle}|${after.boxShadow}`;
    element.blur();
    if (resting === focused) {
      record('focus-visible', element, 'focusing it changes neither the outline nor the box-shadow');
    }
  }

  // --- reduced motion -------------------------------------------------------
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
    for (const element of elements) {
      const style = getComputedStyle(element);
      const durations = [style.animationDuration, style.transitionDuration]
        .flatMap(value => value.split(',').map(part => parseFloat(part) || 0));
      const longest = Math.max(0, ...durations);
      if (longest > 0.05) {
        record('reduced-motion', element, `${longest}s of animation survives prefers-reduced-motion: reduce`);
      }
    }
  }

  /*
   * --- RTL ------------------------------------------------------------------
   *
   * Not "is anything off-screen": in RTL the document's own origin moves, so
   * absolute positions are negative for perfectly correct layouts and the first
   * version of this probe reported 694 of them. The two questions that actually
   * matter are whether the page overflows sideways at all, and whether the
   * components built on a horizontal axis genuinely flipped it.
   */
  if (document.documentElement.dir === 'rtl') {
    const docWidth = document.documentElement.clientWidth;
    if (document.documentElement.scrollWidth > docWidth + 2) {
      record('rtl-overflow', document.documentElement,
        `the page scrolls ${Math.round(document.documentElement.scrollWidth - docWidth)}px sideways in RTL`);
    }

    for (const element of elements) {
      // An exact root-class match. `astryx-carousel-track` is a different
      // component with a different layout, and a loose test caught it too.
      const classes = typeof element.className === 'string' ? element.className.trim().split(/\s+/) : [];
      if (!classes.some(name => ['astryx-carousel', 'astryx-breadcrumbs', 'astryx-stepper', 'astryx-split', 'astryx-toolbar'].includes(name))) continue;

      const children = [...element.children].filter(child => child.getBoundingClientRect().width > 0);
      if (children.length < 2) continue;

      const first = children[0].getBoundingClientRect();
      const last = children[children.length - 1].getBoundingClientRect();
      const style = getComputedStyle(element);
      const horizontal = style.display.includes('flex')
        ? !style.flexDirection.startsWith('column')
        : style.display.includes('grid') ? style.gridAutoFlow.includes('column') : false;
      if (!horizontal) continue;

      if (first.left < last.left) {
        record('rtl-direction', element,
          'its first child is still on the left in RTL — the inline axis did not flip');
      }
    }
  }

  return findings;
};

/**
 * Whether a hover and an active affordance are DECLARED for an element.
 *
 * The first version moved a real mouse and compared computed styles. That is
 * the more direct question, but it is not reliably answerable: hovering is
 * asynchronous, a headless page can report the move before the style
 * recalculates, and the sample has to be capped or the run takes an hour. Both
 * make it report "no hover state" for links that plainly have one.
 *
 * Reading the stylesheets instead asks the question the design system actually
 * cares about — is there a rule at all — and answers it for every element,
 * deterministically, in one pass.
 */
const AFFORDANCE_SOURCE = () => {
  const findings = [];

  /** Every selector in every same-origin sheet that carries :hover or :active. */
  const collect = pseudo => {
    const selectors = [];
    const walk = rules => {
      for (const rule of rules) {
        if (rule.cssRules) walk(rule.cssRules);
        if (!rule.selectorText || !rule.selectorText.includes(pseudo)) continue;
        for (const part of rule.selectorText.split(',')) {
          if (!part.includes(pseudo)) continue;
          // `a:hover span` — the thing that must match is the part carrying the
          // pseudo-class, with the pseudo-class removed.
          const stripped = part.replaceAll(pseudo, '').trim();
          if (stripped) selectors.push(stripped);
        }
      }
    };
    for (const sheet of document.styleSheets) {
      try {
        walk(sheet.cssRules);
      } catch {
        // A cross-origin sheet cannot be read. None of ours are.
      }
    }
    return selectors;
  };

  const hover = collect(':hover');
  const active = collect(':active');
  const matchesAny = (element, selectors) => selectors.some(selector => {
    try {
      return element.matches(selector) || element.closest(selector) !== null;
    } catch {
      return false;
    }
  });

  const interactive = [...document.querySelectorAll(
    '.astryx-button, .astryx-link, .astryx-icon-button, .astryx-clickable-card, .astryx-item[data-interactive]',
  )].filter(element => !element.closest('[data-harness-chrome]'));

  const seen = new Set();
  for (const element of interactive) {
    const key = element.className + '|' + element.getAttribute('data-variant');
    if (seen.has(key)) continue;
    seen.add(key);

    const describe = element.tagName.toLowerCase() + '.' + String(element.className).trim().split(/\s+/)[0]
      + (element.dataset.variant ? `[data-variant="${element.dataset.variant}"]` : '');

    if (!matchesAny(element, hover)) {
      findings.push({rule: 'hover-state', selector: describe, detail: 'no :hover rule anywhere declares an affordance for it'});
    }
    if (!matchesAny(element, active)) {
      findings.push({rule: 'active-state', selector: describe, detail: 'no :active rule — a press gives no feedback'});
    }
  }

  return findings;
};

// -------------------------------------------------------------------- pages

/** @return list<{id: string, url: string}> */
function collectPages(baseUrlForHarness) {
  if (args.harness) {
    return [
      {id: 'harness', url: `${baseUrlForHarness}/`},
      {id: 'harness-rtl', url: `${baseUrlForHarness}/?dir=rtl`, rtl: true},
    ];
  }

  if (!args.urls) {
    console.error(
      '--base-url needs --urls=<file>: a JSON array of entries carrying a URL and a name.\n'
      + '\n'
      + '  [{"id": "hero-split-media", "path": "/?type=…&elPreview=…&cHash=…"}]\n'
      + '\n'
      + 'Produce one from a running instance with\n'
      + '  vendor/bin/typo3 desiderio:library:urls --site=<identifier> --json > '
      + 'Build/Data/design-review-urls.json\n'
      + 'That command writes {cType, uid, group, url} rather than {id, path}; both\n'
      + 'shapes are accepted, and a cType is used as the name when no id is given.',
    );
    process.exit(1);
  }

  const file = path.resolve(EXT_ROOT, args.urls);
  if (!fs.existsSync(file)) {
    console.error(`No URL list at ${file}`);
    process.exit(1);
  }

  /*
   * Two shapes are accepted, because the obvious way to produce this list is to
   * redirect `desiderio:library:urls --json` into it, and that command names an
   * entry by its cType rather than by an `id`.
   */
  const named = entry => entry.id ?? entry.cType ?? String(entry.uid ?? 'unnamed');

  let pages = JSON.parse(fs.readFileSync(file, 'utf8'))
    .filter(entry => entry.url || entry.path);
  if (args.only) pages = pages.filter(entry => args.only.includes(named(entry)));
  return pages.slice(0, args.limit).map(entry => ({
    id: named(entry),
    url: entry.url ?? new URL(entry.path, args.baseUrl).toString(),
  }));
}

// ---------------------------------------------------------------------- run

const {chromium} = await import('playwright');
let AxeBuilder = null;
try {
  ({default: AxeBuilder} = await import('@axe-core/playwright'));
} catch {
  console.warn('@axe-core/playwright is not installed — accessibility checks are skipped.');
}

const date = new Date().toISOString().slice(0, 10);
const outDir = path.join(EXT_ROOT, 'Build/Reports/design-review', date);
fs.mkdirSync(outDir, {recursive: true});

let harness = null;
let harnessBase = null;
if (args.harness) {
  harness = await serveHarness();
  harnessBase = `http://127.0.0.1:${harness.port}`;
}

const pages = collectPages(harnessBase);
const browser = await chromium.launch();
const results = [];

try {
  for (const scheme of args.schemes) {
    for (const viewport of args.viewports) {
      for (const reducedMotion of [false, true]) {
        // Reduced motion only needs one viewport: it is a stylesheet decision,
        // not a layout one, and running it three times says the same thing.
        if (reducedMotion && viewport !== args.viewports[0]) continue;

        const context = await browser.newContext({
          viewport: {width: viewport, height: 900},
          colorScheme: scheme,
          reducedMotion: reducedMotion ? 'reduce' : 'no-preference',
          deviceScaleFactor: 1,
        });

        for (const target of pages) {
          const page = await context.newPage();
          const label = `${target.id}-${viewport}-${scheme}${reducedMotion ? '-reduced' : ''}`;

          try {
            await page.goto(target.url, {waitUntil: 'networkidle', timeout: 30_000});
          } catch (error) {
            results.push({page: target.id, viewport, scheme, reducedMotion, error: String(error.message ?? error)});
            await page.close();
            continue;
          }

          if (target.rtl) {
            await page.evaluate(() => {
              document.documentElement.dir = 'rtl';
              document.documentElement.lang = 'ar';
            });
            await page.waitForTimeout(50);
          }

          const findings = await page.evaluate(PROBE_SOURCE, {
            tokenNames: TOKEN_NAMES,
            tolerance: 0.6,
          });

          if (!reducedMotion) {
            findings.push(...await page.evaluate(AFFORDANCE_SOURCE));
          }

          let violations = [];
          if (AxeBuilder && !reducedMotion) {
            try {
              const axe = await new AxeBuilder({page})
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'])
                .analyze();
              violations = axe.violations.map(violation => ({
                id: violation.id,
                impact: violation.impact,
                help: violation.help,
                nodes: violation.nodes.length,
                sample: violation.nodes[0]?.html?.slice(0, 160) ?? null,
              }));
            } catch (error) {
              violations = [{id: 'axe-failed', impact: 'unknown', help: String(error.message ?? error), nodes: 0}];
            }
          }

          if (!reducedMotion) {
            await page.screenshot({
              path: path.join(outDir, `${label}.png`),
              fullPage: true,
            });
          }

          results.push({page: target.id, viewport, scheme, reducedMotion, findings, violations});
          await page.close();
        }

        await context.close();
      }
    }
  }
} finally {
  await browser.close();
  if (harness) harness.server.close();
}

// ------------------------------------------------------------------ report

const byRule = new Map();
const byAxeRule = new Map();
for (const result of results) {
  for (const finding of result.findings ?? []) {
    const key = finding.rule;
    if (!byRule.has(key)) byRule.set(key, []);
    byRule.get(key).push({...finding, page: result.page, viewport: result.viewport, scheme: result.scheme});
  }
  for (const violation of result.violations ?? []) {
    if (!byAxeRule.has(violation.id)) byAxeRule.set(violation.id, {...violation, pages: new Set(), total: 0});
    const entry = byAxeRule.get(violation.id);
    entry.pages.add(result.page);
    entry.total += violation.nodes;
  }
}

fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify({
  date,
  mode: args.harness ? 'harness' : 'base-url',
  baseUrl: args.baseUrl,
  viewports: args.viewports,
  schemes: args.schemes,
  pages: pages.length,
  results,
}, null, 2) + '\n');

const lines = [
  `# Design review — ${date}`,
  '',
  `Mode: **${args.harness ? 'static component harness' : args.baseUrl}**  `,
  `Pages: ${pages.length} · viewports ${args.viewports.join(', ')} · schemes ${args.schemes.join(', ')}`,
  '',
  '## Computed-style findings',
  '',
];

if (byRule.size === 0) {
  lines.push('_None._', '');
} else {
  lines.push('| rule | findings | example |', '| --- | --- | --- |');
  for (const [rule, findings] of [...byRule].sort((a, b) => b[1].length - a[1].length)) {
    const example = findings[0];
    lines.push(`| \`${rule}\` | ${findings.length} | ${example.selector ?? '—'}: ${example.detail} |`);
  }
  lines.push('');
  for (const [rule, findings] of byRule) {
    lines.push(`### ${rule} (${findings.length})`, '');
    const unique = new Map();
    for (const finding of findings) {
      const key = `${finding.selector}|${finding.detail}`;
      unique.set(key, (unique.get(key) ?? 0) + 1);
    }
    for (const [key, count] of [...unique].sort((a, b) => b[1] - a[1]).slice(0, 20)) {
      const [selector, detail] = key.split('|');
      lines.push(`- \`${selector}\` — ${detail}${count > 1 ? ` (${count}x)` : ''}`);
    }
    lines.push('');
  }
}

lines.push('## Accessibility (axe, WCAG 2.2 AA)', '');
if (byAxeRule.size === 0) {
  lines.push('_No violations._', '');
} else {
  lines.push('| rule | impact | nodes | pages | help |', '| --- | --- | --- | --- | --- |');
  for (const [id, entry] of [...byAxeRule].sort((a, b) => b[1].total - a[1].total)) {
    lines.push(`| \`${id}\` | ${entry.impact} | ${entry.total} | ${entry.pages.size} | ${entry.help} |`);
  }
  lines.push('');
}

const failures = results.filter(result => result.error);
if (failures.length > 0) {
  lines.push('## Pages that would not load', '');
  for (const failure of failures) {
    lines.push(`- ${failure.page} (${failure.viewport}px, ${failure.scheme}): ${failure.error}`);
  }
  lines.push('');
}

fs.writeFileSync(path.join(outDir, 'report.md'), lines.join('\n'));

const totalFindings = [...byRule.values()].reduce((sum, list) => sum + list.length, 0);
const totalViolations = [...byAxeRule.values()].reduce((sum, entry) => sum + entry.total, 0);
console.log(`Build/Reports/design-review/${date}/report.md`);
console.log(`  ${pages.length} page(s) x ${args.viewports.length} viewport(s) x ${args.schemes.length} scheme(s)`);
console.log(`  ${totalFindings} computed-style finding(s) across ${byRule.size} rule(s)`);
console.log(`  ${totalViolations} axe violation(s) across ${byAxeRule.size} rule(s)`);
if (failures.length > 0) console.log(`  ${failures.length} page load failure(s)`);

process.exitCode = totalFindings + totalViolations > 0 ? 1 : 0;
