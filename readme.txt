=== WEB27 Site Search ===
Tags: search, ai, site search
Requires at least: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local WordPress search with optional conversational AI, semantic embeddings, public catalog connectors and a customizable search widget.

== Description ==
English and Russian dashboard, setup guide and search widget for public WordPress content. Additional knowledge sources support opt-in public custom post types, taxonomies and explicit connectors. Search uses a local passage index and WordPress queries during initial indexing. Excludes drafts, private posts and password-protected posts. Page builder metadata, gated membership content and specialized ecommerce fields require separate integration. The standalone edition has its own option and shortcode.

== Installation ==
1. Upload and activate the plugin.
2. Open Site Search → Setup guide for the built-in setup guide. Select content types and try a published title.
3. Use the floating button or place [web27_site_search] in a Shortcode block.
4. In the Interface tab, choose Automatic, English or Russian for the widget, and light, dark, high contrast, system or custom colors. The administration language follows your WordPress profile. Design Studio configures desktop/mobile placement, window layouts, dimensions, colors and material cards.

PHP mbstring is required. Optional sitemap indexing requires SimpleXML and DOM for full HTML extraction.

To upgrade, upload the ZIP and replace the installed plugin. Do not delete the previous plugin: uninstall deletes the local index and settings.

== Additional knowledge sources ==
Open Site Search → Knowledge sources. Review discovered public post types, taxonomies and registered connectors. Select only sources that anonymous visitors may genuinely access and save. Additional sources are off by default. Connectors return explicit public records; the plugin does not execute arbitrary queries or scan PHP files. The overview and background vectors cover up to 150 objects per source, 600 overall, up to 12 sources. Targeted text lookup can find further matches for supported sources. A dedicated adapter is needed for proprietary or access-controlled data. Standard WordPress and virtual-page storage remains unchanged.

== External services ==
Local text search works without registration or external AI. Select OpenAI (https://api.openai.com/v1), OpenRouter (https://openrouter.ai/api/v1), ProxyAPI (https://api.proxyapi.ru/v1), or a custom OpenAI-compatible HTTPS endpoint. OpenRouter uses Chat Completions; other profiles support Chat Completions or compatible Responses. Provider choice is independent of the interface language. Switching profiles preserves their saved credentials and disables external chat and semantic search until explicitly re-enabled. No automatic paid provider failover is performed.

When external AI is enabled, the chosen service receives visitor questions, matching public titles, URLs, excerpts and relevance scores, site profile where needed, administrator instructions and recent dialogue turns when memory is enabled, as well as the API credential and generation parameters. If additional sources are selected, matching public catalog records and a limited public site profile may also be included. ProxyAPI optionally receives technical session/environment metadata. Aggregators can forward requests to underlying model providers. Requests may incur charges. A changed server constant endpoint must have its own explicit credential if the saved endpoint differs.

Semantic search is a separate opt-in. The selected provider's /embeddings endpoint receives batches of up to 12 public text passages during indexing and queries during search, including follow-up context. This can occur even if chat is disabled. Additional source embeddings require a separate opt-in and send batches of up to six public objects per scheduled run, in addition to ordinary post/page batches. Custom endpoints must implement compatible embeddings. Changing provider, endpoint or embedding model requires new vectors; existing text passages and corrections remain. Never enable semantic search until the embedding model and costs have been checked. After a failed background request, automatic retries for the same connection settings are delayed for five minutes. The daily chat request limit excludes embeddings; set provider-side spending limits. Old incompatible vectors are excluded and replaced gradually; reverting the provider may require new vectors again.

Manual connection tests use a synthetic prompt or sentence and may incur charges. Loading the model catalog sends authentication but no visitor content. A model appearing in a catalog does not guarantee compatibility or access. The administrator must supply a permitted account and verify current regional availability and pricing.

OpenAI: https://platform.openai.com/
Terms: https://openai.com/policies/business-terms/
Privacy: https://openai.com/policies/privacy-policy/
OpenRouter: https://openrouter.ai/
Terms: https://openrouter.ai/terms
Privacy: https://openrouter.ai/privacy
ProxyAPI: https://proxyapi.ru/
Terms: https://proxyapi.ru/terms
Privacy: https://proxyapi.ru/privacy
Underlying model providers may include OpenAI, Anthropic (https://www.anthropic.com/legal/commercial-terms and https://www.anthropic.com/legal/privacy) and Google (https://ai.google.dev/gemini-api/terms and https://policies.google.com/privacy). Review the chosen route and provider policies. For a custom endpoint, review and disclose its own terms and privacy policy before enabling it.

Optional virtual-page indexing retrieves the configured XML sitemap and public HTML on the site's own host. It is off by default. When enabled, search rechecks up to three candidate URLs per query anonymously with a two-second timeout per URL; changed, redirected or unavailable source text is omitted until reindexed or publicly available again. This adds same-origin HTTP traffic and depends on working anonymous access, including correct cache behavior. Temporary crawler failures pause the queue. No external tracking service is used by the plugin.

== Privacy ==
When conversation memory is enabled (default), the first search sets an HTTP-only session cookie (w27studio_session, one day); recent questions and replies are stored locally in transients for the configured duration (45 minutes by default). The New question button starts a new conversation on the next request. When external AI and memory are enabled, recent turns may accompany each AI request. Disabling memory stops new history storage and transmission of stored turns; existing transients expire according to their TTL. Disable memory in the Interface tab to stop new history storage. Rate limiting uses a hashed IP-based transient for ten minutes. The optional local journal (off by default) records questions, answer excerpts, request details, source IDs and votes, retained for the configured period (30 days by default). Visitors should avoid entering personal or confidential information. Administrators should explain their chosen AI/embedding configuration and applicable service policies in their site privacy notice. No API key is exposed to the public widget.

== Frequently Asked Questions ==

= Do I need an AI account? =
No. Local search works without a key. Chat and semantic indexing are separate opt-ins and may incur provider charges.

= Does changing the site language rebuild my index? =
No. Saved content, embeddings and explicit widget text are retained. Default labels follow the widget language. To restore translated defaults, use the explicit reset option in Interface and behavior.

= Is cross-language search guaranteed? =
No. Local matching is lexical; cross-language retrieval depends on the indexed passages and optional embedding model. The AI answer language is independent of the interface language. Local excerpts retain the original wording.

= Which content is supported? =
Published, unprotected standard posts and pages, plus optional same-origin sitemap pages. Public custom post types, taxonomies and registered connectors are optional in Knowledge sources. Full WooCommerce product metadata and arbitrary private data are not automatically read.

= How can I keep data during an update? =
Replace the plugin files without deleting the installed plugin. Back up the database and files first. Uninstall removes plugin tables, settings, corrections, schedules and matching stored history and budget records. Object-cache-only transients may expire later.

== Changelog ==
= 1.0.0 =
* Initial public edition: local search, optional conversational AI and semantic embeddings, public knowledge connectors, bilingual guide and customizable widget.
