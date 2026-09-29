/**
 * Classic Editor plugin: runs the real credits_shortcode_plugin.js against a
 * stub TinyMCE, so it needs no browser, npm packages or build step:
 *
 *   node --test tests/js/*.test.js
 */
'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ROOT = path.join(__dirname, '..', '..');
const SOURCE = fs.readFileSync(path.join(ROOT, 'credits_shortcode_plugin.js'), 'utf8');

/** Load the plugin and return a fake editor initialised with it. */
function loadPlugin(selection = '') {
    let definition = null;
    const tinymce = {
        create: (name, def) => { definition = def; tinymce.plugins = { Credits: def }; },
        PluginManager: { add() {} },
    };
    vm.runInNewContext(SOURCE, { tinymce, document: {}, decodeURIComponent });

    const editor = {
        commands: {},
        opened: [],
        alerts: [],
        inserted: [],
        translate: (text) => text,
        addCommand(name, fn) { this.commands[name] = fn; },
        addButton() {},
        selection: { getContent: () => selection },
        windowManager: {
            open: (config) => editor.opened.push(config),
            alert: (message) => editor.alerts.push(message),
        },
        execCommand(command, ui, value) { this.inserted.push(value); },
    };
    definition.init(editor, 'http://example.test/plugin');
    return editor;
}

/** Open the dialog and submit it with the given field values, as TinyMCE does. */
function submit(values, editor = loadPlugin()) {
    editor.commands.addcredits();
    let prevented = false;
    editor.opened[0].onsubmit({ data: values, preventDefault: () => { prevented = true; } });
    return { prevented, inserted: editor.inserted, alerts: editor.alerts };
}

const valid = { type: '', name: 'Example', link: 'https://example.com/post' };

test('opens one form with type, name and link, and prefills the name from the selection', () => {
    const editor = loadPlugin('  Some [b]<i>Site</i>\n Name ');
    editor.commands.addcredits();
    const fields = editor.opened[0].body;

    assert.equal(JSON.stringify(fields.map((field) => field.name)), '["type","name","link"]');
    assert.equal(fields[1].value, 'Some biSite/i Name');
    assert.doesNotMatch(SOURCE, /\bprompt\s*\(/, 'the browser prompt chain is gone');
});

test('inserts the shortcode, leaving out the type when the site default is chosen', () => {
    assert.deepEqual(submit(valid).inserted, ['[credits link="https://example.com/post"]Example[/credits]']);
    assert.deepEqual(
        submit({ ...valid, type: 'via' }).inserted,
        ['[credits link="https://example.com/post" type="via"]Example[/credits]']
    );
    assert.deepEqual(
        submit({ ...valid, type: 'source"] onmouseover="x' }).inserted,
        ['[credits link="https://example.com/post"]Example[/credits]'],
        'an unknown type is dropped'
    );
});

test('keeps the dialog open with a message when the name or link is missing', () => {
    for (const values of [{ ...valid, name: '' }, { ...valid, name: '[]<>"' }, { ...valid, link: '  ' }]) {
        const result = submit(values);
        assert.equal(result.prevented, true, JSON.stringify(values));
        assert.deepEqual(result.alerts, ['Enter a name and a link.']);
        assert.deepEqual(result.inserted, []);
    }
});

test('rejects links with a scheme the renderer would discard, and accepts the rest', () => {
    for (const link of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java\tscript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=']) {
        const result = submit({ ...valid, link });
        assert.equal(result.prevented, true, link);
        assert.deepEqual(result.inserted, [], link);
    }
    for (const link of ['http://example.com', 'mailto:editor@example.com', '/relative/path', '#section', 'example.com/page']) {
        assert.equal(submit({ ...valid, link }).inserted.length, 1, link);
    }
});

test('encodes what could end the link attribute and escapes ampersands', () => {
    assert.deepEqual(
        submit({ ...valid, link: 'https://example.com/?a[]=1&copy=2 "x"' }).inserted,
        ['[credits link="https://example.com/?a%5B%5D=1&amp;copy=2%20%22x%22"]Example[/credits]']
    );
    assert.deepEqual(
        submit({ ...valid, name: 'Tom & Jerry <b>[x]</b>' }).inserted,
        ['[credits link="https://example.com/post"]Tom &amp; Jerry bx/b[/credits]']
    );
});

test('hostile values can never add attributes, extra shortcodes or markup', () => {
    const hostile = [
        '"] [credits link="https://evil.example"]x[/credits] ["',
        '" onmouseover="alert(1)',
        '<svg onload=alert(1)>',
        '][/credits][gallery ids="1,2,3"]',
        '\u0000\u001f\u007f control',
        '\\" escaped \\\\ backslashes \\n \\x41',
    ];
    const shape = /^\[credits link="[^\s"'<>\[\]\\`]*"( type="(source|via)")?\][^\[\]<>"\n\r\t]*\[\/credits\]$/;

    for (const payload of hostile) {
        for (const values of [
            { ...valid, name: payload },
            { ...valid, link: `https://example.com/${payload}` },
            { ...valid, name: payload, link: `https://example.com/${payload}`, type: 'via' },
        ]) {
            const { inserted } = submit(values);
            assert.equal(inserted.length, 1, JSON.stringify(values));
            assert.match(inserted[0], shape, JSON.stringify(values));
        }
    }
});

test('every string the form translates is in the PHP dictionary', () => {
    const dictionary = fs.readFileSync(path.join(ROOT, 'tinymce-i18n.php'), 'utf8');
    const used = new Set();
    const editor = loadPlugin();
    editor.translate = (text) => { used.add(text); return text; };
    editor.commands.addcredits();
    for (const values of [{ ...valid, name: '' }, { ...valid, link: 'javascript:x' }]) {
        editor.opened[0].onsubmit({ data: values, preventDefault() {} });
    }

    assert.ok(used.size >= 8, `expected the full set of strings, saw ${used.size}`);
    for (const text of used) {
        assert.ok(dictionary.includes(`'${text}'`), `tinymce-i18n.php is missing "${text}"`);
    }
});
