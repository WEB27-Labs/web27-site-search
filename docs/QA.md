# Testing and compatibility

[Русский](QA.ru.md)

Version 1.0.0 is a release candidate. These results use isolated WordPress and provider fixtures, not live services.

| Check | Result |
| --- | --- |
| Core, provider contracts, admin forms | 59 / 169 / 42 assertions passed |
| Design, languages, background embeddings | 18 / 43 / 25 assertions passed |
| Security, universal sources, server constants | 30 / 77 / 3 assertions passed |
| Submission hardening fixtures | 18 assertions passed |
| PHP/JavaScript syntax | 36 PHP, 2 JS files checked |
| EN/RU catalog parity | 603 source messages = POT = PO = MO = fixture; placeholders match |
| Built-in guide per locale | 14 sections, 34 anchor links, 31 settings links |
| Browser legacy UI | 80 panel runs, plus 4 explicit/automatic locale cases |
| Design, relevance, preview | 32 + 14 + 2 scenarios passed |
| Text retrieval benchmark A | 100/100 first result on the supplied fixture |
| Independent text benchmark B | 87/100 first result, 90/100 retrieved at all |

Independent benchmark B: 40/40 English, 40/40 Russian, 7/10 mixed and 0/10 cross-language questions without shared terms at rank 1. These are fixture benchmarks, not measurements of live AI factual accuracy. Tests can be repeated using `python3 scripts/check.py --browser`. The universal tests here use a portable mock registry, not third-party production plugins. 

## Known limitations

- AI citation selection is not proof of factual accuracy or recommendation completeness. A relevant item without a citation may not receive a card.
- Local matching is lexical; genuine cross-language retrieval requires appropriate embeddings and live verification.
- Additional sources are sampled (150 per source, 600 total, 12 sources); only supported adapters offer targeted lookup beyond the sample. The separate virtual HTML index is lexical. Specialized catalogs and gated content require explicit access-aware integrations.
- Optional virtual indexing revalidates up to three anonymous same-origin URLs per query, two seconds each. Availability and performance depend on the host and caching.
- Chat quota is separate from embedding costs. Switching providers or embedding models can require new vectors.


## Verification

Run `python3 scripts/check.py --browser` for isolated checks. The official Plugin Check and WPCS workflow is available in GitHub Actions. Official directory checks, live WordPress compatibility and live provider verification remain pending.
