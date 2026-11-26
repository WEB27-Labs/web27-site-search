<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\{Knowledge,Settings};
class CronDB extends FixtureDB {
 public array $writes=[];
 function get_results($q){[$sql,$args]=$q;if(str_contains($sql,'c.model<>%s'))return array_values(array_filter($GLOBALS['chunks'],fn($r)=>$r->model!==$args[2]));return parent::get_results($q);}
 function update($table,$values,$where){$this->writes[]=['table'=>$table,'values'=>$values,'where'=>$where];foreach($GLOBALS['chunks'] as $r){if($r->id===$where['id']){foreach($values as $k=>$v)$r->$k=$v;}}return 1;}
}
$n=0;function check($ok,$label){global $n;if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
foreach(Settings::providers() as $provider){
 $GLOBALS['wpdb']=new CronDB;$GLOBALS['chunks']=[];$GLOBALS['posts']=[];$GLOBALS['options']=[];$GLOBALS['calls']=[];
 $o=array_replace(Settings::defaults(),Settings::profileDefaults($provider),['api_key'=>'fixture','semantic'=>true,'custom_embeddings_confirmed'=>true]);
 if($provider==='custom'){$o['api_base']='https://fixture.example.test/v1';$o['embed_model']='fixture-embed';}
 update_option(Settings::OPTION,$o);update_option('w27ss_cursor',-1);
 seed(1,'English','Public English text');seed(2,'Русский','Открытый русский текст');seed(3,'Private','private text','private');seed(4,'Already indexed','Existing vector');$GLOBALS['chunks'][3]->model=Settings::vectorModel($o);$GLOBALS['chunks'][3]->vector='[1,0]';
 $GLOBALS['mock_response']=['code'=>200,'body'=>'{"data":[{"index":1,"embedding":[0,2]},{"index":0,"embedding":[3,4]}]}'];
 Knowledge::tick();$call=$GLOBALS['calls'][0];$payload=json_decode($call['args']['body'],true);
 check(count($GLOBALS['calls'])===1&&$call['url']===Settings::embeddingsEndpoint($o)&&$payload['input']===['Public English text','Открытый русский текст'],'batch endpoint and public data '.$provider);
 check(count($GLOBALS['wpdb']->writes)===2&&$GLOBALS['chunks'][0]->model===Settings::vectorModel($o)&&json_decode($GLOBALS['chunks'][0]->vector,true)===[0.6,0.8]&&$GLOBALS['chunks'][2]->vector===null,'writes normalized and isolated model '.$provider);
 Knowledge::tick();check(count($GLOBALS['calls'])===1,'already indexed vectors not billed again '.$provider);
 $GLOBALS['chunks'][0]->model='old';$GLOBALS['mock_response']=['code'=>429,'body'=>'{}'];Knowledge::tick();check(count($GLOBALS['wpdb']->writes)===2&&get_option('w27ss_error')==='gateway_http_429','failure keeps existing vectors '.$provider);
 $o['semantic']=false;update_option(Settings::OPTION,$o);$before=count($GLOBALS['calls']);Knowledge::tick();check(count($GLOBALS['calls'])===$before,'disabled semantic background no network '.$provider);
}
echo "TOTAL $n PASS (isolated cron/database fixtures)\n";
