<?php
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Console.php';
use WEB27\SiteSearch\{Settings,Gateway,Knowledge};
use function WEB27\SiteSearch\{provider_catalog,provider_error_label,query};
function wp_safe_remote_get($url,$args){$GLOBALS['calls'][]=['url'=>$url,'args'=>$args,'method'=>'GET'];return $GLOBALS['mock_get'][$url]??$GLOBALS['mock_response'];}
function get_current_user_id(){return 1;}
$n=0;
function check($condition,$label){global $n;if(!$condition)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
function response($data,$code=200){return ['code'=>$code,'body'=>json_encode($data)];}
function fail(callable $f,$want){try{$f();return false;}catch(RuntimeException $e){return str_contains($e->getMessage(),$want);}}
$legacy=['provider'=>'proxyapi_openai','api_key'=>'fixture-proxy','model'=>'openai/gpt-6-luna','embed_model'=>'openai/text-embedding-3-small','semantic'=>true,'external_enabled'=>true,'rag_limit'=>9,'launcher_label'=>'Custom saved'];
update_option(Settings::OPTION,$legacy);
check(Settings::get()['api_key']==='fixture-proxy' && Settings::embeddingsConfigured(Settings::get()),'legacy ProxyAPI remains configured');
check(Settings::vectorModel(Settings::get())===$legacy['embed_model'],'legacy vector identity preserved');
check(get_option(Settings::OPTION)===$legacy,'reading settings does not mutate storage');
foreach (['openai','openrouter','custom','proxyapi_compatible'] as $p){
 $next=Settings::switchProvider($legacy,$p);check($next['api_key']===''&&!$next['external_enabled']&&!$next['semantic'],'switch isolates key and disables paid options: '.$p);
 check($next['rag_limit']===9&&$next['launcher_label']==='Custom saved','switch retains unrelated settings: '.$p);
 check(Settings::switchProvider($next,'proxyapi_openai')['api_key']==='fixture-proxy','switch restores original secret: '.$p);
}
check(fail(fn()=>Settings::switchProvider($legacy,'invalid'),'provider_switch_unavailable'),'unknown provider rejected');
$cases=['openai'=>'https://api.openai.com/v1','openrouter'=>'https://openrouter.ai/api/v1','proxyapi_openai'=>'https://api.proxyapi.ru/v1','proxyapi_compatible'=>'https://api.proxyapi.ru/v1','custom'=>'https://gateway.example.test/v1'];
foreach($cases as $p=>$base){
 $o=array_replace(Settings::defaults(),Settings::profileDefaults($p),['api_key'=>'fixture-'.$p,'external_enabled'=>true,'semantic'=>true,'custom_embeddings_confirmed'=>true,'daily_ai_budget'=>0,'proxyapi_log_metadata'=>true]);
 if($p==='custom'){$o['api_base']=$base;$o['model']='chat-fixture';$o['embed_model']='embed-fixture';}
 update_option(Settings::OPTION,$o);$o=Settings::get();
 check(Settings::endpoint($o)===$base.'/chat/completions','chat endpoint '.$p);check(Settings::embeddingsEndpoint($o)===$base.'/embeddings','embedding endpoint '.$p);
 $GLOBALS['mock_response']=response(['choices'=>[['message'=>['content'=>'Fixture answer [page:1]']]],'usage'=>['prompt_tokens'=>8,'completion_tokens'=>3]]);
 $r=(new Gateway)->answer('What is the price?',[['title'=>'Fixture','url'=>'https://example.test/resource/1/','excerpt'=>'Price is 90 USD.']],[],'fixture-session',['language'=>'en']);
 $call=end($GLOBALS['calls']);$payload=json_decode($call['args']['body'],true);
 check($call['url']===$base.'/chat/completions'&&$call['args']['headers']['Authorization']==='Bearer fixture-'.$p&&$call['args']['redirection']===0,'chat URL, bearer and redirects '.$p);
 check($r['answer']==='Fixture answer [page:1]'&&$r['input_tokens']===8&&$r['output_tokens']===3&&$payload['model']===$o['model'],'chat response and payload '.$p);
 check(isset($call['args']['headers']['X-Log-Tag'])===str_starts_with($p,'proxyapi_'),'metadata isolation '.$p);
 if($p==='openai')check($payload['store']===false&&isset($payload['max_completion_tokens'])&&!isset($payload['temperature']),'OpenAI GPT-5 request shape');
 if($p==='openrouter')check(isset($payload['max_tokens'])&&!isset($payload['max_completion_tokens'],$payload['temperature'],$payload['prompt_cache_key']),'OpenRouter request shape');
 $GLOBALS['mock_response']=response(['data'=>[['index'=>1,'embedding'=>[0,2]],['index'=>0,'embedding'=>[3,4]]]]);
 $v=Knowledge::embed(['first','second'],$o);$call=end($GLOBALS['calls']);$payload=json_decode($call['args']['body'],true);
 check($call['url']===$base.'/embeddings'&&$call['args']['headers']['Authorization']==='Bearer fixture-'.$p&&$call['args']['redirection']===0,'embedding URL, bearer and redirects '.$p);
 check(abs($v[0][0]-.6)<1e-9&&abs($v[1][1]-1)<1e-9&&$payload['model']===$o['embed_model']&&$payload['input']===['first','second'],'embedding order, normalization, payload '.$p);
 foreach([401,402,403,404,429,500] as $status){$GLOBALS['mock_response']=response([], $status);check(fail(fn()=>Knowledge::embed(['fixture'],$o),'gateway_http_'.$status),'embedding HTTP '.$status.' '.$p);check(fail(fn()=>(new Gateway)->answer('fixture',[],[]),'gateway_http_'.$status),'chat HTTP '.$status.' '.$p);}
 $GLOBALS['mock_response']=new WP_Error('timeout','fixture');check(fail(fn()=>Knowledge::embed(['fixture'],$o),'gateway_request_failed'),'embedding timeout '.$p);
 $GLOBALS['mock_response']=response(['choices'=>[['message'=>['content'=>['not text']]]]]);check(fail(fn()=>(new Gateway)->answer('fixture',[],[]),'gateway_empty_answer'),'malformed chat '.$p);
 $GLOBALS['mock_response']=response(['data'=>[['index'=>0,'embedding'=>[1,2]],['index'=>0,'embedding'=>[1,2]]]]);check(fail(fn()=>Knowledge::embed(['fixture','fixture2'],$o),'Invalid vector'),'reject duplicate index '.$p);
 $GLOBALS['mock_response']=response(['data'=>[['index'=>0,'embedding'=>[1,2]],['index'=>1,'embedding'=>[3,4,5]]]]);check(fail(fn()=>Knowledge::embed(['fixture','fixture2'],$o),'Invalid vector'),'reject inconsistent dimensions '.$p);
 $GLOBALS['mock_response']=response(['data'=>[['index'=>0,'embedding'=>[1,2]]]]);check(fail(fn()=>Knowledge::embed(['fixture','fixture2'],$o),'incomplete batch'),'reject incomplete batch '.$p);
 $o['api_protocol']='responses';update_option(Settings::OPTION,$o);
 $GLOBALS['mock_response']=$p==='openrouter'?response(['choices'=>[['message'=>['content'=>'test']]]]):response(['output'=>[['content'=>[['type'=>'output_text','text'=>'test']]]],'usage'=>['input_tokens'=>2,'output_tokens'=>1]]);
 $r=(new Gateway)->answer('fixture',[],[]);check(end($GLOBALS['calls'])['url']===$base.($p==='openrouter'?'/chat/completions':'/responses')&&$r['answer']==='test','protocol routing '.$p);
 $GLOBALS['mock_get']=[$base.'/models'=>response(['data'=>[['id'=>'fixture/chat'],['id'=>'fixture/chat'],['id'=>'fixture/embedding'],['id'=>'<script>']]]),$base.'/embeddings/models'=>response(['data'=>[['id'=>'fixture/embedding']]])];
 $catalog=provider_catalog(Settings::get());check(in_array('fixture/chat',$catalog['chat'],true)&&!in_array('<script>',$catalog['chat'],true)&&count(array_keys($catalog['chat'],'fixture/chat',true))===1&&$catalog['embed']===['fixture/embedding'],'model catalog parsing '.$p);
}
$custom=array_replace(Settings::defaults(),['provider'=>'custom','api_base'=>'https://old.example.test/v1','api_key'=>'fixture-custom','model'=>'chat','embed_model'=>'embed','external_enabled'=>true,'semantic'=>true]);
update_option(Settings::OPTION,$custom);check(!Settings::embeddingsConfigured(Settings::get()),'legacy custom semantic not silently activated');
$a=Settings::sanitize(['api_base'=>'https://new.example.test/v1','api_key'=>'']);check($a['api_key']===''&&!$a['external_enabled']&&!$a['semantic'],'custom endpoint change clears old key and pauses requests');
$a=Settings::sanitize(['api_base'=>'https://new.example.test/v1','api_key'=>'fixture-new','semantic'=>true,'custom_embeddings_confirmed'=>true]);check($a['api_key']==='fixture-new'&&$a['custom_embeddings_confirmed'],'custom new key and explicit embedding consent');
$a=Settings::sanitize(['api_key'=>'','clear_api_key'=>1]);check($a['api_key']==='','remove current key');
foreach(['http://unsafe.test/v1','https://user:secret@unsafe.test/v1','https://unsafe.test/v1?key=foo','https://unsafe.test/v1#x',''] as $url){$a=Settings::sanitize(['api_base'=>$url,'api_key'=>'fixture-new']);check(Settings::endpoint($a)===''&&$a['api_key']==='','reject invalid custom base '.$url);}
$hashes=[];foreach($cases as $p=>$base){$o=array_replace(Settings::defaults(),['provider'=>$p,'api_base'=>$base,'embed_model'=>'same-model']);$hashes[$p]=Settings::vectorModel($o);}
check(count(array_unique($hashes))===4&&$hashes['proxyapi_openai']===$hashes['proxyapi_compatible'],'same model segregated by provider, legacy ProxyAPI alias shared');
$o=array_replace(Settings::defaults(),['provider'=>'custom','api_base'=>'https://a.example.test/v1','embed_model'=>'same']);$h=Settings::vectorModel($o);$o['api_base']='https://b.example.test/v1';check(Settings::vectorModel($o)!==$h,'custom host namespace separation');$h=Settings::vectorModel($o);$o['embed_model']='other';check(Settings::vectorModel($o)!==$h,'model namespace separation');
 
class VectorDB extends FixtureDB {function get_var($q){[$sql,$args]=$q;if(str_contains($sql,'WHERE model=%s')){foreach($GLOBALS['chunks'] as $c)if($c->model===$args[1])return $c->id;}return null;}}
$GLOBALS['wpdb']=new VectorDB; $GLOBALS['posts']=[];$GLOBALS['chunks']=[];
$o=array_replace(Settings::defaults(),Settings::profileDefaults('openai'),['api_key'=>'fixture','semantic'=>true,'external_enabled'=>false]);update_option(Settings::OPTION,$o);
seed(1,'Unrelated fixture A','Alpha');seed(2,'Unrelated fixture B','Beta');seed(3,'Unrelated fixture C','Gamma');
foreach($GLOBALS['chunks'] as $i=>$c){$c->model=$i===0?Settings::vectorModel($o):($i===1?'text-embedding-3-small':Settings::vectorModel($o));$c->vector=$i===2?'[1,0,0]':'[1,0]';}
$GLOBALS['mock_response']=response(['data'=>[['index'=>0,'embedding'=>[1,0]]]]);
$hits=Knowledge::rank('nomatch',['page','post']);check(count($hits)===1&&$hits[0]['post_id']===1,'rank excludes same-dimension foreign provider and different dimension');
$before=count($GLOBALS['calls']);$v=null;$hits=\WEB27\SiteSearch\sources('Alpha',['page','post'],false);check(count($GLOBALS['calls'])===$before && $hits[0]['post_id']===1,'read-only passage preview does not send embeddings');
$GLOBALS['mock_response']=response([],429);check(Knowledge::rank('Alpha',['page','post'])[0]['post_id']===1,'embedding failure falls back to lexical retrieval');
$o['semantic']=false;update_option(Settings::OPTION,$o);$before=count($GLOBALS['calls']);Knowledge::rank('Alpha',['page','post']);check(count($GLOBALS['calls'])===$before,'disabled embedding sends no request');
foreach(['openai','openrouter','proxyapi_openai','custom'] as $p){$o=array_replace(Settings::defaults(),Settings::profileDefaults($p),['api_key'=>'fixture','external_enabled'=>true,'daily_ai_budget'=>0,'rate_limit'=>120]);if($p==='custom'){$o['api_base']='https://custom.example.test/v1';$o['model']='fixture';}update_option(Settings::OPTION,$o);$GLOBALS['mock_response']=response([],401);$r=query(request('Alpha','en_US'));check($r->get_data()['mode']==='local_fallback'&&$r->get_data()['sources'][0]['post_id']===1,'public pipeline fallback '.$p);}
check(str_contains(provider_error_label('gateway_http_401'),'401'),'safe diagnostics error mapping');
echo "TOTAL $n PASS (isolated provider contracts; no HTTP network or real API keys)\n";
