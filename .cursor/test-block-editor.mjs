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
function check(condition, okMsg, failMsg) {
  if (condition) pass(okMsg);
  else fail(failMsg);
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

  // ---------------------------------------------------------------------------
  // Phase 2: inline editing, incomplete-credit feedback, colors and link opening.
  // ---------------------------------------------------------------------------
  await page.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.edit-post-layout', { timeout: 60000 });
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await page.waitForTimeout(2000);
  await page.evaluate(() => wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false));
  await page.locator('.components-modal__screen-overlay').waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});

  const inline = page.frameLocator('iframe[name="editor-canvas"]');
  const creditAttrs = () =>
    page.evaluate(() => {
      const block = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'credits/shortcode');
      return block ? { ...block.attributes, clientId: block.clientId } : null;
    });
  const topLevelBlockCount = () => page.evaluate(() => wp.data.select('core/block-editor').getBlocks().length);

  await inline.locator('h1.wp-block-post-title, [aria-label="Add title"]').first().click({ timeout: 30000 });
  await page.keyboard.type('Credits editor e2e inline');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(500);
  await inline.locator('.block-editor-default-block-appender__content, p[data-empty="true"]').first().click({ timeout: 10000 });
  await page.keyboard.type('/credits');
  await page.waitForTimeout(800);
  await page.keyboard.press('Enter');
  await inline.locator('.wp-block-credits-shortcode').first().waitFor({ timeout: 30000 });
  await page.waitForTimeout(800);

  // A brand new credit is clearly unfinished, and the caret is already in the name.
  const freshClasses = ((await inline.locator('ul.wp-block-credits-shortcode').getAttribute('class')) || '').split(/\s+/);
  const freshNotice = await inline.locator('.credits-editor-notice').innerText();
  check(
    freshClasses.includes('credits-editor-incomplete') && freshNotice.includes('Add a name') && freshNotice.includes('Add a link'),
    'A new credit is marked incomplete and says what is missing',
    `New credit state: ${JSON.stringify({ freshClasses, freshNotice })}`
  );
  check(
    (await page.evaluate(() => document.querySelector('iframe[name="editor-canvas"]').contentDocument.activeElement.className)).includes('credits-editor-name'),
    'A new credit puts the caret in its name field',
    'Focus is not in the inline name field after inserting the block'
  );
  check(
    (await inline.locator('.credits-editor-name [data-rich-text-placeholder]').count()) > 0 || (await inline.locator('.credits-editor-name').getAttribute('data-empty')) === 'true',
    'The empty name shows a placeholder instead of pretending to be saved text',
    'No placeholder shown for the empty name'
  );
  check((await creditAttrs()).name === '' && (await creditAttrs()).link === '', 'The placeholder is not stored as content', 'Name or link was stored for an untouched credit');

  // Type the name inline. Enter must not split or add a line.
  await page.keyboard.type('Tom & Jerry');
  await page.keyboard.press('Enter');
  await page.keyboard.type(' Fan');
  await page.waitForTimeout(300);
  let attrs = await creditAttrs();
  check(attrs.name === 'Tom & Jerry Fan' && (await topLevelBlockCount()) === 1, 'Inline name editing stores plain text and Enter does not split the block', `Name/blocks after typing: ${JSON.stringify(attrs.name)} / ${await topLevelBlockCount()}`);
  const sidebarName = await page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' }).getByLabel('Source / Via Name').inputValue();
  check(sidebarName === 'Tom & Jerry Fan', 'The sidebar name field shows the inline edit', `Sidebar name: ${sidebarName}`);

  // Markup-like text is kept as typed, never interpreted as HTML.
  await page.keyboard.press('Control+A');
  await page.keyboard.type('<b>x</b> & y');
  await page.waitForTimeout(300);
  attrs = await creditAttrs();
  check(attrs.name === '<b>x</b> & y', 'Markup typed into the name is stored as literal text', `Stored name: ${JSON.stringify(attrs.name)}`);
  await page.keyboard.press('Control+A');
  await page.keyboard.type('Tom & Jerry Fan');
  await page.waitForTimeout(300);

  // Inline link entry, with feedback for an unusable link.
  const linkInput = inline.locator('.credits-editor-link-row input');
  await linkInput.fill('javascript:alert(1)');
  await page.waitForTimeout(300);
  const invalidNotice = await inline.locator('.credits-editor-notice').innerText();
  check(
    invalidNotice.includes('cannot be used') && (await linkInput.getAttribute('aria-invalid')) === 'true',
    'An unusable link is flagged next to the field',
    `Invalid link feedback: ${invalidNotice} / aria-invalid=${await linkInput.getAttribute('aria-invalid')}`
  );
  const sidebarHelp = await page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' }).innerText();
  check(sidebarHelp.includes('cannot be used'), 'The sidebar Link URL field explains the problem too', 'Sidebar shows no link problem');

  await linkInput.fill('');
  await linkInput.press('Backspace');
  await page.waitForTimeout(300);
  check((await creditAttrs()) !== null, 'Backspace in the empty link field does not delete the block', 'The block was removed by Backspace in the link field');
  check((await inline.locator('.credits-editor-notice').innerText()).includes('Add a link'), 'A missing link is reported', 'No missing-link message');

  await linkInput.fill('#references');
  await page.waitForTimeout(300);
  check((await inline.locator('.credits-editor-notice').count()) === 0, 'An intentional #fragment link counts as a complete credit', 'A #fragment link was flagged as incomplete');

  await linkInput.fill('https://example.com/inline?x=1&y=2');
  await page.waitForTimeout(300);
  const completeClasses = ((await inline.locator('ul.wp-block-credits-shortcode').getAttribute('class')) || '').split(/\s+/);
  check(
    !completeClasses.includes('credits-editor-incomplete') && (await inline.locator('.credits-editor-notice').count()) === 0,
    'A complete credit loses the incomplete marker and notice',
    `Still flagged: ${completeClasses.join(' ')}`
  );
  await page.screenshot({ path: join(ART, 'block-editor-inline-complete.png') });

  // Colors: where each one comes from, and reset to the site default.
  const colorPanelTitle = page.locator('.components-panel__body-title button', { hasText: 'Accent Color Settings' });
  // Older editors show a collapsed panel; newer ones render the colors expanded with no toggle.
  if ((await colorPanelTitle.count()) > 0 && (await colorPanelTitle.getAttribute('aria-expanded')) !== 'true') {
    await colorPanelTitle.click();
  }
  const siteBadge = await page.evaluate(() => (window.creditsShortcodeSettings || {}).badge_color || '');
  const status = () => page.locator('.credits-color-status li').allInnerTexts();
  const expectedBadge = siteBadge ? 'Badge Background Color: Site default' : 'Badge Background Color: Plugin default';
  let statuses = await status();
  check(
    statuses.length === 3 && statuses[0] === expectedBadge && statuses[1] === 'Link Background Color: Plugin default' && statuses[2] === 'Link Text Color: Plugin default',
    `Colors say they follow the site default (badge site default: ${siteBadge || 'none'})`,
    `Color status: ${JSON.stringify(statuses)}`
  );
  check((await page.getByRole('button', { name: 'Reset colors to site default' }).count()) === 0, 'No reset button while nothing is customized', 'Reset button shown with no overrides');

  await page.evaluate((clientId) => wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, { badgeColor: '#ff0000', linkTextColor: '#00aa00' }), (await creditAttrs()).clientId);
  await page.waitForTimeout(400);
  statuses = await status();
  check(
    statuses[0] === 'Badge Background Color: Custom for this credit' && statuses[2] === 'Link Text Color: Custom for this credit' && statuses[1] === 'Link Background Color: Plugin default',
    'Customized colors are labelled as custom for this credit',
    `Color status after override: ${JSON.stringify(statuses)}`
  );
  const badgeBg = await inline.locator('.wp-block-credits-shortcode .cre_cate').evaluate((node) => getComputedStyle(node).backgroundColor);
  check(badgeBg === 'rgb(255, 0, 0)', 'The preview uses the custom color', `Badge background: ${badgeBg}`);

  await page.getByRole('button', { name: 'Reset colors to site default' }).click();
  await page.waitForTimeout(400);
  attrs = await creditAttrs();
  statuses = await status();
  check(
    attrs.badgeColor === '' && attrs.linkColor === '' && attrs.linkTextColor === '' && statuses[0] === expectedBadge && (await page.getByRole('button', { name: 'Reset colors to site default' }).count()) === 0,
    'Reset colors to site default clears every override',
    `After reset: ${JSON.stringify({ attrs, statuses })}`
  );

  // Link opening: a new tab by default, the same tab when switched off.
  const settings = page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' });
  check(await settings.getByLabel('Open in new tab').isChecked(), 'Open in new tab is on by default', 'Open in new tab is off for a new credit');
  await settings.getByLabel('Open in new tab').uncheck();
  await page.waitForTimeout(300);
  check((await creditAttrs()).newTab === false, 'Turning Open in new tab off is stored on the block', 'newTab attribute not updated');

  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ status: 'publish' }));
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  await page.waitForFunction(() => {
    const editor = wp.data.select('core/editor');
    return !editor.isSavingPost() && editor.getCurrentPost().status === 'publish';
  }, null, { timeout: 60000 });
  const inlinePermalink = await page.evaluate(() => wp.data.select('core/editor').getPermalink());

  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await page.waitForTimeout(2000);
  const reopened = page.frameLocator('iframe[name="editor-canvas"]');
  await reopened.locator('.wp-block-credits-shortcode').first().click();
  await page.waitForTimeout(800);
  const reopenedName = (await reopened.locator('.credits-editor-name').innerText()).trim();
  const reopenedLink = await reopened.locator('.credits-editor-link-row input').inputValue();
  const reopenedNewTab = await page.locator('.components-panel__body').filter({ hasText: 'Credit Settings' }).getByLabel('Open in new tab').isChecked();
  check(
    reopenedName === 'Tom & Jerry Fan' && reopenedLink === 'https://example.com/inline?x=1&y=2' && reopenedNewTab === false,
    'Inline name, link and link opening survive saving and reloading',
    `After reload: ${JSON.stringify({ reopenedName, reopenedLink, reopenedNewTab })}`
  );

  await page.goto(inlinePermalink, { waitUntil: 'domcontentloaded' });
  const inlinePublished = page.locator('ul.wp-block-credits-shortcode').first();
  const inlineAnchor = inlinePublished.locator('a');
  const inlineResult = {
    href: await inlineAnchor.getAttribute('href'),
    text: (await inlineAnchor.innerText()).trim(),
    target: await inlineAnchor.getAttribute('target'),
    rel: await inlineAnchor.getAttribute('rel'),
    incompleteClassLeaked: ((await inlinePublished.getAttribute('class')) || '').includes('credits-editor'),
  };
  check(
    inlineResult.href === 'https://example.com/inline?x=1&y=2' &&
      inlineResult.text === 'Tom & Jerry Fan' &&
      inlineResult.target === null &&
      inlineResult.rel === null &&
      !inlineResult.incompleteClassLeaked,
    'The published credit uses the inline name and link, opens in the same tab, and carries no editor-only classes',
    `Published inline credit: ${JSON.stringify(inlineResult)}`
  );
  await page.screenshot({ path: join(ART, 'published-inline-credit.png') });
} catch (err) {
  fail(String(err));
  await page.screenshot({ path: join(ART, 'block-editor-error.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log('\nSummary:', results.filter((r) => r.ok).length, 'passed,', failed.length, 'failed');
process.exit(failed.length ? 1 : 0);
