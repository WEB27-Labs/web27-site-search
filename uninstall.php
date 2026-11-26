<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
global $wpdb;
wp_clear_scheduled_hook('w27ss_tick');
wp_clear_scheduled_hook('w27studio_index_cron');
foreach (['w27ss_chunks','w27ss_questions','w27studio_docs','w27studio_chunks','w27studio_logs','w27ss_source_vectors'] as $suffix) {
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i',$wpdb->prefix.$suffix));
}
foreach (['web27_site_search_settings','web27_site_search_appearance','web27_site_search_design','w27ss_cursor','w27ss_schema','w27ss_error','w27studio_db_version','w27studio_index_queue','w27studio_index_state','w27ss_universal_sources','w27ss_universal_schema','w27ss_source_tick_lock'] as $option) { delete_option($option); }
foreach (['w27ss_pin_'=>'[a-f0-9]{64}','w27ss_calls_'=>'[0-9]{8}'] as $prefix=>$suffix) {
    foreach ($wpdb->get_col($wpdb->prepare('SELECT option_name FROM %i WHERE option_name LIKE %s',$wpdb->options,$wpdb->esc_like($prefix).'%')) as $option) {
        if (preg_match('/^'.preg_quote($prefix,'/').$suffix.'$/D',$option)) { delete_option($option); }
    }
}
foreach (['w27studio_hist_v2_'=>'[a-f0-9]{32}','w27public_'=>'[a-f0-9]{24}','w27ss_embed_retry_'=>'[a-f0-9]{32}','w27ss_source_retry_'=>'[a-f0-9]{32}','w27ss_catalog_'=>'[0-9]+','w27ss_provider_'=>'(?:chat|embed|catalog)_[0-9]+','w27studio_test_'=>'[0-9]+','w27studio_admin_error_'=>'[0-9]+'] as $prefix=>$suffix) {
    foreach (['_transient_','_transient_timeout_'] as $storage) {
        foreach ($wpdb->get_col($wpdb->prepare('SELECT option_name FROM %i WHERE option_name LIKE %s',$wpdb->options,$wpdb->esc_like($storage.$prefix).'%')) as $option) {
            $key=substr($option,strlen($storage));
            if (preg_match('/^'.preg_quote($prefix,'/').$suffix.'$/D',$key)) {
                delete_transient($key);
                delete_option('_transient_'.$key);
                delete_option('_transient_timeout_'.$key);
            }
        }
    }
}
