<?php
namespace WEB27\SiteSearch;
defined( 'ABSPATH' ) || exit;

function sources(string $query,array $types,bool $allowSemantic=true): array {
    
    $queryVector=null;
    $ranked=Knowledge::rank($query,$types,$queryVector,$allowSemantic);
    $o=Settings::get();
    if ($o['index_virtual_pages']) {
        $ranked=array_merge($ranked,SearchEngine::virtualSources($query));
        usort($ranked,static fn($a,$b)=>$b['score']<=>$a['score']);
    }
    if(class_exists(Universal::class)){
        $ranked=array_merge($ranked,Universal::rank($query,$o,$queryVector,$allowSemantic));
        usort($ranked,static fn($a,$b)=>$b['score']<=>$a['score']);
    }
    $ranked=array_values(array_filter($ranked,static fn($hit)=>Settings::sameSiteUrl((string)($hit['url']??''))));
    $remaining=(int)$o['rag_context_chars']; $limited=[];
    foreach(array_slice($ranked,0,(int)$o['rag_limit']) as $hit){
        if($remaining<=0){break;}
        $hit['excerpt']=wp_html_excerpt($hit['excerpt'],$remaining,'');
        $remaining-=mb_strlen($hit['excerpt']); $limited[]=$hit;
    }
    $ranked=$limited;
    if(!$types){return $ranked;}
    if ($ranked || (int)get_option('w27ss_cursor')<0) { return $ranked; }
    $args=['post_type'=>$types,'post_status'=>'publish','has_password'=>false,'posts_per_page'=>5,
        'ignore_sticky_posts'=>true,'no_found_rows'=>true,'s'=>$query];
    $results=new \WP_Query($args);
    if (!$results->posts) {
        $words=preg_split('/[^\p{L}\p{N}]+/u',$query,-1,PREG_SPLIT_NO_EMPTY);
        $words=array_filter($words,static fn($w)=>strlen($w)>=4 && !in_array(mb_strtolower($w),['what','where','does','сколько','какая','можно','найти','какой','стоит','есть'],true));
        usort($words,static fn($a,$b)=>strlen($b)<=>strlen($a));
        foreach (array_slice($words,0,3) as $word) {
            $results=new \WP_Query(array_merge($args,['s'=>$word]));
            if ($results->posts) { break; }
        }
    }
    $sources=[]; $remaining=(int)$o['rag_context_chars'];
    foreach ($results->posts as $post) {
        if ($remaining<=0 || count($sources)>=(int)$o['rag_limit']) { break; }
        if (!Knowledge::publicPost($post) || !Settings::sameSiteUrl((string)get_permalink($post))) { continue; }
        $plain=wp_strip_all_tags(strip_shortcodes($post->post_excerpt ?: $post->post_content),true);
        $excerpt=wp_html_excerpt(wp_trim_words($plain,90),$remaining,'');
        $remaining-=mb_strlen($excerpt);
        $sources[]=['title'=>wp_strip_all_tags(get_the_title($post)),'url'=>esc_url_raw(get_permalink($post)),
            'excerpt'=>$excerpt,'score'=>1];
    }
    return $sources;
}

