<?php
/**
 * Plugin Name: WEB27 Site Search
 * Description: Local search for public WordPress content and connected catalogs with optional conversational AI answers.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: WEB27
 * Author URI: https://web27.ru/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: web27-site-search
 * Domain Path: /languages
 */
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;
add_action('init', static function () { load_plugin_textdomain('web27-site-search', false, dirname(plugin_basename(__FILE__)).'/languages'); }, 0);
if (!function_exists('mb_strlen')) {
    add_action('admin_notices',static function(){if(current_user_can('manage_options')){echo '<div class="notice notice-error"><p>'.esc_html__('WEB27 Site Search: enable the PHP mbstring extension to use search.', 'web27-site-search').'</p></div>';}});
    return;
}
define('WEB27_SITE_VERSION','1.0.0');
define('WEB27_SITE_URL',plugin_dir_url(__FILE__));
require_once __DIR__.'/Settings.php';
require_once __DIR__.'/Language.php';
require_once __DIR__.'/Frontend.php';
require_once __DIR__.'/Gateway.php';
require_once __DIR__.'/Welcome.php';
require_once __DIR__.'/Search.php';
foreach (['Database','Conversation','Logger','Indexer','StudioAdmin','SearchEngine'] as $component) { require_once __DIR__.'/'.$component.'.php'; }
(new Admin())->register();
add_action('admin_init', [Settings::class,'privacyPolicy']);
add_action('plugins_loaded',[Settings::class,'initAppearance'],5);
register_activation_hook(__FILE__,[Settings::class,'initAppearance']);
add_action('plugins_loaded',[Database::class,'migrate']);
add_filter('cron_schedules',static function($s){
    $s['w27studio_interval']=['interval'=>60*(int)Settings::get()['index_interval_minutes'],'display'=>did_action('init') ? __('Sitemap search', 'web27-site-search') : 'Sitemap search'];
    return $s;
});
add_action('init',static function(){
    if(!wp_next_scheduled(Indexer::CRON_HOOK)){wp_schedule_event(time()+60,'w27studio_interval',Indexer::CRON_HOOK);}
});
add_action(Indexer::CRON_HOOK,static function(){ if(Settings::get()['index_virtual_pages']){(new Indexer())->processBatch();} Logger::cleanup(); });
register_deactivation_hook(__FILE__,static function(){wp_clear_scheduled_hook(Indexer::CRON_HOOK);});
(new Frontend())->register();
add_action('rest_api_init', static function () {
    register_rest_route('web27-site-search/v1','/query',[
        'methods'=>'POST', 'permission_callback'=>'__return_true','callback'=>static fn($request)=>Language::withLocale($request,static fn()=>query($request)),
        'args'=>['locale'=>Language::restArg(),'query'=>['type'=>'string','required'=>true,'minLength'=>2,'maxLength'=>500,'sanitize_callback'=>'sanitize_text_field'], 'previous'=>['type'=>'string','maxLength'=>500,'sanitize_callback'=>'sanitize_text_field'], 'new_dialog'=>['type'=>'boolean'], 'instance'=>['type'=>'string','pattern'=>'^(?:[a-z0-9-]{1,32})$']],
    ]);
});

require_once __DIR__.'/Knowledge.php';
require_once __DIR__.'/Universal.php';
require_once __DIR__.'/Connectors.php';
add_filter('web27_site_search_sources',[Connectors::class,'register']);
add_action('plugins_loaded',[Universal::class,'install']);
register_activation_hook(__FILE__,[Universal::class,'install']);
add_action('w27ss_tick',[Universal::class,'tick'],20);
add_action('admin_post_w27ss_sources',[Admin::class,'handleSources']);
require_once __DIR__.'/Console.php';
add_action('plugins_loaded',[Knowledge::class,'install']);
register_activation_hook(__FILE__,[Knowledge::class,'install']);
add_filter('cron_schedules',static function($s){ $s['w27ss_minute']=['interval'=>60,'display'=>'WEB27 Search']; return $s; });
add_action('init',static function(){ if (!wp_next_scheduled('w27ss_tick')) { wp_schedule_event(time()+10,'w27ss_minute','w27ss_tick'); } });
add_action('w27ss_tick',[Knowledge::class,'tick']);
register_deactivation_hook(__FILE__,static function(){ wp_clear_scheduled_hook('w27ss_tick'); });
add_action('save_post',static function($id,$post){ if (in_array($post->post_type,['page','post'],true)) { Knowledge::sync($id); } },20,2);
add_action('deleted_post',static function($id){ global $wpdb; $wpdb->delete(Knowledge::table(),['post_id'=>$id]); });
add_action('update_option_'.Settings::OPTION,static function($old,$new){
    $types=static function($value): array {
        $value=is_array($value)?$value:[];
        $legacy=is_array($value['types']??null)?$value['types']:['page','post'];
        return array_keys(array_filter(['page'=>$value['index_wp_pages']??in_array('page',$legacy,true),'post'=>$value['index_wp_posts']??in_array('post',$legacy,true)]));
    };
    if ($types($old)!==$types($new)) { update_option('w27ss_cursor',0,false); }
},10,2);
add_action('admin_post_w27ss_console',__NAMESPACE__.'\\console_action');
add_action('rest_api_init',static function(){
    register_rest_route('web27-site-search/v1','/vote',['methods'=>'POST','permission_callback'=>'__return_true','args'=>['locale'=>Language::restArg(),'token'=>['type'=>'string','required'=>true,'maxLength'=>128,'sanitize_callback'=>'sanitize_text_field'],'vote'=>['type'=>'integer','required'=>true,'enum'=>[-1,1]]],'callback'=>static fn($r)=>Language::withLocale($r,static function() use ($r){
        global $wpdb;
        $parts=explode('.',(string)$r['token'],2); $id=absint($parts[0]);
        if (!Settings::get()['logging'] || !$id || !hash_equals(wp_hash('w27ss-vote-'.$id),$parts[1]??'') || !in_array($r['vote'],[-1,1],true)) {
            return new \WP_Error('invalid_vote',__('Rating is unavailable.', 'web27-site-search'),['status'=>400]);
        }
        $wpdb->update(Knowledge::log(),['vote'=>$r['vote']],['id'=>$id,'vote'=>0]);
        return ['ok'=>true];
    })]);
});
