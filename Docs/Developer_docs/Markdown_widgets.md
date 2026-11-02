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
| `LIBS.MARKIAN.CODE.JS` / `.CSS` | `axenox/markian/dist/extensions/code.min.js` / `code.css` | Inputs activate Code editing; displays reuse the exported grammar catalogue/loader and syntax colors without activating editing controls. |
| `LIBS.MARKIAN.CODE.LANGUAGES` | `axenox/markian/dist/extensions/languages/` | Explicit lazy grammar base, resolved through the facade asset resolver for AJAX-evaluated scripts. |
| `LIBS.MARKIAN.MERMAID.JS` / `.CSS` | `axenox/markian/dist/extensions/mermaid.min.js` / `mermaid.css` | Optional editing extension. |
| `LIBS.MARKIAN.VIEWER_MERMAID.JS` / `.CSS` | `axenox/markian/dist/extensions/viewer-mermaid.min.js` / `viewer-mermaid.css` | Optional rendering-only adapter. |
| `LIBS.MERMAID.JS` | Core Mermaid script URL | Passed as the Mermaid `source` string; no duplicated version setting is needed. |

Install the declared `axenox/markian` Composer dependency alongside a Core containing `MarkianTrait`. There is no npm/build step on the PHP host. Source URLs use the facade asset resolver, including installation subdirectories. The former bundled Toast UI Editor assets and their dependency inventory entry have been removed; this facade ships only the libraries it uses itself.

## Input Widgets

`EuiInputMarkdown` uses Markian without an opt-in setting. All inputs request visual startup, regardless of legacy `editor_mode`. Unsupported visual Markdown can still fall back to source mode under the library's preservation policy. Configured mentions support suggestions and compact chips in Visual mode. Stencil insertion controls remain unfinished and emit a console warning; they never switch editors.

The shared jEasyUI theme scopes Markian toolbar sizing to `.exf-input-markdown .markian`: a 36px toolbar with 26px-high buttons matches table action toolbars. It uses Markian's toolbar CSS variables without changing menus or styling other hosts.

On phone viewports up to 600px, jEasyUI Markdown fullscreen uses the entire visual viewport without Markian's desktop 12px inset. Markian still owns viewport resize/scroll tracking, including keyboard-induced height and position changes, and restores the embedded editor on exit. Desktop fullscreen retains the original inset. Simulated keyboard viewport checks do not establish real-device keyboard conformance.

Dropdowns and the image, link and search popups use the same scoped theme: native 12px typography, compact fields and buttons, menu hover states and keyboard focus. The Material theme supplies its accent colors and underlined inputs through shared theme variables. Popup positioning, keyboard navigation and lifecycle remain owned by Markian; editors outside jEasyUI Markdown inputs retain their original styling.

The additive Core trait supplies safe source encoding, value access, replacement/reset and asset declarations. The Core browser bridge keeps a stable facade root around Markian's inner mount. It queues pre-readiness prefill and disabled updates, suppresses host-load change echoes, reads complete active drafts, and treats identical projected values as no-ops. A different host value explicitly discards pending drafts and resets history: only use it for an intentional record load/reset. Failed visual imports leave the old value intact and report an error.

Inputs retain jEasyUI live references, conditional disabled/required settings and framework data-type validation. Validation sets `aria-invalid` and its message on the Markian root. Disabled/read-only inputs remain editors with selection, search, copying and navigation. Runtime enabling does not discard drafts. Layout changes remeasure the visible editor through `ResizeObserver`; dialog destruction and DOM removal dispose owned instances and observers.

`allow_images: false` removes only the `image` toolbar item through Markian's recursive toolbar registry API, preserving default and custom menus, and rejects raster paste/drop callbacks. Existing image source is preserved, not regex-stripped. This pilot does **not** prevent manually entered image Markdown in Source or pasted textual Markdown; strict source-level image permission enforcement remains a W9 gate. It is not a server authorization boundary.

## Code Highlighting

`InputCode`, `InputMarkdown`, `Markdown`, and `DisplayMarkdown` accept the same `code_formatter` widget part. The configuration remains independent of the editor library: `EuiInputCode` translates it to Ace modes and formatters; Core's `MarkianTrait` and `MarkianAdapter` translate it into the Markian Code extension for inputs and sanitized fence renderers for displays. Markdown widgets never load Ace.

```json
{
	"code_formatter": {
		"colorize": true,
		"language": "javascript",
		"prettify": false
	}
}
```

