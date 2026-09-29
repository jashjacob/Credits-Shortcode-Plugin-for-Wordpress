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

const SOURCE = fs.readFileSync(path.join(__dirname, '..', '..', 'credits_shortcode_plugin.js'), 'utf8');

/**
 * Load the plugin and return a fake editor that has been initialised with it.
 *
 * @param {object} options selection: text under the caret; translations: dictionary for editor.translate().
 */
function loadPlugin(options = {}) {
    const registered = {};
    let definition = null;

    const tinymce = {
        create(name, def) {
            definition = def;
            tinymce.plugins = { Credits: def };
        },
        PluginManager: {
            add(name, plugin) {
                registered[name] = plugin;
            },
        },
    };

    vm.runInNewContext(SOURCE, { tinymce, document: {}, window: {}, decodeURIComponent });
    assert.ok(definition, 'plugin definition should be created');

    const editor = {
        commands: {},
        buttons: {},
        opened: [],
        alerts: [],
        inserted: [],
        translate: (text) => (options.translations && options.translations[text]) || text,
        addCommand(name, fn) {
            this.commands[name] = fn;
        },
        addButton(name, config) {
            this.buttons[name] = config;
        },
        selection: { getContent: () => options.selection || '' },
        windowManager: {
            open: (config) => editor.opened.push(config),
            alert: (message) => editor.alerts.push(message),
        },
        execCommand(command, ui, value) {
            this.inserted.push({ command, ui, value });
        },
    };

    definition.init(editor, 'http://example.test/wp-content/plugins/credits-shortcode');
    return { editor, registered, definition };
}

// Arrays created inside the vm sandbox have another realm's prototype, which
// strict deep-equality rejects, so compare plain copies.
const plain = (value) => JSON.parse(JSON.stringify(value));

/** Open the dialog and return its config. */
function openDialog(loaded) {
    loaded.editor.opened.length = 0;
    loaded.editor.commands.addcredits();
    assert.equal(loaded.editor.opened.length, 1, 'one dialog should open');
    return loaded.editor.opened[0];
}

/** Submit the dialog with the given field values, as TinyMCE does. */
function submit(loaded, values) {
    const dialog = openDialog(loaded);
    let prevented = false;
    dialog.onsubmit({ data: values, preventDefault: () => { prevented = true; } });
    return { prevented, inserted: loaded.editor.inserted.map((entry) => entry.value), alerts: loaded.editor.alerts };
}

const valid = { type: '', name: 'Example', link: 'https://example.com/post', newTab: true };

test('registers the credits plugin and the toolbar button', () => {
    const { registered, editor } = loadPlugin();
    assert.ok(registered.credits);
    assert.equal(editor.buttons.addcredits.cmd, 'addcredits');
    assert.equal(editor.buttons.addcredits.image, 'http://example.test/wp-content/plugins/credits-shortcode/credits_logo.png');
});

test('opens one form with type, name, link and new-tab fields instead of browser prompts', () => {
    const dialog = openDialog(loadPlugin());
    assert.deepEqual(plain(dialog.body.map((field) => field.name)), ['type', 'name', 'link', 'newTab']);
    assert.deepEqual(plain(dialog.body[0].values.map((option) => option.value)), ['', 'source', 'via']);
    assert.equal(dialog.body[0].value, '', 'defaults to the site default type');
    assert.equal(dialog.body[3].checked, true, 'new tab stays the default');
});

