<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class Indexer
{
    public const QUEUE_OPTION = 'w27studio_index_queue';
    public const STATE_OPTION = 'w27studio_index_state';
    public const CRON_HOOK = 'w27studio_index_cron';

     
    public static function state(): array
    {
        $state = get_option(self::STATE_OPTION, []);
        return wp_parse_args(is_array($state) ? $state : [], [
            'running' => false,
            'paused' => false,
            'total' => 0,
            'processed' => 0,
            'failed' => 0,
            'started_at' => '',
            'last_run_at' => '',
            'finished_at' => '',
            'last_error' => '',
        ]);
    }

     
    public static function queue(): array
    {
        $q = get_option(self::QUEUE_OPTION, []);
        if (!is_array($q)) { return []; }
        return array_values(array_filter(array_map('strval', $q), static fn(string $u): bool => $u !== ''));
    }

     
    public function loadSitemapQueue(): array
    {
        $o = Settings::get();
        if (!$o['index_virtual_pages']) { throw new RuntimeException(__('Enable sitemap crawling in the index settings.', 'web27-site-search')); }
        $url = (string)$o['sitemap_url'];
        if (!Settings::sameSiteUrl($url)) { throw new RuntimeException('sitemap_host_mismatch'); }

        $response = wp_safe_remote_get($url, [
            'timeout' => (int)$o['index_timeout'],
            'redirection' => 0,
            'limit_response_size'=>5*1024*1024,
            'user-agent' => (string)$o['index_user_agent'],
            'headers' => ['Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.8'],
        ]);
        if (is_wp_error($response)) { throw new RuntimeException('sitemap_request_failed:' . $response->get_error_code()); }
        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) { throw new RuntimeException('sitemap_http_' . $code); }
        $xml = (string)wp_remote_retrieve_body($response);
        if ($xml === '') { throw new RuntimeException('sitemap_empty'); }

        $urls = $this->parseSitemap($xml);
        $urls = array_values(array_unique(array_filter($urls, static fn(string $u): bool => Settings::sameSiteUrl($u))));
        if ($urls === []) { throw new RuntimeException('sitemap_no_urls'); }
        

         
        global $wpdb;
        $allowed=array_fill_keys($urls,true);
        foreach ($wpdb->get_col($wpdb->prepare('SELECT url FROM %i',Database::docs())) as $known) {
            if (!isset($allowed[$known])) { $this->forgetUrl($known); }
        }
        update_option(self::QUEUE_OPTION, $urls, false);
        update_option(self::STATE_OPTION, [
            'running' => true,
            'paused' => false,
            'total' => count($urls),
            'processed' => 0,
            'failed' => 0,
            'started_at' => current_time('mysql', true),
            'last_run_at' => '',
            'finished_at' => '',
            'last_error' => '',
        ], false);
        return ['count'=>count($urls), 'urls'=>$urls];
    }

     
    private function parseSitemap(string $xml,int $depth=0): array
    {
        if ($depth>4) { throw new RuntimeException('sitemap_depth_limit'); }
        if (!function_exists('simplexml_load_string')) { throw new RuntimeException(__('Sitemap indexing requires PHP SimpleXML.', 'web27-site-search')); }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) { throw new RuntimeException('sitemap_unsafe_xml'); }
        $oldErrors=libxml_use_internal_errors(true);
        try { $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($oldErrors); }
        if ($doc === false) { throw new RuntimeException('sitemap_invalid_xml'); }
        $urls = [];
        if (isset($doc->url)) {
            foreach ($doc->url as $item) {
                $loc = trim((string)$item->loc);
                if ($loc !== '') { $urls[] = $loc; }
            }
        } elseif (isset($doc->sitemap)) {
             
            foreach ($doc->sitemap as $item) {
                $loc = trim((string)$item->loc);
                if ($loc === '') { throw new RuntimeException('sitemap_child_missing_url'); }
                if (!Settings::sameSiteUrl($loc)) { continue; }
                $sub = wp_safe_remote_get($loc, ['timeout'=>12, 'redirection'=>0,'limit_response_size'=>5*1024*1024]);
                if (is_wp_error($sub) || (int)wp_remote_retrieve_response_code($sub) !== 200) { throw new RuntimeException('sitemap_child_unavailable'); }
                $urls = array_merge($urls, $this->parseSitemap((string)wp_remote_retrieve_body($sub),$depth+1));
            }
        } elseif (!in_array($doc->getName(),['urlset','sitemapindex'],true)) {
            throw new RuntimeException('sitemap_invalid_root');
        }
        return $urls;
    }

     
    public function processBatch(?int $override = null): array
    {
        $state = self::state();
        if (empty($state['running']) || !empty($state['paused'])) { return ['processed'=>0,'failed'=>0,'remaining'=>count(self::queue())]; }
        $queue = self::queue();
        if ($queue === []) {
            $this->finish();
            return ['processed'=>0,'failed'=>0,'remaining'=>0];
        }
        $o = Settings::get();
        if (!$o['index_virtual_pages']) { return ['processed'=>0,'failed'=>0,'remaining'=>count($queue)]; }
        $batch = max(1, min(10, $override ?? (int)$o['index_batch_size']));
        $take = array_splice($queue, 0, $batch);
        $processed = 0; $failed = 0; $lastError = '';
        foreach ($take as $position=>$url) {
            try {
                $this->indexUrl($url);
                $processed++;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if (in_array($lastError,['index_http_403','index_http_429','index_request_failed'],true) || preg_match('/^index_http_5\d\d$/D',$lastError)) {
                    $queue=array_merge(array_slice($take,$position),$queue);
                    $state['paused']=true;
                    break;
                }
                $failed++;
            }
        }
        update_option(self::QUEUE_OPTION, $queue, false);
        $state['processed'] = (int)$state['processed'] + $processed;
        $state['failed'] = (int)$state['failed'] + $failed;
        $state['last_run_at'] = current_time('mysql', true);
        $state['last_error'] = $lastError;
        if ($queue === []) {
            $state['running'] = false;
            $state['finished_at'] = current_time('mysql', true);
        }
        update_option(self::STATE_OPTION, $state, false);
        return ['processed'=>$processed,'failed'=>$failed,'remaining'=>count($queue)];
    }

    public function pause(bool $paused): void
    {
        $state = self::state();
        $state['paused'] = $paused;
        update_option(self::STATE_OPTION, $state, false);
    }

    public function stop(): void
    {
        $state = self::state();
        $state['running'] = false;
        $state['paused'] = false;
        $state['finished_at'] = current_time('mysql', true);
        update_option(self::STATE_OPTION, $state, false);
        delete_option(self::QUEUE_OPTION);
    }

    private function finish(): void
    {
        $state = self::state();
        $state['running'] = false;
        $state['finished_at'] = current_time('mysql', true);
        update_option(self::STATE_OPTION, $state, false);
    }

    private function forgetUrl(string $url): void
    {
        global $wpdb;
        $id=$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE url_hash=%s',Database::docs(),sha1($url)));
        if ($id) { $wpdb->delete(Database::chunks(),['doc_id'=>$id]); $wpdb->delete(Database::docs(),['id'=>$id]); }
    }

    public function indexUrl(string $url): int
    {
        if (!Settings::sameSiteUrl($url)) { throw new RuntimeException('index_host_mismatch'); }
        $o = Settings::get();
        if (!$o['index_virtual_pages']) { throw new RuntimeException(__('Crawling is disabled.', 'web27-site-search')); }
        if ($id=url_to_postid($url)) { $this->forgetUrl($url); Knowledge::sync($id); return $id; }
        try {
            $parsed = $this->publicSnapshot($url,(int)$o['index_timeout']);
        } catch (RuntimeException $e) {
            if (in_array($e->getMessage(),['index_http_404','index_http_410'],true)) { $this->forgetUrl($url); }
            throw $e;
        }
        $code = 200;
        if (mb_strlen($parsed['body']) < 80) { throw new RuntimeException('index_body_too_short'); }
        return $this->storeDocument([
            'source_type'=>'virtual', 'source_id'=>0, 'url'=>$url,
            'title'=>$parsed['title'], 'h1'=>$parsed['h1'],
            'meta_description'=>$parsed['description'], 'body'=>$parsed['body'], 'http_code'=>$code,
        ]);
    }

    public function publicSnapshot(string $url,int $timeout): array
    {
        if (!Settings::sameSiteUrl($url)) { throw new RuntimeException('index_host_mismatch'); }
        $response=wp_safe_remote_get($url,[
            'timeout'=>$timeout,'redirection'=>0,'limit_response_size'=>5*1024*1024,
            'user-agent'=>(string)Settings::get()['index_user_agent'],
            'headers'=>['Accept'=>'text/html,application/xhtml+xml;q=0.9','Cache-Control'=>'no-cache'],
            'cookies'=>[],
        ]);
        if (is_wp_error($response)) { throw new RuntimeException('index_request_failed'); }
        $code=(int)wp_remote_retrieve_response_code($response);
        if ($code!==200) { throw new RuntimeException('index_http_'.$code); }
        $type=strtolower((string)wp_remote_retrieve_header($response,'content-type'));
        if ($type!=='' && !str_contains($type,'text/html') && !str_contains($type,'application/xhtml+xml')) { throw new RuntimeException('index_not_html'); }
        $html=(string)wp_remote_retrieve_body($response);
        if (trim($html)==='' || preg_match('~<form\b[^>]*class\s*=\s*["\'][^"\']*post-password-form~i',$html)) { throw new RuntimeException('index_unavailable_html'); }
        return $this->extractHtml($html);
    }

    public function indexWpPost(int $postId): int
    {
        Knowledge::sync($postId);
        return $postId;
    }

    public function syncWpContent(): array
    {
        $o = Settings::get();
        $types = [];
        if (!empty($o['index_wp_pages'])) { $types[] = 'page'; }
        if (!empty($o['index_wp_posts'])) { $types[] = 'post'; }
        if ($types === []) { return ['processed'=>0,'failed'=>0]; }
        $ids = get_posts(['post_type'=>$types,'post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC']);
        $ok=0; $failed=0;
        foreach ($ids as $id) {
            try { if ($this->indexWpPost((int)$id) > 0) { $ok++; } }
            catch (\Throwable) { $failed++; }
        }
        return ['processed'=>$ok,'failed'=>$failed];
    }

    public function deleteWpPost(int $postId): void
    {
        global $wpdb;
        $docs = Database::docs(); $chunks = Database::chunks();
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM %i WHERE source_id=%d AND source_type IN ('wp_page','wp_post')", $docs, $postId));
        foreach ($ids as $id) { $wpdb->delete($chunks, ['doc_id'=>(int)$id], ['%d']); }
        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE source_id=%d AND source_type IN ('wp_page','wp_post')", $docs, $postId));
    }

     
    private function storeDocument(array $doc): int
    {
        global $wpdb;
        $docs = Database::docs(); $chunks = Database::chunks();
        $url = esc_url_raw((string)$doc['url']);
        $urlHash = sha1($url);
        $body = self::normalizeText((string)$doc['body']);
        $title = self::normalizeText((string)($doc['title'] ?? ''));
        $h1 = self::normalizeText((string)($doc['h1'] ?? ''));
        $desc = self::normalizeText((string)($doc['meta_description'] ?? ''));
        $hash = sha1($title . "\n" . $h1 . "\n" . $desc . "\n" . $body);
        $now = current_time('mysql', true);
        $existing = $wpdb->get_row($wpdb->prepare('SELECT id,content_hash FROM %i WHERE url_hash=%s', $docs, $urlHash), ARRAY_A);
        $data = [
            'url_hash'=>$urlHash,
            'source_type'=>sanitize_key((string)($doc['source_type'] ?? 'virtual')),
            'source_id'=>(int)($doc['source_id'] ?? 0),
            'url'=>$url,
            'path'=>(string)(wp_parse_url($url, PHP_URL_PATH) ?: '/'),
            'title'=>$title,
            'h1'=>$h1,
            'meta_description'=>$desc,
            'body'=>$body,
            'content_hash'=>$hash,
            'http_code'=>(int)($doc['http_code'] ?? 200),
            'indexed_at'=>$now,
        ];
        if ($existing) {
            $docId = (int)$existing['id'];
            $wpdb->update($docs, $data, ['id'=>$docId]);
            if ((string)$existing['content_hash'] === $hash) { return $docId; }
            $wpdb->delete($chunks, ['doc_id'=>$docId], ['%d']);
        } else {
            $wpdb->insert($docs, $data);
            $docId = (int)$wpdb->insert_id;
        }
        if ($docId <= 0) { throw new RuntimeException('index_db_doc_failed'); }
        $parts = $this->chunk($body);
        foreach ($parts as $i=>$part) {
            $wpdb->insert($chunks, [
                'doc_id'=>$docId,
                'chunk_index'=>$i,
                'title'=>$title !== '' ? $title : $h1,
                'source_url'=>$url,
                'content'=>$part,
                'keywords'=>$this->keywords($title . ' ' . $h1 . ' ' . $desc . ' ' . $part),
                'content_hash'=>sha1($part),
                'indexed_at'=>$now,
            ]);
        }
        return $docId;
    }

     
    private function extractHtml(string $html): array
    {
        $title=''; $h1=''; $description=''; $body='';
        if (class_exists(DOMDocument::class)) {
            $oldErrors=libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            try { $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($oldErrors); }
            if ($loaded) {
                $xp = new DOMXPath($dom);
                $node = $xp->query('//title')->item(0); if ($node) { $title = $node->textContent; }
                $node = $xp->query('//h1')->item(0); if ($node) { $h1 = $node->textContent; }
                $node = $xp->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]')->item(0);
                if ($node instanceof \DOMElement) { $description = $node->getAttribute('content'); }
                foreach (['//script','//style','//noscript','//svg','//form','//nav','//header','//footer', '//*[@data-w27public-root]', '//*[@id="smart-search"]', '//*[contains(concat(" ",normalize-space(@class)," ")," web27-v3-related-grid ")]', '//*[contains(concat(" ",normalize-space(@class)," ")," web27-v3-cities-section ")]'] as $query) {
                    $nodes = $xp->query($query);
                    if ($nodes) {
                        for ($i=$nodes->length-1; $i>=0; $i--) {
                            $n=$nodes->item($i); if ($n && $n->parentNode) { $n->parentNode->removeChild($n); }
                        }
                    }
                }
                $main = $xp->query('//main')->item(0) ?: $xp->query('//article')->item(0) ?: $xp->query('//body')->item(0);
                if ($main) { $body = $main->textContent; }
            }
        }
        if ($body === '') {
            if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) { $title = wp_strip_all_tags($m[1]); }
            if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) { $h1 = wp_strip_all_tags($m[1]); }
            $clean = preg_replace('#<(script|style|noscript|svg|form|nav|header|footer)[^>]*>.*?</\1>#is', ' ', $html) ?: $html;
            $body = wp_strip_all_tags($clean);
        }
        return [
            'title'=>self::normalizeText($title),
            'h1'=>self::normalizeText($h1),
            'description'=>self::normalizeText($description),
            'body'=>self::normalizeText($body),
        ];
    }

     
    private function chunk(string $text): array
    {
        $size=1200; $overlap=180; $len=mb_strlen($text); $out=[]; $start=0; $guard=0;
        while ($start < $len && $guard < 80) {
            $guard++;
            $part = mb_substr($text, $start, $size);
            if (mb_strlen($part) === $size && ($pos = mb_strrpos($part, '. ')) !== false && $pos > 650) {
                $part = mb_substr($part, 0, $pos+1);
            }
            $part = trim($part);
            if ($part !== '') { $out[]=$part; }
            $step = max(200, mb_strlen($part)-$overlap);
            $start += $step;
            if (count($out) >= 40) { break; }
        }
        return $out;
    }

    private function keywords(string $text): string
    {
        $tokens = SearchEngine::tokens($text, 18);
        return implode(' ', array_values(array_unique($tokens)));
    }

    public static function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\s]+/u', ' ', $text) ?: $text;
        return trim($text);
    }
}