| Widget setting | InputCode (Ace) | Markdown widgets (Markian) |
| --- | --- | --- |
| `colorize` (default `true`) | Select the configured syntax mode; `false` uses plain text. | Enable grammar highlighting; `false` uses an empty language catalogue and retains plain source. |
| `language` (optional) | Overrides the widget's `language` for highlighting; otherwise uses the widget language. | Limits the catalogue to this language, including its aliases. Omit it to highlight each fence according to its own language. It never replaces a fence's language. |
| `prettify` (default `true`) | Use the configured formatter when supported. | Accepted for configuration compatibility but not applied; Markdown source is never automatically reformatted. |
| `dialect` (optional) | Select the SQL formatting dialect. | Accepted for configuration compatibility but not applied; highlighting uses the SQL grammar. |

Markian's bundled catalogue contains JavaScript/`js`, TypeScript/`ts`, JSON, CSS, HTML/`xml`, PHP, SQL, Python/`py`, and YAML/`yml`. Ace supports a larger catalogue; an unsupported Markian language retains plain source. To support mixed-language Markdown, omit `code_formatter.language`. No Markdown configuration is required for the default catalogue. Syntax colors come from Markian's own stylesheet, not the Ace theme. Display highlighting retains plain source on load/render failure and skips highlighting beyond 100,000 characters or 5,000 lines per fence.

Code JS/CSS are loaded for inputs and displays. Individual grammars load only when needed, using the explicitly resolved language base and its asset-version query. Inputs register Code per Markian factory, preserving it when adding Mermaid and after AJAX re-evaluates the editor bundle. Displays use the same loader without registering an editor factory or loading the editor bundle. Their renderers construct text nodes and syntax spans, validate source offsets and token classes, honor cancellation, and leave final HTML sanitization to the viewer. Mermaid renderers are merged with code renderers rather than replacing them. Source, history, and dirty state are unaffected by highlighting.

## Mentions

Mention models keep separate `tag_prefix` and `tag_text_regex` properties. Widgets remain abstract configuration models: Core's `MarkianTrait` translates them into library rules, and `MarkianAdapter` owns the facade's AJAX transport. Markian contains no object aliases, credentials or endpoint logic.

```json
{
	"mentions": [{
		"caption": "Record",
		"tag_prefix": "#",
		"tag_text_regex": "\\d*",
		"tag_color": "lightblue",
		"autosuggest": {
			"object_alias": "example.App.RECORD",
			"filter_attribute": "NUMBER",
			"tag_attribute": "NUMBER",
			"suggestion_attribute": "LABEL",
			"max_number_of_rows": 10
		}
	}]
}
```

The prefix triggers suggestions; the body pattern must match the complete text after it. Omission of the pattern means `.*`. Tag bodies are bounded words containing Unicode letters/numbers, underscores and hyphens, not spaces or arbitrary Markdown. Use `*` rather than `+` when suggestions should appear immediately after the prefix.

All three attribute aliases belong to `autosuggest` and are relative to its `object_alias`, including relation paths. `filter_attribute` controls query matching, `suggestion_attribute` supplies the menu label, and `tag_attribute` supplies the value inserted after the prefix. If the latter two are omitted, they use the effective filter attribute. If the filter is omitted, the object's label attribute is used, or its UID when there is no label. The old mention-level `tag_text_attribute` and `filter_attribute_alias` are removed, with no compatibility aliases.

Suggestions use the configured ReadData action, page/widget identity and facade headers. The adapter requests all three columns and a row limit from 1 to 100 (default 10). A bare prefix reads the first limited results. Nonempty queries use an OR condition over `filter_attribute` and `tag_attribute`, with duplicate attributes removed. Numeric fields can require a complete number. Queries must still match `tag_text_regex` to activate suggestions.

The bridge omits numeric-field conditions for nonnumeric queries. This allows text searches when the inserted tag is a numeric identifier, without a server casting error. If no field can accept a nonempty query, it returns no suggestions without sending an unfiltered request.

Core's color parser converts opaque CSS colors, including names and short hex values, into Markian's hex background tint. Text retains its regular color. Selecting a result inserts the prefix and value as plain Markdown text followed by one space. Existing recognized mention links retain their source. Source/Preview and standalone viewers keep ordinary text/link presentation without suggestion requests.

Queries are aborted on cancellation; Markian rejects stale results after edits, view/permission changes or destruction. Loading, empty and failure states preserve the document. Keyboard selection and read-only restrictions remain library-owned. The suggestion button is cached per mention so rendering and server-side widget lookup use the same ID.

