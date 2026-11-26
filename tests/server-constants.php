<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\Settings;
$mode=$argv[1]??'different';
$old=['provider'=>'proxyapi_openai','api_key'=>'fixture-old-provider-secret','external_enabled'=>true,'semantic'=>true,'custom_embeddings_confirmed'=>true];
update_option(Settings::OPTION,$old);
define('WEB27_SITE_API_URL',$mode==='same'?'https://api.proxyapi.ru/v1':'https://custom.example.test/v1');
if($mode==='explicit')define('WEB27_SITE_API_KEY','fixture-explicit-custom-secret');
$o=Settings::get();$want=match($mode){'same'=>$old['api_key'],'explicit'=>'fixture-explicit-custom-secret',default=>''};
$ok=$o['api_key']===$want&&get_option(Settings::OPTION)===$old&&($mode!=='different'||(!Settings::gatewayConfigured()&&!Settings::embeddingsConfigured($o)));
echo ($ok?'PASS ':'FAIL ').'server constant '.$mode.' preserves stored profile and isolates credentials'."\n";exit($ok?0:1);
