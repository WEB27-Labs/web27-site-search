<?php
require __DIR__.'/bootstrap.php';
function current_user_can($cap){return $cap==='manage_options' && ($GLOBALS['admin_allowed']??true);}
function admin_url($path){return home_url('/wp-admin/'.$path);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_die($message,$title='', $args=[]){throw new RuntimeException('denied:'.$args['response']);}
function add_menu_page(...$args){$GLOBALS['menus'][]=$args;}
function add_submenu_page(...$args){$GLOBALS['menus'][]=$args;}
require dirname(__DIR__).'/StudioAdmin.php';
$GLOBALS['locale']=$argv[1]??'en_US';
$a=new \WEB27\SiteSearch\Admin();
$a->menu();
if(count($GLOBALS['menus'])!==2 || $GLOBALS['menus'][1][4]!=='web27-site-search-guide')throw new RuntimeException('menu');
if(!str_contains($a->pluginLinks([])[0],'page=web27-site-search-guide'))throw new RuntimeException('plugin link');
$GLOBALS['admin_allowed']=false;
if($a->pluginLinks([])!==[])throw new RuntimeException('unauthorized link');
try{$a->renderGuidePage();throw new RuntimeException('unauthorized guide');}catch(RuntimeException $e){if($e->getMessage()!=='denied:403')throw $e;}
$GLOBALS['admin_allowed']=true;
$a->renderGuidePage();