test('does not use window.prompt or window.alert', () => {
    assert.doesNotMatch(SOURCE, /\bprompt\s*\(/);
    assert.doesNotMatch(SOURCE, /(^|[^.\w])alert\s*\(/m);
});

test('prefills the name from the selected text, cleaned', () => {
    const dialog = openDialog(loadPlugin({ selection: '  Some [b]<i>Site</i>\n Name ' }));
    assert.equal(dialog.body[1].value, 'Some biSite/i Name');
});

test('an empty selection leaves the name empty', () => {
    assert.equal(openDialog(loadPlugin()).body[1].value, '');
});

test('inserts a shortcode without a type attribute when the site default is chosen', () => {
    const result = submit(loadPlugin(), valid);
    assert.equal(result.prevented, false);
    assert.deepEqual(result.inserted, ['[credits link="https://example.com/post"]Example[/credits]']);
});

test('inserts the chosen type and opts out of a new tab only when unchecked', () => {
    assert.deepEqual(
        submit(loadPlugin(), { ...valid, type: 'via' }).inserted,
        ['[credits link="https://example.com/post" type="via"]Example[/credits]']
    );
    assert.deepEqual(
        submit(loadPlugin(), { ...valid, type: 'source', newTab: false }).inserted,
        ['[credits link="https://example.com/post" type="source" newtab="false"]Example[/credits]']
    );
});

test('inserts through mceInsertContent so a selection is replaced', () => {
    const loaded = loadPlugin();
    submit(loaded, valid);
    assert.equal(loaded.editor.inserted[0].command, 'mceInsertContent');
});

test('ignores an unknown type value', () => {
    assert.deepEqual(
        submit(loadPlugin(), { ...valid, type: 'source"] onmouseover="x' }).inserted,
        ['[credits link="https://example.com/post"]Example[/credits]']
    );
});

test('keeps the dialog open and explains the problem when a field is missing', () => {
    const cases = [
        [{ ...valid, name: '', link: '' }, 'Enter a name and a link.'],
        [{ ...valid, name: '   ' }, 'Enter a name.'],
        [{ ...valid, name: '[]<>"' }, 'Enter a name.'],
        [{ ...valid, link: '  ' }, 'Enter a link.'],
    ];
    for (const [values, message] of cases) {
        const loaded = loadPlugin();
        const result = submit(loaded, values);
        assert.equal(result.prevented, true, JSON.stringify(values));
        assert.deepEqual(result.alerts, [message], JSON.stringify(values));
        assert.deepEqual(result.inserted, [], 'nothing may be inserted');
    }
});

test('rejects links with a scheme the renderer would discard', () => {
    for (const link of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java\tscript:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4=']) {
        const result = submit(loadPlugin(), { ...valid, link });
        assert.equal(result.prevented, true, link);
        assert.deepEqual(result.inserted, [], link);
        assert.match(result.alerts[0], /^Use a link that starts with/, link);
    }
});

test('accepts http, https, mailto, ftp, relative and scheme-less links', () => {
    for (const link of ['http://example.com', 'https://example.com', 'mailto:editor@example.com', 'ftp://example.com/file', '/relative/path', '#section', 'example.com/page', '//cdn.example.com/x']) {
        const result = submit(loadPlugin(), { ...valid, link });
        assert.equal(result.prevented, false, link);
        assert.equal(result.inserted.length, 1, link);
    }
});

test('percent-encodes characters that could end or alter the shortcode attribute', () => {
    const cases = [
        ['https://example.com/?a[]=1&b[]=2', 'https://example.com/?a%5B%5D=1&amp;b%5B%5D=2'],
        ['https://example.com/a b', 'https://example.com/a%20b'],
        ['https://example.com/"onmouseover="x', 'https://example.com/%22onmouseover=%22x'],
        ['https://example.com/x" type="via', 'https://example.com/x%22%20type=%22via'],
        ["https://example.com/it's", 'https://example.com/it%27s'],
        ['https://example.com/<script>', 'https://example.com/%3Cscript%3E'],
        ['https://example.com/back\\slash', 'https://example.com/back%5Cslash'],
        ['https://example.com/[credits link=x]', 'https://example.com/%5Bcredits%20link=x%5D'],
    ];
    for (const [link, encoded] of cases) {
        assert.deepEqual(
            submit(loadPlugin(), { ...valid, link }).inserted,
            [`[credits link="${encoded}"]Example[/credits]`],
            link
        );
    }
});

test('escapes ampersands so browsers cannot read a query string as an HTML entity', () => {
    // Inserted as HTML, a bare "&copy=2" would be read as the copyright sign.
    assert.deepEqual(
        submit(loadPlugin(), { ...valid, link: 'https://example.com/?a=1&copy=2&lt=3' }).inserted,
        ['[credits link="https://example.com/?a=1&amp;copy=2&amp;lt=3"]Example[/credits]']
    );
    assert.deepEqual(
        submit(loadPlugin(), { ...valid, name: 'Tom & Jerry &amp; friends' }).inserted,
        ['[credits link="https://example.com/post"]Tom &amp; Jerry &amp;amp; friends[/credits]']
    );
});

test('removes characters from the name that could close the shortcode or become HTML', () => {
    const cases = [
        ['Name[/credits][credits link="x"]Evil', 'Name/creditscredits link=xEvil'],
        ['<script>alert(1)</script>', 'scriptalert(1)/script'],
        ['<img src=x onerror=alert(1)>', 'img src=x onerror=alert(1)'],
        ['Line one\nline two\ttabbed', 'Line one line two tabbed'],
        ['Say "hi"', 'Say hi'],
    ];
    for (const [name, cleaned] of cases) {
        const result = submit(loadPlugin(), { ...valid, name });
        assert.equal(result.prevented, false, name);
        assert.deepEqual(result.inserted, [`[credits link="https://example.com/post"]${cleaned}[/credits]`], name);
    }
});

test('hostile values can never add attributes, extra shortcodes or markup', () => {
    const hostile = [
        '"] [credits link="https://evil.example"]x[/credits] ["',
        '" onmouseover="alert(1)',
        "' onload='alert(1)",
        '<svg onload=alert(1)>',
        '][/credits][gallery ids="1,2,3"]',
        '\u0000\u001f\u007f control',
        '`$(reboot)`',
        '\\" escaped \\\\ backslashes \\n \\x41',
    ];
    const shape = /^\[credits link="[^\s"'<>\[\]\\`]*"( type="(source|via)")?( newtab="false")?\][^\[\]<>"\n\r\t]*\[\/credits\]$/;

    for (const payload of hostile) {
        for (const values of [
            { ...valid, name: payload },
            { ...valid, link: `https://example.com/${payload}` },
            { ...valid, name: payload, link: `https://example.com/${payload}`, type: 'via', newTab: false },
        ]) {
            const { inserted } = submit(loadPlugin(), values);
            assert.equal(inserted.length, 1, JSON.stringify(values));
            assert.match(inserted[0], shape, `unexpected shortcode for ${JSON.stringify(values)}: ${inserted[0]}`);
            assert.equal((inserted[0].match(/\[/g) || []).length, 2, 'only the opening and closing tag may contain brackets');
        }
    }
});

test('the dialog labels, options, buttons and messages go through editor.translate()', () => {
    const translations = {
        'Add Credits': 'Krediti dodaj',
        'Credit Type': 'Vrsta',
        'Use site default': 'Privzeto',
        'Source': 'Vir',
        'Name': 'Ime',
        'Link URL': 'Povezava',
        'Open in new tab': 'Odpri v novem zavihku',
        'Insert credit': 'Vstavi',
        'Cancel': 'Preklic',
        'Enter a name.': 'Vnesite ime.',
    };
    const loaded = loadPlugin({ translations });
    const dialog = openDialog(loaded);

    assert.equal(loaded.editor.buttons.addcredits.title, 'Krediti dodaj');
    assert.equal(dialog.title, 'Krediti dodaj');
    assert.deepEqual(plain(dialog.body.map((field) => field.label || field.text)), ['Vrsta', 'Ime', 'Povezava', 'Odpri v novem zavihku']);
    assert.deepEqual(plain(dialog.body[0].values.map((option) => option.text)), ['Privzeto', 'Vir', 'Via']);
    assert.deepEqual(plain(dialog.buttons.map((button) => button.text)), ['Vstavi', 'Preklic']);

    const result = submit(loaded, { ...valid, name: '' });
    assert.deepEqual(result.alerts, ['Vnesite ime.']);
});

test('every string the plugin translates is present in the PHP dictionary', () => {
    const dictionary = fs.readFileSync(path.join(__dirname, '..', '..', 'tinymce-i18n.php'), 'utf8');
    const used = new Set();
    const collect = (text) => used.add(text);

    // Drive every code path so each translate() call is recorded.
    const recorder = loadPlugin();
    recorder.editor.translate = (text) => { collect(text); return text; };
    recorder.definition.init(recorder.editor, 'u');
    for (const values of [{ ...valid, name: '', link: '' }, { ...valid, name: '' }, { ...valid, link: '' }, { ...valid, link: 'javascript:x' }]) {
        submit(recorder, values);
    }

    assert.ok(used.size >= 12, `expected the full set of strings, saw ${used.size}`);
    for (const text of used) {
        assert.ok(dictionary.includes(`'${text}'`), `tinymce-i18n.php is missing "${text}"`);
    }
});
