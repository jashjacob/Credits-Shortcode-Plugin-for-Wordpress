import { chromium } from 'playwright';
import { mkdirSync } from 'fs';
import { join } from 'path';
import { tmpdir } from 'os';

// Classic Editor (TinyMCE) check: the Add Credits button opens one form, bad input
// keeps it open, good input inserts a shortcode that publishes as a working credit.
// Needs the Classic Editor plugin active; scripts/run-editor-e2e.sh takes care of that.

const BASE = process.env.CREDITS_E2E_BASE || 'http://127.0.0.1:8080';
const ART = process.env.CREDITS_E2E_ARTIFACTS || join(tmpdir(), 'credits-e2e-artifacts');
mkdirSync(ART, { recursive: true });

const results = [];
function pass(msg) {
  results.push({ ok: true, msg });
  console.log('PASS:', msg);
}
function fail(msg) {
  results.push({ ok: false, msg });
  console.error('FAIL:', msg);
}
function check(condition, okMsg, failMsg) {
  if (condition) pass(okMsg);
  else fail(failMsg);
}

const browser = await chromium.launch({ headless: true, executablePath: process.env.CREDITS_E2E_CHROMIUM || undefined });
const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });

const dialog = () => page.locator('.mce-window[role="dialog"]').last();
const field = (label) => dialog().locator('.mce-formitem').filter({ has: page.locator(`label:text-is("${label}")`) });
const openDialog = async () => {
  await page.locator('.mce-btn[aria-label="Add Credits"] button').click();
  await dialog().waitFor({ timeout: 10000 });
};
const clickButton = (win, name) => win.locator('div.mce-btn').filter({ hasText: name }).first().click();
const editorContent = () => page.evaluate(() => window.tinymce.get('content').getContent());

