<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\{Settings,Language,Frontend};
use function WEB27\SiteSearch\query;
$n=0;
function check($ok,$label){global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
foreach(['en_US','ru_RU','de_DE'] as $site){
 foreach(['auto','en_US','ru_RU'] as $forced){
  $GLOBALS['locale']=$site;$GLOBALS['available_languages']=[];
  $stored=['widget_language'=>$forced,'provider'=>'proxyapi_openai','api_key'=>'fixture-key','semantic'=>true,'embed_model'=>'openai/text-embedding-3-small'];update_option(Settings::OPTION,$stored);
  $expected=$forced==='auto'?$site:$forced;
  $f=new Frontend;$f->assets();$html=$f->shortcode();
  check($GLOBALS['frontend_config']['locale']===$expected,'JS locale '.$site.' '.$forced);
  $title=$expected==='ru_RU'?'Чем можем помочь?':'How can we help?';$actual=$expected==='ru_RU'?$GLOBALS['translations']['How can we help?']:'How can we help?';
  check(str_contains($html,esc_html($actual)),'panel title '.$site.' '.$forced);
  check(get_locale()===$site&&get_option(Settings::OPTION)===$stored&&empty($GLOBALS['filters']['gettext_web27-site-search']),'locale and storage restored '.$site.' '.$forced);
  check(Language::pageLocale(request('Hello',$site))===$expected,'REST locale '.$site.' '.$forced);
 }
}
$GLOBALS['locale']='en_US';$GLOBALS['available_languages']=[];update_option(Settings::OPTION,['widget_language'=>'ru_RU','enabled'=>false]);
$r=Language::withLocale(request('hello','en_US'),fn()=>query(request('hello','en_US')));check($r instanceof WP_Error&&$r->message==='Поиск выключен.','bundled MO fallback translates REST errors');
try{Language::withInterfaceLocale('ru_RU',fn()=>throw new RuntimeException('fixture'));}catch(RuntimeException $e){}
check(get_locale()==='en_US'&&empty($GLOBALS['filters']['gettext_web27-site-search']),'fallback scope restored after exception');
check(Language::answerLanguage('What is the price?',[],'ru_RU')==='en'&&Language::answerLanguage('Сколько стоит?',[],'en_US')==='ru','AI language independent of widget override');
update_option(Settings::OPTION,['widget_language'=>'en_US','provider'=>'custom','api_base'=>'https://example.test/v1','api_key'=>'fixture-key','semantic'=>true,'custom_embeddings_confirmed'=>true,'embed_model'=>'embed','external_enabled'=>false,'launcher_label'=>'CUSTOM']);$f=new Frontend;
$html=$f->shortcode();ob_start();$f->floating();$floating=ob_get_clean();check(str_contains($html,'w27public-disclosure'),'custom embeddings show disclosure');check(str_contains($floating,'CUSTOM'),'explicit custom launcher retained');
$o=get_option(Settings::OPTION);$o['semantic']=false;update_option(Settings::OPTION,$o);check(!str_contains($f->shortcode(),'w27public-disclosure'),'local-only mode has no external disclosure');
check($GLOBALS['calls']===[],'all language and rendering cases are offline');echo "TOTAL $n PASS (isolated locale and rendering fixtures)\n";