function query(\WP_REST_Request $request, bool $test=false) {
    if(class_exists(Universal::class)){Universal::reset();}
    $o=Settings::get();
    $started=microtime(true); $result=[]; $problem='';
    if (!$test && !$o['enabled']) { return new \WP_Error('disabled',__("Search is disabled.", 'web27-site-search'),['status'=>404]); }
    $q=trim((string)$request['query']);
    if (mb_strlen($q)>500) { return new \WP_Error('long_query',__("The query is too long.", 'web27-site-search'),['status'=>400]); }
    if (mb_strlen($q)<(int)$o['min_query_chars']) { return new \WP_Error('short_query',__("Enter a search query.", 'web27-site-search'),['status'=>400]); }
    $remote=isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $bucket='w27public_'.substr(wp_hash($remote),0,24);
    $count=(int)get_transient($bucket);
    if (!$test && $count>=(int)$o['rate_limit']) { return new \WP_Error('limit',__("Too many requests. Please try again later.", 'web27-site-search'),['status'=>429]); }
    if(!$test){set_transient($bucket,$count+1,10*MINUTE_IN_SECONDS);}
    $previous=sanitize_text_field((string)$request->get_param('previous'));
    $memory=new Conversation((string)$request->get_param('instance')); $history=[];
    if (!$test && $o['history_enabled']){
        $memory->ensureSession();
        if($request->get_param('new_dialog')){$memory->clear();$previous='';}
        $history=$memory->history();
        if(!$previous){foreach(array_reverse($history) as $turn){if($turn['role']==='user' && !Language::isFollowup($turn['content'])){$previous=$turn['content']; break;}}}
    } else {$previous='';}
    $language=Language::answerLanguage($q,$history,Language::pageLocale($request));
    $followup=$previous!=='' && Language::isFollowup($q);
    $context=$followup ? wp_html_excerpt($previous,500,'').' '.$q : $q;
    $intent=Welcome::intent($q);
    $sources=$intent==='' ? sources($context,$o['types']) : [];
    $answer=$sources ? ($sources[0]['excerpt'] ?: $sources[0]['title']) : Language::phrase($language,'I could not confirm an answer to your question. What would you like to know?','Пока не могу подтвердить ответ на ваш вопрос. Что именно вы хотите узнать?');
    $mode=$sources?'local':'empty';
    if ($sources && Language::answerLanguage($answer,[],'en_US')!==$language) {
        $answer=Language::phrase($language,'Here is a relevant excerpt in its original language:','Вот подходящий фрагмент на языке оригинала:')."\n".$answer;
    }
    $actions=[];
    if($intent!=='' || !$sources){
        [$name,$summary,$navigation]=Welcome::profile($o['types']);
        if($intent==='thanks'){
            $answer=Language::phrase($language,'You’re welcome! Ask if you need to find anything else.','Пожалуйста! Если понадобится ещё что-нибудь найти, спрашивайте.');
        } elseif($intent!==''){
            $answer=($intent==='hello'?Language::phrase($language,'Hello! ','Здравствуйте! '):'').sprintf(Language::phrase($language,'Welcome to %s.','Добро пожаловать в «%s».'),$name);
            if($summary!==''){$answer.=' '.rtrim($summary,'.').'.';}
            $answer.=$navigation?Language::phrase($language,' You can explore: ',' У нас можно открыть: ').implode(', ',array_column($navigation,'title')).'.':Language::phrase($language,' What are you interested in?',' Что вас интересует?');
            $sources=$navigation;
            if(class_exists(Universal::class) && Universal::profile(true)){
                $groups=Universal::profile(true);$examples=[];$categories=[];
                foreach($groups as $g){$examples=array_merge($examples,$g['examples']);$categories=array_merge($categories,$g['categories']);}
                if($categories){$answer.=Language::phrase($language,' Categories include: ',' Категории: ').implode(', ',array_slice(array_unique($categories),0,20)).'.';}
                if($examples){$answer.=Language::phrase($language,' Examples: ',' Например: ').implode(', ',array_slice(array_unique($examples),0,30)).'.';}
            }
        }
        if($intent!=='thanks'){
            foreach($navigation as $item){$actions[]=['type'=>'link','url'=>$item['url'],'label'=>$item['title']];}
        }
        if($intent!==''){$mode='welcome';}
    }
    if ($intent!=='thanks' && Settings::gatewayConfigured() && reserve_chat_call()) {
        $profile=($intent!=='' || !$sources) ? ['name'=>$name,'description'=>$summary,'sections'=>array_column($navigation,'title')] : [];
        if(class_exists(Universal::class) && ($catalog=Universal::profile($intent!=='' || !$sources))){$profile['connected_public_sources']=$catalog;}
        try {
            $result=(new Gateway())->answer($context,$sources,$history,$memory->sessionHash(),['language'=>$language,'question'=>$q,'profile'=>$profile]);
            $answer=$result['answer']; $mode='external_ai';
        } catch (\RuntimeException $error) {
            $mode='local_fallback'; $problem=$error->getMessage();
        }
    }
    if(!$test && $o['history_enabled']){$memory->append($q,$answer);}
    $ms=(int)round((microtime(true)-$started)*1000);
    (new Logger())->write(['query'=>$q,'answer'=>$answer,'request_id'=>wp_generate_uuid4(),'mode'=>$mode,'result_count'=>count($sources),'latency_ms'=>$ms,'model'=>$result['model']??'','input_tokens'=>$result['input_tokens']??0,'output_tokens'=>$result['output_tokens']??0,'error_code'=>$problem]);
    $voteToken='';
    if (!$test && $o['logging']) {
        global $wpdb;
        if ($wpdb->insert(Knowledge::log(),['question'=>$q,'mode'=>$mode,'first_source'=>(int)($sources[0]['chunk']??0),'created'=>current_time('mysql',true)])) {
            $id=(int)$wpdb->insert_id;
            $voteToken=$id.'.'.wp_hash('w27ss-vote-'.$id);
        }
    }
    foreach($sources as &$source){
        $image=Settings::sourceImage($source['image']??'');
        if($image===''&&function_exists('get_the_post_thumbnail_url')){
            $id=(int)($source['post_id']??url_to_postid((string)($source['url']??'')));
            if($id&&Knowledge::publicPost(get_post($id))){$image=Settings::sourceImage(get_the_post_thumbnail_url($id,'medium'));}
        }
        if($image!==''){$source['image']=$image;}else{unset($source['image']);}
    }unset($source);
    $response=new \WP_REST_Response(['actions'=>$actions,'vote_token'=>$voteToken,'topic'=>$followup?$previous:$q,'answer'=>$answer,'operator_name'=>$o['operator_name'],'mode'=>$mode,'sources'=>$sources]);
    if($test){$response->set_data(array_merge($response->get_data(),['result_count'=>count($sources),'latency_ms'=>$ms,'gateway'=>$result,'error'=>$problem]));}
    $response->header('Cache-Control','no-store');
    return $response;
}

 
function reserve_chat_call(): bool {
    global $wpdb;
    if((int)Settings::get()['daily_calls']===0){return true;}
    $day='w27ss_calls_'.gmdate('Ymd');
    add_option($day,'0','','no');
    $changed=$wpdb->query($wpdb->prepare(
        'UPDATE %i SET option_value=CAST(option_value AS UNSIGNED)+1 WHERE option_name=%s AND CAST(option_value AS UNSIGNED)<%d',
        $wpdb->options,$day,(int)Settings::get()['daily_calls']
    ));
    wp_cache_delete($day,'options');
    return $changed===1;
}
