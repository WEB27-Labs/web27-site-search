<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

use RuntimeException;

final class Database
{
    public const VERSION = 1;
    public const OPTION = 'w27studio_db_version';

    public static function docs(): string { global $wpdb; return $wpdb->prefix . 'w27studio_docs'; }
    public static function chunks(): string { global $wpdb; return $wpdb->prefix . 'w27studio_chunks'; }
    public static function logs(): string { global $wpdb; return $wpdb->prefix . 'w27studio_logs'; }

    public static function migrate(): void
    {
        if ((int)get_option(self::OPTION, 0) >= self::VERSION && self::tablesExist()) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $docs = self::docs(); $chunks = self::chunks(); $logs = self::logs();

        dbDelta("CREATE TABLE {$docs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url_hash CHAR(40) NOT NULL,
            source_type VARCHAR(32) NOT NULL DEFAULT 'virtual',
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url TEXT NOT NULL,
            path VARCHAR(500) NOT NULL DEFAULT '',
            title TEXT NULL,
            h1 TEXT NULL,
            meta_description TEXT NULL,
            body LONGTEXT NOT NULL,
            content_hash CHAR(40) NOT NULL,
            http_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
            indexed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY url_hash (url_hash),
            KEY source_type (source_type),
            KEY source_id (source_id),
            KEY indexed_at (indexed_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$chunks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doc_id BIGINT UNSIGNED NOT NULL,
            chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
            title TEXT NULL,
            source_url TEXT NOT NULL,
            content LONGTEXT NOT NULL,
            keywords TEXT NULL,
            content_hash CHAR(40) NOT NULL,
            indexed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY doc_chunk (doc_id, chunk_index),
            KEY doc_id (doc_id),
            KEY indexed_at (indexed_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            request_id VARCHAR(64) NOT NULL DEFAULT '',
            session_hash CHAR(64) NOT NULL DEFAULT '',
            search_query TEXT NOT NULL,
            mode VARCHAR(32) NOT NULL DEFAULT 'local',
            result_count INT UNSIGNED NOT NULL DEFAULT 0,
            latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
            model VARCHAR(191) NOT NULL DEFAULT '',
            input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            error_code VARCHAR(100) NOT NULL DEFAULT '',
            answer_excerpt TEXT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY mode (mode),
            KEY request_id (request_id)
        ) {$charset};");

        if (!self::tablesExist()) {
            throw new RuntimeException('WEB27 Site Search: database tables were not created.');
        }
        update_option(self::OPTION, self::VERSION, false);
    }

    public static function tablesExist(): bool
    {
        global $wpdb;
        foreach ([self::docs(), self::chunks(), self::logs()] as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return false; }
        }
        return true;
    }

     
    public static function stats(): array
    {
        global $wpdb;
        $docs = self::docs(); $chunks = self::chunks(); $logs = self::logs();
        return [
            'docs' => (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT post_id) FROM %i', Knowledge::table())) + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $docs)),
            'chunks' => (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', Knowledge::table())) + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $chunks)),
            'logs_24h' => (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= %s', $logs, gmdate('Y-m-d H:i:s', time()-DAY_IN_SECONDS))),
            'zero_24h' => (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= %s AND result_count=0', $logs, gmdate('Y-m-d H:i:s', time()-DAY_IN_SECONDS))),
            'last_indexed' => $wpdb->get_var($wpdb->prepare('SELECT MAX(indexed_at) FROM %i', $docs)),
        ];
    }
}
