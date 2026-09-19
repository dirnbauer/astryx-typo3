#!/usr/bin/env node
/**
 * Write Build/Data/component-contract.json and Build/Data/component-map.json
 * from what is actually on disk.
 *
 *   node Build/Scripts/sync-component-contract.mjs          rewrite both files
 *   node Build/Scripts/sync-component-contract.mjs --check  fail if stale
 *
 * Both files used to be hand-maintained, and both said things the filesystem
 * already said: which layer a component is in, what it is called, which class
 * it renders. Three unit tests existed only to catch the two copies drifting
 * apart. They are derived here instead, so there is one copy and nothing to
 * keep in step.
 *
 * What is NOT derivable stays declared, and there is very little of it:
 * ROOT_CLASS below, for the page chrome that predates the Astryx vocabulary,
 * and ALIASES / NOT_RENDERED, for the places where upstream's inventory and
 * this extension's component library do not line up one to one.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const COMPONENT_ROOT = path.join(EXT_ROOT, 'Resources/Private/Components');
const CONTRACT = path.join(EXT_ROOT, 'Build/Data/component-contract.json');
const MAP = path.join(EXT_ROOT, 'Build/Data/component-map.json');
const INVENTORY = path.join(EXT_ROOT, 'Build/astryx/components.json');

const LAYERS = ['Layout', 'Atom', 'Molecule', 'Organism'];

/**
 * Root classes that are not the kebab-case of the component name.
 *
 * All of them are page chrome rather than Astryx components: Astryx has no
 * SiteHeader, and these classes were in shipped stylesheets before the
 * component layer existed. Renaming them would break a site's own CSS to buy
 * nothing but tidiness.
 */
const ROOT_CLASS = {
  Container: 'astryx-layout',
  CodeBlock: 'astryx-codeblock',
  SiteHeader: 'astryx-header',
  SiteFooter: 'astryx-footer',
  SearchForm: 'astryx-search',
  ThemeSwitcher: 'astryx-scheme-toggle',
  ErrorMessage: 'astryx-error',
  PageSidebar: 'astryx-sidebar-layout',
};

/**
 * Upstream components this library renders through a component of another name,
 * with the reason. Checked: an alias must point at a component that exists.
 */
const ALIASES = {
  Layout: ['Container', 'Upstream\'s Layout is the max-width container; this library calls it what it does.'],
  BaseTypeahead: ['Typeahead', 'Upstream\'s unstyled base renders the same DOM as Typeahead itself.'],
};

/**
 * Upstream components with no Fluid component, and why there can be none.
 *
 * Every entry is a React runtime concept that renders no DOM of its own. A
 * server-rendered library cannot have them, and listing them here — rather than
 * letting the gap go unremarked — is what makes the coverage claim checkable.
 */
const NOT_RENDERED = {
  AppShell: 'Composes LayoutHeader, LayoutContent and LayoutFooter. In TYPO3 the page template is that composition.',
  InternationalizationProvider: 'React context carrying locale and text direction. TYPO3 sets both on <html>.',
  LinkProvider: 'React context swapping the router\'s link component. Fluid links through f:link.typolink.',
  MediaTheme: 'React context scoping a theme to a media query. The theme layer does this in CSS.',
  Theme: 'React context applying a theme. The site set applies it as a data attribute on <body>.',
};

const kebab = name => name
  .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
  .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
  .toLowerCase();

const lower = name => name.charAt(0).toLowerCase() + name.slice(1);

/** @returns {{key: string, layer: string, name: string, rootClass: string, file: string}[]} */
function componentsOnDisk() {
  const found = [];
  for (const layer of LAYERS) {
    const dir = path.join(COMPONENT_ROOT, layer);
    if (!fs.existsSync(dir)) continue;
    for (const name of fs.readdirSync(dir).sort()) {
      const file = path.join(dir, name, `${name}.fluid.html`);
      if (!fs.existsSync(file)) {
        console.error(`! ${layer}/${name} has no ${name}.fluid.html`);
        process.exitCode = 1;
        continue;
      }
      found.push({
        key: `${lower(layer)}.${lower(name)}`,
        layer,
        name,
        rootClass: ROOT_CLASS[name] ?? `astryx-${kebab(name)}`,
        file,
      });
    }
  }
  return found;
}

const components = componentsOnDisk();

