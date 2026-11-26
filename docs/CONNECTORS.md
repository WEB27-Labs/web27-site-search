# Public knowledge connector API / API публичных источников

Connector API for version 1.0.0. No SQL table discovery, raw file scanning, arbitrary callback execution selected by visitors, or automatic scraping of private data. The adapter must be registered by trusted local WordPress code. Administrators explicitly enable each source under Site Search → Knowledge sources.

## Minimal example

```php
add_filter('web27_site_search_sources', static function (array $sources): array {
    $sources['example-directory'] = [
        'label' => 'Public directory',
        'items' => static function (): iterable {
            yield [
                'id' => 'studio-1',
                'public' => true,
                'title' => 'Public recording studio',
                'url' => home_url('/studios/studio-1/'),
                'text' => 'Recording studio available for hourly rental.',
                'tags' => ['Music', 'Recording'],
            ];
        },
    ];
    return $sources;
});
```

Replace fixture content with the source's actual public read API. IDs must be stable strings (max 191 bytes). `public` must be boolean `true` for each record; titles, text and tags must be public and strings. `url` is a same-origin visitor-facing URL. No credentials, admin routes, protected posts, account data, internal options, arbitrary metadata or source code. A registered connector is trusted PHP code, not a sandbox: it must enforce anonymous access, must not return gated content, must be read-only and must not make unbounded or external network calls. The engine runs snapshot and targeted retrieval with the WordPress current user set to guest, but this is not a replacement for source-specific access rules. Use `web27_site_search_public_object` (boolean, source ID, object ID, URL) to further restrict visibility; returning false omits the object. The adapter can also return an optional `search` callable accepting a visitor query and returning the same public record structure. For capped sources the engine can request up to 30 additional records per source; use prepared queries and a deterministic result limit, never concatenate raw visitor input into SQL. `items` should yield a deterministic, representative sample with at most 151 records. A failed connector's partial batch is discarded.

Source IDs: `post:<post_type>`, `term:<taxonomy>`, `connector:<registered-id>`. Native discovery uses public, viewable WordPress types and taxonomies; excludes attachment, draft, private and password-protected posts. Verify any membership plugin's rules before enabling a type. Native CPT uses title, excerpt, content and public taxonomy names only, no arbitrary postmeta. Taxonomy source uses name, description and archive URL. No rendering of arbitrary shortcodes.

Snapshots are read locally on demand and during background embedding jobs; no new HTTP request to each object. The overview/vector sample is bounded to 150 objects per source / 600 total / 12 sources. Targeted text lookup is separate and does not expand persistent semantic coverage. Source embeddings are separately opt-in and rely on configured semantic provider; six new objects per job, fingerprint includes source, ID, URL and current text; provider/endpoint/model key prevents cross-model use. Incompatible or changed vectors are ignored; unused rows expire after 30 days during enabled background processing. Standard post/page and sitemap indexes are not replaced. A removed or hidden item cannot be retrieved from old vector rows alone. Previously transmitted conversation history remains subject to existing history retention.

## Русская инструкция

Разработчик стороннего плагина регистрирует адаптер фильтром `web27_site_search_sources`, как в примере выше. Адаптер возвращает только явно публичные записи, с постоянным ID, названием, содержимым, URL для посетителя и необязательными тегами. Для большого каталога можно добавить локальную функцию `search`. Администратор включает источник вручную; без согласия дополнительный контент не читается для поиска и не отправляется на эмбеддинги. Для нестандартных ограничений доступа адаптер обязан проверить публичность, дополнительный фильтр `web27_site_search_public_object` позволяет исключить объект. Не считывайте закрытые таблицы, ключи, исходники, приватные метаданные. Семантическая выборка ограничена и не заменяет полноразмерный индекс больших каталогов. Для совместимых установок MAX Games Core встроенный адаптер использует `MG_Modules::catalog()` и `catalog_genre()`, не требует изменения ядра.

### Optional public cover image

A connector item may include `image` as an absolute same-origin public raster image URL (PNG, JPG, JPEG, WebP, AVIF or GIF). URLs with credentials, query parameters, fragments or sensitive paths are rejected; no server-side image download is performed. The field is presentation-only, excluded from embeddings and the AI gateway context. An unavailable image falls back to a neutral icon. Never supply private or signed media URLs.

### Публичная обложка (RU)

Необязательное поле `image` содержит абсолютный публичный URL растрового изображения того же сайта (PNG, JPG, JPEG, WebP, AVIF, GIF). URL с учётными данными, query-параметрами, фрагментами и чувствительными путями отклоняются. Сервер не скачивает изображение; поле используется только в интерфейсе и не передаётся модели или на эмбеддинги. При отсутствии изображения используется нейтральная иконка. Не передавайте приватные и подписанные URL.
