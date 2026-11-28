<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Knowledge {
    static function table(): string { global $wpdb; return $wpdb->prefix.'w27ss_chunks'; }
    static function log(): string { global $wpdb; return $wpdb->prefix.'w27ss_questions'; }
    static function install(): void {
        $schema=get_option('w27ss_schema');
        if ($schema==='3') { return; }
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $collate=$wpdb->get_charset_collate();
        $chunks=self::table(); $log=self::log();
        $hadChunks=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($chunks)))===$chunks;
        dbDelta("CREATE TABLE $chunks (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 post_id bigint unsigned NOT NULL,
 fingerprint char(64) NOT NULL,
 body text NOT NULL,
 `vector` longtext NULL,
 model varchar(150) NOT NULL DEFAULT '',
 PRIMARY KEY  (id),
 UNIQUE KEY source (post_id,fingerprint)
) $collate;");
        dbDelta("CREATE TABLE $log (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 question text NOT NULL,
 first_source bigint unsigned NOT NULL DEFAULT 0,
 mode varchar(24) NOT NULL,
 vote smallint NOT NULL DEFAULT 0,
 created datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY created (created)
) $collate;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($chunks)))!==$chunks ||
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($log)))!==$log) { return; }
        update_option('w27ss_schema','3',false);
        if (!$hadChunks || get_option('w27ss_cursor',false)===false) { update_option('w27ss_cursor',0,false); }
    }
    static function publicPost($p): bool {
        return $p instanceof \WP_Post && $p->post_status==='publish' && $p->post_password==='' &&
            in_array($p->post_type,Settings::get()['types'],true) && is_post_publicly_viewable($p);
    }
    static function parts($p): array {
        $html=preg_replace('~</(?:p|h[1-6]|li|div|tr)>|<br\s*/?>~i',"\n",(string)$p->post_content);
        $text=wp_strip_all_tags(strip_shortcodes($p->post_excerpt."\n".$html));
        $parts=[];
        foreach (preg_split('/\R+/u',$text) as $paragraph) {
            $words=preg_split('/\s+/u',trim($paragraph),-1,PREG_SPLIT_NO_EMPTY);
            foreach (array_chunk($words,75) as $wordsPart) {
                $fragment=implode(' ',$wordsPart);
                $parts[hash('sha256',$fragment)]=$fragment;
            }
        }
        if (!$parts && $p->post_title!=='') { $parts[hash('sha256',$p->post_title)]=$p->post_title; }
        return $parts;
    }
    static function sync(int $id): void {
        global $wpdb;
        $p=get_post($id); $table=self::table();
        $parts=self::publicPost($p)?self::parts($p):[];
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,fingerprint FROM %i WHERE post_id=%d",$table,$id));
        foreach ($rows as $row) {
            if (isset($parts[$row->fingerprint])) { unset($parts[$row->fingerprint]); }
            else { $wpdb->delete($table,['id'=>$row->id]); }
        }
        foreach ($parts as $hash=>$body) { $wpdb->insert($table,['post_id'=>$id,'fingerprint'=>$hash,'body'=>$body]); }
    }
    static function tick(): void {
        global $wpdb;
        delete_option('w27ss_calls_'.gmdate('Ymd',time()-DAY_IN_SECONDS));
        $cursor=(int)get_option('w27ss_cursor',0);
        if ($cursor>=0) {
            $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM %i WHERE ID>%d AND post_type IN ('post','page') ORDER BY ID LIMIT 25",$wpdb->posts,$cursor));
            foreach ($ids as $id) { self::sync((int)$id); }
            update_option('w27ss_cursor',count($ids)===25?(int)end($ids):-1,false);
        }
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created < %s',self::log(),gmdate('Y-m-d H:i:s',time()-Settings::get()['log_retention_days']*DAY_IN_SECONDS)));
        $o=Settings::get();
        if (!Settings::embeddingsConfigured($o)) { return; }
        $retryKey='w27ss_embed_retry_'.substr(Settings::profileSignature($o),0,32);
        if (get_transient($retryKey)) { return; }
        if (!$o['types']) { return; }
        $allowed=array_values($o['types']);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.post_id,c.body FROM %i c JOIN %i p ON p.ID=c.post_id WHERE c.model<>%s AND p.post_status='publish' AND p.post_password='' AND p.post_type IN (%s,%s) ORDER BY c.id LIMIT 12",self::table(),$wpdb->posts,Settings::vectorModel($o),$allowed[0],end($allowed)));
        $rows=array_values(array_filter($rows,static function($r){
            $post=get_post($r->post_id);
            return self::publicPost($post) && isset(self::parts($post)[hash('sha256',$r->body)]);
        }));
        if (!$rows) { return; }
        try {
            $vectors=self::embed(array_column($rows,'body'),$o);
            foreach ($rows as $i=>$row) {
                $wpdb->update(self::table(),['model'=>Settings::vectorModel($o),'vector'=>wp_json_encode($vectors[$i])],['id'=>$row->id]);
            }
            delete_option('w27ss_error');
        } catch (\RuntimeException $e) {
            update_option('w27ss_error',$e->getMessage(),false);
            set_transient($retryKey,1,5*MINUTE_IN_SECONDS);
        }
    }
    static function embed(array $inputs,array $o): array {
        if (Settings::embeddingsEndpoint($o)==='' || empty($o['api_key']) || empty($o['embed_model'])) { throw new \RuntimeException('embedding_not_configured'); }
        $res=wp_safe_remote_post(Settings::embeddingsEndpoint($o),[
            'timeout'=>15,'redirection'=>0,'limit_response_size'=>8*1024*1024,'headers'=>['Authorization'=>'Bearer '.$o['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode(['model'=>$o['embed_model'],'input'=>$inputs])]);
        if (is_wp_error($res)) { throw new \RuntimeException('gateway_request_failed'); }
        $status=(int)wp_remote_retrieve_response_code($res);
        if ($status!==200) { throw new \RuntimeException('gateway_http_'.$status); }
        $data=json_decode(wp_remote_retrieve_body($res),true); $vectors=[]; $dimension=null;
        if (!is_array($data) || !is_array($data['data']??null)) { throw new \RuntimeException('embedding_invalid_json'); }
        foreach ($data['data'] ?? [] as $item) {
            if (!is_array($item)) { throw new \RuntimeException('embedding_invalid_item'); }
            $i=$item['index']??-1; $v=$item['embedding']??[];
            if (!is_int($i) || !isset($inputs[$i]) || isset($vectors[$i]) || !is_array($v) || !array_is_list($v) || !$v || count($v)>8192 ||
                count(array_filter($v,'is_numeric'))!==count($v) || ($dimension!==null && count($v)!==$dimension)) {
                throw new \RuntimeException(__('Invalid vector in the provider response.', 'web27-site-search'));
            }
            $dimension=count($v); $norm=sqrt(array_sum(array_map(static fn($x)=>$x*$x,$v)));
            if (!$norm || !is_finite($norm)) { throw new \RuntimeException(__('Invalid vector magnitude.', 'web27-site-search')); }
            $vectors[$i]=array_map(static fn($x)=>$x/$norm,$v);
        }
        if (count($vectors)!==count($inputs)) { throw new \RuntimeException(__('The provider returned an incomplete batch of vectors.', 'web27-site-search')); }
        return $vectors;
    }
    static function pinKey(string $q): string { return 'w27ss_pin_'.hash('sha256',wp_strip_all_tags(trim(preg_replace('/\s+/u',' ',strtolower($q))))); }
    static function rank(string $query,array $types,?array &$queryVector=null,bool $allowSemantic=true): array {
        global $wpdb;
        $o=Settings::get(); $v=[]; $pin=(int)get_option(self::pinKey($query));
        if (!$types) { return []; }
        if ($allowSemantic && Settings::embeddingsConfigured($o) && $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE model=%s LIMIT 1',self::table(),Settings::vectorModel($o)))) {
            try { $v=self::embed([$query],$o)[0]; } catch (\RuntimeException $e) { update_option('w27ss_error',$e->getMessage(),false); }
            $queryVector=$v;
        }
        $terms=preg_split('/[^\pL\pN]+/u',$query,-1,PREG_SPLIT_NO_EMPTY);
        $terms=array_filter(array_unique($terms),static fn($t)=>!preg_match('/^(?:а|в|на|у|по|что|как|вы|ли|это|мне|the|is|of|can)$/iu',$t));
        $best=[]; $after=0; $currentParts=[];
        $columns=$v ? 'c.*' : 'c.id,c.post_id,c.body,c.model';
        do {
            $rows=$wpdb->get_results($wpdb->prepare("SELECT $columns,p.post_title,p.post_type FROM %i c JOIN %i p ON p.ID=c.post_id WHERE c.id>%d AND p.post_status='publish' AND p.post_password='' ORDER BY c.id LIMIT 100",self::table(),$wpdb->posts,$after));
            foreach ($rows as $r) {
                $after=(int)$r->id;
                if (!in_array($r->post_type,$types,true) || !self::publicPost(get_post((int)$r->post_id))) { continue; }
                $lex=0;
                foreach ($terms as $term) {
                    $pat='/'.preg_quote($term,'/').'/iu';
                    $lex+=preg_match($pat,$r->body)*2+preg_match($pat,$r->post_title);
                }
                $sim=0;
                if ($v && $r->model===Settings::vectorModel($o)) {
                    $stored=json_decode($r->vector,true);
                    if (is_array($stored) && array_is_list($stored) && count($stored)===count($v) && count(array_filter($stored,static fn($x)=>is_numeric($x) && is_finite((float)$x) && abs((float)$x)<=1.000001))===count($v)) {
                        foreach ($v as $i=>$x) { $sim+=$x*$stored[$i]; }
                    }
                }
                $pinned=$after===$pin;
                if (!$lex && $sim<0.40 && !$pinned) { continue; }
                $postId=(int)$r->post_id;
                if (!isset($currentParts[$postId])) {
                    if (count($currentParts)>=32) { unset($currentParts[array_key_first($currentParts)]); }
                    $currentParts[$postId]=self::parts(get_post($postId));
                }
                if (!isset($currentParts[$postId][hash('sha256',$r->body)])) { continue; }
                $score=($pinned?1000:0)+$lex/max(1,count($terms))+$sim*3;
                $best[]=['chunk'=>$after,'post_id'=>(int)$r->post_id,'title'=>$r->post_title,'excerpt'=>$r->body,'score'=>round($score,3),'similarity'=>round($sim,3),'pinned'=>$pinned];
            }
            usort($best,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $a['chunk']<=>$b['chunk']);
            $best=array_slice($best,0,(int)$o['rag_limit']);
        } while (count($rows)===100);
        foreach ($best as &$hit) { $hit['url']=get_permalink($hit['post_id']); }
        return $best;
    }
}
