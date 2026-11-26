# WEB27 Site Search

[Русский](README.ru.md) · [Setup guide](docs/SETUP.md) · [Privacy and services](readme.txt) · [Changelog](CHANGELOG.md)

**Local search and optional source-grounded conversational AI for WordPress.**

WEB27 Site Search helps visitors find public content, ask follow-up questions and open relevant materials. It combines a local search index, optional semantic embeddings, retrieval-augmented generation (RAG), and a customizable search widget. It works without an external AI account in local search mode.

**Release status:** 1.0.0 — first public edition, currently a release candidate. The source and installation packages are prepared for private review and staging. Live WordPress compatibility, official directory checks and live API verification remain release gates. No WordPress.org approval is claimed.

## Features

- Local search for published, unprotected WordPress posts and pages.
- Optional same-origin sitemap indexing for virtual public pages; background indexing, passages and optional embeddings.
- Optional AI chat grounded in retrieved site content; configurable instructions, recent dialogue context, source links, local fallback, saved query corrections, optional local journal and voting.
- Opt-in public custom post types, public taxonomies and a [connector API](docs/CONNECTORS.md) for custom catalogs. A built-in MAX Games Core adapter is included; it is not a dependency.
- OpenAI, OpenRouter, two ProxyAPI presets and custom OpenAI-compatible HTTPS endpoints. Separate saved profiles, model catalog and independent chat/embedding checks; no automatic paid failover.
- English and Russian administration, built-in guide and widget; widget language can be selected independently of the site's locale. AI answer language follows the visitor's message and conversation context.
- Five color schemes, configurable desktop/mobile launcher, four window layouts, adjustable sizes, preview, shortcode and four material-card layouts.
- AI material cards are shown for validated citations from the answer; optional public same-origin cover images. No fabricated links are required.

## Quick start

1. Obtain the **FULL installation ZIP** from an approved release asset or build it using the instructions below. Do not install GitHub's automatic source ZIP.
2. On a staging WordPress installation, go to Plugins → Add New → Upload Plugin, install and activate. The canonical plugin folder is `web27-site-search/`.
3. Open **Site Search → Setup guide**. Try a published page title with AI disabled. Allow scheduled indexing to run.
4. Select public sources in **Knowledge sources**, if needed, and configure **Interface → Design Studio**. Use the floating launcher or shortcode `[web27_site_search]`.
5. To enable AI, choose a provider in **AI and answers**, obtain your own permitted key, select a model and run the chat test. Test the embedding model independently before enabling semantic search. Both paid features are opt-in.

Declared minimums: WordPress 6.6, PHP 8.1, PHP mbstring. SimpleXML and DOM are needed for optional sitemap/HTML functionality (DOM for full HTML extraction). These are declared requirements, not a verified compatibility matrix. See the [setup guide](docs/SETUP.md) and [upgrade precautions](docs/UPGRADE-ROLLBACK.md).

## What can be searched?

| Source | Support |
| --- | --- |
| Published posts and pages | Default |
| Same-origin virtual pages | Optional sitemap/HTML text index |
| Public custom post types and taxonomies | Administrator opt-in |
| Custom catalogs | Explicit public connector; supported source lookup |
| Private data, arbitrary database tables, plugin source code | Not automatically indexed |

Additional source overview/vector coverage is capped at 150 objects per source, 600 overall and 12 sources. Supported connectors may provide targeted lexical lookup beyond that sample. Semantic coverage of very large catalogs and the separate virtual-page HTML index is limited. Full ecommerce metadata, membership access rules and proprietary data need dedicated integration. Local matching is lexical; cross-language retrieval and AI factual accuracy are not guaranteed. [Known limitations and QA](docs/QA.md).

## Privacy and cost

Chat and embeddings are separate opt-ins. When configured, external providers can receive visitor queries, public excerpts, administrator instructions and recent conversation context; embeddings also transmit public passages. Requests may incur charges. The daily chat request limit does not cover embedding costs. Review provider policies, restrict spending at the provider, and disclose the selected services in the site's privacy notice. No API key is needed for local search. API keys remain server-side; never commit keys or production dumps. Local dialogue memory is enabled by default and can be disabled; the optional journal is off by default. See the detailed [service disclosures and privacy documentation](readme.txt).

## Build and test

```sh
python3 scripts/build.py /tmp/web27-site-search.zip
python3 -m pip install -r tests/requirements.txt
python3 scripts/check.py
```

For browser tests, install Chromium (or run `python3 -m playwright install chromium`) and set `CHROMIUM` to its executable path if needed. Then run `python3 scripts/check.py --browser`. The fixture tests do not require WordPress, an API account or paid calls. They do not certify live compatibility. See [QA](docs/QA.md) and [contribution guide](CONTRIBUTING.md).

## Updates, support and license

Back up files and the database. Replace the existing installation; do not uninstall to update, because uninstall removes main plugin data. See [upgrade and rollback](docs/UPGRADE-ROLLBACK.md). Report bugs with sanitized reproduction steps; report security issues privately as described in [SECURITY.md](SECURITY.md).

Developed by WEB27 · https://web27.ru/ · GPL-2.0-or-later, see [LICENSE](LICENSE). Public distribution remains subject to owner confirmation of rights and release approval.
