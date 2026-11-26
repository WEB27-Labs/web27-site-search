<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Universal {
    const OPTION='w27ss_universal_sources';
    const SCHEMA='w27ss_universal_schema';
    const LIMIT=150;
    const TOTAL=600;
    private static ?array $snapshot=null;
    private static array $status=[];

    public static function reset(): void { self::$snapshot=null; self::$status=[]; }
    public static function config(): array {
        $o=get_option(self::OPTION,[]);$o=is_array($o)?$o:[];
        return ['enabled'=>array_values(array_filter((array)($o['enabled']??[]),'is_string')),'semantic'=>!empty($o['semantic'])];
    }
    public static function table(): string { global $wpdb; return $wpdb->prefix.'w27ss_source_vectors'; }
    public static function install(): void {
        if(get_option(self::SCHEMA)==='1'){return;}
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table(); $collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
 fingerprint char(64) NOT NULL,
 model char(64) NOT NULL,
 vector longtext NOT NULL,
 updated datetime NOT NULL,
 PRIMARY KEY  (fingerprint,model),
 KEY updated (updated)
 ) $collate;");
        if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table){update_option(self::SCHEMA,'1',false);}
    }
    public static function definitions(): array {
        $out=[];
        foreach(get_post_types(['public'=>true],'objects') as $type=>$obj){
            if(in_array($type,['post','page','attachment'],true)||!is_post_type_viewable($obj)){continue;}
            $out['post:'.$type]=['label'=>(string)$obj->label,'items'=>static fn()=>self::posts($type),'search'=>static fn(string $q)=>self::posts($type,$q)];
        }
        foreach(get_taxonomies(['public'=>true],'objects') as $type=>$obj){
            if(!is_taxonomy_viewable($obj)){continue;}
            $out['term:'.$type]=['label'=>(string)$obj->label,'items'=>static fn()=>self::terms($type),'search'=>static fn(string $q)=>self::terms($type,$q)];
        }
        try {$external=apply_filters('web27_site_search_sources',[]);}catch(\Throwable $e){$external=[];}
        foreach(is_array($external)?$external:[] as $id=>$def){
            if(!is_string($id)||!preg_match('/^[a-z0-9][a-z0-9_-]{1,79}$/D',$id)||!is_array($def)||!is_string($def['label']??null)||!is_callable($def['items']??null)){continue;}
            $out['connector:'.$id]=['label'=>self::text($def['label'],120),'items'=>$def['items'],'search'=>is_callable($def['search']??null)?$def['search']:null];
        }
        return $out;
    }
    public static function text($v,int $max): string {
        if(!is_string($v)){return '';}
        return mb_substr(trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(strip_shortcodes($v),true))??''),0,$max);
    }
    private static function safeUrl(string $url): bool {
        if(strlen($url)>2048||!Settings::sameSiteUrl($url)||preg_match('/[\x00-\x20\x7f]/',$url)){return false;}
        $p=wp_parse_url($url); $path=rawurldecode((string)($p['path']??''));
        if(preg_match('~(?:^|/)(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:/|$)~i',$path)){return false;}
        parse_str((string)($p['query']??''),$params);
        foreach(array_keys($params) as $k){if(preg_match('/token|nonce|password|secret|auth|session|api.?key|preview|rest_route/i',(string)$k)){return false;}}
        return true;
    }
    private static function isPublicPost($p): bool {
        return $p instanceof \WP_Post && $p->post_status==='publish' && $p->post_password==='' && is_post_publicly_viewable($p) && !in_array($p->post_type,['attachment'],true);
    }
    private static function posts(string $type,string $query=''): iterable {
        $obj=get_post_type_object($type);
        if(!$obj||!$obj->public||!is_post_type_viewable($obj)){return;}
                $args=['post_type'=>$type,'post_status'=>'publish','has_password'=>false,'numberposts'=>self::LIMIT+1,'orderby'=>'ID','order'=>'DESC','suppress_filters'=>false];
        if($query!==''){$args['s']=self::searchTerm($query);$args['numberposts']=30;if($args['s']===''){return;}}
        $posts=get_posts($args);
        foreach($posts as $p){
            if(!self::isPublicPost($p)||$p->post_type!==$type){continue;}
            $tags=[];
            foreach(get_object_taxonomies($type,'objects') as $tax){
                if(!$tax->public||!is_taxonomy_viewable($tax)){continue;}
                $terms=get_the_terms($p->ID,$tax->name);
                if(is_array($terms)){foreach($terms as $term){if(!self::placeholderTerm($tax->name,$term)){$tags[]=(string)$term->name;}}}
            }
            yield ['id'=>(string)$p->ID,'public'=>true,'title'=>$p->post_title,'url'=>get_permalink($p),'text'=>self::text($p->post_excerpt,500).' '.self::text($p->post_content,2800),'tags'=>$tags,'image'=>function_exists('get_the_post_thumbnail_url')?get_the_post_thumbnail_url($p->ID,'medium'): ''];
        }
    }
    private static function placeholderTerm(string $type,$term): bool {
        if($type!=='category'||(int)($term->term_id??0)!==(int)get_option('default_category',0)||trim((string)($term->description??''))!==''){return false;}
        $name=mb_strtolower(trim((string)($term->name??'')));
        return $name==='uncategorized'||$name===(function_exists('translate')?mb_strtolower(translate('Uncategorized')):'uncategorized');
    }
    private static function terms(string $type,string $query=''): iterable {
        $tax=get_taxonomy($type);
        if(!$tax||!$tax->public||!is_taxonomy_viewable($tax)){return;}
                $args=['taxonomy'=>$type,'hide_empty'=>true,'number'=>self::LIMIT+1,'orderby'=>'name','order'=>'ASC'];
        if($query!==''){$args['search']=self::searchTerm($query);$args['number']=30;if($args['search']===''){return;}}
        $terms=get_terms($args);
        if(!is_array($terms)){return;}
        foreach($terms as $term){
            if(self::placeholderTerm($type,$term)){continue;}
            $url=get_term_link($term);
            if(is_wp_error($url)){continue;}
            yield ['id'=>(string)$term->term_id,'public'=>true,'title'=>$term->name,'url'=>$url,'text'=>$term->description,'tags'=>[(string)$tax->label]];
        }
    }
    public static function normalize(string $source,$r): ?array {
        if(!is_array($r)||($r['public']??null)!==true||!is_string($r['id']??null)||$r['id']===''||strlen($r['id'])>191||!is_string($r['url']??null)||!self::safeUrl($r['url'])){return null;}
        $postId=url_to_postid($r['url']);
        if($postId&&!self::isPublicPost(get_post($postId))){return null;}
        if(!apply_filters('web27_site_search_public_object',true,$source,$r['id'],$r['url'])){return null;}
        $title=self::text($r['title']??'',180); if($title===''){return null;}
        $body=self::text($r['text']??'',3200);
        $tags=[]; foreach(array_slice((array)($r['tags']??[]),0,24) as $tag){$t=self::text($tag,100);if($t!==''){$tags[]=$t;}}
        $tags=array_values(array_unique($tags));
        $body=trim($title."\n".$body.($tags?"\n".implode(' · ',$tags):''));
        $body=mb_substr($body,0,4000);
        return ['source'=>$source,'id'=>$r['id'],'title'=>$title,'url'=>$r['url'],'excerpt'=>$body,'tags'=>$tags,'image'=>Settings::sourceImage($r['image']??''),'fingerprint'=>hash('sha256',wp_json_encode([$source,$r['id'],$r['url'],$body])),'score'=>0];
    }
    public static function snapshot(): array {
        if(self::$snapshot!==null){return self::$snapshot;}
        $enabled=self::config()['enabled']; $all=[]; self::$status=[];
        if(!$enabled){return self::$snapshot=[];}
        $user=get_current_user_id();
        if($user){wp_set_current_user(0);}
        try {
            $defs=self::definitions();
            foreach(array_slice(array_unique($enabled),0,12) as $id){
                $def=$defs[$id]??null;
                if(!$def){self::$status[$id]=['count'=>0,'state'=>'missing'];continue;}
                $local=[]; $seen=0; $state='ok';
                try {
                    $items=($def['items'])();
                    if(!is_iterable($items)){throw new \RuntimeException('invalid_source');}
                    foreach($items as $item){
                        if(++$seen>self::LIMIT||count($all)+count($local)>=self::TOTAL){$state='limited';break;}
                        $r=self::normalize($id,$item);
                        if($r){$local[$r['id']]=$r;}
                    }
                } catch(\Throwable $e){$local=[];$state='error';}
                self::$status[$id]=['count'=>count($local),'state'=>$state];
                array_push($all,...array_values($local));
            }
        } finally {if($user){wp_set_current_user($user);}}
        return self::$snapshot=$all;
    }
    public static function status(): array {self::snapshot();return self::$status;}
    public static function publicHit(array $r,float $score=0): array {return ['title'=>$r['title'],'url'=>$r['url'],'excerpt'=>$r['excerpt'],'score'=>$score,'image'=>$r['image']??''];}
    public static function overview(int $limit): array {
        $rows=self::snapshot();$out=[];$seen=[];
        foreach($rows as $r){if(isset($seen[$r['source']])){continue;}$seen[$r['source']]=true;$out[]=self::publicHit($r);if(count($out)>=$limit){return $out;}}
        foreach($rows as $r){if(in_array($r['url'],array_column($out,'url'),true)){continue;}$out[]=self::publicHit($r);if(count($out)>=$limit){break;}}
        return $out;
    }
    public static function profile(bool $details=false): array {
        $rows=self::snapshot();if(!$rows){return [];}
        $groups=[]; $remaining=4000;
        foreach($rows as $r){
            $s=$r['source'];
            if(!isset($groups[$s])){$groups[$s]=['sample_count'=>0,'categories'=>[],'examples'=>[]];}
            $groups[$s]['sample_count']++;
            foreach($r['tags'] as $tag){if($remaining<mb_strlen($tag)||count($groups[$s]['categories'])>=24||in_array($tag,$groups[$s]['categories'],true)){continue;}$groups[$s]['categories'][]=$tag;$remaining-=mb_strlen($tag);}
            if($details && $remaining>=mb_strlen($r['title']) && count($groups[$s]['examples'])<40){$groups[$s]['examples'][]=$r['title'];$remaining-=mb_strlen($r['title']);}
        }
        foreach($groups as $id=>&$g){$g['coverage']=self::$status[$id]['state']??'limited';}unset($g);
        return $groups;
    }
    private static function model(array $o): string {return hash('sha256',wp_json_encode([$o['provider'],Settings::base($o),$o['embed_model']]));}
    private static function vectors(array $rows,array $o,bool $keysOnly=false): array {
        if(!$rows||get_option(self::SCHEMA)!=='1'){return [];}
        global $wpdb; $out=[];
        foreach(array_chunk(array_column($rows,'fingerprint'),100) as $hashes){
            $sql=($keysOnly?'SELECT fingerprint':'SELECT fingerprint,vector').' FROM %i WHERE model=%s AND fingerprint IN ('.implode(',',array_fill(0,count($hashes),'%s')).')';
            foreach((array)$wpdb->get_results($wpdb->prepare($sql,self::table(),self::model($o),...$hashes)) as $row){$out[$row->fingerprint]=$keysOnly?true:$row->vector;}
        }
        return $out;
    }
    public static function tick(): void {
        $config=self::config();$o=Settings::get();
        if(!$config['enabled']||!$config['semantic']||!Settings::embeddingsConfigured($o)||get_option(self::SCHEMA)!=='1'){return;}
        $retry='w27ss_source_retry_'.substr(self::model($o),0,32);
        if(get_transient($retry)){return;}
        $lock='w27ss_source_tick_lock';
        if((int)get_option($lock,0)<time()-120){delete_option($lock);}
        if(!add_option($lock,time(),'','no')){return;}
        try {
        self::reset(); $rows=self::snapshot();$stored=self::vectors($rows,$o,true);$missing=[];
        foreach($rows as $r){if(!isset($stored[$r['fingerprint']])){$missing[]=$r;if(count($missing)>=6){break;}}}
        global $wpdb;
        foreach(array_chunk(array_keys($stored),100) as $hashes){
            $sql='UPDATE %i SET updated=%s WHERE model=%s AND updated < %s AND fingerprint IN ('.implode(',',array_fill(0,count($hashes),'%s')).')';
            $wpdb->query($wpdb->prepare($sql,self::table(),gmdate('Y-m-d H:i:s'),self::model($o),gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS),...$hashes));
        }
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE updated < %s',self::table(),gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS))); 
        if(!$missing){return;}
        try {
            $vectors=Knowledge::embed(array_column($missing,'excerpt'),$o);
            foreach($missing as $i=>$r){$wpdb->replace(self::table(),['fingerprint'=>$r['fingerprint'],'model'=>self::model($o),'vector'=>wp_json_encode($vectors[$i]),'updated'=>gmdate('Y-m-d H:i:s')]);}
        }catch(\RuntimeException $e){set_transient($retry,1,5*MINUTE_IN_SECONDS);update_option('w27ss_error',$e->getMessage(),false);}
        } finally {delete_option($lock);}
    }
    private static function searchTerm(string $q): string {
        $terms=self::tokens($q);
        usort($terms,static fn($a,$b)=>mb_strlen($b)<=>mb_strlen($a));
        return $terms[0]??'';
    }
    private static function tokens(string $q): array {
        return array_values(array_diff(SearchEngine::tokens($q,18),['what','which','have','with','about','there','please','show','find','tell','какие','какой','какая','есть','играть','игры','игру','игра','можно','найти','покажи','расскажи','для','или','это','вас','нас']));
    }
    private static function additional(string $q): array {
        $out=[]; $user=get_current_user_id();
        if($user){wp_set_current_user(0);}
        try {
            $defs=self::definitions();
            foreach(self::$status as $id=>$status){
                if($status['state']!=='limited'||!is_callable($defs[$id]['search']??null)){continue;}
                $local=[];$seen=0;
                try {
                    $items=($defs[$id]['search'])($q);
                    if(!is_iterable($items)){continue;}
                    foreach($items as $item){if(++$seen>30){break;}$r=self::normalize($id,$item);if($r){$local[$r['id']]=$r;}}
                    array_push($out,...array_values($local));
                }catch(\Throwable $e){}
            }
        }finally{if($user){wp_set_current_user($user);}}
        return $out;
    }
    public static function rank(string $q,array $o,?array &$queryVector=null,bool $allowSemantic=true): array {
        $rows=self::snapshot();if(!$rows){return [];}
        if(in_array('limited',array_column(self::$status,'state'),true)){
            $merged=[];foreach(array_merge($rows,self::additional($q)) as $r){$merged[$r['source'].':'.$r['id']]=$r;}$rows=array_values($merged);
        }
        $keys=[];
        if($allowSemantic&&self::config()['semantic']&&Settings::embeddingsConfigured($o)){$keys=self::vectors($rows,$o,true);}
        if($keys&&$queryVector===null){try{$queryVector=Knowledge::embed([$q],$o)[0];}catch(\RuntimeException $e){$queryVector=[];}}
        $tokens=self::tokens($q);
        $hits=[];
        foreach(array_chunk($rows,25) as $batch){
        $vectors=$queryVector&&$keys?self::vectors(array_values(array_filter($batch,static fn($r)=>isset($keys[$r['fingerprint']]))),$o):[];
        foreach($batch as $r){
            $lex=0; $sim=0;
            foreach($tokens as $term){
                $needle=mb_strlen($term)>=5?mb_substr($term,0,-1):$term;
                $needle=str_replace('ё','е',$needle);
                if(mb_stripos(str_replace('ё','е',mb_strtolower($r['title'])),$needle)!==false){$lex+=3;}
                if(mb_stripos(str_replace('ё','е',mb_strtolower($r['excerpt'])),$needle)!==false){$lex+=1;}
            }
            if($queryVector&&isset($vectors[$r['fingerprint']])){
                $v=json_decode($vectors[$r['fingerprint']],true);
                if(is_array($v)&&array_is_list($v)&&count($v)===count($queryVector)&&count(array_filter($v,static fn($x)=>is_numeric($x)&&is_finite((float)$x)&&abs((float)$x)<=1.000001))===count($v)){
                    foreach($queryVector as $i=>$x){$sim+=$x*$v[$i];}
                }
            }
            if(!$lex&&$sim<0.40){continue;}
            $hits[]=self::publicHit($r,$lex/max(1,count($tokens))+$sim*3);
        }
        }
        usort($hits,static fn($a,$b)=>$b['score']<=>$a['score']);
        return array_slice($hits,0,40);
    }
}
