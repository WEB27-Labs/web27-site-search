<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\{Settings,Knowledge,Indexer,SearchEngine,Conversation,Gateway,Database};
use function WEB27\SiteSearch\sources;
ob_start();
$n=0;$fails=0;
function check($ok,$label){global $n,$fails;$n++;if(!$ok)$fails++;echo ($ok?'PASS ':'FAIL ').$label."\n";}
function rejects(callable $f,string $code):bool{try{$f();return false;}catch(RuntimeException $e){return $e->getMessage()===$code;}}
function wp_safe_remote_get($url,$args){$GLOBALS['calls'][]=['url'=>$url,'args'=>$args,'method'=>'GET'];return $GLOBALS['get_responses'][$url]??new WP_Error('timeout','fixture');}
function htmlResponse(string $title,string $body,int $code=200,string $type='text/html'):array{return ['code'=>$code,'body'=>"<html><head><title>$title</title></head><body><main>$body</main></body></html>",'headers'=>['content-type'=>$type]];}
function setup(array $opts=[]){$GLOBALS['options']=[Settings::OPTION=>$opts,'w27ss_cursor'=>-1];$GLOBALS['posts']=[];$GLOBALS['chunks']=[];$GLOBALS['virtual']=[];$GLOBALS['transients']=[];$GLOBALS['calls']=[];$GLOBALS['get_responses']=[];$GLOBALS['wpdb']=new AuditDB();}
class AuditDB extends FixtureDB {
 public array $writes=[];public array $deletes=[];public array $known=[];public bool $modelExists=false;
 function get_var($q){if(is_array($q)&&str_contains($q[0],'WHERE model=%s'))return $this->modelExists?1:null;if(is_array($q)&&str_contains($q[0],'WHERE url_hash=%s'))return 77;return null;}
 function get_col($q){return $this->known;}
 function delete($t,$where,$format=null){$this->deletes[]=[$t,$where];return 1;}
 function get_results($q){[$sql,$args]=$q;if(str_contains($sql,'c.model<>%s'))return array_values(array_filter($GLOBALS['chunks'],fn($r)=>$r->model!==$args[2]));return parent::get_results($q);}
 function update($t,$v,$w){$this->writes[]=[$t,$v,$w];return 1;}
}
setup(['index_virtual_pages'=>true]);
$url=home_url('/public-guide/');$title='Orchard guide';$text='Orchard entry costs 12 USD on Saturday.';
$GLOBALS['virtual']=[(object)['source_url'=>$url,'title'=>$title,'content'=>$text]];
$GLOBALS['get_responses'][$url]=htmlResponse($title,$text);
check(count(SearchEngine::virtualSources('Orchard'))===1,'fresh anonymous virtual page remains searchable');
$call=end($GLOBALS['calls']);check(is_array($call)&&$call['args']['redirection']===0&&$call['args']['cookies']===[]&&$call['args']['timeout']===2&&!isset($call['args']['headers']['Authorization']),'anonymous bounded same-origin verification');
foreach([401,403,404,410,302,429,503] as $status){$GLOBALS['get_responses'][$url]=htmlResponse($title,$text,$status);check(SearchEngine::virtualSources('Orchard')===[],"stale virtual hidden on HTTP $status");}
$GLOBALS['get_responses'][$url]=new WP_Error('timeout','fixture');check(SearchEngine::virtualSources('Orchard')===[],'stale virtual hidden on timeout');
$GLOBALS['get_responses'][$url]=htmlResponse($title,'Orchard is closed; price information removed.');check(SearchEngine::virtualSources('Orchard')===[],'removed virtual text not disclosed even with HTTP 200');
$GLOBALS['get_responses'][$url]=htmlResponse('Login',$text);check(SearchEngine::virtualSources('Orchard')===[],'stale virtual title not returned');
$GLOBALS['get_responses'][$url]=htmlResponse($title,$text,200,'application/json');check(SearchEngine::virtualSources('Orchard')===[],'non-HTML virtual response rejected');
$GLOBALS['get_responses'][$url]=htmlResponse($title,'<form class="post-password-form">'.$text.'</form>');check(SearchEngine::virtualSources('Orchard')===[],'password form rejected');
check(count($GLOBALS['virtual'])===1 && !$GLOBALS['wpdb']->deletes,'read-time verification does not erase index');
$GLOBALS['virtual']=[];$GLOBALS['calls']=[];
for($i=1;$i<=5;$i++){$u=home_url('/virtual-'.$i.'/');$GLOBALS['virtual'][]=(object)['source_url'=>$u,'title'=>$title,'content'=>$text];$GLOBALS['get_responses'][$u]=htmlResponse($title,$text);}
$GLOBALS['virtual'][]=(object)['source_url'=>'https://other.test/','title'=>$title,'content'=>$text];$GLOBALS['virtual'][]=clone $GLOBALS['virtual'][0];
$hits=SearchEngine::virtualSources('Orchard');check(count($GLOBALS['calls'])===3&&count($hits)===4,'at most three unique virtual URLs, repeated chunks reuse snapshot, external URL rejected');
setup(['index_virtual_pages'=>true,'index_batch_size'=>2]);$queue=[home_url('/retry-1/'),home_url('/retry-2/')];update_option(Indexer::QUEUE_OPTION,$queue);update_option(Indexer::STATE_OPTION,['running'=>true,'paused'=>false,'total'=>2]);
$GLOBALS['get_responses'][$queue[0]]=htmlResponse('Title','Temporary failure',429);(new Indexer())->processBatch();
check(Indexer::queue()===$queue&&Indexer::state()['paused']===true&&count($GLOBALS['calls'])===1&&!$GLOBALS['wpdb']->deletes,'429 pauses batch, preserves current and remaining URLs and index');
setup(['index_virtual_pages'=>false]);update_option(Indexer::QUEUE_OPTION,$queue);update_option(Indexer::STATE_OPTION,['running'=>true,'paused'=>false]);(new Indexer())->processBatch();check(Indexer::queue()===$queue&&!$GLOBALS['calls'],'disabled sitemap does not consume queued URLs');
setup(['index_virtual_pages'=>true]);
$root=Settings::get()['sitemap_url'];$child=home_url('/child.xml');$xml='<sitemapindex><sitemap><loc>'.$child.'</loc></sitemap></sitemapindex>';
if(!function_exists('simplexml_load_string')) {
    class SimpleXMLElement {public array $sitemap=[];public function getName(){return 'sitemapindex';}}
    function simplexml_load_string($value,$class,$flags){$obj=new SimpleXMLElement();preg_match_all('~<loc>([^<]+)</loc>~',$value,$matches);foreach($matches[1] as $url)$obj->sitemap[]=(object)['loc'=>$url];return $obj;}
    echo "NOTE nested sitemap traversal uses a test-only XML tree adapter; real SimpleXML parsing NOT RUN.\n";
}
$GLOBALS['get_responses'][$root]=['code'=>200,'body'=>$xml];$GLOBALS['get_responses'][$child]=new WP_Error('timeout','fixture');$GLOBALS['wpdb']->known=[home_url('/keep/')];update_option(Indexer::QUEUE_OPTION,['existing-queue']);
check(rejects(fn()=>(new Indexer())->loadSitemapQueue(),'sitemap_child_unavailable')&&Indexer::queue()===['existing-queue']&&!$GLOBALS['wpdb']->deletes,'failed child sitemap aborts reconciliation without deleting previous index or queue');
setup();seed(1,'Orchard','Old Orchard price 12 USD');$GLOBALS['posts'][1]->post_content='New Orchard price 20 USD';
check(Knowledge::rank('Orchard',['page','post'])===[],'stale standard passage excluded after content changed without sync');
seed(2,'Cameras','Cameras are available');$GLOBALS['posts'][2]->post_password='protected';check(Knowledge::rank('Cameras',['page','post'])===[],'password-protected standard page excluded');
setup(['semantic'=>true,'api_key'=>'fixture-not-real']);seed(1,'Old text','Removed information');$GLOBALS['posts'][1]->post_content='Replacement text';Knowledge::tick();check(!$GLOBALS['calls']&&!$GLOBALS['wpdb']->writes,'stale passage not sent to embeddings provider');
setup(['semantic'=>true,'api_key'=>'fixture-not-real']);seed(1,'Vector','Vector current text');$GLOBALS['mock_response']=['code'=>429,'body'=>'{}'];Knowledge::tick();Knowledge::tick();check(count($GLOBALS['calls'])===1&&!$GLOBALS['wpdb']->writes,'failed embedding batch has five-minute automatic retry delay');
setup(['semantic'=>true,'api_key'=>'fixture-not-real']);seed(1,'Orchard','Orchard current text');$GLOBALS['chunks'][0]->model=Settings::vectorModel(Settings::get());$GLOBALS['chunks'][0]->vector='[1e400,0]';$GLOBALS['wpdb']->modelExists=true;$GLOBALS['mock_response']=['code'=>200,'body'=>'{"data":[{"index":0,"embedding":[1,0]}]}'];check(Knowledge::rank('Unrelated',['page','post'])===[],'nonfinite stored vector cannot create invalid semantic hit');
setup(['rag_limit'=>3,'rag_context_chars'=>4000]);update_option('w27ss_cursor',0);for($i=1;$i<=5;$i++)seed($i,'Orchard '.$i,'Orchard '.str_repeat('abcdefghij ',90));$GLOBALS['chunks']=[];check(count(sources('Orchard',['page','post']))===3,'initial WordPress fallback observes configured source count');
setup(['external_enabled'=>true,'api_key'=>'fixture-secret-not-real','model'=>'fixture-model','api_protocol'=>'responses']);$GLOBALS['mock_response']=['code'=>200,'body'=>'{"output":[{"type":"reasoning","content":[{"type":"output_text","text":"internal-not-answer"}]},{"type":"message","content":[{"type":"output_text","text":"Verified public answer"}]}]}'];
check((new Gateway())->answer('fixture',[],[])['answer']==='Verified public answer','Responses parser uses message output text only');
$GLOBALS['mock_response']=['code'=>200,'body'=>'{"output_text":"Error echoes fixture-secret-not-real"}'];check(rejects(fn()=>(new Gateway())->answer('fixture',[],[]),'gateway_sensitive_response'),'provider echo of active credential blocked');
$_COOKIE['w27studio_session']='bad';$memory=new Conversation();$memory->ensureSession();check(strlen($memory->sessionId())>=16,'malformed session cookie repaired');
$memory->append('first','reply');$other=new Conversation('panel-2');check(count($memory->history())===2&&$other->history()===[],'widget instances isolate transient history');$memory->clear();check($memory->history()===[],'session reset clears transient');
echo "TOTAL ".($n-$fails)."/$n PASS; $fails FAIL (mock transport/database, no live services)\n";ob_end_flush();exit($fails?1:0);
