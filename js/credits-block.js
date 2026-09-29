(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var blockEditor = wp.blockEditor || wp.editor || {};
    var InspectorControls = blockEditor.InspectorControls;
    var PanelColorSettings = blockEditor.PanelColorSettings;
    var RichText = blockEditor.RichText;
    var useBlockProps = typeof blockEditor.useBlockProps === 'function' ? blockEditor.useBlockProps : null;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;
    var Button = wp.components.Button;
    var __ = (wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__ : function (text) { return text; };
    var sprintf = (wp.i18n && typeof wp.i18n.sprintf === 'function') ? wp.i18n.sprintf : function (format, value) { return String(format).replace('%s', value); };
    var savedSettings = (window.creditsShortcodeSettings && typeof window.creditsShortcodeSettings === 'object') ? window.creditsShortcodeSettings : {};

    // Every translatable string passes the text domain as a literal, which is what
    // the translation extractor needs to find it: keep it that way.

    /**
     * Match frontend esc_url behavior for the editor's link checks (not stored attributes).
     */
    var sanitizeEditorLinkUrl = function (value) {
        var raw = String(value || '').trim();
        if (!raw || raw === '#') {
            return '#';
        }
        var cleaned = raw.replace(/[\x00-\x20\x7f]/g, '');
        var decoded = cleaned;
        try {
            if (typeof document !== 'undefined') {
                var ta = document.createElement('textarea');
                ta.innerHTML = cleaned;
                decoded = ta.value.replace(/[\x00-\x20\x7f]/g, '');
            }
        } catch (e) {
            decoded = cleaned;
        }
        var schemeMatch = decoded.match(/^([a-zA-Z][a-zA-Z0-9+.\-]*):/);
        if (schemeMatch) {
            var scheme = schemeMatch[1].toLowerCase();
            if (['http', 'https', 'mailto', 'ftp'].indexOf(scheme) === -1) {
                return '#';
            }
        }
        return cleaned;
    };

    /**
     * 'empty'   - nothing usable yet (blank, or the bare "#" placeholder)
     * 'invalid' - something was typed that the front end would discard
     * 'ok'      - a link that renders, including intentional #fragment links
     */
    var getLinkState = function (value) {
        var raw = String(value || '').trim();
        if (raw === '' || raw === '#') {
            return 'empty';
        }
        return sanitizeEditorLinkUrl(raw) === '#' ? 'invalid' : 'ok';
    };

    // The name is stored as plain text; RichText edits HTML, so convert at the boundary.
    var textToHtml = function (text) {
        return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };

    var htmlToText = function (html) {
        var withoutBreaks = String(html || '').replace(/<br\s*\/?>/gi, ' ');
        // DOMParser builds an inert document, so nothing in the markup can run.
        var doc = new DOMParser().parseFromString(withoutBreaks, 'text/html');
        return (doc.body.textContent || '').replace(/[ \r\n]+/g, ' ');
    };

    var hexColor = function (value) {
        return /^#(?:[A-Fa-f0-9]{3}){1,2}$/.test(value || '') ? value : '';
    };

    registerBlockType('credits/shortcode', {
        apiVersion: 3,
        title: __('Credits Link', 'credits-shortcode'),
        icon: 'share',
        category: 'widgets',
        attributes: {
            link: {
                type: 'string',
                default: ''
            },
            type: {
                type: 'string',
                default: ''
            },
            spacing: {
                type: 'string',
                default: ''
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
            },
            newTab: {
                type: 'boolean',
                default: true
            }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var savedType = savedSettings.type === 'via' ? 'via' : 'source';
            var currentType = (attributes.type || savedType).toLowerCase();
            var spacingValues = ['compact', 'standard', 'spacious'];
            var sanitizeSpacing = function (value) {
                return spacingValues.indexOf(value) !== -1 ? value : '';
            };
            var siteSpacing = sanitizeSpacing(savedSettings.spacing) || 'standard';
            var currentSpacing = sanitizeSpacing(attributes.spacing) || siteSpacing;
            var displayType = currentType === 'via' ? __('Via', 'credits-shortcode') : __('Source', 'credits-shortcode');

            // Incomplete credits are flagged in the editor only; saved content and the
            // front end are not changed by this.
            var linkState = getLinkState(attributes.link);
            var hasName = String(attributes.name || '').trim() !== '';
            var problems = [];
            var linkProblem = '';
            if (linkState === 'empty') {
                linkProblem = __('Add a link so readers can reach the source.', 'credits-shortcode');
            } else if (linkState === 'invalid') {
                linkProblem = __('This link cannot be used. Start it with http://, https://, mailto: or ftp://.', 'credits-shortcode');
            }
            if (!hasName) {
                problems.push(__('Add a name for this credit.', 'credits-shortcode'));
            }
            if (linkProblem) {
                problems.push(linkProblem);
            }

            var blockClassName = 'credits wp-block-credits-shortcode credits-spacing-' + currentSpacing + (problems.length ? ' credits-editor-incomplete' : '');
            var blockProps = useBlockProps ? useBlockProps({ className: blockClassName }) : { className: blockClassName };

            var noticeId = 'credits-notice-' + props.clientId;
            var linkInputId = 'credits-link-' + props.clientId;

            // Colors: a per-credit color wins, then the site default, then neutral styling.
            var overrides = {
                badge: hexColor(attributes.badgeColor),
                link: hexColor(attributes.linkColor),
                text: hexColor(attributes.linkTextColor)
            };
            var siteColors = {
                badge: hexColor(savedSettings.badge_color),
                link: hexColor(savedSettings.link_color),
                text: hexColor(savedSettings.link_text_color)
            };
            var badgeBg = overrides.badge || siteColors.badge;
            var linkBg = overrides.link || siteColors.link;
            var linkTextClr = overrides.text || siteColors.text;
            var hasColorOverride = !!(overrides.badge || overrides.link || overrides.text);

            var badgeStyle = badgeBg ? { backgroundColor: badgeBg } : null;
            var linkStyle = linkBg ? { backgroundColor: linkBg } : null;
            var linkTextStyle = linkTextClr ? { color: linkTextClr } : null;

            // Sidebar swatch labels are truncated, so where each color comes from is spelled out below them.
            var colorStatus = function (label, override, siteValue) {
                var source = override
                    ? __('Custom for this credit', 'credits-shortcode')
                    : (siteValue ? __('Site default', 'credits-shortcode') : __('Plugin default', 'credits-shortcode'));
                /* translators: 1: name of a color setting, for example "Badge Background Color". 2: where that color comes from, for example "Site default". */
                return sprintf(__('%1$s: %2$s', 'credits-shortcode'), label, source);
            };

            var colorLabels = {
                badge: __('Badge Background Color', 'credits-shortcode'),
                link: __('Link Background Color', 'credits-shortcode'),
                text: __('Link Text Color', 'credits-shortcode')
            };

            var inspectorChildren = [
                el(
                    PanelBody,
                    { key: 'settings', title: __('Credit Settings', 'credits-shortcode'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Credit Type', 'credits-shortcode'),
                        value: attributes.type || '',
                        options: [
                            { label: __('Use site default', 'credits-shortcode'), value: '' },
                            { label: __('Source', 'credits-shortcode'), value: 'source' },
                            { label: __('Via', 'credits-shortcode'), value: 'via' }
                        ],
                        onChange: function (newType) {
                            setAttributes({ type: newType });
                        }
                    }),
                    el(TextControl, {
                        label: __('Source / Via Name', 'credits-shortcode'),
                        value: attributes.name || '',
                        placeholder: __('e.g. TechZei', 'credits-shortcode'),
                        onChange: function (newName) {
                            setAttributes({ name: newName });
                        }
                    }),
                    el(TextControl, {
                        label: __('Link URL', 'credits-shortcode'),
                        value: attributes.link || '',
                        placeholder: 'https://example.com',
                        help: linkProblem || undefined,
                        onChange: function (newLink) {
                            setAttributes({ link: newLink });
                        }
                    }),
                    el(ToggleControl, {
                        label: __('Open in new tab', 'credits-shortcode'),
                        checked: attributes.newTab !== false,
                        onChange: function (newTab) {
                            setAttributes({ newTab: !!newTab });
                        }
                    }),
                    el(SelectControl, {
                        label: __('Spacing', 'credits-shortcode'),
                        value: attributes.spacing || '',
                        options: [
                            { label: __('Use site default', 'credits-shortcode'), value: '' },
                            { label: __('Compact', 'credits-shortcode'), value: 'compact' },
                            { label: __('Standard', 'credits-shortcode'), value: 'standard' },
                            { label: __('Spacious', 'credits-shortcode'), value: 'spacious' }
                        ],
                        onChange: function (newSpacing) {
                            setAttributes({ spacing: sanitizeSpacing(newSpacing) });
                        }
                    })
                )
            ];

            if (PanelColorSettings) {
                inspectorChildren.push(
                    el(
                        PanelColorSettings,
                        {
                            key: 'colors',
                            title: __('Accent Color Settings', 'credits-shortcode'),
                            initialOpen: false,
                            colorSettings: [
                                {
                                    value: badgeBg,
                                    onChange: function (newColor) {
                                        setAttributes({ badgeColor: hexColor(newColor) });
                                    },
                                    label: colorLabels.badge
                                },
                                {
                                    value: linkBg,
                                    onChange: function (newColor) {
                                        setAttributes({ linkColor: hexColor(newColor) });
                                    },
                                    label: colorLabels.link
                                },
                                {
                                    value: linkTextClr,
                                    onChange: function (newColor) {
                                        setAttributes({ linkTextColor: hexColor(newColor) });
                                    },
                                    label: colorLabels.text
                                }
                            ]
                        },
                        el(
                            'ul',
                            { className: 'credits-color-status' },
                            el('li', null, colorStatus(colorLabels.badge, overrides.badge, siteColors.badge)),
                            el('li', null, colorStatus(colorLabels.link, overrides.link, siteColors.link)),
                            el('li', null, colorStatus(colorLabels.text, overrides.text, siteColors.text))
                        ),
                        el(
                            'p',
                            { className: 'credits-color-help' },
                            __('Colors you leave unchanged follow the site defaults set under Settings → Credits.', 'credits-shortcode')
                        ),
                        hasColorOverride
                            ? el(
                                Button,
                                {
                                    variant: 'secondary',
                                    onClick: function () {
                                        setAttributes({ badgeColor: '', linkColor: '', linkTextColor: '' });
                                    }
                                },
                                __('Reset colors to site default', 'credits-shortcode')
                            )
                            : null
                    )
                );
            }

            var nameElement = RichText
                ? el(RichText, {
                    tagName: 'span',
                    className: 'credits-editor-name',
                    value: textToHtml(attributes.name),
                    onChange: function (html) {
                        setAttributes({ name: htmlToText(html) });
                    },
                    placeholder: __('Add credit name', 'credits-shortcode'),
                    'aria-label': __('Credit name', 'credits-shortcode'),
                    allowedFormats: [],
                    withoutInteractiveFormatting: true,
                    disableLineBreaks: true
                })
                : (attributes.name || __('Credit Name', 'credits-shortcode'));

            var chip = el(
                'li',
                { className: 'credits', key: 'chip' },
                el('span', { className: 'cre_cate', style: badgeStyle }, displayType),
                el(
                    'span',
                    { className: 'cre_cate_link', style: linkStyle },
                    // No href on purpose: the editor preview must never navigate, and an
                    // editable child inside a real link cannot reliably take the caret.
                    el('a', { style: linkTextStyle, onClick: function (event) { event.preventDefault(); } }, nameElement)
                )
            );

            var listChildren = [chip];

            if (problems.length) {
                listChildren.push(
                    el(
                        'li',
                        { className: 'credits-editor-notice', id: noticeId, key: 'notice', 'aria-live': 'polite' },
                        problems.map(function (message) {
                            return el('span', { key: message }, message);
                        })
                    )
                );
            }

            if (props.isSelected) {
                listChildren.push(
                    el(
                        'li',
                        { className: 'credits-editor-link-row', key: 'link-row' },
                        el('label', { htmlFor: linkInputId }, __('Link URL', 'credits-shortcode')),
                        el('input', {
                            id: linkInputId,
                            type: 'text',
                            inputMode: 'url',
                            autoComplete: 'off',
                            spellCheck: false,
                            value: attributes.link || '',
                            placeholder: 'https://example.com',
                            'aria-invalid': linkState === 'invalid' ? 'true' : undefined,
                            'aria-describedby': linkProblem ? noticeId : undefined,
                            onChange: function (event) {
                                setAttributes({ link: event.target.value });
                            }
                        })
                    )
                );
            }

            return el(
                Fragment,
                null,
                el(InspectorControls, null, inspectorChildren),
                el('ul', blockProps, listChildren)
            );
        },

        save: function () {
            // Dynamic block rendered via PHP server-side callback
            return null;
        }
    });
})(window.wp);
