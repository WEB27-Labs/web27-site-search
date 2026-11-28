<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\{Universal,Connectors,Settings,Knowledge,Welcome};
function get_current_user_id(){return $GLOBALS['user']??0;}
function wp_set_current_user($id){$GLOBALS['user']=$id;}
function apply_filters($tag,$v,...$args){foreach($GLOBALS['filters'][$tag]??[] as $f){$v=$f($v,...$args);}return $v;}
function get_post_types($args,$out){return $GLOBALS['fixture_public_types']??[];}
function get_post_type_object($type){return $GLOBALS['fixture_public_types'][$type]??null;}
function is_post_type_viewable($obj){return $obj->publicly_queryable;}
function get_taxonomies($args,$out){return $GLOBALS['fixture_taxonomies']??[];}
function get_taxonomy($type){return $GLOBALS['fixture_taxonomies'][$type]??null;}
function is_taxonomy_viewable($obj){return $obj->publicly_queryable;}
function get_object_taxonomies($type,$out){return $type==='product'?get_taxonomies([],[]):[];}
function get_the_terms($id,$tax){return $GLOBALS['fixture_terms'][$tax]??[];}
function get_terms($args){return $GLOBALS['fixture_terms'][$args['taxonomy']]??[];}
function get_term_link($term){return home_url('/category/'.$term->term_id.'/');}
function add_action(...$args){}
function plugin_dir_path($f){return dirname($f).'/';}
function plugin_dir_url($f){return home_url('/wp-content/plugins/'.basename(dirname($f)).'/');}
function add_query_arg($k,$v=null,$u=null){if(is_array($k)){$a=$k;$u=$v;}else{$a=[$k=>$v];}return $u.(str_contains($u,'?')?'&':'?').http_build_query($a);}
class UniversalDB extends FixtureDB {
 public array $vectors=[];public int $writes=0;
 function get_results($q){[$sql,$args]=$q;if((str_contains($sql,'SELECT fingerprint,`vector`')||str_contains($sql,'SELECT fingerprint FROM'))){return array_values(array_filter($this->vectors,fn($r)=>$r->model===$args[1]&&in_array($r->fingerprint,array_slice($args,2),true)));}return parent::get_results($q);}
 function replace($table,$data){$this->writes++;$this->vectors[$data['fingerprint'].$data['model']]=(object)$data;return 1;}
}
$n=0;function check($ok,$label){global $n;if(!$ok){throw new RuntimeException('FAIL '.$label);}$n++;echo "PASS $label\n";}
function config(array $ids,bool $semantic=false){update_option(Universal::OPTION,['enabled'=>$ids,'semantic'=>$semantic]);Universal::reset();}
function item($id,$title,$text,$url=null,$public=true){return ['id'=>$id,'title'=>$title,'text'=>$text,'url'=>$url??home_url('/catalog/'.$id.'/'),'public'=>$public];}
$GLOBALS['wpdb']=new UniversalDB;
$GLOBALS['fixture_public_types']=['product'=>(object)['label'=>'Products','public'=>true,'publicly_queryable'=>true],'secret'=>(object)['label'=>'Secret','public'=>true,'publicly_queryable'=>false]];
$GLOBALS['fixture_taxonomies']=['product_cat'=>(object)['name'=>'product_cat','label'=>'Product categories','public'=>true,'publicly_queryable'=>true],'internal'=>(object)['name'=>'internal','label'=>'Internal','public'=>true,'publicly_queryable'=>false]];
$GLOBALS['fixture_terms']=['product_cat'=>[(object)['term_id'=>1,'name'=>'Ceramics','description'=>'Handmade pottery']], 'internal'=>[(object)['term_id'=>2,'name'=>'Private group','description'=>'Never index']]];
seed(1,'Porcelain cup','Handmade cup', 'publish','','product');seed(2,'Draft Cup','Draft secret','draft','','product');seed(3,'Password Cup','Secret text','publish','pw','product');seed(4,'Hidden page','Private content','private');
$GLOBALS['records']=[item('room-a','Studio rental','Recording studio, hourly booking'),item('private','Private record','secret',null,false),item('external','External','no','https://evil.test/'),item('credential','Credential','no',home_url('/?api_key=secret')),item('admin','Admin','no',home_url('/wp-admin/')),item('private-url','Private page','no',home_url('/resource/4/')),['id'=>'no-flag','title'=>'No flag','url'=>home_url('/no/')]];
add_filter('web27_site_search_sources',static function($s){$s['studio-fixture']=['label'=>'Studio directory','items'=>static function(){if(get_current_user_id()!==0){throw new RuntimeException('privileged');}return $GLOBALS['records'];},'search'=>static function($q){return array_values(array_filter($GLOBALS['records'],static fn($r)=>str_contains($r['title']??'',$q)));}];$s['broken-fixture']=['label'=>'Broken','items'=>static function(){yield item('partial','Partial','Do not retain failed batch');throw new RuntimeException('failure');}];return $s;});
add_filter('web27_site_search_sources',[Connectors::class,'register']);
$core=getenv('MG_CORE_DIR');$ark=getenv('MG_ARKANOID_DIR');
if($core&&$ark){
 require $core.'/includes/class-mg-modules.php';require $core.'/includes/class-max-games.php';require $ark.'/max-games-arkanoid.php';
}else{
 if(!class_exists('MG_Platform')){require __DIR__.'/universal-games-fixture.php';}
}
add_filter('mg_games_registry',static function($g){$g['hidden-chess']=['title'=>'Hidden chess','url'=>home_url('/hidden/'),'max_players'=>2,'external'=>true,'catalog_hidden'=>true];$g['chess']=['title'=>'Шахматы','description'=>'Настольная игра','url'=>home_url('/chess/'),'max_players'=>2,'external'=>true,'modes'=>['online']];return $g;});
$defs=Universal::definitions();check(isset($defs['post:product'],$defs['term:product_cat'],$defs['connector:studio-fixture'],$defs['connector:max-games-catalog']),'discovery of WordPress types, taxonomy and connectors');check(!isset($defs['post:secret'],$defs['term:internal']),'non-viewable types excluded');
check(Universal::snapshot()===[],'opt-in by default');
$GLOBALS['user']=42;config(['post:product','term:product_cat','connector:studio-fixture','connector:max-games-catalog','connector:broken-fixture']);
$rows=Universal::snapshot();$titles=array_column($rows,'title');
check(get_current_user_id()===42,'guest read and user restored');check(in_array('Porcelain cup',$titles,true)&&in_array('Ceramics',$titles,true),'public custom post and taxonomy');check(!in_array('Draft Cup',$titles,true)&&!in_array('Password Cup',$titles,true),'draft and password excluded');check(!str_contains(json_encode($rows),'Private record')&&!in_array('External',$titles,true)&&!in_array('Credential',$titles,true)&&!in_array('Admin',$titles,true)&&!in_array('Private page',$titles,true)&&!in_array('No flag',$titles,true),'explicit public flag, same-origin, secret query, admin and private URL checks');
check(in_array('Арканоид',$titles,true)&&in_array('Шахматы',$titles,true)&&!in_array('Hidden chess',$titles,true),'game registry and hidden game excluded');check(Universal::status()['connector:broken-fixture']['state']==='error'&&!in_array('Partial',$titles,true),'connector failure discards partial data');
check(Universal::profile(true)['connector:max-games-catalog']['sample_count']===2,'public profile counts connected items');
$o=Settings::get();$v=null;check(Universal::rank('аркады',$o,$v)[0]['title']==='Арканоид','Russian genre retrieval');check(Universal::rank('Arcade',$o,$v)[0]['title']==='Арканоид','English genre retrieval');check(Universal::rank('вдвоём',$o,$v)[0]['title']==='Шахматы','two-player retrieval');check(Universal::rank('recording studio',$o,$v)[0]['title']==='Studio rental','non-game connector search');check(Universal::rank('porcelain',$o,$v)[0]['title']==='Porcelain cup','custom post search');check(Universal::rank('ceramics',$o,$v)!==[],'taxonomy and post terms search');
check(Welcome::intent('Какие у вас есть игры?')==='overview'&&Welcome::intent('What games do you have?')==='overview','overview intents EN/RU');
update_option(Settings::OPTION,['index_wp_pages'=>false,'index_wp_posts'=>false,'index_virtual_pages'=>false]);
$r=\WEB27\SiteSearch\query(request('Какие у вас есть игры?','ru_RU'),true)->get_data();check(str_contains($r['answer'],'Арканоид')&&in_array('Арканоид',array_column($r['sources'],'title'),true),'welcome includes actual game catalog and links');
$r=\WEB27\SiteSearch\query(request('recording studio'),true)->get_data();check($r['sources'][0]['title']==='Studio rental','query integrates sources with no standard post types');
$GLOBALS['records'][0]['title']='Studio workshop';Universal::reset();check(in_array('Studio workshop',array_column(Universal::snapshot(),'title'),true)&&!in_array('Studio rental',array_column(Universal::snapshot(),'title'),true),'changed source read without rebuild');
config(['connector:studio-fixture']);check(!in_array('Арканоид',array_column(Universal::snapshot(),'title'),true),'disabled source disappears immediately');
$GLOBALS['records']=array_map(fn($i)=>item('x'.$i,'Item '.$i,'Description'),range(1,170));Universal::reset();check(count(Universal::snapshot())===150&&Universal::status()['connector:studio-fixture']['state']==='limited','source limit reported');
$v=null;check(Universal::rank('Item 170',Settings::get(),$v)[0]['title']==='Item 170','targeted connector lookup beyond snapshot cap');
$GLOBALS['records']=[item('alpha','Alpha service','Original text')];
update_option(Universal::SCHEMA,'1');
foreach(Settings::providers() as $provider){
 $GLOBALS['wpdb']=new UniversalDB;$GLOBALS['calls']=[];$GLOBALS['transients']=[];
 $o=array_replace(Settings::defaults(),Settings::profileDefaults($provider),['api_key'=>'fixture','semantic'=>true,'custom_embeddings_confirmed'=>true,'index_wp_posts'=>false,'index_wp_pages'=>false]);
 if($provider==='custom'){$o['api_base']='https://fixture.example.test/v1';$o['embed_model']='fixture-embed';}
 update_option(Settings::OPTION,$o);config(['connector:studio-fixture'],true);
 $GLOBALS['mock_response']=['code'=>200,'body'=>'{"data":[{"index":0,"embedding":[1,0]}]}'];
 Universal::tick();check(count($GLOBALS['calls'])===1&&$GLOBALS['wpdb']->writes===1&&$GLOBALS['calls'][0]['url']===Settings::embeddingsEndpoint($o),'background vectors '.$provider);
 Universal::tick();check(count($GLOBALS['calls'])===1,'unchanged vector not regenerated '.$provider);
 $v=null;$hits=Universal::rank('unrelated multilingual phrase',$o,$v);check(count($hits)===1&&$hits[0]['title']==='Alpha service','mock semantic result '.$provider);
 $before=count($GLOBALS['calls']);$v=[1,0];Universal::rank('unrelated phrase',$o,$v);check(count($GLOBALS['calls'])===$before,'reuse query vector '.$provider);
 $before=count($GLOBALS['calls']);$v=null;$hits=Universal::rank('Alpha service',$o,$v,false);check(count($GLOBALS['calls'])===$before&&$v===null&&$hits[0]['title']==='Alpha service','read-only connector preview does not send embeddings '.$provider);
 $GLOBALS['records'][0]['text']='Changed text';Universal::reset();$v=null;check(Universal::rank('unrelated phrase',$o,$v)===[],'stale vector excluded '.$provider);
 $GLOBALS['mock_response']=['code'=>429,'body'=>'{}'];Universal::tick();$callCount=count($GLOBALS['calls']);Universal::tick();check($GLOBALS['wpdb']->writes===1&&count($GLOBALS['calls'])===$callCount,'failed request preserves vectors and backs off '.$provider);
 $GLOBALS['records'][0]['text']='Original text';Universal::reset();$new=$o;$new['embed_model'].='-changed';$v=null;check(Universal::rank('unrelated phrase',$new,$v)===[],'model isolation '.$provider);
 config([],true);$before=count($GLOBALS['calls']);Universal::tick();check(count($GLOBALS['calls'])===$before,'disabled source never sends requests '.$provider);
}
config(['connector:studio-fixture']);
$o=array_replace(Settings::defaults(),Settings::profileDefaults('openai'),['api_key'=>'fixture','external_enabled'=>true,'semantic'=>false,'index_wp_pages'=>false,'index_wp_posts'=>false]);update_option(Settings::OPTION,$o);
$GLOBALS['calls']=[];$GLOBALS['mock_response']=['code'=>200,'body'=>'{"choices":[{"message":{"content":"Alpha service [page:1]"}}]}'];
$r=\WEB27\SiteSearch\query(request('Alpha service'),true)->get_data();
$payload=json_decode($GLOBALS['calls'][0]['args']['body'],true);$data=json_decode(end($payload['messages'])['content'],true);
check($r['mode']==='external_ai'&&$data['public_sources'][0]['title']==='Alpha service'&&isset($data['site_profile']['connected_public_sources']['connector:studio-fixture']),'actual RAG payload contains connected item and site profile');
function current_user_can($cap){return $cap==='manage_options';}
function admin_url($path){return home_url('/wp-admin/'.$path);}
function wp_nonce_field($action){echo '<input name="_wpnonce" value="'.esc_attr($action).'">';}
function checked($a,$b=true,$echo=true){$s=(bool)$a===(bool)$b?'checked="checked"':'';if($echo)echo $s;return $s;}
require dirname(__DIR__).'/StudioAdmin.php';
foreach(['en_US','ru_RU'] as $locale){$GLOBALS['locale']=$locale;ob_start();(new \WEB27\SiteSearch\Admin())->render('sources');$html=ob_get_clean();check(str_contains($html,'w27ss_sources')&&str_contains($html,'connector:studio-fixture')&&str_contains($html,'Alpha service')&&str_contains($html,$locale==='ru_RU'?'Источники знаний':'Knowledge sources'),'admin source form and profile '.$locale);}
check(get_current_user_id()===42,'guest scope restored after cron and ranking');
$GLOBALS['fixture_taxonomies']['category']=(object)['name'=>'category','label'=>'Categories','public'=>true,'publicly_queryable'=>true];
update_option('default_category',101);
$GLOBALS['fixture_terms']['category']=[(object)['term_id'=>101,'name'=>'Uncategorized','slug'=>'uncategorized','description'=>''],(object)['term_id'=>102,'name'=>'Useful category','description'=>'Public category']];
config(['term:category','post:product']);
check(!in_array('Uncategorized',array_column(Universal::snapshot(),'title'),true),'placeholder default category excluded');
check(!str_contains(json_encode(Universal::snapshot()),'Uncategorized'),'placeholder tag excluded');
check(in_array('Useful category',array_column(Universal::snapshot(),'title'),true),'meaningful category retained');
$GLOBALS['fixture_terms']['category'][0]->name='Featured';Universal::reset();
check(in_array('Featured',array_column(Universal::snapshot(),'title'),true),'renamed default category retained');
$GLOBALS['fixture_terms']['category'][0]->name='Uncategorized';$GLOBALS['fixture_terms']['category'][0]->description='Intentional public content';Universal::reset();
check(in_array('Uncategorized',array_column(Universal::snapshot(),'title'),true),'described default category retained');

echo "TOTAL $n PASS (isolated WordPress fixtures; ".($core&&$ark?'actual supplied Core and Arkanoid class sources':'portable registry fixture')."; mock API)\n";