Suggestion rows use ordinary dropdown colors, not filled action-button styling. Only the hovered or keyboard-selected row receives the menu highlight.

Opening autosuggest dismisses the details tooltip and suppresses hover/focus details until the menu closes. Details text has equal padding on all sides, without extra space for an absent description.

Chip activation opens suggestions filtered by the complete tag body, including the `tag_attribute` condition, and emits the bubbling host event `exf-markian-tag-click`, whose `detail` is `{ rule, value, destination? }`. Hosts can subscribe to navigate or show a related record; clicking does not modify Markdown. Read-only activation keeps details without insertion suggestions. The default hover/focus details show the configured caption and tag text. No record action is invented when the model has none.

## Display Widgets

`EuiMarkdown` and its `EuiDisplayMarkdown` subclass pass Markdown directly to `MarkianViewer`, never through the widget's PHP `getHtml()` converter or the display data-type HTML formatter. Getters return source, setters replace the viewer document, and explicit empty values clear output. The viewer sanitizes HTML and URLs and keeps tasks nonmutating. Unknown comments remain in source but are invisible in output.

Displays retain the original GitHub light stylesheet selected by `LIBS.MARKDOWN.CSS`. Both inputs and displays use Markian's `markdownCssClasses: ["markdown-body"]` on their document wrappers, not on toolbars. Explicit `markdownCssUrls` replaces Markian's built-in content theme, so inline code, headings and tables receive the host stylesheet consistently after Preview switches and value updates. jEasyUI keeps display backgrounds transparent and tables full-width.

`Markdown.base_url` becomes a render-time document base. Its popup/new-tab link options remain host callbacks; same-document anchors stay local. `DisplayMarkdown` does not require these `Markdown`-specific properties. Data-type newline settings control softbreak rendering. Mermaid uses the optional viewer adapter with the Core runtime URL as `source`; Markian checks for `initialize()` and `render()` at runtime. There is no version setting or old `asset` fallback. It never globally scans or reconfigures other widgets. Display-only pages load no editor bundle.

Raw HTML previously accepted by the server converter can be omitted from sanitized output. General HTML stencils, front-matter presentation, dynamic `base_url_attribute_alias` updates and `html_template` wrapping have not been accepted for this integration. Consumers relying on these behaviors need compatibility work; there is no legacy display switch. This integration does not change or secure other server-rendered Markdown paths such as DocsFacade.

## Verification

Phone dialogs use full-width direct grid items, including nested widget groups, without changing compact inline groups or desktop columns. Header/body wrappers share a bounded flex layout so tabs receive only the height remaining below the header. Dialog toolbar wrappers contain floated button groups, allowing the footer measurement to reserve all wrapped rows rather than clipping Save/Close below the viewport. The focused browser regressions cover 320px/390px phones, desktop columns, header/tab bounds, wrapped buttons, and simulated fullscreen keyboard viewport changes. The ticket editor was also checked without saving a record.

The browser fixture exercises the Core bridge against installed packaged assets. Real New/Edit message dialogs additionally verified AJAX startup, visual/source editing, description loading, disabled/enabled transitions, clearing, explicit content CSS and attribute-safe dialog destruction/reopening. Repeated AJAX evaluation of the editor bundle registers Mermaid per factory, not just once per page. These checks do not replace the remaining validation, fullscreen/iframe and mixed-facade acceptance gates or the missing library prerequisites. No records were saved or deleted.

Serve the installation's `vendor` directory on an isolated loopback test port, for example:

```powershell
php -S 127.0.0.1:5197 -t <installation>/vendor
```

Open `/exface/jeasyuifacade/Tests/Playwrite/markian.html`. Run the focused Playwright tests with `Tests/Playwrite/playwright.config.js`, a provisioned Playwright runner, `--max-failures=1 --global-timeout=120000`. Playwright-specific tests and fixtures live in `Tests/Playwrite`, leaving room for other browser test frameworks. `JEASYUI_TEST_URL` overrides the test origin. `PLAYWRIGHT_MODULE` optionally points to a host-provided `@playwright/test` module, including Markian's contributor installation. No test runner is required by production PHP requests.

After changing bridge or library assets during development, bypass the browser cache or refresh the installation's normal asset version. A cached bridge can otherwise hide a lifecycle fix even when PHP output is current.

Do not initialize two engines on one mount. Retain the current complete Markdown value before intentionally destroying or replacing an editor instance. Upgrading this facade replaces the implementation, not stored Markdown data.