// The derived root class is a claim about the markup, so it gets checked here
// rather than in a test that reads a file this script just wrote.
for (const component of components) {
  const source = fs.readFileSync(component.file, 'utf8').replace(/<f:comment>[\s\S]*?<\/f:comment>/g, '');
  if (!source.includes(component.rootClass)) {
    console.error(
      `! ${component.layer}/${component.name} never renders ${component.rootClass}. `
      + 'Either the markup is wrong, or the name is — and if the class is deliberately different, '
      + 'add it to ROOT_CLASS in this script with the reason.',
    );
    process.exitCode = 1;
  }
}

const contract = {
  _comment:
    'GENERATED by Build/Scripts/sync-component-contract.mjs from Resources/Private/Components/. '
    + 'One row per Fluid component: the layer it lives in and the single root class it renders. '
    + 'Do not edit by hand — run the script.',
  _attributes:
    'Modifiers are data attributes on the root, never classes. The stylesheet is the authority on '
    + 'which values exist for a component; Tests/Unit/AtomicDesignConformanceTest.php forbids the '
    + 'bare-class form outright, and Build/Scripts/design-review.mjs reads the built CSS to find '
    + 'every case worth probing.',
  components: Object.fromEntries(
    components.map(({key, layer, name, rootClass}) => [key, {layer, name, rootClass}]),
  ),
};

const byName = new Map(components.map(component => [component.name, component]));
const inventory = JSON.parse(fs.readFileSync(INVENTORY, 'utf8'));
const upstreamNames = [...new Set(inventory.components.map(entry => entry.component))].sort();
const packageOf = new Map(inventory.components.map(entry => [entry.component, entry.package]));

const mapped = {};
const unmapped = [];
for (const upstream of upstreamNames) {
  if (NOT_RENDERED[upstream]) {
    mapped[upstream] = {upstream, package: packageOf.get(upstream), rendered: false, reason: NOT_RENDERED[upstream]};
    continue;
  }
  const [target, reason] = ALIASES[upstream] ?? [upstream, ''];
  const component = byName.get(target);
  if (!component) {
    unmapped.push(upstream);
    continue;
  }
  mapped[upstream] = {
    upstream,
    package: packageOf.get(upstream),
    component: component.key,
    tag: `a:${component.key}`,
    rootClass: component.rootClass,
    ...(reason ? {note: reason} : {}),
  };
}

if (unmapped.length > 0) {
  console.error(
    `! ${unmapped.length} upstream component(s) have neither a Fluid component nor a stated reason:\n  `
    + unmapped.join('\n  ')
    + '\nAdd the component, or add it to NOT_RENDERED in this script with the reason there can be none.',
  );
  process.exitCode = 1;
}

const map = {
  _comment:
    'GENERATED by Build/Scripts/sync-component-contract.mjs. Every component in the vendored Astryx '
    + 'inventory, and the Fluid component that renders it here — or, for the React runtime concepts '
    + 'that render no DOM at all, the reason there is none. Do not edit by hand.',
  _upstream: inventory.release,
  components: mapped,
};

const files = [[CONTRACT, contract], [MAP, map]];

if (process.argv.includes('--check')) {
  let stale = false;
  for (const [file, data] of files) {
    if (fs.readFileSync(file, 'utf8') !== JSON.stringify(data, null, 2) + '\n') {
      console.error(`! ${path.relative(EXT_ROOT, file)} is stale. Run: node Build/Scripts/sync-component-contract.mjs`);
      stale = true;
    }
  }
  if (stale) process.exit(1);
  console.log(`contract and map are current — ${components.length} components, ${upstreamNames.length} upstream entries`);
  process.exit(process.exitCode ?? 0);
}

for (const [file, data] of files) fs.writeFileSync(file, JSON.stringify(data, null, 2) + '\n');

const rendered = Object.values(mapped).filter(entry => entry.rendered !== false).length;
console.log(`${components.length} components across ${LAYERS.length} layers`);
for (const layer of LAYERS) {
  console.log(`  ${layer.padEnd(9)} ${components.filter(c => c.layer === layer).length}`);
}
console.log(`${rendered}/${upstreamNames.length} upstream components render through one of them`);
console.log(`${Object.keys(NOT_RENDERED).length} render no DOM upstream either, with the reason recorded`);
