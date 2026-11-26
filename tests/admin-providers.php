<?php
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Console.php';
require dirname(__DIR__).'/StudioAdmin.php';
use WEB27\SiteSearch\{Admin,Settings};
function current_user_can($cap){return $cap==='manage_options'&&($GLOBALS['allowed']??true);}
function admin_url($path){return home_url('/wp-admin/'.$path);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_die($message,$title='', $args=[]){throw new RuntimeException('denied:'.($args['response']??403));}
function check_admin_referer($action){if(($_POST['_wpnonce']??'')!==$action)throw new RuntimeException('nonce');}
function wp_nonce_field($action,$name='_wpnonce',$referer=true,$echo=true){$s='<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($action).'">';if($echo)echo $s;return $s;}
function selected($a,$b,$echo=true){$s=(string)$a===(string)$b?'selected="selected"':'';if($echo)echo $s;return $s;}
function checked($a,$b=true,$echo=true){$s=(bool)$a===(bool)$b?'checked="checked"':'';if($echo)echo $s;return $s;}
function submit_button($label){echo '<button type="submit">'.esc_html($label).'</button>';}
function get_current_user_id(){return 1;}
function wp_safe_redirect($url){throw new RuntimeException('redirect:'.$url);}
$n=0;function verify($ok,$label){global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
function expect(callable $fn,string $prefix){try{$fn();return false;}catch(RuntimeException $e){return str_starts_with($e->getMessage(),$prefix);}}
$a=new Admin();$legacy=['provider'=>'proxyapi_openai','api_key'=>'fixture-private-proxy','external_enabled'=>true,'semantic'=>true,'launcher_label'=>'Saved label'];update_option(Settings::OPTION,$legacy);
foreach(['en_US','ru_RU'] as $loc){$GLOBALS['locale']=$loc;foreach(['openai','openrouter','proxyapi_openai','proxyapi_compatible','custom'] as $p){$o=Settings::switchProvider($legacy,$p);$o['api_key']='fixture-private-current';update_option(Settings::OPTION,$o);ob_start();$a->render('ai');$html=ob_get_clean();verify(!str_contains($html,'fixture-private-')&&str_contains($html,'value="'.$p.'" selected')&&str_contains($html,'value="test_chat"')&&str_contains($html,'value="test_embed"')&&str_contains($html,'value="load_models"')&&str_contains($html,'connection_revision'),"render $loc $p; no key exposure");verify(str_contains($html,'name="settings[api_base]"')===($p==='custom')&&str_contains($html,'value="responses"')===($p!=='openrouter'),"provider-specific fields $loc $p");}}
update_option(Settings::OPTION,$legacy);$_POST=['_wpnonce'=>'w27studio_switch_provider','provider'=>'openai'];$GLOBALS['allowed']=false;verify(expect(fn()=>$a->handleSwitchProvider(),'denied:403'),'unauthorized switch blocked');$GLOBALS['allowed']=true;$_POST['_wpnonce']='invalid';verify(expect(fn()=>$a->handleSwitchProvider(),'nonce'),'switch nonce required');$_POST['_wpnonce']='w27studio_switch_provider';verify(expect(fn()=>$a->handleSwitchProvider(),'redirect:'),'switch handler redirects');$o=Settings::get();verify($o['provider']==='openai'&&$o['api_key']===''&&!$o['semantic']&&!$o['external_enabled'],'switch handler isolates credentials');
$_POST=['_wpnonce'=>'w27studio_save','return_tab'=>'ai','connection_revision'=>'stale','settings'=>['api_key'=>'fixture-private-openai','external_enabled'=>'1','semantic'=>'1']];verify(expect(fn()=>$a->handleSave(),'denied:409'),'stale connection form blocked');$_POST['connection_revision']=Settings::profileSignature($o);verify(expect(fn()=>$a->handleSave(),'redirect:'),'save handler redirects');verify(Settings::get()['api_key']==='fixture-private-openai','saved key belongs to selected provider');$_POST=['_wpnonce'=>'w27studio_switch_provider','provider'=>'proxyapi_openai'];verify(expect(fn()=>$a->handleSwitchProvider(),'redirect:')&&Settings::get()['api_key']==='fixture-private-proxy','switch restores original key');verify($GLOBALS['calls']===[],'render and configuration never contact external API');$legacy['panel_title']='Custom title';$legacy['placeholder']='Custom placeholder';$legacy['rag_answer_instructions']='Keep me';update_option(Settings::OPTION,$legacy);
$GLOBALS['locale']='en_US';$GLOBALS['available_languages']=[];
ob_start();$a->render('ux');$html=ob_get_clean();verify(str_contains($html,'name="settings[widget_language]"')&&str_contains($html,'name="reset_widget_labels"')&&str_contains($html,'profile.php'),'widget language control, reset and profile link');
$_POST=['_wpnonce'=>'w27studio_save','return_tab'=>'ux','settings'=>['widget_language'=>'ru_RU']];verify(expect(fn()=>$a->handleSave(),'redirect:'),'language form save');
$o=Settings::get();verify($o['widget_language']==='ru_RU'&&$o['api_key']===$legacy['api_key']&&$o['semantic']&&$o['panel_title']==='Custom title','language save preserves provider and custom title');
ob_start();$a->render('ux');$html=ob_get_clean();verify(str_contains($html,'value="ru_RU" selected')&&str_contains($html,'Custom title')&&str_contains($html,__('Widget language','web27-site-search'))&&$GLOBALS['locale']==='en_US','saved language preview renders and restores admin locale');
$_POST['reset_widget_labels']='1';verify(expect(fn()=>$a->handleSave(),'redirect:'),'explicit label reset saves');$raw=get_option(Settings::OPTION);verify(!isset($raw['launcher_label'],$raw['panel_title'],$raw['placeholder'])&&!array_key_exists('launcher_label',$raw)&&!array_key_exists('panel_title',$raw)&&!array_key_exists('placeholder',$raw)&&$raw['rag_answer_instructions']==='Keep me'&&$raw['api_key']===$legacy['api_key'],'reset removes only three label overrides');
$_POST=['_wpnonce'=>'w27studio_save','return_tab'=>'ux','settings'=>['widget_language'=>'invalid']];verify(expect(fn()=>$a->handleSave(),'redirect:')&&Settings::widgetLanguage()==='auto','invalid widget locale normalized');
verify($GLOBALS['calls']===[],'language settings send no network requests');
$_SERVER['REQUEST_METHOD']='GET';$_POST=['_wpnonce'=>'w27studio_save','return_tab'=>'ux','settings'=>['widget_language'=>'en_US']];
$before=get_option(Settings::OPTION);verify(expect(fn()=>$a->handleSave(),'denied:405')&&get_option(Settings::OPTION)===$before,'GET cannot mutate saved settings even with a nonce');
$_POST=['_wpnonce'=>'w27ss_sources'];verify(expect(fn()=>Admin::handleSources(),'denied:405'),'GET cannot save source settings');
$_POST=['_wpnonce'=>'w27ss_console','task'=>'test_chat'];verify(expect(fn()=>\WEB27\SiteSearch\console_action(),'denied:405'),'GET cannot trigger provider test');
$_SERVER['REQUEST_METHOD']='POST';$_POST=['_wpnonce'=>'w27studio_pause','action'=>'w27studio_pause'];
verify(expect(fn()=>$a->handleStop(),'nonce'),'stop handler rejects a pause nonce even with mismatched POST action');
$_GET=['tab'=>['bad']];ob_start();$a->render('guide');$html=ob_get_clean();verify(str_contains($html,'w27-guide'),'array input does not affect forced guide');unset($_GET['tab']);
echo "TOTAL $n PASS (isolated administrative forms and handlers)\n";
