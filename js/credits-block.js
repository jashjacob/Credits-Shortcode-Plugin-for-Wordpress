(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var PanelColorSettings = wp.blockEditor ? wp.blockEditor.PanelColorSettings : (wp.editor ? wp.editor.PanelColorSettings : null);
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : null;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;

    registerBlockType('credits/shortcode', {
        title: 'Credits Link',
        icon: 'share',
        category: 'embed',
        attributes: {
            link: {
                type: 'string',
                default: ''
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
                default: '#ef4423'
            },
            linkColor: {
                type: 'string',
                default: '#E0D9D9'
            },
            linkTextColor: {
                type: 'string',
                default: '#ef4423'
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps ? useBlockProps({ className: 'credits wp-block-credits-shortcode' }) : { className: 'credits wp-block-credits-shortcode' };

            var currentType = (attributes.type || 'source').toLowerCase();
            var displayType = currentType === 'via' ? 'Via' : 'Source';
            var linkName = attributes.name || 'Credit Name';
            var linkUrl = attributes.link || '#';

            var badgeBg = attributes.badgeColor || '#ef4423';
            var linkBg = attributes.linkColor || '#E0D9D9';
            var linkTextClr = attributes.linkTextColor || '#ef4423';

            var inspectorChildren = [
                el(
                    PanelBody,
                    { title: 'Credit Settings', initialOpen: true },
                    el(SelectControl, {
                        label: 'Credit Type',
                        value: attributes.type || 'source',
                        options: [
                            { label: 'Source', value: 'source' },
                            { label: 'Via', value: 'via' }
                        ],
                        onChange: function (newType) {
                            setAttributes({ type: newType });
                        }
                    }),
                    el(TextControl, {
                        label: 'Source / Via Name',
                        value: attributes.name || '',
                        placeholder: 'e.g. TechZei',
                        onChange: function (newName) {
                            setAttributes({ name: newName });
                        }
                    }),
                    el(TextControl, {
                        label: 'Link URL',
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
                        title: 'Accent Color Settings',
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: badgeBg,
                                onChange: function (newColor) {
                                    setAttributes({ badgeColor: newColor || '#ef4423' });
                                },
                                label: 'Badge Background Color'
                            },
                            {
                                value: linkBg,
                                onChange: function (newColor) {
                                    setAttributes({ linkColor: newColor || '#E0D9D9' });
                                },
                                label: 'Link Background Color'
                            },
                            {
                                value: linkTextClr,
                                onChange: function (newColor) {
                                    setAttributes({ linkTextColor: newColor || '#ef4423' });
                                },
                                label: 'Link Text Color'
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
                    el('span', { className: 'cre_cate', style: { backgroundColor: badgeBg } }, displayType),
                    el(
                        'span',
                        { className: 'cre_cate_link', style: { backgroundColor: linkBg } },
                        el('a', { href: linkUrl, style: { color: linkTextClr }, onClick: function(e) { e.preventDefault(); } }, linkName)
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
