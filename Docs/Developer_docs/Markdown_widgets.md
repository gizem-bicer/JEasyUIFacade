# Markdown Widgets

jEasyUI uses Markian directly for Markdown inputs and displays. There is no backend selector or Toast UI fallback. UI5 and other facades are unaffected: Core's legacy `ToastUIEditorTrait` remains unchanged for those consumers.

| Setting | Default | Meaning |
| --- | --- | --- |
| `LIBS.MARKIAN.EDITOR.JS` / `.CSS` | `axenox/markian/dist/markian.min.js` / `markian.css` | Prebuilt editor assets. |
| `LIBS.MARKIAN.MARKDOWN.CSS` | `npm-asset/github-markdown-css/github-markdown-light.css` | Explicit editor content CSS, including AJAX-evaluated startup. |
| `LIBS.MARKIAN.MARKDOWN.CSS_CLASSES` | `["markdown-body"]` | Additional editor document-wrapper classes; controls are not given these classes. |
| `LIBS.MARKDOWN.CSS` | `npm-asset/github-markdown-css/github-markdown-light.css` | Original display-widget content stylesheet, passed to the viewer. |
| `LIBS.MARKIAN.VIEWER.JS` / `.CSS` | `axenox/markian/dist/markian-viewer.min.js` / `markian-viewer.css` | Independent rendering-only assets. |
| `LIBS.MARKIAN.BRIDGE.JS` | `exface/core/Facades/AbstractAjaxFacade/js/markian/MarkianAdapter.js` | JavaScript adapter for host lifecycle and value bindings. |
| `LIBS.MARKIAN.MERMAID.JS` / `.CSS` | `axenox/markian/dist/extensions/mermaid.min.js` / `mermaid.css` | Optional editing extension. |
| `LIBS.MARKIAN.VIEWER_MERMAID.JS` / `.CSS` | `axenox/markian/dist/extensions/viewer-mermaid.min.js` / `viewer-mermaid.css` | Optional rendering-only adapter. |
| `LIBS.MERMAID.JS` | Core Mermaid script URL | Passed as the Mermaid `source` string; no duplicated version setting is needed. |

Install the declared `axenox/markian` Composer dependency alongside a Core containing `MarkianTrait`. There is no npm/build step on the PHP host. Source URLs use the facade asset resolver, including installation subdirectories. The former bundled Toast UI Editor assets and their dependency inventory entry have been removed; this facade ships only the libraries it uses itself.

## Input Widgets

`EuiInputMarkdown` uses Markian without an opt-in setting. All inputs request visual startup, regardless of legacy `editor_mode`. Unsupported visual Markdown can still fall back to source mode under the library's preservation policy. Mention/stencil insertion controls require the unfinished W8 integration. Configured widgets remain editable and retain their document source, but emit a console warning because those controls are unavailable. They never switch editors.

The shared jEasyUI theme scopes Markian toolbar sizing to `.exf-input-markdown .markian`: a 36px toolbar with 26px-high buttons matches table action toolbars. It uses Markian's toolbar CSS variables without changing menus or styling other hosts.

Dropdowns and the image, link and search popups use the same scoped theme: native 12px typography, compact fields and buttons, menu hover states and keyboard focus. The Material theme supplies its accent colors and underlined inputs through shared theme variables. Popup positioning, keyboard navigation and lifecycle remain owned by Markian; editors outside jEasyUI Markdown inputs retain their original styling.

The additive Core trait supplies safe source encoding, value access, replacement/reset and asset declarations. The Core browser bridge keeps a stable facade root around Markian's inner mount. It queues pre-readiness prefill and disabled updates, suppresses host-load change echoes, reads complete active drafts, and treats identical projected values as no-ops. A different host value explicitly discards pending drafts and resets history: only use it for an intentional record load/reset. Failed visual imports leave the old value intact and report an error.