try {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/, { timeout: 60000 });

  await page.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#content_ifr', { timeout: 60000 });
  await page.waitForFunction(() => window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden(), null, { timeout: 30000 });
  await page.fill('#title', 'Credits classic e2e');

  // Put some text in the editor and select a word: the dialog should prefill the name from it.
  await page.evaluate(() => {
    const ed = window.tinymce.get('content');
    ed.setContent('<p>Photo by SelectedName today</p>');
    const body = ed.getBody();
    const textNode = body.querySelector('p').firstChild;
    const start = textNode.data.indexOf('SelectedName');
    const range = ed.getDoc().createRange();
    range.setStart(textNode, start);
    range.setEnd(textNode, start + 'SelectedName'.length);
    ed.selection.setRng(range);
  });

  // --- one form, not a chain of browser prompts -------------------------------
  let browserDialogs = 0;
  page.on('dialog', async (d) => {
    browserDialogs += 1;
    await d.dismiss();
  });

  await openDialog();
  await page.screenshot({ path: join(ART, 'classic-dialog-open.png') });
  check(browserDialogs === 0, 'Add Credits opens a TinyMCE form, not a browser prompt', `Browser dialogs opened: ${browserDialogs}`);

  const labels = await dialog().locator('label').allTextContents();
  check(
    ['Credit Type', 'Name', 'Link URL'].every((l) => labels.includes(l)) && (await dialog().locator('text=Open in new tab').count()) > 0,
    'Form has Credit Type, Name, Link URL and Open in new tab',
    `Unexpected form labels: ${JSON.stringify(labels)}`
  );

  const prefilled = await field('Name').locator('input').inputValue();
  check(prefilled === 'SelectedName', 'Name is prefilled from the selected text', `Name prefill was "${prefilled}"`);

  // --- Escape cancels without inserting ------------------------------------------
  await page.keyboard.press('Escape');
  await dialog().waitFor({ state: 'detached', timeout: 5000 }).catch(() => {});
  check((await page.locator('.mce-window[role="dialog"]').count()) === 0, 'Escape closes the form', 'Form still open after Escape');
  check(!(await editorContent()).includes('[credits'), 'Cancelling inserts nothing', 'Shortcode inserted after cancelling');

  // --- validation keeps the form open ------------------------------------------------
  await openDialog();
  await field('Link URL').locator('input').fill('');
  await clickButton(dialog(), 'Insert credit');
  await page.waitForSelector('.mce-window[role="alertdialog"], .mce-window:has-text("Enter a link.")', { timeout: 5000 });
  const alertText = await page.locator('.mce-window').filter({ hasText: 'Enter a link.' }).last().innerText();
  check(alertText.includes('Enter a link.'), 'A missing link is reported', `Alert text: ${alertText}`);
  await clickButton(page.locator('.mce-window').filter({ hasText: 'Enter a link.' }).last(), 'Ok');
  check((await dialog().count()) === 1 && (await field('Name').locator('input').inputValue()) === 'SelectedName', 'The form stays open with its values after a validation error', 'Form lost after validation error');
  check(!(await editorContent()).includes('[credits'), 'Nothing is inserted while the form is invalid', 'Shortcode inserted from invalid form');

  await field('Link URL').locator('input').fill('javascript:alert(1)');
  await clickButton(dialog(), 'Insert credit');
  await page.locator('.mce-window').filter({ hasText: 'Use a link that starts with' }).last().waitFor({ timeout: 5000 });
  pass('An unsafe scheme is rejected in the form');
  await clickButton(page.locator('.mce-window').filter({ hasText: 'Use a link that starts with' }).last(), 'Ok');

  // --- a valid entry, submitted with Enter, replaces the selection -----------------------
  await field('Credit Type').locator('.mce-listbox').click();
  await page.locator('.mce-menu-item .mce-text', { hasText: /^Via$/ }).click();
  await field('Link URL').locator('input').fill('https://example.com/a?x=1&copy=2&b[]=3');
  await field('Name').locator('input').fill('Tom & Jerry [Fan] <Club>');
  await page.screenshot({ path: join(ART, 'classic-dialog-filled.png') });
  await field('Link URL').locator('input').press('Enter');
  await dialog().waitFor({ state: 'detached', timeout: 5000 }).catch(() => {});
  check((await page.locator('.mce-window[role="dialog"]').count()) === 0, 'Enter submits a valid form and closes it', 'Form still open after Enter with valid values');

  const html = await editorContent();
  console.log('Editor content:', html);
  check(
    html.includes('[credits link="https://example.com/a?x=1&amp;copy=2&amp;b%5B%5D=3" type="via"]Tom &amp; Jerry Fan Club[/credits]'),
    'The shortcode is inserted with the link and name safely encoded',
    `Unexpected editor content: ${html}`
  );
  check(html.startsWith('<p>Photo by [credits') && html.endsWith('[/credits] today</p>'), 'The selection is replaced and the surrounding text is kept', `Unexpected surrounding text: ${html}`);

  // --- publish and check the front end -----------------------------------------------------
  await page.evaluate(() => window.tinymce.get('content').save());
  await page.click('#publish');
  await page.waitForSelector('#message a[href*="?p="], #message a[href^="http"]', { timeout: 60000 });
  const permalink = await page.locator('#message a').first().getAttribute('href');
  await page.goto(permalink, { waitUntil: 'domcontentloaded' });
  const credit = page.locator('ul.wp-block-credits-shortcode').first();
  const anchor = credit.locator('a');
  const href = await anchor.getAttribute('href');
  const text = (await anchor.innerText()).trim();
  const badge = (await credit.locator('.cre_cate').innerText()).trim();
  check(
    // [] arrives percent-encoded (the same query string); &copy=2 must not turn into a copyright sign.
    href === 'https://example.com/a?x=1&copy=2&b%5B%5D=3' && text === 'Tom & Jerry Fan Club' && badge === 'Via' && (await anchor.getAttribute('target')) === '_blank',
    'The published credit has the right link, name, type and opens in a new tab',
    `Published credit: ${JSON.stringify({ href, text, badge })}`
  );
  await page.screenshot({ path: join(ART, 'classic-published.png') });

  // --- unchecking "Open in new tab" ---------------------------------------------------------------
  await page.goBack();
  await page.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden(), null, { timeout: 30000 });
  await page.evaluate(() => window.tinymce.get('content').setContent('<p>x</p>'));
  await openDialog();
  await field('Name').locator('input').fill('Same tab');
  await field('Link URL').locator('input').fill('https://example.com/same');
  await dialog().locator('.mce-checkbox').click();
  await clickButton(dialog(), 'Insert credit');
  await dialog().waitFor({ state: 'detached', timeout: 5000 }).catch(() => {});
  const sameTab = await editorContent();
  check(sameTab.includes('newtab="false"'), 'Unchecking Open in new tab adds newtab="false"', `Content: ${sameTab}`);

  // --- the dialog's strings load translations (German test fixture) ---------------------------
  if (process.env.CREDITS_E2E_TRANSLATIONS) {
    const germanContext = await browser.newContext({ viewport: { width: 1400, height: 1000 } });
    await germanContext.addCookies([{ name: 'credits_e2e_locale', value: 'de_DE', url: BASE }]);
    const de = await germanContext.newPage();
    await de.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    await de.fill('#user_login', 'admin');
    await de.fill('#user_pass', 'admin');
    await de.click('#wp-submit');
    await de.waitForURL(/wp-admin/, { timeout: 60000 });
    await de.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
    await de.waitForFunction(() => window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden(), null, { timeout: 30000 });

    await de.locator('.mce-btn[aria-label="Quellenangabe hinzufügen"] button').click();
    const deDialog = de.locator('.mce-window[role="dialog"]').last();
    await deDialog.waitFor({ timeout: 10000 });
    const deLabels = await deDialog.locator('label').allTextContents();
    check(
      (await deDialog.locator('.mce-title').innerText()) === 'Quellenangabe hinzufügen' &&
        deLabels.includes('Art der Angabe') &&
        deLabels.includes('Link-Adresse') &&
        (await deDialog.locator('text=In neuem Tab öffnen').count()) > 0,
      'The Classic Editor button, dialog title and labels are translated',
      `German dialog: ${JSON.stringify(deLabels)}`
    );
    check((await deDialog.locator('div.mce-btn', { hasText: 'Angabe einfügen' }).count()) > 0, 'The Classic Editor dialog button is translated', 'Translated Insert button not found');

    await deDialog.locator('.mce-formitem').filter({ has: de.locator('label:text-is("Name")') }).locator('input').fill('Nur ein Name');
    await deDialog.locator('div.mce-btn', { hasText: 'Angabe einfügen' }).first().click();
    const deAlert = de.locator('.mce-window').filter({ hasText: 'Bitte einen Link eingeben.' });
    await deAlert.last().waitFor({ timeout: 5000 });
    pass('The Classic Editor validation message is translated');
    await de.screenshot({ path: join(ART, 'classic-editor-german.png') });
    await germanContext.close();
  } else {
    console.log('SKIP: translation checks (CREDITS_E2E_TRANSLATIONS not set; scripts/run-editor-e2e.sh sets it)');
  }
} catch (err) {
  fail(String(err));
  await page.screenshot({ path: join(ART, 'classic-editor-error.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log('\nSummary:', results.filter((r) => r.ok).length, 'passed,', failed.length, 'failed');
process.exit(failed.length ? 1 : 0);
