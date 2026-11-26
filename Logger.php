<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Logger
{
     
    public function write(array $data): void
    {
        if (empty(Settings::get()['log_enabled'])) { return; }
        global $wpdb;
        $wpdb->insert(Database::logs(), [
            'created_at'=>current_time('mysql', true),
            'request_id'=>sanitize_text_field((string)($data['request_id'] ?? '')),
            'session_hash'=>sanitize_text_field((string)($data['session_hash'] ?? '')),
            'search_query'=>sanitize_text_field(mb_substr((string)($data['query'] ?? ''),0,700)),
            'mode'=>sanitize_key((string)($data['mode'] ?? 'local')),
            'result_count'=>(int)($data['result_count'] ?? 0),
            'latency_ms'=>(int)($data['latency_ms'] ?? 0),
            'model'=>sanitize_text_field((string)($data['model'] ?? '')),
            'input_tokens'=>(int)($data['input_tokens'] ?? 0),
            'output_tokens'=>(int)($data['output_tokens'] ?? 0),
            'error_code'=>sanitize_text_field((string)($data['error_code'] ?? '')),
            'answer_excerpt'=>sanitize_textarea_field(mb_substr((string)($data['answer'] ?? ''),0,1200)),
        ]);
    }

    public static function cleanup(): void
    {
        global $wpdb;
        $days=(int)Settings::get()['log_retention_days'];
        $cutoff=gmdate('Y-m-d H:i:s', time()-$days*DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at < %s', Database::logs(), $cutoff));
    }

     
    public static function recent(int $limit=100): array
    {
        global $wpdb;
        $limit=max(1,min(500,$limit));
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', Database::logs(), $limit), ARRAY_A) ?: [];
    }
}
