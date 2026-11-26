<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\Frontend;
use WEB27\SiteSearch\Settings;
$GLOBALS['locale']=$argv[1]??'en_US';
$GLOBALS['options'][Settings::APPEARANCE]=$argv[2]??'system';
$GLOBALS['options'][Settings::OPTION]=['external_enabled'=>true,'api_key'=>'fixture-only','suggestions'=>get_locale()==='ru_RU'?"Как заказать консультацию?\nСтоимость обслуживания":"How can I book a consultation?\nMaintenance pricing"];
$GLOBALS['options'][Settings::OPTION]['widget_language']=$argv[3]??'auto';
if (($argv[4]??'')==='no-pack') {$GLOBALS['available_languages']=[];}
$GLOBALS['options'][Settings::DESIGN]=json_decode($argv[5]??'{}',true);
$f=new Frontend;$f->assets();
?><!doctype html><html lang="<?=esc_attr(get_locale())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;padding:8px;font-family:system-ui}<?=file_get_contents($plugin.'/assets/search.css')?></style></head><body><?=$f->shortcode()?><?php $f->floating();?><script>var Web27SiteSearch=<?=wp_json_encode($GLOBALS['frontend_config'])?>;</script><script><?=file_get_contents($plugin.'/assets/search.js')?></script></body></html>
