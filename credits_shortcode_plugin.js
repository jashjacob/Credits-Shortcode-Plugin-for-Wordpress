(function () {
    var textdomain = 'credits-shortcode';
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

    var translate = function (text) {
        return (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function')
            ? window.wp.i18n.__(text, textdomain)
            : text;
    };

    tinymce.create('tinymce.plugins.Credits', {
        init: function (ed, url) {
            ed.addCommand('addcredits', function () {
                var ctype = prompt(translate('What do you want to add (Source/ Via) ? '));
                var clink;
                var cname;
                var shortcode;

                if (ctype === null) {
                    return;
                }

                ctype = String(ctype).toLowerCase();
                if (ctype !== 'source' && ctype !== 'via') {
                    alert(translate('Invalid type'));
                    return;
                }

                clink = prompt(translate('Link?'));
                cname = prompt(translate('Link Name'));
                if (clink === null || cname === null) {
                    return;
                }

                clink = String(clink).replace(/"/g, '');
                cname = String(cname).replace(/\[/g, '').replace(/\]/g, '');
                shortcode = '[credits link="' + clink + '" type="' + ctype + '"]' + cname + '[/credits]';
                ed.execCommand('mceInsertContent', 0, shortcode);
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
