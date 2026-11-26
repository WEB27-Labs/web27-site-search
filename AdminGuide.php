<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) { return; }
?>
<div class="w27-guide">
<section class="w27a-box w27-guide-intro" aria-labelledby="w27-guide-title">
<div class="w27-guide-kicker"><?php echo esc_html__('WEB27 · GETTING STARTED', 'web27-site-search'); ?></div>
<h2 id="w27-guide-title"><?php echo esc_html__('Set up search on a new site', 'web27-site-search'); ?></h2>
<p class="w27-guide-lead"><?php echo esc_html__('From installation to the first answer: connect an AI provider if needed, prepare the knowledge base, choose the appearance and check the results. Local text search needs no API key.', 'web27-site-search'); ?></p>
<p><strong><?php echo esc_html__('This guide does not change settings automatically.', 'web27-site-search'); ?></strong> <?php echo esc_html__('Save settings on their respective tabs. Do not uninstall the plugin when upgrading: uninstalling removes its local data.', 'web27-site-search'); ?></p>
<div class="w27-guide-steps">
<a class="w27-guide-step" href="#w27-guide-install"><span>01</span><strong><?php echo esc_html__('Preparation', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Restrict access during setup and check the site name.', 'web27-site-search'); ?></small></a>
<a class="w27-guide-step" href="#w27-guide-proxyapi"><span>02</span><strong><?php echo esc_html__('AI provider', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Obtain your own key and set a budget.', 'web27-site-search'); ?></small></a>
<a class="w27-guide-step" href="#w27-guide-ai"><span>03</span><strong><?php echo esc_html__('AI and answers', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Save the key and test the model.', 'web27-site-search'); ?></small></a>
<a class="w27-guide-step" href="#w27-guide-index"><span>04</span><strong><?php echo esc_html__('Knowledge base', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Index posts and pages; enable sitemap indexing and vectors if needed.', 'web27-site-search'); ?></small></a>
<a class="w27-guide-step" href="#w27-guide-interface"><span>05</span><strong><?php echo esc_html__('Interface', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Choose a color scheme, placement and assistant name.', 'web27-site-search'); ?></small></a>
<a class="w27-guide-step" href="#w27-guide-acceptance"><span>06</span><strong><?php echo esc_html__('Verification', 'web27-site-search'); ?></strong><small><?php echo esc_html__('Check search, conversation, links, costs and privacy.', 'web27-site-search'); ?></small></a>
</div>
</section>
<nav class="w27a-box w27-guide-toc" aria-label="<?php echo esc_attr__('Guide contents', 'web27-site-search'); ?>"><h3><?php echo esc_html__('All sections', 'web27-site-search'); ?></h3><ul>
<li><a href="#w27-guide-install"><?php echo esc_html__('1. Installation and preparation', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-proxyapi"><?php echo esc_html__('2. Choose a provider and obtain a key', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-ai"><?php echo esc_html__('3. Configure AI', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-index"><?php echo esc_html__('4. Index the site', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-vectors"><?php echo esc_html__('5. Embeddings — semantic search', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-rag"><?php echo esc_html__('6. RAG and conversation rules', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-interface"><?php echo esc_html__('7. Interface and placement', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-budget"><?php echo esc_html__('8. Cost and data controls', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-acceptance"><?php echo esc_html__('9. New site acceptance checks', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-errors"><?php echo esc_html__('10. Troubleshooting', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-resources"><?php echo esc_html__('11. Official resources', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-languages"><?php echo esc_html__('12. Languages and translations', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-quality"><?php echo esc_html__('13. Answer quality and corrections', 'web27-site-search'); ?></a></li>
<li><a href="#w27-guide-sources"><?php echo esc_html__('14. Additional knowledge sources', 'web27-site-search'); ?></a></li>
</ul></nav>
<section class="w27a-box w27-guide-section" id="w27-guide-install" aria-labelledby="w27-guide-install-title">
<h2 id="w27-guide-install-title"><?php echo esc_html__('1. Installation and preparation', 'web27-site-search'); ?></h2>
<ul>
<li><?php echo esc_html__('WordPress 6.6+, PHP 8.1+, mbstring. XML/HTML crawling requires SimpleXML and DOM. Working WP-Cron, REST API and outgoing HTTPS access to the chosen gateway are needed; sitemap crawling also requires access to your own site.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('In WordPress: Plugins → Add Plugin → Upload Plugin. Choose the', 'web27-site-search'); ?> <strong><?php echo esc_html__('WEB27 Site Search installation ZIP', 'web27-site-search'); ?></strong> <?php echo esc_html__('(skip this step if already active), install and activate. The Site Search menu appears. Do not upload the outer release kit archive to WordPress.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('When upgrading, replace the existing plugin without uninstalling it. Uninstalling through WordPress removes settings and local data. Make a backup first.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Start on a staging site. On a live site, before connecting paid AI, open Interface, disable Enable search and Enable public interface, then save. Disabling the UI alone does not disable REST. Background embeddings are controlled separately.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Under Settings → General, check the site name and tagline: the assistant uses them for greetings and an overview. Publish accurate information, contacts, prices (if applicable) and terms.', 'web27-site-search'); ?></li>
</ul>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ux')); ?>"><?php echo esc_html__('Open interface settings', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-proxyapi" aria-labelledby="w27-guide-proxyapi-title">
<h2 id="w27-guide-proxyapi-title"><?php echo esc_html__('2. Choose a provider and obtain a key', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('The same plugin supports every provider in either interface language. Select a provider in AI and answers, click Select provider, then follow the corresponding steps below. Switching pauses automatic chat and embeddings; the previous connection profile is saved.', 'web27-site-search'); ?></p>
<h3>OpenAI — Direct API</h3>
<ol>
<li><?php echo esc_html__('Create an OpenAI API account in a supported country or region. Set up project billing and create a project key:', 'web27-site-search'); ?> <a href="https://platform.openai.com/api-keys">https://platform.openai.com/api-keys</a>.</li>
<li><?php echo esc_html__('Select OpenAI in the plugin, paste the key, and keep or choose a chat model. Suggested starting models:', 'web27-site-search'); ?> <code>gpt-5-mini</code>, <code>text-embedding-3-small</code>.</li>
<li><?php echo esc_html__('Save settings, then load models or run the separate answer and embedding connection tests. Availability and billing depend on your project.', 'web27-site-search'); ?></li>
</ol>
<h3>OpenRouter</h3>
<ol>
<li><?php echo esc_html__('Create an OpenRouter account, check its applicable terms and funding options, and generate a key:', 'web27-site-search'); ?> <a href="https://openrouter.ai/keys">https://openrouter.ai/keys</a>.</li>
<li><?php echo esc_html__('Select OpenRouter, paste the key and use the full model identifiers from its catalog. Suggested starting models:', 'web27-site-search'); ?> <code>openai/gpt-5-mini</code>, <code>openai/text-embedding-3-small</code>.</li>
<li><?php echo esc_html__('This integration uses Chat Completions. Check the selected model’s availability, costs, data policies and routing in your account.', 'web27-site-search'); ?></li>
</ol>
<h3>ProxyAPI.ru</h3>
<ol>
<li><?php echo esc_html__('Open', 'web27-site-search'); ?> <a href="https://console.proxyapi.ru/">https://console.proxyapi.ru/</a> <?php echo esc_html__('and register, then verify your email.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Add a small balance sufficient for testing in the billing section.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Under API keys, create a new key, for example', 'web27-site-search'); ?> <code>Site Search — example.com</code>.</li>
<li><?php echo esc_html__('Copy the secret', 'web27-site-search'); ?> <code>sk-...</code> <?php echo esc_html__('immediately after creation. It is shown in full only once. Never expose it in client-side JavaScript, shortcodes, screenshots, correspondence or public archives.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('In the key settings, authorize the required models:', 'web27-site-search'); ?> <code>openai/gpt-6-luna</code> <?php echo esc_html__('and, if vectors are needed,', 'web27-site-search'); ?> <code>openai/text-embedding-3-small</code><?php echo esc_html__('. Choose models from the current catalog:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/models/browse">https://proxyapi.ru/models/browse</a>.</li>
<li><?php echo esc_html__('Set a nonzero key budget suitable for your test. If necessary, restrict access to your server’s fixed outgoing IPv4 address, not the administrator’s or visitor’s IP. According to ProxyAPI documentation, the monthly budget is compared with total account spending for the calendar month. Verify terms in your account before launch.', 'web27-site-search'); ?></li>
</ol>
<p><?php echo esc_html__('Use a separate key for each site. A key may support both chat and embeddings when the provider permits both. If exposed, revoke it with that provider and create a new key.', 'web27-site-search'); ?></p>
<h3><?php echo esc_html__('Custom OpenAI-compatible endpoint', 'web27-site-search'); ?></h3>
<p><?php echo esc_html__('Select Custom, enter the provider’s public HTTPS API base URL and its own key, then enter compatible chat and embedding model IDs. The custom service must implement the chosen API endpoints; semantic search is optional. Private network endpoints and redirected requests are not supported by the safe WordPress HTTP client.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Do not use another provider’s key for a custom endpoint. Changing its base URL clears the saved key unless a new key is entered. Check the provider’s terms, privacy and billing before any test.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ai')); ?>"><?php echo esc_html__('Go to AI settings', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-ai" aria-labelledby="w27-guide-ai-title">
<h2 id="w27-guide-ai-title"><?php echo esc_html__('3. Configure AI', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('WordPress → Site Search → AI and answers.', 'web27-site-search'); ?></p>
<div class="w27-guide-table"><table class="widefat striped">
<thead>
<tr>
<th><?php echo esc_html__('Interface field', 'web27-site-search'); ?></th>
<th><?php echo esc_html__('Recommended starting value', 'web27-site-search'); ?></th>
</tr>
</thead>
<tbody>
<tr>
<td><?php echo esc_html__('Enable external AI', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Yes (after setting a budget and providing data disclosure)', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Provider', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Your selected provider', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Protocol', 'web27-site-search'); ?></td>
<td>chat/completions</td>
</tr>
<tr>
<td><?php echo esc_html__("Custom API base", 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Only for Custom; public HTTPS base URL', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__("API key", 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Your secret key', 'web27-site-search'); ?> <code>sk-...</code><?php echo esc_html__(', without the Bearer prefix or quotes', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Model', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('The selected provider’s suggested or tested model', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__("Max output tokens", 'web27-site-search'); ?></td>
<td>700</td>
</tr>
<tr>
<td><?php echo esc_html__('HTTP timeout, seconds', 'web27-site-search'); ?></td>
<td>18</td>
</tr>
<tr>
<td><?php echo esc_html__("Temperature", 'web27-site-search'); ?></td>
<td>0.2</td>
</tr>
<tr>
<td><?php echo esc_html__("Top P", 'web27-site-search'); ?></td>
<td>1.0</td>
</tr>
<tr>
<td><?php echo esc_html__("Reasoning effort", 'web27-site-search'); ?></td>
<td><?php echo esc_html__("none", 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__("Prompt cache key", 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Empty', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('AI requests per day', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('100 (20–50 for a cautious pilot)', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Requests / 10 minutes / IP', 'web27-site-search'); ?></td>
<td>20</td>
</tr>
<tr>
<td><?php echo esc_html__('Send technical metadata to ProxyAPI', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('No', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Enable semantic search (embeddings)', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Not yet', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Embedding model', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('The selected provider’s tested embedding model', 'web27-site-search'); ?></td>
</tr>
</tbody>
</table></div>
<p><?php echo esc_html__('Click Save AI settings. Under Connection test, click Test answer model. A successful test displays a response from the configured model. This uses a potentially billable attempt counted against the daily limit. AI configured indicates configuration only, not a verified connection.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Standard providers have automatic API base URLs:', 'web27-site-search'); ?> <code>https://api.openai.com/v1</code>, <code>https://openrouter.ai/api/v1</code>, <code>https://api.proxyapi.ru/v1</code>. <?php echo esc_html__('Only Custom needs a manually entered base URL. Chat Completions and embeddings append their respective paths; Responses is optional for compatible providers other than OpenRouter in this integration.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Load model list retrieves model identifiers with your saved key. The list may contain models that are not compatible with text chat or embeddings. You can always enter the exact identifier manually. Test the model after saving. GPT-5/6 Chat Completions do not send Temperature or Top P; Responses can send Reasoning effort when configured. Increase the output token limit if a reasoning model returns no visible answer.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('After saving, the API key field is empty. An empty field keeps only the selected provider’s key. Remove saved key for this provider clears it. Keys are stored in WordPress settings, so protect the database and backups. Disable both AI and embeddings to stop automatic AI requests. Manual connection tests and model-list requests are separate actions.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('For system administrators: this build supports the server constant', 'web27-site-search'); ?> <code>WEB27_SITE_API_KEY</code> <?php echo esc_html__('(for example, from a secret environment variable in wp-config.php), which overrides the key in the dashboard. This is not required for normal installation.', 'web27-site-search'); ?> <code>WEB27_SITE_API_URL</code> <?php echo esc_html__('switches the provider to custom; do not set it for a standard provider. The provider selector is locked while any connection constant is active. When migrating, check for old constants, including', 'web27-site-search'); ?> <code>WEB27_SITE_MODEL</code>.</p>
<p><?php echo esc_html__('When a server API URL changes to a different endpoint, define a key for that endpoint explicitly. The plugin will not send a stored key from a different endpoint.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ai')); ?>"><?php echo esc_html__('Open AI and answers', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-index" aria-labelledby="w27-guide-index-title">
<h2 id="w27-guide-index-title"><?php echo esc_html__('4. Index the site', 'web27-site-search'); ?></h2>
<h3><?php echo esc_html__('4.1. Standard WordPress pages and blog posts', 'web27-site-search'); ?></h3>
<p><?php echo esc_html__('Open Site Search → Index. Enable Index standard WordPress pages and, if applicable, Index blog posts. Leave Load additional pages from sitemap disabled. Click Save index settings, then Synchronize WordPress content.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('This passage preview uses local text matching only. Use the full query test for AI and semantic search.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Open Passages and vectors and check the document and passage counts. Use Find passage by text to locate known published text. Background text indexing runs in batches of 25 posts through WP-Cron; Process next batch runs one step manually. For very large sites, prefer background indexing over a full manual sync in one HTTP request.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Overview and Index show combined document and passage counts from both index systems; the same page may be counted twice. Passages and vectors shows the separate standard WordPress passage index. Use actual search results to verify readiness. Clearing the index removes both indexes and embeddings; it also invalidates pinned passage IDs. To rebuild standard passages after clearing, use Recheck all pages under Passages and vectors.', 'web27-site-search'); ?></p>
<h3><?php echo esc_html__('4.2. Virtual pages, additional routes and dynamic public HTML', 'web27-site-search'); ?></h3>
<p><?php echo esc_html__('If content is not stored in standard posts/pages, check the XML sitemap in your browser. For example,', 'web27-site-search'); ?> <code>https://example.com/wp-sitemap.xml</code> <?php echo esc_html__('or', 'web27-site-search'); ?> <code>https://example.com/sitemap.xml</code> <?php echo esc_html__('— use the actual same-host URL returning XML and HTTP 200 without redirection. For a custom sitemap, use its actual path, for example', 'web27-site-search'); ?> <code>https://example.com/custom-sitemap.xml</code> <?php echo esc_html__('on your own domain; do not copy the example URL literally.', 'web27-site-search'); ?></p>
<div class="w27-guide-table"><table class="widefat striped">
<thead>
<tr>
<th><?php echo esc_html__('Indexer setting', 'web27-site-search'); ?></th>
<th><?php echo esc_html__('Recommendation', 'web27-site-search'); ?></th>
</tr>
</thead>
<tbody>
<tr>
<td><?php echo esc_html__("Sitemap URL", 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Your own site’s actual XML sitemap', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Batch size', 'web27-site-search'); ?></td>
<td>1–2 URL</td>
</tr>
<tr>
<td><?php echo esc_html__('Cron interval, minutes', 'web27-site-search'); ?></td>
<td>5</td>
</tr>
<tr>
<td><?php echo esc_html__('Page timeout, seconds', 'web27-site-search'); ?></td>
<td>15</td>
</tr>
<tr>
<td><?php echo esc_html__("User-Agent", 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Keep the default', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Load additional pages from sitemap', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Yes', 'web27-site-search'); ?></td>
</tr>
</tbody>
</table></div>
<p><?php echo esc_html__('Save index settings. Click Load sitemap and start. Monitor the queue, successful URLs and errors. Use Process batch now for a single step. On 403/429, repeated network errors or hosting overload, stop or pause the queue and resolve the cause. Avoid repeated full crawls. If URLs change, verify the sitemap before refreshing the queue.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Virtual HTML pages participate in text search. Vectors cover standard posts/pages and selected additional knowledge sources when enabled. Public custom post types and taxonomies can be connected separately. Private accounts, page-builder fields, proprietary product metadata and JavaScript-only content require a suitable public connector. Do not include private or sensitive data in the sitemap.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('For virtual search results, the plugin checks up to three matching URLs per query with a two-second timeout per URL, without visitor cookies or redirects. A changed or inaccessible source is omitted until its public content matches the index again. Temporary HTTP 403, 429, 5xx or network errors pause crawling without discarding the queued URLs. Resume after resolving the error.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('index')); ?>"><?php echo esc_html__('Open index settings', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('vectors')); ?>"><?php echo esc_html__('Check passages', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-vectors" aria-labelledby="w27-guide-vectors-title">
<h2 id="w27-guide-vectors-title"><?php echo esc_html__('5. Embeddings — semantic search', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('After testing local search, open AI and answers and save the selected provider’s embedding model. Click Test embedding model to verify one synthetic sentence without writing vectors. Enable semantic search (embeddings) only after a successful test and consent to charges and data transfer.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('In Passages and vectors, check the active model’s vector count. With WP-Cron running, up to 12 passages per batch are sent about once a minute. Use Process next batch for testing. Wait for vectors, then try a paraphrased query without exact word matches. A different provider, endpoint or embedding model requires replacement vectors. Existing text passages and pinned answer IDs are preserved; switching back after replacement requires rebuilding the former vectors.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Embeddings do not train AI weights. They are numerical representations of text used to find semantically similar passages. They are billed separately and may be sent even when external chat is disabled if semantic search is enabled. The chat request limit does not cap embedding costs. Disable semantic search to stop these requests.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('After an unsuccessful background embedding request, automatic retries for the same connection settings are delayed for five minutes. Existing vectors are kept. The daily chat limit does not limit embedding charges; configure spending controls in the provider account.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ai')); ?>"><?php echo esc_html__('Configure embeddings', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('vectors')); ?>"><?php echo esc_html__('Open passages and vectors', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-rag" aria-labelledby="w27-guide-rag-title">
<h2 id="w27-guide-rag-title"><?php echo esc_html__('6. RAG and conversation rules', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('Open RAG and instructions. Initial values: 8 RAG sources and 18,000 context characters. Keep the built-in answer, data and conversation rules unless customization is needed. You can enter the following additional instruction, adapting it to your site:', 'web27-site-search'); ?></p>
<blockquote>
<p><?php echo esc_html__('You are our team’s assistant. Help visitors understand published materials and find the right section. Speak naturally on behalf of the site. Do not invent prices, availability, deadlines, guarantees, specifications, addresses or capabilities. If information is missing, say so and ask one short clarifying question. Suggest only verified pages and actions. Never request passwords, card details or confidential information. If a question is unrelated to the site, offer to return to its subject.', 'web27-site-search'); ?></p>
</blockquote>
<p><?php echo esc_html__('Click Save RAG and instructions. Rules apply to the next model request without reindexing. Update facts such as prices in published content, not in instructions. AI instructions are not executed in local fallback mode.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('rag')); ?>"><?php echo esc_html__('Open RAG and instructions', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-interface" aria-labelledby="w27-guide-interface-title">
<h2 id="w27-guide-interface-title"><?php echo esc_html__('7. Interface and placement', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('In Interface, set the assistant name (for example, the site name), launcher label, panel title, placeholder and minimum query length of 2. Enter suggested questions one per line; up to five are displayed. Choose questions your site can actually answer.', 'web27-site-search'); ?></p>
<ul>
<li><?php echo esc_html__('For a site-wide floating button, choose the floating UI mode and automatic button visibility. The theme must call', 'web27-site-search'); ?> <code>wp_footer()</code><?php echo esc_html__('. If the theme declares support for', 'web27-site-search'); ?> <code>web27-site-search-inline</code><?php echo esc_html__(', the separate floating button is disabled in automatic visibility mode.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('For shortcode only, choose that mode with automatic button visibility and place', 'web27-site-search'); ?> <code>[web27_site_search]</code> <?php echo esc_html__('in a Shortcode block on the desired page. Standard themes will not display a separate floating button.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Color scheme: light, dark, high contrast, system or custom colors. Use the preview. New installations default to the system scheme.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('In Design Studio, choose desktop and mobile button placement, visibility, shape, window size and material layout. Always show overrides theme integration and shortcode-only mode; Never show hides only the floating button, not the shortcode or search API. Custom colors apply only to the Custom colors scheme. The sample preview uses no AI requests.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Choose one of four window layouts: centered dialog, right panel, left panel or full screen. Width is 360–1100 px, maximum height 420–1200 px, offsets 8–96 px and corner radius 0–32 px. The actual size is constrained by the viewport.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Choose icon and text, icon only or text only for the button. A circular button shows the icon. Mobile placement can inherit desktop placement or use its own corner.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Materials can use a card grid, vertical list, horizontal carousel or compact links. AI answers show cards only for cited public sources; local search still shows matching results. Covers are optional public image URLs on the same site; missing or unsupported covers use an icon. Check real links and long answers on desktop and mobile after saving.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Choose custom accent, background, card and text colors if needed. Check text contrast yourself. The mobile preview and saved widget language are examples, not a substitute for testing on the real site. Save interface and clear caches if changes are not visible.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Conversation memory is enabled by default: four turn pairs, 45-minute TTL. Use New question for a fresh conversation. Test follow-ups in the public interface: admin tests do not save conversations.', 'web27-site-search'); ?></li>
</ul>
<p><?php echo esc_html__('On staging, enable search and the public interface, then click Save interface. Enable these on a live site only after preparation and with budget controls. Clear site caches if placement changes are not visible.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ux')); ?>"><?php echo esc_html__('Open interface settings', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-budget" aria-labelledby="w27-guide-budget-title">
<h2 id="w27-guide-budget-title"><?php echo esc_html__('8. Cost and data controls', 'web27-site-search'); ?></h2>
<ul>
<li><?php echo esc_html__('AI requests per day counts site-wide chat generation attempts, not money. Zero disables the internal daily limit. Admin tests also consume the limit. Per-IP rate limiting is separate and does not replace a budget.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Provider balance, billing limits and plugin request limits are separate controls. Monitor actual token usage, including history, RAG context and embeddings.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('WordPress Settings → Privacy offers suggested privacy policy text from Site Search. Review and adapt it before publishing your privacy policy.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Before enabling external features, update your privacy policy: questions, public excerpts, URLs, instructions and recent conversation history are sent to the selected external service; embeddings send public passages and queries. Review the selected service and underlying model provider’s terms and privacy policy.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Logging is off by default. If quality monitoring is needed, enable it for a limited time and set retention (for example, 7–30 days). Do not submit sensitive text. Conversation memory stores a short exchange in temporary WordPress data, not a customer profile.', 'web27-site-search'); ?></li>
</ul>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ai')); ?>"><?php echo esc_html__('Configure AI limits', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('logs')); ?>"><?php echo esc_html__('Open logs', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-acceptance" aria-labelledby="w27-guide-acceptance-title">
<h2 id="w27-guide-acceptance-title"><?php echo esc_html__('9. New site acceptance checks', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('In Diagnostics, check PHP, WordPress, database tables, REST, gateway endpoint and index cron. Then test a question from a real published page. A live AI test should show', 'web27-site-search'); ?> <code>Mode: external_ai</code><?php echo esc_html__(', correct sources, the model and no error. Token counts appear if supplied by the provider.', 'web27-site-search'); ?></p>
<div class="w27-guide-table"><table class="widefat striped">
<thead>
<tr>
<th><?php echo esc_html__('Mode', 'web27-site-search'); ?></th>
<th><?php echo esc_html__('Meaning', 'web27-site-search'); ?></th>
</tr>
</thead>
<tbody>
<tr>
<td>external_ai</td>
<td><?php echo esc_html__('External model response received', 'web27-site-search'); ?></td>
</tr>
<tr>
<td>local</td>
<td><?php echo esc_html__('Local results without generation (including when the limit is reached)', 'web27-site-search'); ?></td>
</tr>
<tr>
<td>local_fallback</td>
<td><?php echo esc_html__('External AI error; fallback answer returned', 'web27-site-search'); ?></td>
</tr>
<tr>
<td>welcome</td>
<td><?php echo esc_html__('Local greeting, overview or thanks', 'web27-site-search'); ?></td>
</tr>
<tr>
<td>empty</td>
<td><?php echo esc_html__('No relevant local result; navigation suggestions may appear', 'web27-site-search'); ?></td>
</tr>
</tbody>
</table></div>
<p><?php echo esc_html__('In a private browser window as a logged-out visitor, test on desktop and mobile:', 'web27-site-search'); ?></p>
<ul>
<li><?php echo esc_html__('Hello and What do you offer? — correct name, subject and first-person site voice.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('A question about an unambiguous published fact — accurate fact and relevant source.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('A paraphrased question — check whether semantic search finds a relevant result when embeddings are enabled. Cross-language retrieval depends on the model and indexed content.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('A follow-up such as How much?, Where? or Tell me more — the current topic is retained when relevant.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Click a suggested navigation button — it opens the correct existing page.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Ask about a nonexistent offer — no invented terms or prices.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('New question — a fresh conversation without old context.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Text is readable, buttons work on mobile and desktop, and there is no theme conflict.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('On staging, disable chat and verify local search still works; also disable embeddings for a fully local test. Restore the desired settings afterward.', 'web27-site-search'); ?></li>
<li><?php echo esc_html__('Verify test charges and limits in the selected provider account. Update the site privacy policy before enabling external processing.', 'web27-site-search'); ?></li>
</ul>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('diagnostics')); ?>"><?php echo esc_html__('Open diagnostics', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-errors" aria-labelledby="w27-guide-errors-title">
<h2 id="w27-guide-errors-title"><?php echo esc_html__('10. Troubleshooting', 'web27-site-search'); ?></h2>
<div class="w27-guide-table"><table class="widefat striped">
<thead>
<tr>
<th><?php echo esc_html__('Symptom', 'web27-site-search'); ?></th>
<th><?php echo esc_html__('What to do', 'web27-site-search'); ?></th>
</tr>
</thead>
<tbody>
<tr>
<td><code>gateway_http_401</code></td>
<td><?php echo esc_html__('Check the selected provider key, save without spaces or Bearer, verify it has not been revoked and check whether a server constant overrides it.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><code>gateway_http_402</code></td>
<td><?php echo esc_html__('Check the balance and budget limits in the selected provider account.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><code>gateway_http_403</code></td>
<td><?php echo esc_html__('Check authorized models and the server’s outgoing IP address.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><code>gateway_http_400</code> / <code>404</code></td>
<td><?php echo esc_html__('Check the full model name, protocol and parameter compatibility. Do not set a custom base for a standard provider.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><code>gateway_http_429</code></td>
<td><?php echo esc_html__('Avoid continuous retries; check provider and model limits. Public HTTP 429 can also be the local IP rate limit.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><code>gateway_http_502</code> / <code>504</code>, <code>gateway_request_failed</code></td>
<td><?php echo esc_html__('Check provider availability, DNS/TLS and outgoing HTTPS access. For a confirmed timeout, try increasing the HTTP timeout to 30 seconds and retest once.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('AI configured, but answers are local', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Check the daily limit, model test and diagnostic mode. Greetings or thanks can be local.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Vector count is not increasing', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Check semantic search, key, embedding model permission, balance, standard WP passages and WP-Cron. Run one batch.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Sitemap fails to load', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Check the actual XML URL, matching host, absence of redirects, PHP SimpleXML, loopback permission and HTTP status.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Incorrect answer while AI is working', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Review source text, RAG and the index; correct the publication. Under Quality, pin a relevant standard WP passage to a specific question.', 'web27-site-search'); ?></td>
</tr>
<tr>
<td><?php echo esc_html__('Missing button or widget', 'web27-site-search'); ?></td>
<td><?php echo esc_html__('Check both interface switches, UI mode, theme support,', 'web27-site-search'); ?> <code>wp_footer()</code><?php echo esc_html__(', shortcode placement and cache.', 'web27-site-search'); ?></td>
</tr>
</tbody>
</table></div>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ai')); ?>"><?php echo esc_html__('Test AI', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('diagnostics')); ?>"><?php echo esc_html__('Open diagnostics', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-resources" aria-labelledby="w27-guide-resources-title">
<h2 id="w27-guide-resources-title"><?php echo esc_html__('11. Official resources', 'web27-site-search'); ?></h2>
<ul>
<li>OpenAI: <a href="https://platform.openai.com/api-keys"><?php echo esc_html__('API keys', 'web27-site-search'); ?></a> · <a href="https://platform.openai.com/docs/models"><?php echo esc_html__('Model catalog', 'web27-site-search'); ?></a> · <a href="https://openai.com/api/pricing/"><?php echo esc_html__('Pricing', 'web27-site-search'); ?></a> · <a href="https://help.openai.com/en/articles/5347006-openai-api-supported-countries-and-territories"><?php echo esc_html__('Supported countries and territories', 'web27-site-search'); ?></a></li>
<li>OpenRouter: <a href="https://openrouter.ai/keys"><?php echo esc_html__('API keys', 'web27-site-search'); ?></a> · <a href="https://openrouter.ai/docs"><?php echo esc_html__('API documentation', 'web27-site-search'); ?></a> · <a href="https://openrouter.ai/models"><?php echo esc_html__('Model catalog', 'web27-site-search'); ?></a> · <a href="https://openrouter.ai/terms"><?php echo esc_html__('Terms', 'web27-site-search'); ?></a> · <a href="https://openrouter.ai/privacy"><?php echo esc_html__('Privacy', 'web27-site-search'); ?></a></li>
<li>ProxyAPI.ru:</li>

<li><?php echo esc_html__('Account:', 'web27-site-search'); ?> <a href="https://console.proxyapi.ru/">https://console.proxyapi.ru/</a></li>
<li><?php echo esc_html__('Quick start:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/docs/quickstart">https://proxyapi.ru/docs/quickstart</a></li>
<li><?php echo esc_html__('Keys and budgets:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/docs/api-keys">https://proxyapi.ru/docs/api-keys</a></li>
<li><?php echo esc_html__('Models:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/models/browse">https://proxyapi.ru/models/browse</a></li>
<li><?php echo esc_html__('Embeddings:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/docs/embeddings">https://proxyapi.ru/docs/embeddings</a></li>
<li><?php echo esc_html__('Errors:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/docs/errors">https://proxyapi.ru/docs/errors</a></li>
<li><?php echo esc_html__('Terms:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/terms">https://proxyapi.ru/terms</a></li>
<li><?php echo esc_html__('Privacy:', 'web27-site-search'); ?> <a href="https://proxyapi.ru/privacy">https://proxyapi.ru/privacy</a></li>
</ul>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-languages" aria-labelledby="w27-guide-languages-title">
<h2 id="w27-guide-languages-title"><?php echo esc_html__('12. Languages and translations', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('This guide is included in the installed plugin and does not need an external website or API key. Open Site Search → Setup guide, the Guide tab, or the Setup guide link in the installed plugins list. Administrator access is required.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('To change the administration and guide language, open Users → Profile → Language and select English or Russian, then save the profile. If Russian is unavailable, install it under Settings → General → Site Language. The public widget uses its own language setting, not the administrator profile language.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Open Interface and behavior → Widget language. Choose Automatic (site or page language), English or Russian and save. The choice also applies to public REST messages. The bundled Russian widget translation works even if the WordPress Russian language pack is not installed.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Default interface labels are translated automatically. Previously saved labels, suggested questions and custom RAG instructions remain unchanged. Edit custom text explicitly or use Restore translated default labels in the Interface tab. Changing language does not translate indexed content or require rebuilding embeddings.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('The answer language is chosen independently from the interface: a substantive visitor question or explicit language request takes priority, then conversation history and page language. Local search quotes sources in their original language. English and Russian are supported; other languages fall back to English interface strings unless a translation is installed.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Test English and Russian with both interface locales, including a language change during a conversation and a request in a different language from the source. Cross-language retrieval quality is not guaranteed by interface translation.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('ux')); ?>"><?php echo esc_html__('Open interface settings', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('rag')); ?>"><?php echo esc_html__('Open RAG and instructions', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-quality" aria-labelledby="w27-guide-quality-title">
<h2 id="w27-guide-quality-title"><?php echo esc_html__('13. Answer quality and corrections', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('To review unsuccessful questions or negative ratings, enable logging deliberately under Logs and choose a retention period. The Quality tab shows up to 100 recent entries matching the selected filter. Do not use real personal information in tests.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('To correct a specific query, first update inaccurate published content. Under Passages and vectors, find the relevant public passage and copy its numeric ID. Under Quality, enter the exact question and the passage ID, then click Pin. Pinning prioritizes that passage in standard WordPress retrieval; it does not train the model or guarantee the final answer. Use Unpin with the same question to remove the correction. Retest after editing or rebuilding passages.', 'web27-site-search'); ?></p>
<div class="w27a-actions">
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('logs')); ?>"><?php echo esc_html__('Open logs', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('vectors')); ?>"><?php echo esc_html__('Open passages and vectors', 'web27-site-search'); ?></a>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('quality')); ?>"><?php echo esc_html__('Open quality tools', 'web27-site-search'); ?></a>
</div>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
<section class="w27a-box w27-guide-section" id="w27-guide-sources" aria-labelledby="w27-guide-sources-title">
<h2 id="w27-guide-sources-title"><?php echo esc_html__('14. Additional knowledge sources', 'web27-site-search'); ?></h2>
<p><?php echo esc_html__('Open Knowledge sources, review detected public post types, taxonomies and registered connectors, select the sources you want and save. Local text search works immediately without an API key. The profile is built from current public objects.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Only enable sources that are genuinely public. The plugin does not scan plugin PHP files, arbitrary tables, private metadata or authenticated REST endpoints. Ask the source developer for a connector if its data is not listed.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('Enable embeddings for additional sources only after checking provider costs. Changes are detected from current public content; removed, hidden or disconnected objects are excluded immediately. Old vectors are not used for changed content.', 'web27-site-search'); ?></p>
<p><?php echo esc_html__('The overview and background vectors cover up to 150 objects per source, 600 in total, with up to 12 selected sources. Targeted text lookup can find additional matches when supported. Full semantic coverage of large catalogs is not yet available.', 'web27-site-search'); ?></p>
<a class="button button-secondary" href="<?php echo esc_url($this->tabUrl('sources')); ?>"><?php echo esc_html__('Knowledge sources', 'web27-site-search'); ?></a>
<p class="w27-guide-back"><a href="#w27-guide-title"><?php echo esc_html__('Back to guide contents ↑', 'web27-site-search'); ?></a></p>
</section>
</div>
