(function () {
    tinymce.create('tinymce.plugins.Credits', {
        init: function (ed, url) {
            ed.addCommand('addcredits', function () {
                var ctype = prompt('What do you want to add (Source/ Via) ? ');
                var clink;
                var cname;
                var shortcode;

                if (ctype === null) {
                    return;
                }

                ctype = String(ctype).toLowerCase();
                if (ctype !== 'source' && ctype !== 'via') {
                    alert('Invalid type');
                    return;
                }

                clink = prompt('Link?');
                cname = prompt('Link Name');
                if (clink === null || cname === null) {
                    return;
                }

                clink = String(clink).replace(/"/g, '');
                cname = String(cname).replace(/\[/g, '').replace(/\]/g, '');
                shortcode = '[credits link="' + clink + '" type="' + ctype + '"]' + cname + '[/credits]';
                ed.execCommand('mceInsertContent', 0, shortcode);
            });

            ed.addButton('addcredits', {
                title: 'Add Credits',
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
                authorurl: 'http://jashjacob.com',
                infourl: 'http://techzei.com',
                version: '1.3.1'
            };
        }
    });

    tinymce.PluginManager.add('credits', tinymce.plugins.Credits);
})();
