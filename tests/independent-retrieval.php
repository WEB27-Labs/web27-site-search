<?php
require __DIR__.'/bootstrap.php';
use WEB27\SiteSearch\Knowledge;
$data=json_decode(file_get_contents(__DIR__.'/independent-retrieval.json'),true,512,JSON_THROW_ON_ERROR);
foreach($data as $site){
    $GLOBALS['posts']=[];$GLOBALS['chunks']=[];$GLOBALS['options']=[];
    foreach($site['documents'] as $doc)seed($doc['id'],$doc['title'],$doc['body']);
    foreach($site['cases'] as $case){
        $hits=Knowledge::rank($case['query'],['page','post']);
        echo json_encode(['id'=>$case['id'],'site'=>$site['site'],'category'=>$case['category'],'question'=>$case['query'],'expected_fact'=>$case['fact'],'expected'=>$case['source'],'top'=>$hits[0]['post_id']??null,'returned'=>array_column($hits,'post_id'),'top_excerpt'=>$hits[0]['excerpt']??null],JSON_UNESCAPED_UNICODE)."\n";
    }
}
