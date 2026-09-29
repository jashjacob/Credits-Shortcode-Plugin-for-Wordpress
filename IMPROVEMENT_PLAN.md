# Credits Shortcode & Block improvement plan

Start with a small reliability release, follow with improvements to everyday editing and styling, then add grouped credits. Keep existing shortcodes, saved block attributes, and site-wide settings compatible throughout.

The initial review was completed on September 18, 2026 against version 1.5.0. The local suite passed with 38 tests and 162 assertions on PHP 8.5.10. Those tests use WordPress stubs; a live WordPress editor and theme compatibility check is still needed.

| Order | Work | Expected outcome |
| --- | --- | --- |
| 1 | Fix block registration and rendering inconsistencies | Editor settings and published output behave consistently. |
| 2 | Add WordPress integration coverage | Tests catch failures caused by real WordPress behavior. |
| 3 | Improve link entry and default controls | Authors can add valid credits with fewer steps. |
| 4 | Improve styling and accessibility | Credits fit different themes, devices, and input methods. |
| 5 | Finish performance, documentation, and release checks | The release is accurate, lightweight, and installable. |
| 6 | Build a grouped credits block | Authors can manage several references together. |

1. **Fix the confirmed editor and rendering issues.**

   - [x] Correct the block API version passed from PHP to JavaScript. ~~WordPress localization converts the number `3` to the string `"3"`, which currently fails the strict JavaScript comparison and selects API version 1. Normalize the value or pass typed configuration using `wp_add_inline_script()` and `wp_json_encode()`.~~ Done as of 1.6.0: `apiVersion: 3` is hardcoded in both `block.json` and `js/credits-block.js` rather than passed through localization, so the string/number mismatch this bullet describes cannot occur. Regression test: `tests/test_settings.php` asserts `block_api_version` is never localized.
   - [ ] ~~Retain the older WordPress registration fallback~~ Superseded by a 1.6.0 decision: the legacy Block API version 1 fallback was removed outright instead of retained (see `readme.txt` changelog, 1.6.0). Flagging the mismatch with this bullet rather than resolving it — reintroducing a fallback is a product decision, not a bug fix.
   - [x] Preserve a block's custom CSS classes in published output. Implemented via `credits_get_block_wrapper_class()` using `get_block_wrapper_attributes()` (WP 5.6+) with a `className`-attribute fallback for older WordPress; see `credits_shortcode.php`.
   - [x] Keep shortcode rendering independent of the active block's wrapper context. `credits_print_shortcode()` only receives a wrapper class when called from `credits_render_block()`; direct shortcode calls never see it (see `test_shortcode_rendering_is_independent_of_block_wrapper_context`). The HTML allowlist needed no change — `ul.class` was already permitted and only additional class tokens are added, never new attributes.
   - [x] Add focused regression coverage for API version selection and custom class preservation. API version: `tests/test_settings.php`. Custom class preservation: new tests in `tests/test_renderer.php` (duplicate-class, multi-class merge, hostile-input sanitization, and shortcode/block parity checks).

   Acceptance: modern WordPress registers the block with API version 3 on both sides; the legacy path still works; a custom class entered in the editor appears on the front end; existing shortcode output remains compatible.

   Main files: `credits_shortcode.php`, `js/credits-block.js`, `tests/bootstrap.php`, and the renderer/settings tests.

2. **Add tests that run against actual WordPress.**

   - [ ] Keep the fast unit suite and add a separate WordPress integration suite.
   - [ ] Exercise real script localization, block registration, `do_shortcode()`, and `render_block()` rather than only direct calls to the renderer.
   - [ ] Cover site defaults, explicit overrides, custom classes, and representative unsafe URL, text, and color inputs using WordPress's own sanitizers.
   - [ ] Add an editor smoke test: insert a credit, edit its values, save, reload, and verify the published output.
   - [ ] Test representative older and current supported WordPress versions using compatible PHP versions. Add PHP 8.5 to CI after confirming dependency compatibility.
   - [ ] Document how contributors run both suites and the editor check.

   Acceptance: the integration suite detects the API version issue from step 1 when that fix is reverted. Saving and reloading a block retains its name, URL, type, colors, spacing, and custom class.

   Main files: `tests/`, `scripts/ci-local.sh`, `composer.json`, and PHPUnit configuration.

   Note: CI now runs locally through `scripts/ci-local.sh` (`composer ci`, `composer ci:full`) and a pre-push hook; the GitHub Actions workflows were removed. Any "add to CI" bullet above now means adding a stage to that script, and the PHP version matrix is no longer exercised.

