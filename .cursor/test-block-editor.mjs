import { chromium } from 'playwright';
import { mkdirSync } from 'fs';
import { join } from 'path';

const BASE = 'http://127.0.0.1:8080';
const ART = '/opt/cursor/artifacts';
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

const browser = await chromium.launch({ headless: true });
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

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');

  await canvas.locator('h1.wp-block-post-title, [aria-label="Add title"]').first().click({ timeout: 30000 });
  await page.keyboard.type('Block editor P1 test');
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
} catch (err) {
  fail(String(err));
  await page.screenshot({ path: join(ART, 'block-editor-error.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log('\nSummary:', results.filter((r) => r.ok).length, 'passed,', failed.length, 'failed');
process.exit(failed.length ? 1 : 0);
