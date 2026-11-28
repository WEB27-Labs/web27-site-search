# WEB27 Site Search

[![Download WordPress Plugin](https://img.shields.io/badge/DOWNLOAD-WORDPRESS_PLUGIN-238636?style=for-the-badge&logo=wordpress&logoColor=white)](https://github.com/WEB27-Labs/web27-site-search/raw/main/web27-site-search.zip)

[![Version](https://img.shields.io/badge/Version-1.0.0_RC-blue)](https://github.com/WEB27-Labs/web27-site-search/releases)
![License](https://img.shields.io/badge/License-GPL--2.0+-blue)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4)
![WordPress](https://img.shields.io/badge/WordPress-6.6+-21759B)

[Русский](README.ru.md) · [Setup guide](docs/SETUP.md) · [Changelog](CHANGELOG.md) · [Website](https://web27.ru/)

**Intelligent search. Conversational AI. Your WordPress website.**

WEB27 Site Search is a free, open-source search plugin for WordPress, developed by WEB27 Labs. It combines local search, conversational AI, semantic embeddings, retrieval-augmented generation (RAG), universal knowledge sources and a customizable search widget.

Local search works without an external AI account.

## Download and installation

**[Download WEB27 Site Search — ready-to-install ZIP](https://github.com/WEB27-Labs/web27-site-search/raw/main/web27-site-search.zip)**

1. Download the plugin ZIP.
2. Open your WordPress administration panel.
3. Go to Plugins → Add New → Upload Plugin.
4. Upload the ZIP, install and activate.
5. Open Site Search → Setup guide.

No manual compilation or GitHub account is required.

**Use the installation ZIP above, not GitHub's automatic source code ZIP.**

Version 1.0.0 is a public release candidate. Official WordPress.org compliance and full live compatibility verification are pending.

## Features

- Local search without an external AI account.
- Conversational AI grounded in retrieved website content.
- Retrieval-augmented generation (RAG).
- Semantic search and embeddings.
- Background content indexing.
- Conversation memory and follow-up questions.
- Configurable AI instructions.
- Validated source links and interactive content cards.
- Saved query corrections and answer feedback.
- Optional local search journal.
- Public custom post types and taxonomies.
- Custom catalog integration through the connector API.
- English and Russian interfaces and documentation.
- Responsive desktop and mobile search widget.

## AI providers

| Provider | Chat | Embeddings |
| --- | --- | --- |
| OpenAI | Supported | Supported |
| OpenRouter | Supported | Supported |
| ProxyAPI | Supported | Supported |
| Custom API | Compatible endpoints | Compatible endpoints |

Each provider has a separate saved configuration, model selection and independent connection tests.

AI chat and embeddings are optional. No automatic paid failover is performed.

## Universal knowledge sources

WEB27 Site Search can retrieve public content beyond standard WordPress pages and posts.

| Source | Support |
| --- | --- |
| Published posts and pages | Default |
| Public virtual pages | Optional sitemap and HTML indexing |
| Custom post types | Administrator opt-in |
| Public taxonomies | Administrator opt-in |
| Custom catalogs | Connector API |
| Private data and plugin source code | Not automatically indexed |

A built-in MAX Games Core adapter is included without introducing a dependency on that plugin.

Developers can integrate their own public catalogs using the [Connector API](docs/CONNECTORS.md).

## Design Studio

Customize the search interface directly from WordPress.

- Five color schemes, including custom colors.
- Independent desktop and mobile launcher positions.
- Configurable button shapes and content.
- Four search window layouts.
- Adjustable window dimensions and spacing.
- Four content-card layouts.
- Optional public same-origin cover images.
- Live design preview.
- Floating launcher and shortcode integration.

## Multilingual support

- English administration and setup guide.
- Russian administration and setup guide.
- Independent widget language selection.
- Automatic AI answer language selection based on the visitor's message and conversation context.

## Getting started

After activation:

1. Open Site Search → Setup guide.
2. Test local search with AI disabled.
3. Allow scheduled indexing to run.
4. Enable additional public knowledge sources if needed.
5. Customize the widget under Interface → Design Studio.
6. Select an AI provider and enter your own permitted API key if AI features are required.
7. Test chat and embeddings independently before enabling semantic search.

Use the floating launcher or the following shortcode:

`[web27_site_search]`

## Requirements

- WordPress 6.6 or later.
- PHP 8.1 or later.
- PHP mbstring extension.
- SimpleXML and DOM for optional sitemap and HTML functionality.
- External API account only when AI features are enabled.

These are declared minimum requirements. Full compatibility verification is ongoing.

## Privacy and costs

Local search does not require an AI API key.

When external AI services are enabled, visitor queries, public content excerpts, administrator instructions and recent conversation context may be transmitted to the selected provider. Embeddings may also transmit public content passages.

External providers may charge for API usage. The daily chat request limit does not cover embedding costs.

API keys remain server-side. Administrators should review provider policies, configure spending limits and disclose enabled external services in their website privacy notice.

Local conversation memory can be disabled. The optional search journal is disabled by default.

See [service disclosures and privacy documentation](readme.txt).

## Known limitations

Additional source overview and vector coverage is limited to 150 objects per source, 600 objects overall and 12 sources.

Supported connectors may provide targeted lexical lookup beyond this sample.

Large catalog semantic coverage, cross-language retrieval, proprietary metadata and AI factual accuracy are not guaranteed. Specialized access rules and data structures may require dedicated integration.

See [known limitations and quality assurance](docs/QA.md).

## Documentation

- [English setup guide](docs/SETUP.md)
- [Russian setup guide](docs/SETUP.ru.md)
- [Connector API](docs/CONNECTORS.md)
- [Upgrade and rollback](docs/UPGRADE-ROLLBACK.md)
- [Quality assurance](docs/QA.md)
- [Changelog](CHANGELOG.md)
- [Security policy](SECURITY.md)

## Build from source

The complete source code is available in this repository.

For developers:

    python3 scripts/build.py /tmp/web27-site-search.zip

Run automated checks:

    python3 -m pip install -r tests/requirements.txt
    python3 scripts/check.py

For browser testing:

    python3 -m playwright install chromium
    python3 scripts/check.py --browser

Fixture tests do not require external API credentials or paid API calls and do not certify live WordPress compatibility.

## Updates and support

Back up your website files and database before updating.

Replace the existing plugin installation. Do not uninstall the plugin to update it, because uninstalling removes main plugin data.

Report reproducible issues through GitHub Issues without including credentials or private website data.

Report security vulnerabilities privately according to [SECURITY.md](SECURITY.md).

## License

WEB27 Site Search is distributed under the GPL-2.0-or-later license.

See [LICENSE](LICENSE).

---

**Developed by WEB27 Labs**

Website: https://web27.ru/

GitHub: https://github.com/WEB27-Labs

Free and open-source software.