3. **Make individual credits easier to edit.**

   - [ ] Add inline name and URL editing to the block, while retaining sidebar controls for appearance.
   - [ ] Show an instructional empty state and clear feedback for missing or invalid URLs. Avoid presenting an untouched credit as a finished link.
   - [ ] Define and test incomplete-credit rendering before changing it. Preserve existing valid links and intentionally used fragment links; distinguish the editor placeholder from saved content.
   - [ ] Add an "Open in new tab" control to blocks and shortcodes. Preserve the existing new-tab behavior when the new attribute is absent, and retain appropriate link security attributes.
   - [ ] Add "Use site default" to the credit-type selector so an explicit Source/Via override can be cleared.
   - [ ] Make inherited colors and per-credit overrides understandable, with a clear reset-to-default action.
   - [ ] Replace the Classic Editor's sequence of browser prompts with one form containing type, name, and URL. Prefill the name from selected text and use the same validation expectations as the block.
   - [ ] Ensure inserted shortcode values cannot break out of shortcode syntax or become unintended editor HTML.
   - [ ] Translate all new strings and verify that they load in both editors.

   Acceptance: an author can create a valid credit directly in the block, identify an incomplete link before publishing, reset the type to the site default, and choose link-opening behavior. Existing saved credits keep their behavior. The Classic Editor supports keyboard entry, cancellation, and safe insertion.

   Main files: `js/credits-block.js`, `credits_shortcode_plugin.js`, `credits_shortcode.php`, and `block.json`.

4. **Improve theme compatibility and accessibility.**

   - [ ] Replace unnecessary `!important` rules with scoped styles and CSS variables for colors and spacing. Retain targeted legacy rules where a compatibility check demonstrates they are needed.
   - [ ] Allow theme typography and container layout to influence the credit without losing its compact badge presentation.
   - [ ] Handle long names and unbroken text on narrow screens without horizontal overflow.
   - [ ] Use logical alignment, spacing, and corner rules for right-to-left layouts.
   - [ ] Add visible keyboard focus styles and ensure they are not clipped by the badge container.
   - [ ] Respect `prefers-reduced-motion` for hover movement and transitions.
   - [ ] Check default and inherited color combinations in light and dark themes; add useful contrast feedback for custom colors where practical.
   - [ ] Associate settings-page labels with their controls using stable IDs and Settings API label configuration.
   - [ ] Verify editor/front-end consistency in a classic theme and a block theme, including narrow layouts, 200% zoom, and keyboard navigation.

   Acceptance: credits remain readable and operable across the checked themes and layouts, long text does not overflow, RTL presentation is coherent, and site owners can customize appearance without competing with broad overrides.

   Main files: `css/style.css`, settings markup in `credits_shortcode.php`, and editor controls.

5. **Complete performance and release polish.**

   - [ ] Replace unconditional front-end CSS loading with a loading strategy that covers blocks and shortcodes, including widgets, templates, reusable content, and programmatic rendering. Avoid relying solely on scanning the current post.
   - [ ] Verify that styles arrive in time to prevent unstyled credits. Keep the change separate if reliable loading across the supported environments needs further work.
   - [ ] Update the README's old color defaults and outdated test count. The CI badge now points at `scripts/ci-local.sh` since there is no hosted CI to report status.
   - [ ] Document site defaults, override precedence, link-opening behavior, empty-link behavior, and the updated Classic Editor workflow.
   - [ ] Refresh screenshots after the UI changes are stable.
   - [ ] Keep plugin headers, block metadata, changelogs, and release versions consistent. Set "Tested up to" from completed compatibility checks.
   - [ ] Exclude this plan and other development-only files from the release package.
   - [ ] Verify a clean packaged installation, an upgrade from 1.5.0, settings persistence, and deactivation/reactivation behavior.

   Acceptance: pages without credits avoid unnecessary plugin CSS where the supported loading strategy allows it; all credit locations remain styled; the installation package and documentation match the shipped behavior.

   Main files: `credits_shortcode.php`, `README.md`, `readme.txt`, `.distignore`, `scripts/ci-local.sh`, and release assets.

6. **Add grouped credits as a separate feature release.**

   - [ ] Introduce a new parent Credits Group block that contains existing Credits Link blocks, preserving the individual block and shortcode formats.
   - [ ] Provide add, remove, and reorder controls for references.
   - [ ] Add shared appearance and spacing controls. Define inheritance explicitly: individual override, then group setting, then site default.
   - [ ] Use accessible group/list markup and avoid applying individual outer margins between every grouped item.
   - [ ] Add an explicit way to group selected existing credit blocks without changing their names or URLs.
   - [ ] Cover saving, reloading, reordering, inheritance, and responsive rendering in the editor and integration checks.

   Acceptance: authors can maintain several references as one unit, retain per-credit exceptions, and group existing credits without losing attribution data. Individual credits continue to work independently.

Ship steps 1–2 as the first reliability milestone. Ship steps 3–5 as the usability milestone once editor and theme checks pass. Begin step 6 after those changes are stable. Avoid coupling the fixes to a broad PHP rewrite or a new JavaScript build system.

Implementation references: [WordPress script localization](https://developer.wordpress.org/reference/classes/wp_scripts/localize/), [dynamic block wrappers](https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-wrapper/), and [block supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/).
