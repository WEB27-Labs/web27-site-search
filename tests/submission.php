<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\{Settings,Database,Logger};
define("ARRAY_A","ARRAY_A");
function wp_add_privacy_policy_content($name,$content){$GLOBALS['policy']=[$name,$content];}
function wp_clear_scheduled_hook($hook){$GLOBALS['cleared_hooks'][]=$hook;}
function verify($value,$name){if(!$value){throw new RuntimeException('FAIL '.$name);}echo "PASS $name\n";$GLOBALS['total']++;}
$GLOBALS['total']=0;
$settings=['api_key'=>'fixture-not-a-secret','provider'=>'openai','external_enabled'=>true,'semantic'=>true];
update_option(Settings::OPTION,$settings);
foreach(['en_US','ru_RU'] as $locale){$GLOBALS['locale']=$locale;Settings::privacyPolicy();$html=$GLOBALS['policy'][1];verify($GLOBALS['policy'][0]==='WEB27 Site Search'&&str_contains($html,$locale==='ru_RU'?'конфиденциальности':'privacy policy')&&!str_contains($html,'fixture-not-a-secret'),'localized privacy suggestion with no credential '.$locale);}
verify(get_option(Settings::OPTION)===$settings&&$GLOBALS['calls']===[],'privacy suggestion does not mutate settings or call providers');
class SubmissionDB extends FixtureDB {
 public array $queries=[];
 function get_col($q){[$sql,$args]=$q; $prefix=str_replace(['\\_','\\%'],['_','%'],substr($args[1],0,-1));return array_values(array_filter(array_keys($GLOBALS['options']),fn($key)=>str_starts_with($key,$prefix)));}
 function query($q){$this->queries[]=$q;return 1;}
 function get_var($q){$this->queries[]=$q;return null;}
 function get_results($q,$format=null){$this->queries[]=$q;return [];}
}
$GLOBALS['wpdb']=new SubmissionDB;
Database::stats();Logger::cleanup();Logger::recent(9999);
verify(count($GLOBALS['wpdb']->queries)===9&&count(array_filter($GLOBALS['wpdb']->queries,fn($q)=>is_array($q)&&str_contains($q[0],'%i')))===9,'stats and logs prepare table identifiers');
$last=end($GLOBALS['wpdb']->queries);verify($last[1]===[Database::logs(),500],'log query limit is bound and clamped');
$hash=str_repeat('a',32);$pin='w27ss_pin_'.str_repeat('b',64);$budget='w27ss_calls_20261126';$hist='w27studio_hist_v2_'.$hash;$retry='w27ss_embed_retry_'.$hash;$counter='w27public_'.str_repeat('c',24);
foreach([$pin,$budget,'_transient_'.$hist,'_transient_timeout_'.$hist,'_transient_timeout_'.$retry,'_transient_'.$counter,'_transient_w27ss_provider_chat_7','_transient_w27studio_test_7','w27ss_calls_other','_transient_w27studio_hist_v2_unrelated','unrelated'] as $key){update_option($key,'fixture');}
set_transient($hist,'fixture');
define('WP_UNINSTALL_PLUGIN',true);require dirname(__DIR__).'/uninstall.php';
foreach([$pin,$budget,'_transient_'.$hist,'_transient_timeout_'.$hist,'_transient_timeout_'.$retry,'_transient_'.$counter,'_transient_w27ss_provider_chat_7','_transient_w27studio_test_7',Settings::OPTION] as $key){verify(get_option($key)===false,'uninstall removes exact owned record '.$key);}
verify(get_transient($hist)===false,'uninstall clears discovered history transient');
verify(get_option('w27ss_calls_other')==='fixture'&&get_option('_transient_w27studio_hist_v2_unrelated')==='fixture'&&get_option('unrelated')==='fixture','uninstall preserves unrelated and lookalike option names');
verify($GLOBALS['cleared_hooks']===['w27ss_tick','w27studio_index_cron'],'uninstall clears only plugin schedules');
$drops=array_values(array_filter($GLOBALS['wpdb']->queries,fn($q)=>str_contains($q[0],'DROP TABLE')));verify(count($drops)===6&&count(array_filter($drops,fn($q)=>str_contains($q[0],'%i')))===6,'uninstall prepares six plugin table names');
echo 'TOTAL '.$GLOBALS['total']." PASS (isolated submission fixtures)\n";
