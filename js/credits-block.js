(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var blockEditor = wp.blockEditor || {};
    var InspectorControls = blockEditor.InspectorControls;
    var PanelColorSettings = blockEditor.PanelColorSettings;
    var useBlockProps = typeof blockEditor.useBlockProps === 'function' ? blockEditor.useBlockProps : null;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var __ = (wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__ : function (text) { return text; };
    var textdomain = 'credits-shortcode';
    var savedSettings = (window.creditsShortcodeSettings && typeof window.creditsShortcodeSettings === 'object') ? window.creditsShortcodeSettings : {};

    registerBlockType('credits/shortcode', {
        title: __('Credits Link', textdomain),
        icon: 'share',
        category: 'widgets',
        attributes: {
            link: {
                type: 'string',
                default: '#'
            },
            type: {
                type: 'string',
                default: 'source'
            },
            name: {
                type: 'string',
                default: ''
            },
            badgeColor: {
                type: 'string',
                default: ''
            },
            linkColor: {
                type: 'string',
                default: ''
            },
            linkTextColor: {
                type: 'string',
                default: ''
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps ? useBlockProps({ className: 'credits wp-block-credits-shortcode' }) : { className: 'credits wp-block-credits-shortcode' };

            var hexColor = function (value) {
                return /^#(?:[A-Fa-f0-9]{3}){1,2}$/.test(value || '') ? value : '';
            };

            var currentType = (attributes.type || 'source').toLowerCase();
            var displayType = currentType === 'via' ? __('Via', textdomain) : __('Source', textdomain);
            var linkName = attributes.name || __('Credit Name', textdomain);
            var linkUrl = attributes.link || '#';

            var badgeBg = hexColor(attributes.badgeColor) || hexColor(savedSettings.badge_color);
            var linkBg = hexColor(attributes.linkColor) || hexColor(savedSettings.link_color);
            var linkTextClr = hexColor(attributes.linkTextColor) || hexColor(savedSettings.link_text_color);

            var badgeStyle = badgeBg ? { backgroundColor: badgeBg } : null;
            var linkStyle = linkBg ? { backgroundColor: linkBg } : null;
            var linkTextStyle = linkTextClr ? { color: linkTextClr } : null;

            var inspectorChildren = [
                el(
                    PanelBody,
                    { title: __('Credit Settings', textdomain), initialOpen: true },
                    el(SelectControl, {
                        label: __('Credit Type', textdomain),
                        value: attributes.type || 'source',
                        options: [
                            { label: __('Source', textdomain), value: 'source' },
                            { label: __('Via', textdomain), value: 'via' }
                        ],
                        onChange: function (newType) {
                            setAttributes({ type: newType });
                        }
                    }),
                    el(TextControl, {
                        label: __('Source / Via Name', textdomain),
                        value: attributes.name || '',
                        placeholder: __('e.g. TechZei', textdomain),
                        onChange: function (newName) {
                            setAttributes({ name: newName });
                        }
                    }),
                    el(TextControl, {
                        label: __('Link URL', textdomain),
                        value: attributes.link || '',
                        placeholder: 'https://example.com',
                        onChange: function (newLink) {
                            setAttributes({ link: newLink });
                        }
                    })
                )
            ];

            if (PanelColorSettings) {
                inspectorChildren.push(
                    el(PanelColorSettings, {
                        title: __('Accent Color Settings', textdomain),
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: badgeBg,
                                onChange: function (newColor) {
                                    setAttributes({ badgeColor: hexColor(newColor) });
                                },
                                label: __('Badge Background Color', textdomain)
                            },
                            {
                                value: linkBg,
                                onChange: function (newColor) {
                                    setAttributes({ linkColor: hexColor(newColor) });
                                },
                                label: __('Link Background Color', textdomain)
                            },
                            {
                                value: linkTextClr,
                                onChange: function (newColor) {
                                    setAttributes({ linkTextColor: hexColor(newColor) });
                                },
                                label: __('Link Text Color', textdomain)
                            }
                        ]
                    })
                );
            }

            return el(
                'ul',
                blockProps,
                el(InspectorControls, null, inspectorChildren),
                el(
                    'li',
                    { className: 'credits' },
                    el(
                        'span',
                        { className: 'cre_cate', style: badgeStyle },
                        displayType
                    ),
                    el(
                        'span',
                        { className: 'cre_cate_link', style: linkStyle },
                        el('a', { href: linkUrl, style: linkTextStyle, onClick: function(e) { e.preventDefault(); } }, linkName)
                    )
                )
            );
        },

        save: function () {
            // Dynamic block rendered via PHP server-side callback
            return null;
        }
    });
})(window.wp);
