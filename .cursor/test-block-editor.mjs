import { chromium } from 'playwright';
import { mkdirSync } from 'fs';
import { join } from 'path';
import { tmpdir } from 'os';

const BASE = process.env.CREDITS_E2E_BASE || 'http://127.0.0.1:8080';
const ART = process.env.CREDITS_E2E_ARTIFACTS || join(tmpdir(), 'credits-e2e-artifacts');
mkdirSync(ART, { recursive: true });

const siteDefaultType = (process.env.CREDITS_E2E_DEFAULT_TYPE || 'via').toLowerCase() === 'via' ? 'via' : 'source';
const siteDefaultLabel = siteDefaultType === 'via' ? 'Via' : 'Source';

const results = [];

function pass(msg) {
  results.push({ ok: true, msg });
  console.log('PASS:', msg);
}
function fail(msg) {
  results.push({ ok: false, msg });
  console.error('FAIL:', msg);
}

const browser = await chromium.launch({ headless: true, executablePath: process.env.CREDITS_E2E_CHROMIUM || undefined });
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });

try {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/, { timeout: 60000 });

  await page.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.edit-post-layout', { timeout: 60000 });
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await page.waitForTimeout(2000);

  // A fresh install shows a welcome guide that blocks clicks.
  await page.evaluate(() => wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false));
  await page.locator('.components-modal__screen-overlay').waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');

  await canvas.locator('h1.wp-block-post-title, [aria-label="Add title"]').first().click({ timeout: 30000 });
  await page.keyboard.type('Credits editor e2e');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(500);

  await canvas.locator('.block-editor-default-block-appender__content, p[data-empty="true"]').first().click({ timeout: 10000 });
  await page.keyboard.type('/credits');
  await page.waitForTimeout(800);
  await page.keyboard.press('Enter');
  await canvas.locator('.wp-block-credits-shortcode').first().waitFor({ timeout: 30000 });

  await canvas.locator('.wp-block-credits-shortcode').first().click();
  await page.waitForTimeout(800);

  const typeSelect = page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' }).locator('select').first();
  await typeSelect.waitFor({ timeout: 15000 });

  const options = await typeSelect.locator('option').allTextContents();
  if (options.some((t) => t.includes('Use site default'))) {
    pass('Credit Type dropdown includes "Use site default"');
  } else {
    fail(`Credit Type options missing site default: ${JSON.stringify(options)}`);
  }

  await typeSelect.selectOption('');
  await page.waitForTimeout(500);
  let badge = await canvas.locator('.wp-block-credits-shortcode .cre_cate').first().innerText();
  if (badge.trim() === siteDefaultLabel) {
    pass(`Preview shows ${siteDefaultLabel} when type is Use site default (site default = ${siteDefaultType})`);
  } else {
    fail(`Expected ${siteDefaultLabel} preview for site default ${siteDefaultType}, got "${badge}"`);
  }

  await page.screenshot({ path: join(ART, 'block-editor-site-default-via.png'), fullPage: false });
  pass('Screenshot: block-editor-site-default.png');

  await typeSelect.selectOption('source');
  await page.waitForTimeout(500);
  badge = await canvas.locator('.wp-block-credits-shortcode .cre_cate').first().innerText();
  if (badge.trim() === 'Source') {
    pass('Preview shows Source when type explicitly set');
  } else {
    fail(`Expected Source preview, got "${badge}"`);
  }

  await page.screenshot({ path: join(ART, 'block-editor-explicit-source.png'), fullPage: false });
  pass('Screenshot: block-editor-explicit-source.png');

  await typeSelect.selectOption('');
  await page.waitForTimeout(500);
  badge = await canvas.locator('.wp-block-credits-shortcode .cre_cate').first().innerText();
  if (badge.trim() === siteDefaultLabel) {
    pass(`Preview returns to ${siteDefaultLabel} after re-selecting Use site default`);
  } else {
    fail(`Expected ${siteDefaultLabel} again, got "${badge}"`);
  }

  const invalidNest = await page.evaluate(() => {
    const iframe = document.querySelector('iframe[name="editor-canvas"]');
    const doc = iframe && iframe.contentDocument;
    const ul = doc && doc.querySelector('ul.wp-block-credits-shortcode');
    if (!ul) return 'no-ul';
    return ul.querySelector('.components-panel, .block-editor-block-inspector') ? 'panel-inside-ul' : 'ok';
  });
  if (invalidNest === 'ok') {
    pass('Inspector panel is not nested inside preview <ul> in editor DOM');
  } else {
    fail(`Inspector nesting check: ${invalidNest}`);
  }

  // Edit values, save, reload the editor, then check the published page.
  const settingsPanel = page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' });
  await settingsPanel.getByLabel('Credit Type').selectOption('via');
  await settingsPanel.getByLabel('Source / Via Name').fill('E2E Source Name');
  await settingsPanel.getByLabel('Link URL').fill('https://example.com/e2e?a=1&b=2');
  await settingsPanel.getByLabel('Spacing').selectOption('spacious');

  await page.getByRole('button', { name: 'Advanced', exact: true }).click();
  await page.getByLabel('Additional CSS class(es)').fill('e2e-custom-class');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(500);

  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ status: 'publish' }));
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  await page.waitForFunction(() => {
    const editor = wp.data.select('core/editor');
    return !editor.isSavingPost() && editor.getCurrentPost().status === 'publish';
  }, null, { timeout: 60000 });
  const permalink = await page.evaluate(() => wp.data.select('core/editor').getPermalink());
  pass('Edited credit saved and published');

  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await page.waitForTimeout(2000);
  const reloaded = page.frameLocator('iframe[name="editor-canvas"]');
  await reloaded.locator('.wp-block-credits-shortcode').first().click();
  await page.waitForTimeout(800);

  const panel = page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' });
  const persisted = {
    name: await panel.getByLabel('Source / Via Name').inputValue(),
    link: await panel.getByLabel('Link URL').inputValue(),
    type: await panel.getByLabel('Credit Type').inputValue(),
    spacing: await panel.getByLabel('Spacing').inputValue(),
  };
  const expected = { name: 'E2E Source Name', link: 'https://example.com/e2e?a=1&b=2', type: 'via', spacing: 'spacious' };
  if (JSON.stringify(persisted) === JSON.stringify(expected)) {
    pass('Name, URL, type and spacing persist after saving and reloading the editor');
  } else {
    fail(`Values changed after reload: ${JSON.stringify(persisted)}`);
  }

  const editorClasses = await reloaded.locator('ul.wp-block-credits-shortcode').first().getAttribute('class');
  if ((editorClasses || '').split(/\s+/).includes('e2e-custom-class')) {
    pass('Custom CSS class persists after reload');
  } else {
    fail(`Custom class missing in editor after reload: ${editorClasses}`);
  }

  await page.goto(permalink, { waitUntil: 'domcontentloaded' });
  const published = page.locator('ul.wp-block-credits-shortcode');
  const publishedClasses = ((await published.first().getAttribute('class')) || '').split(/\s+/);
  const badgeText = (await published.first().locator('.cre_cate').innerText()).trim();
  const anchor = published.first().locator('a');
  const publishedHref = await anchor.getAttribute('href');
  const publishedName = (await anchor.innerText()).trim();
  const publishedTarget = await anchor.getAttribute('target');
  const ok =
    (await published.count()) === 1 &&
    publishedClasses.includes('e2e-custom-class') &&
    publishedClasses.includes('credits-spacing-spacious') &&
    publishedClasses.filter((c) => c === 'wp-block-credits-shortcode').length === 1 &&
    badgeText === 'Via' &&
    publishedName === 'E2E Source Name' &&
    publishedHref === 'https://example.com/e2e?a=1&b=2' &&
    publishedTarget === '_blank';
  if (ok) {
    pass('Published page shows the edited credit with its custom class');
  } else {
    fail(`Published output mismatch: ${JSON.stringify({ publishedClasses, badgeText, publishedName, publishedHref, publishedTarget })}`);
  }
  await page.screenshot({ path: join(ART, 'published-credit.png'), fullPage: false });
} catch (err) {
  fail(String(err));
  await page.screenshot({ path: join(ART, 'block-editor-error.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log('\nSummary:', results.filter((r) => r.ok).length, 'passed,', failed.length, 'failed');
process.exit(failed.length ? 1 : 0);
