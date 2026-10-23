import {strict as assert} from 'node:assert';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {chromium} from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const resource = name => fileURLToPath(new URL(`../../Resources/Public/${name}`, import.meta.url));
const browser = await chromium.launch({headless: true});
try {
  const context = await browser.newContext({viewport: {width: 1280, height: 720}});
  const page = await context.newPage();
  await page.clock.install({time: new Date(1700000065000)});
  await page.setContent(`<!doctype html><html lang="en"><head><title>Astryx 0.7 component checks</title></head><body data-astryx-theme="neutral"><main><h1>Component checks</h1>
    <button type="button" data-g-dialog-open="inspector">Open details</button>
    <dialog id="inspector" class="astryx-dialog astryx-drawer" data-side="end" data-size="md" aria-label="Details">
      <div class="astryx-dialog-header astryx-drawer-header"><h2>Details</h2>
        <form method="dialog"><button type="submit">Close</button></form>
      </div><p>Inspector content</p>
    </dialog>
    <time class="astryx-timer" datetime="PT0S" data-g-timer="clock" data-g-timer-start="1700000000000">0:00</time>
    <div id="records"><label><input id="row-one" type="checkbox" data-g-table-row>First row</label>
      <label><input id="row-two" type="checkbox" data-g-table-row>Second row</label></div>
    <label><input id="unrelated" type="checkbox" checked>Unrelated selection</label>
    <div class="astryx-table-selection-toolbar" role="group" aria-label="Bulk actions" hidden
      data-g-table-selection="records" data-g-count-label="%d selected">
      <button type="button">Export</button><span data-g-selected-count role="status">0 selected</span>
      <button type="button" data-g-selection-clear>Unselect all</button>
    </div>
  </main></body></html>`);
  for (const name of ['astryx-theme.css', 'astryx-components.css', 'astryx.css']) {
    await page.addStyleTag({content: readFileSync(resource(`Css/${name}`), 'utf8')});
  }
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addScriptTag({path: resource('Js/astryx.js')});
  assert.equal(await page.locator('.astryx-timer').innerText(), '1:05');
  assert.equal(await page.locator('.astryx-timer').getAttribute('datetime'), 'PT65S');
  await page.clock.runFor(1100);
  assert.equal(await page.locator('.astryx-timer').innerText(), '1:06');
  await page.evaluate(() => { window.astryxInit(); window.astryxInit(); });
  await page.clock.runFor(1000);
  assert.equal(await page.locator('.astryx-timer').innerText(), '1:07');

  await page.getByRole('button', {name: 'Open details'}).click();
  assert.equal(await page.locator('#inspector').evaluate(element => element.open), true);
  assert.equal(await page.locator('#inspector').evaluate(element => getComputedStyle(element).height), '720px');
  const accessibility = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
  assert.deepEqual(accessibility.violations.map(violation => violation.id), []);
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#inspector').evaluate(element => element.open), false);
  assert.equal(await page.getByRole('button', {name: 'Open details'}).evaluate(element => element === document.activeElement), true);
  await page.getByRole('button', {name: 'Open details'}).click();
  await page.getByRole('button', {name: 'Close', exact: true}).click();
  assert.equal(await page.locator('#inspector').evaluate(element => element.open), false);

  await page.check('#row-one');
  await page.clock.runFor(80);
  assert.equal(await page.locator('.astryx-table-selection-toolbar').isVisible(), true);
  assert.equal(await page.locator('[data-g-selected-count]').innerText(), '1 selected');
  await page.check('#row-two');
  await page.clock.runFor(80);
  assert.equal(await page.locator('[data-g-selected-count]').innerText(), '2 selected');
  await page.getByRole('button', {name: 'Unselect all'}).click();
  assert.equal(await page.locator('.astryx-table-selection-toolbar').isVisible(), false);
  assert.equal(await page.locator('#row-one').isChecked(), false);
  assert.equal(await page.locator('#row-two').isChecked(), false);
  assert.equal(await page.locator('#unrelated').isChecked(), true);
  assert.equal(await page.locator('#row-one').evaluate(element => element === document.activeElement), true);
  assert.deepEqual(errors, []);
  console.log('PASS: elapsed timer, idempotent initialisation, native drawer dismissal/focus, WCAG AA checks, scoped selection and clear action.');
} finally {
  await browser.close();
}
