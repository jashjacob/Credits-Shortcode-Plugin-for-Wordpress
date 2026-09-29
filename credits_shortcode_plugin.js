(function () {
    var pluginVersion = '1.6.0';

    try {
        var currentScript = document.currentScript;
        var match = currentScript && currentScript.src ? currentScript.src.match(/[?&]ver=([^&]+)/) : null;
        if (match && match[1]) {
            pluginVersion = decodeURIComponent(match[1]);
        }
    } catch (err) {
        pluginVersion = '1.6.0';
    }

    var stripControlChars = function (value) {
        return String(value || '').replace(/[\x00-\x1f\x7f]/g, '');
    };

    /**
     * A shortcode attribute value sits inside double quotes and is unescaped by
     * WordPress (stripcslashes), and the whole shortcode is inserted as HTML. So
     * anything that could end the attribute, start a new shortcode or tag, or be
     * eaten as an escape is percent-encoded rather than dropped, which keeps
     * legitimate URLs such as ?a[]=1 working. Ampersands are escaped because
     * browsers read a bare "&copy" or "&lt" in HTML as a character, which would
     * silently corrupt a query string like ?a=1&copy=2.
     */
    var encodeLinkForShortcode = function (value) {
        return stripControlChars(value)
            .trim()
            .replace(/[\s"'<>\[\]\\`]/g, function (character) {
                return '%' + ('0' + character.charCodeAt(0).toString(16)).slice(-2).toUpperCase();
            })
            .replace(/&/g, '&amp;');
    };

    /**
     * The name is the enclosed shortcode content: brackets would end it early
     * and angle brackets would become editor HTML, so they are removed.
     */
    var cleanName = function (value) {
        return stripControlChars(String(value || '').replace(/[\t\r\n]+/g, ' '))
            .replace(/[\[\]<>"]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    };

    // Escape ampersands so entity-like text is kept exactly as typed.
    var sanitizeNameForShortcode = function (value) {
        return cleanName(value).replace(/&/g, '&amp;');
    };

    /**
     * Mirror the schemes the renderer keeps (http, https, mailto, ftp); a link
     * without a scheme, such as a relative path or example.com, is left to the
     * renderer to complete.
     */
    var hasAllowedScheme = function (link) {
        var schemeMatch = stripControlChars(link).replace(/\s/g, '').match(/^([a-zA-Z][a-zA-Z0-9+.\-]*):/);
        return !schemeMatch || ['http', 'https', 'mailto', 'ftp'].indexOf(schemeMatch[1].toLowerCase()) !== -1;
    };

    var buildShortcode = function (values) {
        var attributes = ['link="' + encodeLinkForShortcode(values.link) + '"'];
        if (values.type === 'source' || values.type === 'via') {
            attributes.push('type="' + values.type + '"');
        }
        if (values.newTab === false) {
            attributes.push('newtab="false"');
        }
        return '[credits ' + attributes.join(' ') + ']' + sanitizeNameForShortcode(values.name) + '[/credits]';
    };

    tinymce.create('tinymce.plugins.Credits', {
        init: function (ed, url) {
            // Strings are translated by TinyMCE from the dictionary the plugin
            // registers through the mce_external_languages filter.
            var translate = function (text) {
                return typeof ed.translate === 'function' ? ed.translate(text) : text;
            };

            var validate = function (values) {
                var name = cleanName(values.name);
                var link = stripControlChars(values.link).trim();

                if (!name && !link) {
                    return translate('Enter a name and a link.');
                }
                if (!name) {
                    return translate('Enter a name.');
                }
                if (!link) {
                    return translate('Enter a link.');
                }
                if (!hasAllowedScheme(link)) {
                    return translate('Use a link that starts with http://, https://, mailto: or ftp://, or a relative address.');
                }
                return '';
            };

            ed.addCommand('addcredits', function () {
                var selectedText = '';
                try {
                    selectedText = cleanName(ed.selection.getContent({ format: 'text' }));
                } catch (err) {
                    selectedText = '';
                }

                ed.windowManager.open({
                    title: translate('Add Credits'),
                    minWidth: 420,
                    body: [
                        {
                            type: 'listbox',
                            name: 'type',
                            label: translate('Credit Type'),
                            value: '',
                            values: [
                                { text: translate('Use site default'), value: '' },
                                { text: translate('Source'), value: 'source' },
                                { text: translate('Via'), value: 'via' }
                            ]
                        },
                        {
                            type: 'textbox',
                            name: 'name',
                            label: translate('Name'),
                            value: selectedText,
                            autofocus: true
                        },
                        {
                            type: 'textbox',
                            name: 'link',
                            label: translate('Link URL'),
                            value: '',
                            placeholder: 'https://example.com'
                        },
                        {
                            type: 'checkbox',
                            name: 'newTab',
                            text: translate('Open in new tab'),
                            checked: true
                        }
                    ],
                    buttons: [
                        { text: translate('Insert credit'), subtype: 'primary', onclick: 'submit' },
                        { text: translate('Cancel'), onclick: 'close' }
                    ],
                    onsubmit: function (e) {
                        var values = e.data;
                        var problem = validate(values);

                        if (problem) {
                            e.preventDefault();
                            ed.windowManager.alert(problem);
                            return;
                        }

                        ed.execCommand('mceInsertContent', false, buildShortcode(values));
                    }
                });
            });

            ed.addButton('addcredits', {
                title: translate('Add Credits'),
                cmd: 'addcredits',
                image: url + '/credits_logo.png'
            });
        },

        createControl: function () {
            return null;
        },

        getInfo: function () {
            return {
                longname: 'Credits Buttons',
                author: 'Jash Jacob',
                authorurl: 'https://jashjacob.com',
                infourl: 'https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress',
                version: pluginVersion
            };
        }
    });

    tinymce.PluginManager.add('credits', tinymce.plugins.Credits);
})();
