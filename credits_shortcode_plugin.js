(function() {
    tinymce.create('tinymce.plugins.Credits', {
 
        init : function(ed, url) {

            ed.addCommand('addcredits', function() {
            var ctype = prompt("What do you want to add (Source/ Via) ? "), 
                shortcode;

            if (ctype !== null) 
            {
                ctype=ctype.toLowerCase();
              if(ctype=="source"|| ctype=="via")
                {
                     var clink = prompt("Link?"), shortcode;
                     var cname = prompt("Link Name"), shortcode;
                     shortcode = '[credits link="'+ clink +'" type="' + ctype + '"]' + cname +'[/credits]';
                     ed.execCommand('mceInsertContent', 0, shortcode);
                }
                else
                {
                    alert("Invalid type");
                }

            }
        });

            ed.addButton('addcredits', {
            title : 'Add Credits',
            cmd : 'addcredits',
            image : url + '/credits_logo.png'
        });

    },
 
       
        createControl : function(n, cm) {
            return null;
        },
 
        getInfo : function() {
            return {
                longname : 'Credits Buttons',
                author : 'Jash Jacob',
                authorurl : 'http://jashjacob.com',
                infourl : 'http://techzei.com',
                version : "1.2"
            };
        }
    });

    tinymce.PluginManager.add('credits', tinymce.plugins.Credits );
})();