Inputs retain jEasyUI live references, conditional disabled/required settings and framework data-type validation. Validation sets `aria-invalid` and its message on the Markian root. Disabled/read-only inputs remain editors with selection, search, copying and navigation. Runtime enabling does not discard drafts. Layout changes remeasure the visible editor through `ResizeObserver`; dialog destruction and DOM removal dispose owned instances and observers.

`allow_images: false` removes only the `image` toolbar item through Markian's recursive toolbar registry API, preserving default and custom menus, and rejects raster paste/drop callbacks. Existing image source is preserved, not regex-stripped. This pilot does **not** prevent manually entered image Markdown in Source or pasted textual Markdown; strict source-level image permission enforcement remains a W9 gate. It is not a server authorization boundary.

## Display Widgets

`EuiMarkdown` and its `EuiDisplayMarkdown` subclass pass Markdown directly to `MarkianViewer`, never through the widget's PHP `getHtml()` converter or the display data-type HTML formatter. Getters return source, setters replace the viewer document, and explicit empty values clear output. The viewer sanitizes HTML and URLs and keeps tasks nonmutating. Unknown comments remain in source but are invisible in output.

Displays retain the original GitHub light stylesheet selected by `LIBS.MARKDOWN.CSS`. Both inputs and displays use Markian's `markdownCssClasses: ["markdown-body"]` on their document wrappers, not on toolbars. Explicit `markdownCssUrls` replaces Markian's built-in content theme, so inline code, headings and tables receive the host stylesheet consistently after Preview switches and value updates. jEasyUI keeps display backgrounds transparent and tables full-width.

`Markdown.base_url` becomes a render-time document base. Its popup/new-tab link options remain host callbacks; same-document anchors stay local. `DisplayMarkdown` does not require these `Markdown`-specific properties. Data-type newline settings control softbreak rendering. Mermaid uses the optional viewer adapter with the Core runtime URL as `source`; Markian checks for `initialize()` and `render()` at runtime. There is no version setting or old `asset` fallback. It never globally scans or reconfigures other widgets. Display-only pages load no editor bundle.

Raw HTML previously accepted by the server converter can be omitted from sanitized output. General HTML stencils, front-matter presentation, dynamic `base_url_attribute_alias` updates and `html_template` wrapping have not been accepted for this integration. Consumers relying on these behaviors need compatibility work; there is no legacy display switch. This integration does not change or secure other server-rendered Markdown paths such as DocsFacade.

## Verification

The browser fixture exercises the Core bridge against installed packaged assets. Real New/Edit message dialogs additionally verified AJAX startup, visual/source editing, description loading, disabled/enabled transitions, clearing, explicit content CSS and attribute-safe dialog destruction/reopening. Repeated AJAX evaluation of the editor bundle registers Mermaid per factory, not just once per page. These checks do not replace the remaining validation, fullscreen/iframe and mixed-facade acceptance gates or the missing library prerequisites. No records were saved or deleted.

Serve the installation's `vendor` directory on an isolated loopback test port, for example:

```powershell
php -S 127.0.0.1:5197 -t <installation>/vendor
```

Open `/exface/jeasyuifacade/Tests/Playwrite/markian.html`. Run the focused Playwright tests with `Tests/Playwrite/playwright.config.js`, a provisioned Playwright runner, `--max-failures=1 --global-timeout=120000`. Playwright-specific tests and fixtures live in `Tests/Playwrite`, leaving room for other browser test frameworks. `JEASYUI_TEST_URL` overrides the test origin. `PLAYWRIGHT_MODULE` optionally points to a host-provided `@playwright/test` module, including Markian's contributor installation. No test runner is required by production PHP requests.

After changing bridge or library assets during development, bypass the browser cache or refresh the installation's normal asset version. A cached bridge can otherwise hide a lifecycle fix even when PHP output is current.

Do not initialize two engines on one mount. Retain the current complete Markdown value before intentionally destroying or replacing an editor instance. Upgrading this facade replaces the implementation, not stored Markdown data.