<?php
require __DIR__.'/admin-providers.php';
use WEB27\SiteSearch\Settings;
$d=Settings::design(['placement'=>'top-left','mobile'=>'bottom-right','shape'=>'circle','content'=>'text','window'=>'right','cards'=>'carousel','width'=>99999,'height'=>-5,'offset_x'=>-1,'offset_y'=>999,'radius'=>40,'accent'=>'#ffcc00','surface'=>'red;display:none','text'=>['bad']]);
verify($d['placement']==='top-left'&&$d['mobile']==='bottom-right'&&$d['width']===1100&&$d['height']===420&&$d['offset_x']===8&&$d['offset_y']===96&&$d['radius']===32,'design range and choices validation');
verify($d['accent']==='#ffcc00'&&$d['surface']==='#14263c'&&$d['text']==='#f3f7fc','invalid CSS values rejected');
$before=get_option(Settings::OPTION);$_POST=['_wpnonce'=>'w27studio_save','return_tab'=>'ux','design'=>$d,'appearance'=>'custom'];verify(expect(fn()=>$a->handleSave(),'redirect:'),'design form saves');
verify(Settings::design()===$d&&Settings::appearance()==='custom'&&get_option(Settings::OPTION)===$before,'independent design save leaves knowledge and credentials untouched');
$_POST['_wpnonce']='invalid';verify(expect(fn()=>$a->handleSave(),'nonce'),'design nonce required');$_POST['_wpnonce']='w27studio_save';$GLOBALS['allowed']=false;verify(expect(fn()=>$a->handleSave(),'denied:403'),'design requires capability');$GLOBALS['allowed']=true;
foreach(['en_US','ru_RU'] as $loc){$GLOBALS['locale']=$loc;ob_start();$a->render('ux');$html=ob_get_clean();verify(str_contains($html,'name="design[placement]"')&&str_contains($html,'value="top-left" selected')&&str_contains($html,'data-w27public-preview-launcher')&&str_contains($html,$loc==='ru_RU'?'Конструктор дизайна':'Design Studio'),'design admin rendering '.$loc);}
foreach(['https://example.test/wp-content/uploads/cover.webp','https://example.test/wp-content/plugins/test/cover.png'] as $url){verify(Settings::sourceImage($url)===$url,'public same-origin image allowed');}
foreach(['https://evil.test/cover.png','https://example.test/wp-json/secret.png','https://example.test/wp-content/cover.png?token=secret','https://example.test/cover.svg','javascript:alert(1)','https://user:pass@example.test/cover.png','https://example.test/cover.jpg#token'] as $url){verify(Settings::sourceImage($url)==='','unsafe image blocked '.$url);}
verify($GLOBALS['calls']===[],'design and image validation make no external API requests');
echo "TOTAL additional design assertions 18 PASS\n";
