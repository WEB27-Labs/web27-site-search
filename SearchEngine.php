<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;
final class SearchEngine {
    public function search(string $q,bool $public=false): array {
        $request=new \WP_REST_Request('POST');
        $request->set_param('query',$q);
        $result=query($request,!$public);
        if(is_wp_error($result)){throw new \RuntimeException($result->get_error_message());}
        return $result->get_data();
    }
    public static function tokens(string $text,int $limit=10): array {
        preg_match_all('/[\pL\pN]{3,}/u',mb_strtolower($text),$m);
        return array_slice(array_values(array_unique($m[0])),0,$limit);
    }
    public static function virtualSources(string $q): array {
        global $wpdb;
        $terms=self::tokens($q);
        if(!$terms){return [];}
        $conditions=[];$params=[Database::chunks()];
        foreach($terms as $term){$conditions[]='(content LIKE %s OR title LIKE %s)';$like='%'.$wpdb->esc_like($term).'%';array_push($params,$like,$like);}
        $rows=$wpdb->get_results($wpdb->prepare('SELECT title,source_url,content FROM %i WHERE '.implode(' OR ',$conditions).' ORDER BY id DESC LIMIT 100',...$params));
        $hits=[];
        foreach($rows as $r){
            if (!Settings::sameSiteUrl((string)$r->source_url)) { continue; }
            $id=url_to_postid($r->source_url);
            if ($id && !Knowledge::publicPost(get_post($id))) { continue; }
            $score=0;
            foreach($terms as $term){$score+=(mb_stripos($r->content,$term)!==false?2:0)+(mb_stripos($r->title,$term)!==false?1:0);}
            $hits[]=['title'=>$r->title,'url'=>$r->source_url,'excerpt'=>$r->content,'score'=>$score/count($terms)];
        }
        usort($hits,static fn($a,$b)=>$b['score']<=>$a['score']);
        $snapshots=[]; $verified=[]; $indexer=new Indexer();
        foreach ($hits as $hit) {
            $url=(string)$hit['url'];
            if (!array_key_exists($url,$snapshots)) {
                if (count($snapshots)>=3) { continue; }
                try { $snapshots[$url]=$indexer->publicSnapshot($url,2); }
                catch (\RuntimeException) { $snapshots[$url]=null; }
            }
            $snapshot=$snapshots[$url];
            if (!$snapshot) { continue; }
            $title=$snapshot['title']!==''?$snapshot['title']:$snapshot['h1'];
            $excerpt=Indexer::normalizeText((string)$hit['excerpt']);
            if ($title!==$hit['title'] || $excerpt==='' || !str_contains($snapshot['body'],$excerpt)) { continue; }
            $verified[]=$hit;
        }
        return $verified;
    }
}
