<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Admin
{
    private const PAGE='web27-site-search';

    public function register(): void
    {
        add_action('admin_menu',[$this,'menu']);
        add_filter('plugin_action_links_'.plugin_basename(__DIR__.'/web27-site-search.php'),[$this,'pluginLinks']);
        add_action('admin_enqueue_scripts',[$this,'assets']);
        foreach (['save','switch_provider','load_sitemap','process','pause','resume','stop','sync_wp','clear_index','test','clear_logs'] as $action) {
            add_action('admin_post_w27studio_'.$action,[$this,'handle'.str_replace(' ','',ucwords(str_replace('_',' ',$action)))]);
        }
    }

    public function menu(): void
    {
        add_menu_page(__('WEB27 Site Search', 'web27-site-search'),__('Site Search', 'web27-site-search'),'manage_options',self::PAGE,[$this,'render'],'dashicons-search',25);
        add_submenu_page(self::PAGE,__('Setup guide', 'web27-site-search'),__('Setup guide', 'web27-site-search'),'manage_options',self::PAGE.'-guide',[$this,'renderGuidePage']);
    }

    private function tabUrl(string $tab, string $section=''): string
    {
        $args=$tab==='guide'?['page'=>self::PAGE.'-guide']:['page'=>self::PAGE,'tab'=>$tab];
        return add_query_arg($args,admin_url('admin.php')).($section!==''?'#w27-guide-'.$section:'');
    }

    public function pluginLinks(array $links): array
    {
        if (current_user_can('manage_options')) {
            array_unshift($links,'<a href="'.esc_url($this->tabUrl('guide')).'">'.esc_html__('Setup guide', 'web27-site-search').'</a>');
        }
        return $links;
    }

    public function renderGuidePage(): void { $this->render('guide'); }

    private function guideHint(string $section,string $title,string $description): void
    {
        echo '<aside class="w27-guide-hint"><div><h2>'.esc_html($title).'</h2><p>'.esc_html($description).'</p></div><a class="button button-secondary" href="'.esc_url($this->tabUrl('guide',$section)).'">'.esc_html__('Open guide', 'web27-site-search').'</a></aside>';
    }

    public function assets(string $hook): void
    {
        if (!str_contains($hook,self::PAGE)) { return; }
        wp_register_style('web27-search-admin',false,[],WEB27_SITE_VERSION);
        wp_enqueue_style('web27-search-admin');
        wp_add_inline_style('web27-search-admin',$this->adminCss());
        wp_enqueue_style('web27-search-admin-guide',WEB27_SITE_URL.'assets/admin-guide.css',['web27-search-admin'],WEB27_SITE_VERSION);
        wp_enqueue_style('web27-site-search-preview',WEB27_SITE_URL.'assets/search.css',[],WEB27_SITE_VERSION);
        wp_enqueue_script('web27-site-search-preview',WEB27_SITE_URL.'assets/preview.js',[],WEB27_SITE_VERSION,true);
    }

    public function render(string $forcedTab=''): void
    {
        $this->guard();
        $tab=$forcedTab!==''?$forcedTab:(isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview');
        $allowed=['overview','guide','ai','rag','index','ux','logs','diagnostics','vectors','quality','sources']; if (!in_array($tab,$allowed,true)) { $tab='overview'; }
        echo '<div class="wrap w27a"><div class="w27a-title"><div><div class="w27a-eyebrow">WEB27 · SITE SEARCH '.esc_html(WEB27_SITE_VERSION).'</div><h1>'.esc_html__('Site Search', 'web27-site-search').'</h1><p>'.esc_html__('Search published content and manage assistant answers.', 'web27-site-search').'</p></div><div class="w27a-badge">'.esc_html(Settings::gatewayConfigured()?__('AI configured', 'web27-site-search'):__('AI not configured', 'web27-site-search')).'</div></div>';
        $this->notice();
        echo '<nav class="nav-tab-wrapper w27a-tabs">';
        foreach (['overview'=>__('Overview', 'web27-site-search'),'guide'=>__('Guide', 'web27-site-search'),'ai'=>__('AI and answers', 'web27-site-search'),'rag'=>__('RAG and instructions', 'web27-site-search'),'index'=>__("Index", 'web27-site-search'),'ux'=>__('Interface', 'web27-site-search'),'logs'=>__('Logs', 'web27-site-search'),'diagnostics'=>__('Diagnostics', 'web27-site-search'),'vectors'=>__('Passages and vectors', 'web27-site-search'),'quality'=>__('Quality', 'web27-site-search'),'sources'=>__('Knowledge sources', 'web27-site-search')] as $key=>$label) {
            $url=$this->tabUrl($key);
            echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        echo '</nav><div class="w27a-body">';
        match($tab){
            'guide'=>$this->renderGuide(),
            'sources'=>$this->renderSources(),
            'vectors'=>$this->vectors(), 'quality'=>$this->renderQuality(),
            'ai'=>$this->renderAi(),
            'rag'=>$this->renderRag(),
            'index'=>$this->renderIndex(),
            'ux'=>$this->renderUx(),
            'logs'=>$this->renderLogs(),
            'diagnostics'=>$this->renderDiagnostics(),
            default=>$this->renderOverview(),
        };
        echo '</div></div>';
    }

    private function renderSources(): void
    {
        $this->guideHint('sources',__('Connect public catalogs', 'web27-site-search'),__('Choose the public sources that search and the assistant may use. No plugin files or arbitrary database tables are read.', 'web27-site-search'));
        $config=Universal::config();$definitions=Universal::definitions();$status=Universal::status();
        echo '<div class="w27a-box"><h2>'.esc_html__('Detected sources', 'web27-site-search').'</h2><p>'.esc_html__('Only enable content that anonymous visitors are allowed to read. Membership-controlled content requires a visibility-aware connector.', 'web27-site-search').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27ss_sources">';wp_nonce_field('w27ss_sources');
        if(!$definitions){echo '<p>'.esc_html__('No additional sources detected.', 'web27-site-search').'</p>';}
        foreach($definitions as $id=>$def){
            $enabled=in_array($id,$config['enabled'],true);$current=$status[$id]??null;
            echo '<p><label><input type="checkbox" name="sources[]" value="'.esc_attr($id).'" '.checked($enabled,true,false).'> <strong>'.esc_html($def['label']).'</strong> <code>'.esc_html($id).'</code></label>';
            if($current){$states=['ok'=>__('Available', 'web27-site-search'),'limited'=>__('Sample limit reached', 'web27-site-search'),'error'=>__('Source failed', 'web27-site-search'),'missing'=>__('Source unavailable', 'web27-site-search')];echo ' — '.esc_html((string)$current['count']).' · '.esc_html($states[$current['state']]??'');}
            echo '</p>';
        }
        foreach(array_diff($config['enabled'],array_keys($definitions)) as $id){echo '<p>'.esc_html($id).' — '.esc_html__('Source unavailable', 'web27-site-search').'</p>';}
        echo '<p><label><input type="checkbox" name="source_semantic" value="1" '.checked($config['semantic'],true,false).'> '.esc_html__('Enable embeddings for additional sources', 'web27-site-search').'</label></p><p>'.esc_html__('Requires the main semantic search setting and a configured embedding model. Background indexing sends up to six public objects per scheduled run; charges may apply.', 'web27-site-search').'</p>';
        echo '<p>'.esc_html__('The overview and background vectors cover up to 150 objects per source, 600 in total, with up to 12 selected sources. Targeted text lookup can find additional matches when supported. Full semantic coverage of large catalogs is not yet available.', 'web27-site-search').'</p>';
        echo '<button type="submit" class="button button-primary">'.esc_html__('Save settings', 'web27-site-search').'</button></form></div>';
        $profile=Universal::profile(true);
        echo '<div class="w27a-box"><h2>'.esc_html__('Public site profile', 'web27-site-search').'</h2>';
        if(!$profile){echo '<p>'.esc_html__('Select sources and save to preview the public site profile.', 'web27-site-search').'</p>';}
        foreach($profile as $id=>$group){echo '<h3>'.esc_html($definitions[$id]['label']??$id).'</h3><p>'.esc_html(implode(', ',$group['categories'])).'</p><p>'.esc_html(implode(', ',$group['examples'])).'</p>';}
        echo '</div>';
    }
    public static function handleSources(): void
    {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.', 'web27-site-search'),'', ['response'=>403]);}
        if(!Settings::isPostRequest()){wp_die(esc_html__('Access denied.', 'web27-site-search'),'', ['response'=>405]);}
        check_admin_referer('w27ss_sources');
        $available=Universal::definitions();$raw=isset($_POST['sources'])&&is_array($_POST['sources'])?wp_unslash($_POST['sources']):[];
        $selected=[];
        foreach($raw as $id){if(is_string($id)&&isset($available[$id])){$selected[$id]=$id;}if(count($selected)>=12){break;}}
        $semantic=isset($_POST['source_semantic'])&&$_POST['source_semantic']==='1';
        update_option(Universal::OPTION,['enabled'=>array_values($selected),'semantic'=>$semantic],false);
        wp_safe_redirect(add_query_arg(['page'=>self::PAGE,'tab'=>'sources'],admin_url('admin.php')));exit;
    }

    private function renderGuide(): void { require __DIR__.'/AdminGuide.php'; }

    private function renderQuality(): void { $this->guideHint('quality',__('Review and correct answers', 'web27-site-search'),__('Find public passage IDs and learn how to pin or unpin a correction.', 'web27-site-search')); public_tools('quality'); }

    private function vectors(): void { $this->guideHint('vectors',__('Prepare semantic search', 'web27-site-search'),__('Check text passages before enabling embeddings. They are billed separately from AI answers.', 'web27-site-search')); public_tools('knowledge'); public_tools('test'); }

    private function renderOverview(): void
    {
        $stats=Database::stats(); $state=Indexer::state(); $queue=count(Indexer::queue()); $o=Settings::localized();
        
        $this->guideHint('install',__('Setting up search for the first time?', 'web27-site-search'),__('Step-by-step guide: provider keys, indexing, embeddings, appearance, limits and verification. Local search requires no key.', 'web27-site-search'));
        echo '<div class="w27a-grid4">';
        $this->card(__('Documents', 'web27-site-search'),(string)$stats['docs'],__('In the current search index', 'web27-site-search'));
        $this->card(__('Passages', 'web27-site-search'),(string)$stats['chunks'],__("RAG chunks", 'web27-site-search'));
        $this->card(__('Requests in 24 hours', 'web27-site-search'),(string)$stats['logs_24h'],__('No results: ', 'web27-site-search').(int)$stats['zero_24h']);
        $this->card(__('Index queue', 'web27-site-search'),(string)$queue, !empty($state['running'])?(!empty($state['paused'])?__('Pause', 'web27-site-search'):__('Running', 'web27-site-search')):__('Not started', 'web27-site-search'));
        echo '</div>';
        echo '<div class="w27a-columns"><section class="w27a-box"><h2>'.esc_html__('System status', 'web27-site-search').'</h2><table class="w27a-kv">';
        $rows=[
            
            [__('Sitemap', 'web27-site-search'),(string)$o['sitemap_url'],__('Current site sitemap.', 'web27-site-search')],
            [__('External AI', 'web27-site-search'),Settings::gatewayConfigured()?__('configured', 'web27-site-search'):__('local mode', 'web27-site-search'),__('A quick test verifies the actual connection; search works locally without an API.', 'web27-site-search')],
            [__('Last indexed', 'web27-site-search'),(string)($stats['last_indexed'] ?: '—'),__('UTC in the WordPress database.', 'web27-site-search')],
            [__('Memory', 'web27-site-search'),!empty($o['history_enabled'])?__('enabled', 'web27-site-search'):__('disabled', 'web27-site-search'),sprintf( /* translators: 1: number of conversation turn pairs, 2: memory lifetime in minutes. */ __('Turn pairs: %1$d · TTL: %2$d min.', 'web27-site-search'),(int)$o['history_turns'],(int)$o['history_ttl_minutes'])],
        ];
        foreach($rows as $r){echo '<tr><th>'.esc_html($r[0]).'</th><td><strong>'.esc_html($r[1]).'</strong><span>'.esc_html($r[2]).'</span></td></tr>';}
        echo '</table></section><section class="w27a-box"><h2>'.esc_html__('Quick test', 'web27-site-search').'</h2><p>'.esc_html__('Tests the entire pipeline: retrieval → local answer → AI (if configured) → sources → logs.', 'web27-site-search').'</p>'.$this->testForm('overview').'<p class="w27a-note">'.esc_html__('For the first run, open Index and synchronize WordPress content. Additional pages are indexed through the sitemap.', 'web27-site-search').'</p></section></div>';
    }

    private function renderAi(): void
    {
        $o=Settings::localized();
        $this->guideHint('proxyapi',__('Connect an AI provider', 'web27-site-search'),__('Create a separate key, set a budget and authorize required models. After saving settings, test the model at the bottom of this page.', 'web27-site-search'));
        echo '<form class="w27a-box" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_switch_provider">'; wp_nonce_field('w27studio_switch_provider');
        echo '<h2>'.esc_html__('AI provider', 'web27-site-search').'</h2><p>'.esc_html__('Choose a provider independently of the site language. Switching restores its saved connection profile and pauses AI and embeddings until you explicitly enable them again.', 'web27-site-search').'</p>';
        echo '<label>'.esc_html__('Provider', 'web27-site-search').' <select name="provider" '.(Settings::serverLocked()?'disabled':'').'>';
        foreach(['openai'=>'OpenAI · Direct API','openrouter'=>'OpenRouter','proxyapi_openai'=>'ProxyAPI.ru · OpenAI','proxyapi_compatible'=>__('ProxyAPI.ru · compatible (legacy)', 'web27-site-search'),'custom'=>__('Custom OpenAI-compatible endpoint', 'web27-site-search')] as $k=>$v){echo '<option value="'.esc_attr($k).'" '.selected((string)$o['provider'],$k,false).'>'.esc_html($v).'</option>';}
        echo '</select></label> <button type="submit" class="button button-secondary" '.(Settings::serverLocked()?'disabled':'').'>'.esc_html__('Select provider', 'web27-site-search').'</button>';
        if (Settings::serverLocked()) { echo '<p>'.esc_html__('Connection settings are controlled by server constants. Remove or update those constants before switching providers.', 'web27-site-search').'</p>'; }
        echo '</form>';
        $links=match($o['provider']) {
            'openai'=>['https://platform.openai.com/api-keys','https://platform.openai.com/docs/models'],
            'openrouter'=>['https://openrouter.ai/keys','https://openrouter.ai/models'],
            'proxyapi_openai','proxyapi_compatible'=>['https://console.proxyapi.ru/','https://proxyapi.ru/models/browse'],
            default=>[],
        };
        echo '<section class="w27-guide-hint w27-guide-inline"><div><h2>'.esc_html__('Selected API endpoint', 'web27-site-search').'</h2><p><code>'.esc_html(Settings::base($o)?:'—').'</code></p><p>';
        if ($links) { echo '<a href="'.esc_url($links[0]).'">'.esc_html__('Get API key', 'web27-site-search').'</a> · <a href="'.esc_url($links[1]).'">'.esc_html__('Model catalog', 'web27-site-search').'</a> · '; }
        echo '<a href="'.esc_url($this->tabUrl('guide','budget')).'">'.esc_html__('Limits and costs', 'web27-site-search').'</a></p></div></section>';
        $catalog=provider_catalog_cache($o);
        echo '<form class="w27a-box" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_save"><input type="hidden" name="return_tab" value="ai">'; wp_nonce_field('w27studio_save');
        echo '<input type="hidden" name="connection_revision" value="'.esc_attr(Settings::profileSignature($o)).'">';
        echo '<h2>'.esc_html__('AI answer generation', 'web27-site-search').'</h2><p class="w27a-lead">'.esc_html__('When embeddings are enabled, queries are sent to the selected provider for semantic search. The answer model receives the question, selected passages and possibly recent conversation turns. The key owner pays for requests.', 'web27-site-search').'</p>';
        $this->checkbox('external_enabled',__('Enable external AI', 'web27-site-search'),!empty($o['external_enabled']),__('When disabled, local fallback is used.', 'web27-site-search'));
        echo '<div class="w27a-formgrid"><label><span>'.esc_html__('Protocol', 'web27-site-search').'</span><select name="settings[api_protocol]"><option value="chat_completions" '.selected((string)$o['api_protocol'],'chat_completions',false).'>chat/completions</option>';
        if ($o['provider']!=='openrouter') { echo '<option value="responses" '.selected((string)$o['api_protocol'],'responses',false).'>responses</option>'; }
        echo '</select></label>';
        if ($o['provider']==='custom') { echo '<label class="span2"><span>'.esc_html__('Custom API base', 'web27-site-search').'</span><input type="url" name="settings[api_base]" value="'.esc_attr((string)$o['api_base']).'" placeholder="https://example.com/v1" '.(Settings::serverLocked()?'readonly':'').'><small>'.esc_html__('HTTPS only. Changing the endpoint clears the old key unless a new key is entered.', 'web27-site-search').'</small></label>'; }
        $configured=((string)$o['api_key']!=='');
        echo '<label><span>'.esc_html__("API key", 'web27-site-search').'</span><input type="password" name="settings[api_key]" value="" autocomplete="new-password" '.(Settings::serverLocked()?'disabled':'').' placeholder="'.esc_attr($configured?__('Key saved — leave empty to keep it', 'web27-site-search'):'sk-…').'" /><small>'.esc_html__('Enter the key without Bearer or quotes. An empty field keeps only the selected provider’s saved key.', 'web27-site-search').' <a href="'.esc_url($this->tabUrl('guide','proxyapi')).'">'.esc_html__('Where do I get a key?', 'web27-site-search').'</a></small></label>';
        echo '<label><input type="checkbox" name="settings[clear_api_key]" value="1" '.(Settings::serverLocked()?'disabled':'').'> '.esc_html__('Remove saved key for this provider', 'web27-site-search').'</label>';
        echo '<label><span>'.esc_html__('Model', 'web27-site-search').'</span><input type="text" name="settings[model]" '.(defined('WEB27_SITE_MODEL')&&WEB27_SITE_MODEL!==''?'readonly':'').' value="'.esc_attr((string)$o['model']).'" list="w27-chat-models"></label>';
        echo '<label><span>'.esc_html__("Max output tokens", 'web27-site-search').'</span><input type="number" min="120" max="4000" name="settings[max_tokens]" value="'.esc_attr((string)$o['max_tokens']).'"></label>';
        echo '<label><span>'.esc_html__('HTTP timeout, seconds', 'web27-site-search').'</span><input type="number" min="5" max="60" name="settings[request_timeout]" value="'.esc_attr((string)$o['request_timeout']).'"></label>';
        echo '<label><span>'.esc_html__("Temperature", 'web27-site-search').'</span><input type="number" step="0.05" min="0" max="2" name="settings[temperature]" value="'.esc_attr((string)$o['temperature']).'"></label>';
        echo '<label><span>'.esc_html__("Top P", 'web27-site-search').'</span><input type="number" step="0.05" min="0.05" max="1" name="settings[top_p]" value="'.esc_attr((string)$o['top_p']).'"></label>';
        echo '<label><span>'.esc_html__("Reasoning effort", 'web27-site-search').'</span><select name="settings[reasoning_effort]">';foreach(['none'=>__("none", 'web27-site-search'),'low'=>__("low", 'web27-site-search'),'medium'=>__("medium", 'web27-site-search'),'high'=>__("high", 'web27-site-search'),'xhigh'=>__("xhigh", 'web27-site-search'),'max'=>__("max", 'web27-site-search')] as $v=>$label){echo '<option value="'.esc_attr($v).'" '.selected((string)$o['reasoning_effort'],$v,false).'>'.esc_html($label).'</option>';}echo '</select></label>';
        echo '<label><span>'.esc_html__("Prompt cache key", 'web27-site-search').'</span><input type="text" name="settings[prompt_cache_key]" value="'.esc_attr((string)$o['prompt_cache_key']).'"></label>';
        echo '<label><span>'.esc_html__('AI requests per day', 'web27-site-search').'</span><input type="number" min="0" max="10000" name="settings[daily_ai_budget]" value="'.esc_attr((string)$o['daily_ai_budget']).'"><small>'.esc_html__('0 = no internal daily limit. Counts answer attempts, not money; embeddings are excluded.', 'web27-site-search').'</small></label>';
        echo '<label><span>'.esc_html__('Requests / 10 minutes / IP', 'web27-site-search').'</span><input type="number" min="1" max="120" name="settings[rate_limit]" value="'.esc_attr((string)$o['rate_limit']).'"></label></div>';
        if (in_array($o['provider'],['proxyapi_openai','proxyapi_compatible'],true)) $this->checkbox('proxyapi_log_metadata',__('Send technical metadata to ProxyAPI', 'web27-site-search'),!empty($o['proxyapi_log_metadata']),__('No query text in headers; technical tag/environment/session metadata only.', 'web27-site-search'));
        $this->checkbox('semantic',__('Enable semantic search (embeddings)', 'web27-site-search'),!empty($o['semantic']) && ($o['provider']!=='custom'||!empty($o['custom_embeddings_confirmed'])),__('WordPress passages and queries are sent to the selected provider with separate permission. A custom gateway must support the embeddings endpoint.', 'web27-site-search'));
        echo '<label>'.esc_html__('Embedding model', 'web27-site-search').' <input name="settings[embed_model]" list="w27-embed-models" value="'.esc_attr($o['embed_model']).'"></label>';
        foreach (['chat'=>'w27-chat-models','embed'=>'w27-embed-models'] as $kind=>$id) { echo '<datalist id="'.esc_attr($id).'">'; foreach (($catalog[$kind]??[]) as $model) { echo '<option value="'.esc_attr($model).'"></option>'; } echo '</datalist>'; }
        echo '<p>'.esc_html__('Changing the embedding provider, endpoint or model requires new vectors. Text passages and pinned answers remain. Existing vectors are replaced gradually after semantic search is enabled.', 'web27-site-search').'</p>';
        submit_button(__('Save AI settings', 'web27-site-search')); echo '</form>'; public_tools('settings');
    }

    private function renderRag(): void
    {
        $o=Settings::localized();
        $this->guideHint('rag',__('Configure conversation rules', 'web27-site-search'),__('Use the built-in rules and the universal instruction example. Keep site facts in published materials.', 'web27-site-search'));
        echo '<form class="w27a-box" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_save"><input type="hidden" name="return_tab" value="rag">';
        wp_nonce_field('w27studio_save');
        echo '<h2>'.esc_html__('RAG and assistant instructions', 'web27-site-search').'</h2><p class="w27a-lead">'.esc_html__('These instructions are sent to the model with each AI request. Keep behavior rules here and service facts, prices and deadlines on public pages. Never add passwords or personal data.', 'web27-site-search').'</p>';
        echo '<p>'.esc_html__('Changes apply to the next request without reindexing. Model instructions are not executed in local fallback mode.', 'web27-site-search').'</p>';
        echo '<div class="w27a-formgrid">';
        echo '<label><span>'.esc_html__('RAG sources', 'web27-site-search').'</span><input type="number" min="3" max="12" name="settings[rag_limit]" value="'.esc_attr((string)$o['rag_limit']).'"></label>';
        echo '<label><span>'.esc_html__('RAG context characters', 'web27-site-search').'</span><input type="number" min="4000" max="50000" step="1000" name="settings[rag_context_chars]" value="'.esc_attr((string)$o['rag_context_chars']).'"></label>';
        echo '</div>';
        echo '<label class="w27a-full"><span>'.esc_html__('Answer style', 'web27-site-search').'</span><textarea name="settings[rag_answer_instructions]" rows="5">'.esc_textarea((string)$o['rag_answer_instructions']).'</textarea></label><br>';
        echo '<label class="w27a-full"><span>'.esc_html__('Data handling', 'web27-site-search').'</span><textarea name="settings[rag_data_instructions]" rows="5">'.esc_textarea((string)$o['rag_data_instructions']).'</textarea></label><br>';
        echo '<label class="w27a-full"><span>'.esc_html__('Conversation style', 'web27-site-search').'</span><textarea name="settings[rag_client_instructions]" rows="5">'.esc_textarea((string)$o['rag_client_instructions']).'</textarea></label><br>';
        echo '<label class="w27a-full"><span>'.esc_html__('Additional model instruction', 'web27-site-search').'</span><textarea name="settings[system_prompt]" rows="7">'.esc_textarea((string)$o['system_prompt']).'</textarea><small>'.esc_html__('Built-in rules instruct the model to avoid invented facts and ignore prompt injection; these rules cannot be disabled.', 'web27-site-search').'</small></label>';
        submit_button(__('Save RAG and instructions', 'web27-site-search')); echo '</form>';
        echo '<section class="w27a-box"><h2>'.esc_html__('Test an answer', 'web27-site-search').'</h2><p>'.esc_html__('Save instructions first, then ask a question. The test indicates external AI or fallback mode.', 'web27-site-search').'</p>'.$this->testForm('rag').$this->testResult().'</section>';
    }

    private function renderIndex(): void
    {
        $this->guideHint('index',__('Populate the knowledge base', 'web27-site-search'),__('Synchronize WordPress content for standard posts and pages. A sitemap is for additional public HTML pages; check your own sitemap.', 'web27-site-search'));
        $o=Settings::localized(); $state=Indexer::state(); $queue=count(Indexer::queue()); $stats=Database::stats();
        $total=max(1,(int)$state['total']); $done=min($total,(int)$state['processed']+(int)$state['failed']); $pct=(int)round($done/$total*100);
        echo '<div class="w27a-grid4">';$this->card(__('Documents', 'web27-site-search'),(string)$stats['docs'],__('ready for search', 'web27-site-search'));$this->card(__('Queue', 'web27-site-search'),(string)$queue,__('URLs remaining', 'web27-site-search'));$this->card(__('Successful', 'web27-site-search'),(string)$state['processed'],__('current run', 'web27-site-search'));$this->card(__('Errors', 'web27-site-search'),(string)$state['failed'],(string)($state['last_error'] ?: __('none', 'web27-site-search')));echo '</div>';
        echo '<section class="w27a-box"><h2>'.esc_html__('Site content indexing', 'web27-site-search').'</h2><p>'.esc_html__('URLs are taken from', 'web27-site-search').' <code>'.esc_html((string)$o['sitemap_url']).'</code>'.esc_html__('. Pages are crawled in small batches on the WordPress cron schedule.', 'web27-site-search').'</p><div class="w27a-progress"><i style="width:'.esc_attr((string)$pct).'%"></i></div><p class="w27a-note">'.esc_html__('Progress:', 'web27-site-search').' '.$done.' / '.(int)$state['total'].' · '.$pct.esc_html__('% · status: ', 'web27-site-search').esc_html(!empty($state['running'])?(!empty($state['paused'])?__('paused', 'web27-site-search'):__('running', 'web27-site-search')):__('stopped', 'web27-site-search')).'</p><div class="w27a-actions">';
        $this->actionButton('load_sitemap',__('Load sitemap and start', 'web27-site-search'),'primary','index');
        $this->actionButton('process',__('Process batch now', 'web27-site-search'),'secondary','index');
        if (!empty($state['paused'])) {$this->actionButton('resume',__('Resume', 'web27-site-search'),'secondary','index');} else {$this->actionButton('pause',__('Pause', 'web27-site-search'),'secondary','index');}
        $this->actionButton('stop',__('Stop queue', 'web27-site-search'),'secondary','index');
        $this->actionButton('sync_wp',__('Synchronize WordPress content', 'web27-site-search'),'secondary','index');
        echo '</div></section>';
        echo '<form class="w27a-box" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_save"><input type="hidden" name="return_tab" value="index">';wp_nonce_field('w27studio_save');echo '<h2>'.esc_html__('Indexer settings', 'web27-site-search').'</h2><div class="w27a-formgrid">';
        echo '<label class="span2"><span>'.esc_html__("Sitemap URL", 'web27-site-search').'</span><input type="url" name="settings[sitemap_url]" value="'.esc_attr((string)$o['sitemap_url']).'"></label>';
        echo '<label><span>'.esc_html__('Batch size', 'web27-site-search').'</span><input type="number" min="1" max="10" name="settings[index_batch_size]" value="'.esc_attr((string)$o['index_batch_size']).'"><small>'.esc_html__('Start with 1–2 on shared hosting.', 'web27-site-search').'</small></label>';
        echo '<label><span>'.esc_html__('Cron interval, minutes', 'web27-site-search').'</span><input type="number" min="1" max="60" name="settings[index_interval_minutes]" value="'.esc_attr((string)$o['index_interval_minutes']).'"></label>';
        echo '<label><span>'.esc_html__('Page timeout, seconds', 'web27-site-search').'</span><input type="number" min="5" max="30" name="settings[index_timeout]" value="'.esc_attr((string)$o['index_timeout']).'"></label>';
        echo '<label><span>'.esc_html__("User-Agent", 'web27-site-search').'</span><input type="text" name="settings[index_user_agent]" value="'.esc_attr((string)$o['index_user_agent']).'"></label></div>';
        $this->checkbox('index_virtual_pages',__('Load additional pages from sitemap', 'web27-site-search'),!empty($o['index_virtual_pages']),__('Through sitemap and safe loopback HTTP.', 'web27-site-search'));
        $this->checkbox('index_wp_pages',__('Index standard WordPress pages', 'web27-site-search'),!empty($o['index_wp_pages']),__('Locally, without HTTP.', 'web27-site-search'));
        $this->checkbox('index_wp_posts',__('Index blog posts', 'web27-site-search'),!empty($o['index_wp_posts']),__('Locally, without HTTP.', 'web27-site-search'));
        submit_button(__('Save index settings', 'web27-site-search')); echo '</form>';
        echo '<section class="w27a-box danger"><h2>'.esc_html__('Maintenance', 'web27-site-search').'</h2><p>'.esc_html__('Clearing deletes both search indexes and embeddings, invalidates pinned passage IDs and stops the queue. Site content and sitemap are unchanged. Back up the database first.', 'web27-site-search').'</p>';$this->actionButton('clear_index',__('Clear index', 'web27-site-search'),'delete','index',true);echo '</section>';
    }

    private function renderUx(): void
    {
        $o=Settings::localized();
        $this->guideHint('languages',__('Languages and translations', 'web27-site-search'),__('Change the administration or site language without resetting the index or saved custom text.', 'web27-site-search'));
        $this->guideHint('interface',__('Show the assistant on your site', 'web27-site-search'),__('Choose a floating button or shortcode, set the name and color scheme. Check search, limits and privacy policy before making it available to visitors.', 'web27-site-search'));
        echo '<form class="w27a-box" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_save"><input type="hidden" name="return_tab" value="ux">';wp_nonce_field('w27studio_save');echo '<h2>'.esc_html__('Interface and behavior', 'web27-site-search').'</h2><p class="w27a-lead">'.esc_html__('When the theme supports inline integration, the floating button is disabled throughout that theme. To embed manually, use the shortcode', 'web27-site-search').' <code>[web27_site_search]</code>.</p>';
        $this->checkbox('enabled',__('Enable search', 'web27-site-search'),!empty($o['enabled']),__('Main API and UI switch.', 'web27-site-search'));
        $this->checkbox('frontend_enabled',__('Enable public interface', 'web27-site-search'),!empty($o['frontend_enabled']),__('REST may remain available when the UI is disabled.', 'web27-site-search'));
        echo '<div class="w27a-formgrid"><label><span>'.esc_html__('UI mode', 'web27-site-search').'</span><select name="settings[frontend_mode]"><option value="floating" '.selected((string)$o['frontend_mode'],'floating',false).'>'.esc_html__('Site-wide floating button', 'web27-site-search').'</option><option value="shortcode_only" '.selected((string)$o['frontend_mode'],'shortcode_only',false).'>'.esc_html__('Shortcode only', 'web27-site-search').'</option></select></label>';
        echo '<label><span>'.esc_html__('Widget language', 'web27-site-search').'</span><select name="settings[widget_language]">';
        foreach (['auto'=>__('Automatic — site or page language', 'web27-site-search'),'en_US'=>'English (EN)','ru_RU'=>'Русский (RU)'] as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected(Settings::widgetLanguage(),$key,false).'>'.esc_html($label).'</option>';
        }
        echo '</select><small>'.esc_html__('Changes public interface labels only. AI answers follow the visitor’s language. Custom text is preserved.', 'web27-site-search').' <a href="'.esc_url(admin_url('profile.php')).'">'.esc_html__('Change administration language', 'web27-site-search').'</a></small></label>';
        echo '<label><span>'.esc_html__('Assistant name', 'web27-site-search').'</span><input type="text" name="settings[operator_name]" value="'.esc_attr((string)$o['operator_name']).'"></label><label><span>'.esc_html__('Launcher label', 'web27-site-search').'</span><input type="text" name="settings[launcher_label]" value="'.esc_attr((string)$o['launcher_label']).'"></label><label><span>'.esc_html__('Panel title', 'web27-site-search').'</span><input type="text" name="settings[panel_title]" value="'.esc_attr((string)$o['panel_title']).'"></label><label class="span2"><span>'.esc_html__("Placeholder", 'web27-site-search').'</span><input type="text" name="settings[placeholder]" value="'.esc_attr((string)$o['placeholder']).'"></label><label><span>'.esc_html__('Minimum query length', 'web27-site-search').'</span><input type="number" min="2" max="12" name="settings[min_query_chars]" value="'.esc_attr((string)$o['min_query_chars']).'"></label></div>';
        echo '<label class="w27a-check"><input type="checkbox" name="reset_widget_labels" value="1"><span><b>'.esc_html__('Restore translated default labels', 'web27-site-search').'</b><small>'.esc_html__('On save, reset only the launcher label, panel title and placeholder. Other custom settings are retained.', 'web27-site-search').'</small></span></label>';
        echo '<label class="w27a-full"><span>'.esc_html__('Suggested questions — one per line', 'web27-site-search').'</span><textarea name="settings[suggestions]" rows="5">'.esc_textarea((string)$o['suggestions']).'</textarea></label>';
        $this->checkbox('history_enabled',__('Conversation memory', 'web27-site-search'),!empty($o['history_enabled']),__('Stored in a temporary transient, not a user profile.', 'web27-site-search'));
        echo '<div class="w27a-formgrid"><label><span>'.esc_html__('Turn pairs', 'web27-site-search').'</span><input type="number" min="1" max="10" name="settings[history_turns]" value="'.esc_attr((string)$o['history_turns']).'"></label><label><span>'.esc_html__('Memory TTL, minutes', 'web27-site-search').'</span><input type="number" min="5" max="240" name="settings[history_ttl_minutes]" value="'.esc_attr((string)$o['history_ttl_minutes']).'"></label></div>';
        echo '<label class="w27a-full"><span>'.esc_html__('Color scheme', 'web27-site-search').'</span><select name="appearance" data-w27public-theme-select>';
        foreach (['light'=>__('Light', 'web27-site-search'),'dark'=>__('Dark', 'web27-site-search'),'contrast'=>__('High contrast', 'web27-site-search'),'system'=>__('System', 'web27-site-search'),'custom'=>__('Custom colors', 'web27-site-search')] as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected(Settings::appearance(),$key,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></label>'; $this->designFields(); echo '<p>'.esc_html__('Appearance preview uses the saved widget language. Save changes to update it. No AI requests are made.', 'web27-site-search').'</p>';
        Language::withInterfaceLocale(Language::interfaceLocale(),fn()=>$this->widgetPreview());
        echo '<p class="w27a-note">'.esc_html__('Default labels follow the widget language. Saved custom text stays unchanged when the language changes.', 'web27-site-search').'</p>';
        submit_button(__('Save interface', 'web27-site-search'));echo '</form>';
        echo '<section class="w27a-box"><h2>'.esc_html__('Typography', 'web27-site-search').'</h2><p>'.esc_html__('The front end uses a consistent type scale: H2 via', 'web27-site-search').' <code>clamp(30px, 3.1vw, 48px)</code>'.esc_html__(', H3 16px, body 15px, cards 12px, buttons 14px, eyebrow 11px and meta 10px. Body text is not reduced on mobile.', 'web27-site-search').'</p></section>';
    }

    private function designFields(): void {
        $d=Settings::design();
        $corners=['bottom-right'=>__('Bottom right', 'web27-site-search'),'bottom-left'=>__('Bottom left', 'web27-site-search'),'top-right'=>__('Top right', 'web27-site-search'),'top-left'=>__('Top left', 'web27-site-search')];
        $fields=[
            'placement'=>[__('Launcher position', 'web27-site-search'),$corners],
            'mobile'=>[__('Mobile launcher position', 'web27-site-search'),['inherit'=>__('Same as desktop', 'web27-site-search')]+$corners],
            'visibility'=>[__('Floating button visibility', 'web27-site-search'),['auto'=>__('Respect theme and UI mode', 'web27-site-search'),'always'=>__('Always show', 'web27-site-search'),'never'=>__('Never show', 'web27-site-search')]],
            'shape'=>[__('Button shape', 'web27-site-search'),['pill'=>__('Pill', 'web27-site-search'),'rounded'=>__('Rounded rectangle', 'web27-site-search'),'circle'=>__('Circle (icon only)', 'web27-site-search')]],
            'content'=>[__('Button content', 'web27-site-search'),['both'=>__('Icon and text', 'web27-site-search'),'icon'=>__('Icon only', 'web27-site-search'),'text'=>__('Text only', 'web27-site-search')]],
            'window'=>[__('Window layout', 'web27-site-search'),['modal'=>__('Centered dialog', 'web27-site-search'),'right'=>__('Right side panel', 'web27-site-search'),'left'=>__('Left side panel', 'web27-site-search'),'full'=>__('Full screen', 'web27-site-search')]],
            'cards'=>[__('Material layout', 'web27-site-search'),['grid'=>__('Cards grid', 'web27-site-search'),'list'=>__('Vertical list', 'web27-site-search'),'carousel'=>__('Horizontal carousel', 'web27-site-search'),'compact'=>__('Compact links', 'web27-site-search')]],
        ];
        echo '<h2>'.esc_html__('Design Studio', 'web27-site-search').'</h2><div class="w27a-formgrid">';
        foreach($fields as $k=>[$label,$options]){echo '<label><span>'.esc_html($label).'</span><select name="design['.esc_attr($k).']">';foreach($options as $v=>$text){echo '<option value="'.esc_attr($v).'" '.selected($d[$k],$v,false).'>'.esc_html($text).'</option>';}echo '</select></label>';}
        foreach(['width'=>[__('Window width, px', 'web27-site-search'),360,1100],'height'=>[__('Maximum window height, px', 'web27-site-search'),420,1200],'offset_x'=>[__('Horizontal offset, px', 'web27-site-search'),8,96],'offset_y'=>[__('Vertical offset, px', 'web27-site-search'),8,96],'radius'=>[__('Corner radius, px', 'web27-site-search'),0,32]] as $k=>[$label,$min,$max]){echo '<label><span>'.esc_html($label).'</span><input type="number" name="design['.esc_attr($k).']" min="'.(int)$min.'" max="'.(int)$max.'" value="'.(int)$d[$k].'"></label>';}
        foreach(['accent'=>__('Accent color', 'web27-site-search'),'background'=>__('Background color', 'web27-site-search'),'surface'=>__('Card color', 'web27-site-search'),'text'=>__('Text color', 'web27-site-search')] as $k=>$label){echo '<label><span>'.esc_html($label).'</span><input type="color" name="design['.esc_attr($k).']" value="'.esc_attr($d[$k]).'"></label>';}
        echo '</div><p>'.esc_html__('Custom colors apply only with the Custom colors scheme. The preview shows a sample launcher and materials, without API calls.', 'web27-site-search').'</p><label><input type="checkbox" data-w27-preview-mobile> '.esc_html__('Mobile preview', 'web27-site-search').'</label>';
    }

    private function widgetPreview(): void
    {
        $o=Settings::localized();
        echo '<section class="w27public-panel is-preview" data-w27public-preview data-w27public-theme="'.esc_attr(Settings::appearance()).'"'.Settings::designAttributes().'><div class="w27public-layout"><div class="w27public-head"><div><div class="w27public-eyebrow">'.esc_html__('SITE SEARCH', 'web27-site-search').'</div><h2>'.esc_html((string)$o['panel_title']).'</h2><p>'.esc_html__('Enter a question or the name of a resource.', 'web27-site-search').'</p></div><div class="w27public-head-actions"><button type="button" class="w27public-reset">'.esc_html__('New question', 'web27-site-search').'</button></div></div><div class="w27public-form"><label class="w27public-sr" for="w27public-preview-input">'.esc_html__('Example question', 'web27-site-search').'</label><input id="w27public-preview-input" type="search" value="'.esc_attr__("What do you offer?", 'web27-site-search').'"><button type="button">'.esc_html__('Find an answer', 'web27-site-search').'</button></div><div class="w27public-results"><div class="w27public-answer"><div class="w27public-answer-name">'.esc_html__('Assistant', 'web27-site-search').'</div><div class="w27public-answer-text">'.esc_html__('Hello! You can find our published materials and open the relevant page.', 'web27-site-search').'</div></div><div class="w27public-source-list">'.str_repeat('<div class="w27public-source"><span class="w27public-source-visual" aria-hidden="true">▤</span><span class="w27public-source-body"><span class="w27public-source-title">'.esc_html__('Example material', 'web27-site-search').'</span><span class="w27public-source-text">'.esc_html__('A short description of a public resource.', 'web27-site-search').'</span><span class="w27public-source-link">'.esc_html__('Open page →', 'web27-site-search').'</span></span></div>',3).'</div></div></div></section><div class="w27-preview-stage" data-w27-preview-stage><button type="button" class="w27public-launcher" data-w27public-preview-launcher data-w27public-theme="'.esc_attr(Settings::appearance()).'"'.Settings::designAttributes().'><span class="w27public-launcher-icon" aria-hidden="true">⌕</span><span class="w27public-launcher-label">'.esc_html((string)$o['launcher_label']).'</span></button></div>';
    }

    private function renderLogs(): void
    {
        $this->guideHint('budget',__('Data retention and costs', 'web27-site-search'),__('Logging is optional. Enable it deliberately, set retention and consider the site’s privacy policy.', 'web27-site-search'));
        $o=Settings::localized(); $logs=Logger::recent(120);
        echo '<form class="w27a-box compact" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_save"><input type="hidden" name="return_tab" value="logs">';wp_nonce_field('w27studio_save');
        $this->checkbox('log_enabled',__('Save query logs', 'web27-site-search'),!empty($o['log_enabled']),__('Queries may contain user text — set a reasonable retention period.', 'web27-site-search'));
        echo '<label class="w27a-inline"><span>'.esc_html__('Retention, days', 'web27-site-search').'</span><input type="number" min="1" max="365" name="settings[log_retention_days]" value="'.esc_attr((string)$o['log_retention_days']).'"></label>';submit_button(__('Save', 'web27-site-search'),'secondary','submit',false);echo '</form>';$this->actionButton('clear_logs',__('Clear logs', 'web27-site-search'),'delete','logs',true);
        echo '<section class="w27a-box"><h2>'.esc_html__('Recent queries', 'web27-site-search').'</h2><div class="w27a-tablewrap"><table class="widefat striped"><thead><tr><th>'.esc_html__('Time', 'web27-site-search').'</th><th>'.esc_html__('Query', 'web27-site-search').'</th><th>'.esc_html__('Mode', 'web27-site-search').'</th><th>'.esc_html__('Results', 'web27-site-search').'</th><th>'.esc_html__("Latency", 'web27-site-search').'</th><th>'.esc_html__('Model', 'web27-site-search').'</th><th>'.esc_html__('Error', 'web27-site-search').'</th></tr></thead><tbody>';
        if(!$logs){echo '<tr><td colspan="7">'.esc_html__('No logs yet.', 'web27-site-search').'</td></tr>';} else foreach($logs as $r){echo '<tr><td>'.esc_html((string)$r['created_at']).'</td><td class="query">'.esc_html((string)$r['search_query']).'</td><td><code>'.esc_html((string)$r['mode']).'</code></td><td>'.(int)$r['result_count'].'</td><td>'.(int)$r['latency_ms'].' '.esc_html__('ms', 'web27-site-search').'</td><td>'.esc_html((string)$r['model']).'</td><td>'.esc_html((string)$r['error_code']).'</td></tr>';}
        echo '</tbody></table></div></section>';
    }

    private function renderDiagnostics(): void
    {
        $this->guideHint('errors',__('Troubleshoot search', 'web27-site-search'),__('Check common API, sitemap, WP-Cron and answer-mode errors. The guide contains new site acceptance scenarios.', 'web27-site-search'));
        $stats=Database::stats(); $o=Settings::localized(); $state=Indexer::state();
        echo '<div class="w27a-columns"><section class="w27a-box"><h2>'.esc_html__('Full query test', 'web27-site-search').'</h2>'.$this->testForm('diagnostics').$this->testResult().'</section><section class="w27a-box"><h2>'.esc_html__('Diagnostics', 'web27-site-search').'</h2><table class="w27a-kv">';
        $checks=[
            ['PHP',PHP_VERSION,version_compare(PHP_VERSION,'8.1','>=')?'OK':__('Error', 'web27-site-search')],
            ['WordPress',get_bloginfo('version'),__('6.6+ recommended', 'web27-site-search')],
            [__("DB tables", 'web27-site-search'),Database::tablesExist()?'OK':__('none', 'web27-site-search'),__('Document, passage and log tables', 'web27-site-search')],
            ['REST',rest_url('web27-site-search/v1/query'),'POST'],
            [__("Gateway endpoint", 'web27-site-search'),Settings::endpoint($o) ?: '—',Settings::gatewayConfigured()?__('configured; test to verify connection', 'web27-site-search'):__('not configured', 'web27-site-search')],
            [__('Sitemap', 'web27-site-search'),(string)$o['sitemap_url'],Settings::sameSiteUrl((string)$o['sitemap_url'])?__("same-site", 'web27-site-search'):__('check host', 'web27-site-search')],
            [__("Cron index", 'web27-site-search'),wp_next_scheduled(Indexer::CRON_HOOK)?wp_date('Y-m-d H:i:s',(int)wp_next_scheduled(Indexer::CRON_HOOK)):__('not scheduled', 'web27-site-search'),sprintf( /* translators: %d: index batch size. */ __('Batch: %d', 'web27-site-search'),(int)$o['index_batch_size'])],
            [__("Index", 'web27-site-search'), sprintf( /* translators: 1: document count, 2: passage count. */ __('Documents: %1$d / passages: %2$d', 'web27-site-search'),(int)$stats['docs'],(int)$stats['chunks']),!empty($state['last_error'])?__('Last error: ', 'web27-site-search').$state['last_error']:__('no recent error', 'web27-site-search')],
        ];
        foreach($checks as $c){echo '<tr><th>'.esc_html((string)$c[0]).'</th><td><strong>'.esc_html((string)$c[1]).'</strong><span>'.esc_html((string)$c[2]).'</span></td></tr>';}
        echo '</table></section></div>';
    }

    public function handleSave(): void
    {
        $this->guardPost('w27studio_save');
        $current=get_option(Settings::OPTION,[]); $current=is_array($current)?$current:[];
        $incoming=isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $tab=isset($_POST['return_tab']) && is_string($_POST['return_tab']) ? sanitize_key(wp_unslash($_POST['return_tab'])) : 'overview';
        $allowed=[
            'ux'=>['enabled','frontend_enabled','frontend_mode','widget_language','operator_name','launcher_label','panel_title','placeholder','suggestions','min_query_chars','history_enabled','history_turns','history_ttl_minutes'],
            'ai'=>['external_enabled','api_base','api_protocol','api_key','clear_api_key','model','max_tokens','request_timeout','temperature','top_p','reasoning_effort','prompt_cache_key','daily_ai_budget','rate_limit','proxyapi_log_metadata','semantic','embed_model'],
            'rag'=>['rag_limit','rag_context_chars','rag_answer_instructions','rag_data_instructions','rag_client_instructions','system_prompt'],
            'index'=>['sitemap_url','index_batch_size','index_interval_minutes','index_timeout','index_user_agent','index_virtual_pages','index_wp_pages','index_wp_posts'],
            'logs'=>['log_enabled','log_retention_days'],
        ];
        $incoming=array_filter(array_intersect_key($incoming,array_flip($allowed[$tab]??[])),'is_scalar');
        $display = Settings::localized();
        foreach (['launcher_label','panel_title','placeholder','rag_answer_instructions','rag_data_instructions','rag_client_instructions'] as $key) {
            if (!array_key_exists($key,$current) && isset($incoming[$key]) && $incoming[$key] === $display[$key]) { unset($incoming[$key]); }
        }
        if ($tab==='ai') {
            $revision=(isset($_POST['connection_revision']) && is_string($_POST['connection_revision']) ? sanitize_text_field(wp_unslash($_POST['connection_revision'])) : '');
            if (!hash_equals(Settings::profileSignature(Settings::get()),$revision)) { wp_die(esc_html__('Connection settings changed. Reload the AI page before saving.', 'web27-site-search'),'', ['response'=>409]); }
            if (Settings::serverLocked()) { unset($incoming['api_base'],$incoming['api_key'],$incoming['clear_api_key']); }
            $incoming['custom_embeddings_confirmed']=!empty($incoming['semantic']);
        }
        $clean=Settings::sanitize($incoming);
        $next=array_replace($current,array_intersect_key($clean,$incoming));
        if ($tab==='ai') {
             
            foreach (['api_key','external_enabled','semantic','custom_embeddings_confirmed','api_protocol'] as $k) { $next[$k]=$clean[$k]; }
            if (Settings::serverLocked()) { $next['api_key']=$current['api_key']??''; }
        }
        if ($tab==='index') {
            $next['types']=array_keys(array_filter(['page'=>$clean['index_wp_pages'],'post'=>$clean['index_wp_posts']]));
            wp_clear_scheduled_hook(Indexer::CRON_HOOK);
        }
        $resetLabels=$tab==='ux' && ($_POST['reset_widget_labels']??'')==='1';
        if ($resetLabels) { foreach (['launcher_label','panel_title','placeholder'] as $key) { unset($next[$key]); } }
        if ($tab==='ux' && isset($_POST['appearance'])) { Settings::saveAppearance(wp_unslash($_POST['appearance'])); }
        if ($tab==='ux' && isset($_POST['design']) && is_array($_POST['design'])) { update_option(Settings::DESIGN,Settings::design(wp_unslash($_POST['design'])),false); }
        if ($incoming || $resetLabels) { update_option(Settings::OPTION,$next,false); }
        $this->redirect($tab,['saved'=>1]);
    }

    public function handleSwitchProvider(): void
    {
        $this->guardPost('w27studio_switch_provider');
        $target=(isset($_POST['provider']) && is_string($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '');
        $saved=get_option(Settings::OPTION,[]);
        try { $next=Settings::switchProvider(is_array($saved)?$saved:[],$target); }
        catch (\RuntimeException $e) { wp_die(esc_html__('Provider switching is unavailable for this configuration.', 'web27-site-search'),'', ['response'=>400]); }
        update_option(Settings::OPTION,$next,false);
        $this->redirect('ai',['saved'=>1]);
    }

    public function handleLoadSitemap(): void { $this->simpleAction('load_sitemap',function(){return (new Indexer())->loadSitemapQueue();},'queued'); }
    public function handleProcess(): void { $this->simpleAction('process',fn()=>(new Indexer())->processBatch(),'processed'); }
    public function handlePause(): void { $this->simpleAction('pause',function(){(new Indexer())->pause(true);return true;},'paused'); }
    public function handleResume(): void { $this->simpleAction('resume',function(){(new Indexer())->pause(false);return true;},'resumed'); }
    public function handleStop(): void { $this->simpleAction('stop',function(){(new Indexer())->stop();return true;},'stopped'); }
    public function handleSyncWp(): void { $this->simpleAction('sync_wp',fn()=>(new Indexer())->syncWpContent(),'wp_synced'); }
    public function handleClearIndex(): void
    {
        $this->guardPost('w27studio_clear_index');global $wpdb;$wpdb->query($wpdb->prepare('DELETE FROM %i',Knowledge::table()));update_option('w27ss_cursor',-1,false);$wpdb->query($wpdb->prepare('DELETE FROM %i',Database::chunks()));$wpdb->query($wpdb->prepare('DELETE FROM %i',Database::docs()));(new Indexer())->stop();$this->redirect('index',['cleared'=>1]);
    }
    public function handleClearLogs(): void { $this->guardPost('w27studio_clear_logs');global $wpdb;$wpdb->query($wpdb->prepare('DELETE FROM %i',Knowledge::log()));$wpdb->query($wpdb->prepare('DELETE FROM %i',Database::logs()));$this->redirect('logs',['cleared'=>1]); }
    public function handleTest(): void
    {
        $this->guardPost('w27studio_test');$q=isset($_POST['query']) && is_string($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';$payload=['query'=>$q];
        try{$payload['result']=(new SearchEngine())->search($q,false);}catch(\Throwable $e){$payload['error']=$e->getMessage();}
        set_transient('w27studio_test_'.get_current_user_id(),$payload,10*MINUTE_IN_SECONDS);$tab=isset($_POST['return_tab']) && is_string($_POST['return_tab']) ? sanitize_key(wp_unslash($_POST['return_tab'])) : 'diagnostics';$this->redirect(in_array($tab,['overview','rag','diagnostics'],true)?$tab:'diagnostics',['tested'=>1]);
    }

     
    private function simpleAction(string $action,callable $fn,string $flag): void
    {
        $tab='index';
        $this->guardPost('w27studio_'.$action);
        try{$fn();$this->redirect($tab,[$flag=>1]);}catch(\Throwable $e){set_transient('w27studio_admin_error_'.get_current_user_id(),$e->getMessage(),MINUTE_IN_SECONDS);$this->redirect($tab,['error'=>1]);}
    }

    private function testForm(string $returnTab): string
    {
        $test=get_transient('w27studio_test_'.get_current_user_id());$q=is_array($test)?(string)($test['query'] ?? ''):'';
        return '<form class="w27a-test" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27studio_test"><input type="hidden" name="return_tab" value="'.esc_attr($returnTab).'">'.wp_nonce_field('w27studio_test','_wpnonce',true,false).'<input type="search" name="query" maxlength="500" value="'.esc_attr($q).'" placeholder="'.esc_attr__('For example: what information is available here?', 'web27-site-search').'" required><button class="button button-primary" type="submit">'.esc_html__('Run test', 'web27-site-search').'</button></form>'.($returnTab==='overview'?$this->testResult():'');
    }

    private function testResult(): string
    {
        $test=get_transient('w27studio_test_'.get_current_user_id());if(!is_array($test))return '';
        if(!empty($test['error']))return '<div class="notice notice-error inline"><p>'.esc_html((string)$test['error']).'</p></div>';
        $r=$test['result'] ?? null;if(!is_array($r))return '';
        $html='<div class="w27a-testresult"><div class="w27a-answer"><b>'.esc_html((string)($r['operator_name'] ?? get_bloginfo('name'))).'</b><p>'.nl2br(esc_html((string)($r['answer'] ?? ''))).'</p></div><div class="w27a-meta">'.esc_html__("Mode:", 'web27-site-search').' <code>'.esc_html((string)($r['mode'] ?? '')).'</code> · '.esc_html__("Sources:", 'web27-site-search').' '.(int)($r['result_count'] ?? 0).' · '.(int)($r['latency_ms'] ?? 0).' '.esc_html__('ms', 'web27-site-search');
        if(!empty($r['gateway'])&&is_array($r['gateway'])){$html.=' · '.esc_html((string)($r['gateway']['model'] ?? '')).' · '.esc_html(sprintf( /* translators: 1: input tokens, 2: output tokens. */ __('Input tokens: %1$d / output tokens: %2$d', 'web27-site-search'),(int)($r['gateway']['input_tokens'] ?? 0),(int)($r['gateway']['output_tokens'] ?? 0)));}if(!empty($r['error'])){$html.=' · '.esc_html__("Error:", 'web27-site-search').' <code>'.esc_html((string)$r['error']).'</code>';}$html.='</div>';
        foreach((array)($r['sources'] ?? []) as $s){if(!is_array($s))continue;$html.='<a class="w27a-source" target="_blank" rel="noopener" href="'.esc_url((string)($s['url'] ?? '')).'"><b>'.esc_html((string)($s['title'] ?? '')).'</b><span>'.esc_html(mb_substr((string)($s['excerpt'] ?? ''),0,260)).'</span></a>';}$html.='</div>';return $html;
    }

    private function checkbox(string $name,string $label,bool $checked,string $desc=''): void
    {
        echo '<label class="w27a-check"><input type="hidden" name="settings['.esc_attr($name).']" value="0"><input type="checkbox" name="settings['.esc_attr($name).']" value="1" '.checked($checked,true,false).'><span><b>'.esc_html($label).'</b>'.($desc!==''?'<small>'.esc_html($desc).'</small>':'').'</span></label>';
    }

    private function actionButton(string $action,string $label,string $class,string $tab,bool $confirm=false): void
    {
        $nonce='w27studio_'.$action;
        echo '<form class="w27a-actionform" method="post" action="'.esc_url(admin_url('admin-post.php')).'" '.($confirm?'onsubmit="return confirm('.esc_attr(wp_json_encode(__("Are you sure?", 'web27-site-search'))).' )"':'').'><input type="hidden" name="action" value="w27studio_'.esc_attr($action).'"><input type="hidden" name="return_tab" value="'.esc_attr($tab).'">'.wp_nonce_field($nonce,'_wpnonce',true,false).'<button type="submit" class="button '.($class==='primary'?'button-primary':($class==='delete'?'button-link-delete':'button-secondary')).'">'.esc_html($label).'</button></form>';
    }

    private function card(string $label,string $value,string $meta): void { echo '<div class="w27a-card"><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong><small>'.esc_html($meta).'</small></div>'; }

    private function notice(): void
    {
        if(!empty($_GET['saved']))echo '<div class="notice notice-success inline"><p>'.esc_html__('Settings saved.', 'web27-site-search').'</p></div>';
        if(!empty($_GET['queued']))echo '<div class="notice notice-success inline"><p>'.esc_html__('Sitemap loaded and indexing queue started.', 'web27-site-search').'</p></div>';
        if(!empty($_GET['processed']))echo '<div class="notice notice-success inline"><p>'.esc_html__('One queue batch processed.', 'web27-site-search').'</p></div>';
        if(!empty($_GET['wp_synced']))echo '<div class="notice notice-success inline"><p>'.esc_html__('Standard WordPress content synchronized.', 'web27-site-search').'</p></div>';
        if(!empty($_GET['cleared']))echo '<div class="notice notice-success inline"><p>'.esc_html__('Data cleared.', 'web27-site-search').'</p></div>';
        if(!empty($_GET['error'])){$e=get_transient('w27studio_admin_error_'.get_current_user_id());echo '<div class="notice notice-error inline"><p>'.esc_html__('Operation failed', 'web27-site-search').''.($e?': '.esc_html((string)$e):'.').'</p></div>';}
    }

    private function guard(): void { if(!current_user_can('manage_options'))wp_die(esc_html__("Access denied.", 'web27-site-search'),'', ['response'=>403]); }
    private function guardPost(string $nonce): void { $this->guard();if(!Settings::isPostRequest()){wp_die(esc_html__('Access denied.', 'web27-site-search'),'', ['response'=>405]);}check_admin_referer($nonce); }
     
    private function redirect(string $tab,array $args=[]): never { wp_safe_redirect(add_query_arg(array_merge(['page'=>self::PAGE,'tab'=>$tab],$args),admin_url('admin.php')));exit; }

    private function adminCss(): string
    {
        return '.w27a{max-width:1320px}.w27a-title{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;margin:22px 0 18px}.w27a-title h1{font-size:32px;font-weight:760;letter-spacing:-.035em;margin:4px 0}.w27a-title p{font-size:14px;color:#667085;margin:0}.w27a-eyebrow{font-size:10px;letter-spacing:.18em;color:#2271b1;text-transform:uppercase}.w27a-badge{border:1px solid #c9d9e8;background:#fff;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:700}.w27a-tabs{margin-bottom:18px}.w27a-body{padding-bottom:40px}.w27a-grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0}.w27a-card,.w27a-box{background:#fff;border:1px solid #dcdcde;border-radius:14px;box-shadow:0 1px 2px rgba(16,24,40,.03)}.w27a-card{padding:18px}.w27a-card span{font-size:12px;color:#667085}.w27a-card strong{display:block;font-size:30px;line-height:1.1;margin:8px 0;font-weight:750;letter-spacing:-.04em}.w27a-card small{font-size:11px;color:#8a94a3}.w27a-box{padding:22px;margin:16px 0}.w27a-box.compact{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.w27a-box h2{margin:0 0 12px;font-size:20px;letter-spacing:-.02em}.w27a-lead{font-size:14px;line-height:1.6;color:#475467;max-width:900px}.w27a-columns{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}.w27a-kv{width:100%;border-collapse:collapse}.w27a-kv th,.w27a-kv td{border-top:1px solid #eee;padding:11px 0;text-align:left;vertical-align:top}.w27a-kv tr:first-child th,.w27a-kv tr:first-child td{border-top:0}.w27a-kv th{width:180px;font-size:12px;color:#667085}.w27a-kv td strong{display:block;font-size:13px}.w27a-kv td span{display:block;font-size:11px;color:#8a94a3;margin-top:3px}.w27a-formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;margin:18px 0}.w27a-formgrid label,.w27a-full{display:block}.w27a-formgrid label>span,.w27a-full>span,.w27a-inline>span{display:block;font-size:12px;font-weight:700;margin-bottom:6px}.w27a-formgrid input,.w27a-formgrid select,.w27a-full textarea{width:100%;max-width:none}.w27a-formgrid small,.w27a-full small{display:block;color:#7a8494;margin-top:5px}.w27a-formgrid .span2{grid-column:span 2}.w27a-check{display:flex;align-items:flex-start;gap:10px;margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:10px}.w27a-check input[type=checkbox]{margin-top:2px}.w27a-check b{display:block}.w27a-check small{display:block;font-size:11px;color:#7a8494;margin-top:2px}.w27a-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.w27a-actionform{display:inline-block;margin:0}.w27a-progress{height:10px;background:#edf2f7;border-radius:99px;overflow:hidden;margin:18px 0 8px}.w27a-progress i{display:block;height:100%;background:#2271b1}.w27a-note,.w27a-meta{font-size:11px;color:#667085}.w27a-test{display:grid;grid-template-columns:1fr auto;gap:8px;margin:12px 0}.w27a-test input{min-width:0;width:100%}.w27a-answer{border:1px solid #d7e7f5;background:#f7fbff;border-radius:10px;padding:14px;margin-top:14px}.w27a-answer p{font-size:14px;line-height:1.6;margin-bottom:0}.w27a-source{display:block;text-decoration:none;border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-top:8px}.w27a-source b,.w27a-source span{display:block}.w27a-source span{font-size:11px;color:#667085;margin-top:4px}.w27a-tablewrap{overflow:auto}.w27a-tablewrap .query{min-width:280px;max-width:520px}.w27a-inline{display:flex;gap:8px;align-items:center}.w27a-inline>span{margin:0}.w27a-inline input{width:90px}.w27a-box.danger{border-color:#f3c9c9}@media(max-width:980px){.w27a-grid4{grid-template-columns:repeat(2,1fr)}.w27a-columns{grid-template-columns:1fr}}@media(max-width:640px){.w27a-grid4{grid-template-columns:1fr}.w27a-formgrid{grid-template-columns:1fr}.w27a-formgrid .span2{grid-column:span 1}.w27a-title{align-items:flex-start;flex-direction:column}.w27a-test{grid-template-columns:1fr}}';
    }
}
