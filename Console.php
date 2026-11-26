<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;
function provider_catalog_cache(array $o): array {
    $data=get_transient('w27ss_catalog_'.get_current_user_id());
    return is_array($data) && hash_equals(Settings::profileSignature($o),(string)($data['signature']??'')) ? $data : [];
}
function provider_model_list(array $o,string $suffix): array {
    $base=Settings::base($o);
    if ($base==='' || empty($o['api_key'])) { throw new \RuntimeException('gateway_not_configured'); }
    $res=wp_safe_remote_get($base.$suffix,['timeout'=>15,'redirection'=>0,'limit_response_size'=>4*1024*1024,
        'headers'=>['Authorization'=>'Bearer '.$o['api_key'],'Accept'=>'application/json']]);
    if (is_wp_error($res)) { throw new \RuntimeException('gateway_request_failed'); }
    $status=(int)wp_remote_retrieve_response_code($res);
    if ($status!==200) { throw new \RuntimeException('gateway_http_'.$status); }
    $body=json_decode((string)wp_remote_retrieve_body($res),true);
    if (!is_array($body) || !is_array($body['data']??null)) { throw new \RuntimeException('gateway_invalid_json'); }
    $ids=[];
    foreach ($body['data'] as $item) {
        if (is_array($item) && is_string($item['id']??null) && preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:/@+-]{0,199}$~D',$item['id'])) { $ids[]=$item['id']; }
    }
    $ids=array_values(array_unique($ids)); sort($ids,SORT_STRING);
    return array_slice($ids,0,2000);
}
function provider_catalog(array $o): array {
    $ids=provider_model_list($o,'/models');
    if ($o['provider']==='openrouter') {
        $embed=[]; $warning='';
        try { $embed=provider_model_list($o,'/embeddings/models'); } catch (\RuntimeException $e) { $warning=$e->getMessage(); }
        return ['chat'=>$ids,'embed'=>$embed,'warning'=>$warning];
    }
    return ['chat'=>array_values(array_filter($ids,static fn($m)=>!str_contains(strtolower($m),'embedding'))),
        'embed'=>array_values(array_filter($ids,static fn($m)=>str_contains(strtolower($m),'embed'))),'warning'=>''];
}
function provider_test_status(array $o,string $kind): string {
    $result=get_transient('w27ss_provider_'.$kind.'_'.get_current_user_id());
    return is_array($result) && hash_equals(Settings::profileSignature($o),(string)($result['signature']??'')) ? (string)$result['status'] : '';
}
function provider_test_save(array $o,string $kind,string $status): void {
    set_transient('w27ss_provider_'.$kind.'_'.get_current_user_id(),['signature'=>Settings::profileSignature($o),'status'=>$status],300);
}
function provider_error_label(string $code): string {
    return match(true) {
        str_contains($code,'gateway_http_401')=>__('Authentication failed (401). Verify the selected provider key.', 'web27-site-search'),
        str_contains($code,'gateway_http_403')=>__('Access denied (403). Verify model permission, region and provider rules.', 'web27-site-search'),
        str_contains($code,'gateway_http_429')=>__('Rate limit or quota exceeded (429). Check the provider account.', 'web27-site-search'),
        str_contains($code,'gateway_http_402')=>__('Payment required (402). Check the provider balance.', 'web27-site-search'),
        str_contains($code,'gateway_http_404')=>__('Endpoint or model not found (404). Verify the model and protocol.', 'web27-site-search'),
        str_contains($code,'gateway_http_400')=>__('Invalid request (400). Verify model and parameter compatibility.', 'web27-site-search'),
        str_contains($code,'gateway_http_5')=>__('Provider server error. Try again later.', 'web27-site-search'),
        str_contains($code,'gateway_request_failed')=>__('Network request failed. Check DNS, TLS and server connectivity.', 'web27-site-search'),
        str_contains($code,'gateway_not_configured') || str_contains($code,'embedding_not_configured')=>__('Save a valid endpoint, model and key first.', 'web27-site-search'),
        default=>__('The provider returned an unexpected or incomplete response. Check its configuration.', 'web27-site-search'),
    };
}
function console_action(): void {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'web27-site-search'),'', ['response'=>403]); }
    if (!Settings::isPostRequest()) { wp_die(esc_html__('Access denied.', 'web27-site-search'),'', ['response'=>405]); }
    check_admin_referer('w27ss_console');
    $act=isset($_POST['task']) && is_string($_POST['task']) ? sanitize_key(wp_unslash($_POST['task'])) : '';
    $tab='knowledge';
    if ($act==='test_chat') {
        $tab='ai';
        $o=Settings::get();
        $status=__('Save the key, model and permission for AI answers.', 'web27-site-search');
        if (Settings::gatewayConfigured()) {
            $status=__('The daily attempt limit has been reached.', 'web27-site-search');
            if (reserve_chat_call()) {
                try {
                    $result=(new Gateway())->answer(__('What is the name of the test material?', 'web27-site-search'),[['title'=>__('Connection test', 'web27-site-search'),'excerpt'=>__('Material name: Connection test.', 'web27-site-search')]],[]);
                    $status=sprintf( /* translators: 1: model name, 2: answer excerpt. */ __('Response from %1$s: %2$s', 'web27-site-search'),Settings::get()['model'],wp_trim_words($result['answer'],24));
                } catch (\RuntimeException $error) { $status=provider_error_label($error->getMessage()); }
            }
        }
        provider_test_save($o,'chat',$status);
    }
    if ($act==='test_embed') {
        $tab='ai'; $o=Settings::get();
        try {
             
            $v=Knowledge::embed(['WEB27 Site Search connection test.'],$o);
            $status=sprintf( /* translators: %d: vector dimension. */ __('Embedding test passed: %d dimensions.', 'web27-site-search'),count($v[0]));
        } catch (\RuntimeException $e) { $status=provider_error_label($e->getMessage()); }
        provider_test_save($o,'embed',$status);
    }
    if ($act==='load_models') {
        $tab='ai'; $o=Settings::get();
        delete_transient('w27ss_catalog_'.get_current_user_id());
        try {
            $catalog=provider_catalog($o);
            set_transient('w27ss_catalog_'.get_current_user_id(),$catalog+['signature'=>Settings::profileSignature($o)],600);
            $status=sprintf( /* translators: %d: number of model identifiers. */ __('Model list loaded: %d identifiers. Choose or enter a compatible model manually.', 'web27-site-search'),count($catalog['chat'])+count($catalog['embed']));
            if ($catalog['warning']!=='') { $status.=' '.provider_error_label($catalog['warning']); }
        } catch (\RuntimeException $e) { $status=provider_error_label($e->getMessage()); }
        provider_test_save($o,'catalog',$status);
    }
    if ($act==='rebuild') { update_option('w27ss_cursor',0,false); }
    if (in_array($act,['rebuild','batch'],true)) { Knowledge::tick(); }
    if ($act==='pin' || $act==='unpin') {
        $tab='quality';
        $question=isset($_POST['question']) && is_string($_POST['question']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['question'])),0,500) : '';
        $id=isset($_POST['chunk']) && is_scalar($_POST['chunk']) ? absint(wp_unslash($_POST['chunk'])) : 0;
        global $wpdb;
        if ($act==='unpin') { delete_option(Knowledge::pinKey($question)); }
        elseif ($question && Knowledge::publicPost(get_post((int)$wpdb->get_var($wpdb->prepare('SELECT post_id FROM %i WHERE id=%d',Knowledge::table(),$id))))) {
            update_option(Knowledge::pinKey($question),$id,false);
        } else { wp_die(esc_html__('Enter a question and the ID of an existing public passage.', 'web27-site-search'),'', ['response'=>400]); }
    }
    wp_safe_redirect(add_query_arg(['page'=>'web27-site-search','tab'=>$tab==='knowledge'?'vectors':$tab],admin_url('admin.php'))); exit;
}
function console_form(): void {
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="w27ss_console">';
    wp_nonce_field('w27ss_console');
}
function public_tools(string $tab): void {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    echo '<div>';
    if ($tab==='settings') {

        echo '<h2>'.esc_html__('Connection test', 'web27-site-search').'</h2><p>'.esc_html__('After saving settings, send one test request using your key. The attempt counts toward the daily limit.', 'web27-site-search').'</p>';
        console_form(); echo '<button class="button" name="task" value="test_chat">'.esc_html__('Test answer model', 'web27-site-search').'</button></form>';
        $o=Settings::get();
        echo '<p>'.esc_html(provider_test_status($o,'chat')).'</p>';
        console_form(); echo '<button class="button" name="task" value="test_embed">'.esc_html__('Test embedding model', 'web27-site-search').'</button></form>';
        echo '<p>'.esc_html__('The embedding test sends one synthetic sentence and may incur a charge. It does not write vectors or enable background indexing.', 'web27-site-search').'</p><p>'.esc_html(provider_test_status($o,'embed')).'</p>';
        console_form(); echo '<button class="button" name="task" value="load_models">'.esc_html__('Load model list', 'web27-site-search').'</button></form>';
        echo '<p>'.esc_html__('Save the key before loading models. Catalogs may include unsupported models; manual entry and connection tests remain available. No visitor content is sent for the catalog.', 'web27-site-search').'</p><p>'.esc_html(provider_test_status($o,'catalog')).'</p>';
    }
    elseif ($tab==='quality') {
        echo '<h2>'.esc_html__('Questions within the retention period', 'web27-site-search').'</h2><p>'.esc_html__('Enable logging in settings. Filters help investigate questions with no results and negative ratings.', 'web27-site-search').'</p>';
        $filter=isset($_GET['filter']) && is_string($_GET['filter']) ? sanitize_key(wp_unslash($_GET['filter'])) : 'all';
        foreach (['all'=>__('All questions', 'web27-site-search'),'miss'=>__('No results', 'web27-site-search'),'negative'=>__('Not helpful', 'web27-site-search')] as $key=>$label) {
            echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'web27-site-search','tab'=>'quality','filter'=>$key],admin_url('admin.php'))).'">'.esc_html($label).'</a> ';
        }
        $rows=match($filter){
            'miss'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE mode=%s ORDER BY id DESC LIMIT 100',Knowledge::log(),'empty')),
            'negative'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE vote=%d ORDER BY id DESC LIMIT 100',Knowledge::log(),-1)),
            default=>$wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT 100',Knowledge::log())),
        };
        echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Question', 'web27-site-search').'</th><th>'.esc_html__('Passage', 'web27-site-search').'</th><th>'.esc_html__('Mode', 'web27-site-search').'</th><th>'.esc_html__('Rating', 'web27-site-search').'</th></tr></thead><tbody>';
        foreach ($rows as $r) { echo '<tr><td>'.esc_html($r->question).'</td><td>'.intval($r->first_source).'</td><td>'.esc_html($r->mode).'</td><td>'.esc_html(['-1'=>__('Not helpful', 'web27-site-search'),'0'=>'—','1'=>__('Helpful', 'web27-site-search')][$r->vote]).'</td></tr>'; }
        echo '</tbody></table><h2>'.esc_html__('Correct an answer', 'web27-site-search').'</h2><p>'.esc_html__('Find suitable text under Passages and vectors. Pinning ranks it first for the specified question.', 'web27-site-search').'</p>';
        console_form();
        echo '<label>'.esc_html__('Question', 'web27-site-search').' <input name="question" class="regular-text" required maxlength="500"></label> <label>'.esc_html__('Passage ID', 'web27-site-search').' <input name="chunk" type="number" min="1"></label> <button class="button" name="task" value="pin">'.esc_html__('Pin', 'web27-site-search').'</button> <button class="button" name="task" value="unpin">'.esc_html__('Unpin', 'web27-site-search').'</button></form>';
    } elseif ($tab==='test') {
        search_form($tab,'sample',__('Test question', 'web27-site-search'));
        echo '<p>'.esc_html__('This passage preview uses local text matching only. Use the full query test for AI and semantic search.', 'web27-site-search').'</p>';
        $q=isset($_GET['sample']) && is_string($_GET['sample']) ? mb_substr(sanitize_text_field(wp_unslash($_GET['sample'])),0,500) : '';
        if ($q!=='') {
            $hits=sources($q,Settings::get()['types'],false);
            if (!$hits) { echo '<p>'.esc_html__('No matching results for this query.', 'web27-site-search').'</p>'; }
            foreach ($hits as $hit) {
                echo '<p><strong>'.esc_html($hit['title']).'</strong> · № '.intval($hit['chunk']??0).esc_html__(' · score ', 'web27-site-search').esc_html($hit['score']).esc_html__(' · similarity ', 'web27-site-search').esc_html($hit['similarity']??0).'</p><blockquote>'.esc_html($hit['excerpt']).'</blockquote>';
            }
        }
    } else {
        $o=Settings::get();
        $stats=$wpdb->get_row($wpdb->prepare('SELECT COUNT(DISTINCT post_id) docs, COUNT(*) chunks, SUM(CASE WHEN model=%s THEN 1 ELSE 0 END) vectors FROM %i',Settings::vectorModel($o),Knowledge::table()));
        echo '<h2>'.esc_html__('Index status', 'web27-site-search').'</h2><p>'.esc_html__('Documents:', 'web27-site-search').' '.intval($stats->docs).esc_html__(' · Passages: ', 'web27-site-search').intval($stats->chunks).esc_html__(' · Vectors for selected model: ', 'web27-site-search').intval($stats->vectors).'</p>';
        echo '<p>'.esc_html__('Text indexing:', 'web27-site-search').' '.esc_html((int)get_option('w27ss_cursor')<0?__('complete', 'web27-site-search'):__('queued', 'web27-site-search')).esc_html__('. Semantic search: ', 'web27-site-search').esc_html(Settings::embeddingsConfigured($o)?__('enabled', 'web27-site-search'):__('disabled', 'web27-site-search')).''.esc_html__('. Background batches run through WP-Cron.', 'web27-site-search').'</p>';
        if ($error=get_option('w27ss_error')) { echo '<p>'.esc_html($error).'</p>'; }
        console_form(); echo '<button class="button button-primary" name="task" value="batch">'.esc_html__('Process next batch', 'web27-site-search').'</button> <button class="button" name="task" value="rebuild">'.esc_html__('Recheck all pages', 'web27-site-search').'</button></form>';
        search_form('knowledge','fragment',__('Find passage by text', 'web27-site-search'));
        $needle=isset($_GET['fragment']) && is_string($_GET['fragment']) ? mb_substr(sanitize_text_field(wp_unslash($_GET['fragment'])),0,500) : '';
        $rows=$wpdb->get_results($wpdb->prepare('SELECT id,post_id,body,model FROM %i WHERE body LIKE %s ORDER BY id DESC LIMIT 30',Knowledge::table(),'%'.$wpdb->esc_like($needle).'%'));
        foreach ($rows as $r) { echo '<p><strong>№ '.intval($r->id).' · '.esc_html(get_the_title($r->post_id)).'</strong> · '.esc_html($r->model===Settings::vectorModel($o)?$o['embed_model']:($r->model?__('Vector from another profile or model', 'web27-site-search'):__('text only', 'web27-site-search'))).'<br>'.esc_html($r->body).'</p>'; }
    }
    echo '</div>';
}
function search_form(string $tab,string $field,string $label): void {
    echo '<form method="get"><input type="hidden" name="page" value="web27-site-search"><input type="hidden" name="tab" value="'.esc_attr($tab==='knowledge'?'vectors':$tab).'"><p><label>'.esc_html($label).' <input class="regular-text" name="'.esc_attr($field).'" value="'.esc_attr(isset($_GET[$field]) && is_string($_GET[$field]) ? mb_substr(sanitize_text_field(wp_unslash($_GET[$field])),0,500) : '').'"></label> <button class="button">'.esc_html__('Search', 'web27-site-search').'</button></p></form>';
}
