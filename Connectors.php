<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Connectors {
    public static function register(array $sources): array {
        if(is_callable(['\\MG_Modules','catalog'])&&is_callable(['\\MG_Modules','catalog_genre'])&&is_callable(['\\MG_Modules','catalog_genres'])){
            $sources['max-games-catalog']=['label'=>'MAX Games Core','items'=>[self::class,'games']];
        }
        return $sources;
    }
    public static function games(): iterable {
        $genres=\MG_Modules::catalog_genres();
        foreach(\MG_Modules::catalog() as $slug=>$game){
            if(!is_array($game)||!empty($game['catalog_hidden'])){continue;}
            $genre=\MG_Modules::catalog_genre((string)$slug,$game);
            $english=['board'=>'Board games','puzzle'=>'Puzzles','arcade'=>'Arcades','cards'=>'Card games','words'=>'Word games and quizzes','adventure'=>'Adventure','action'=>'Action and 3D','other'=>'Other'];
            $tags=[$genre,(string)($genres[$genre]??''),$english[$genre]??''];
            $facts=[];
            if(isset($game['max_players'])&&is_numeric($game['max_players'])){$facts[]='Maximum players / Максимум игроков: '.(int)$game['max_players'];}
            if(!empty($game['solo'])||(int)($game['max_players']??0)===1){$facts[]='Solo play / Одиночная игра';$tags[]='solo';$tags[]='одиночная';}
            if(in_array('online',(array)($game['modes']??[]),true)){$facts[]='Online mode / Онлайн-режим';$tags[]='multiplayer';$tags[]='мультиплеер';if((int)($game['max_players']??0)>=2){$tags[]='two players';$tags[]='два игрока';$tags[]='вдвоём';}}
            if(in_array('ai',(array)($game['modes']??[]),true)){$facts[]='AI mode / Режим с AI';}
            $image=$game['catalog_cover_url']??$game['cover_url']??$game['icon_url']??'';
            if(is_callable(['\\MG_Modules','catalog_cover'])&&defined('MG_CORE_DIR')&&defined('MG_CORE_URL')&&defined('MG_CORE_VERSION')){
                try{$cover=\MG_Modules::catalog_cover((string)$slug,$game);$cover=preg_replace('/\?ver=[a-zA-Z0-9._%+-]+$/D','',$cover);if(Settings::sourceImage($cover)!==''){$image=$cover;}}catch(\Throwable $e){}
            }
            yield ['id'=>(string)$slug,'public'=>true,'title'=>$game['catalog_title']??$game['title']??'','url'=>$game['catalog_url']??$game['url']??'','text'=>Universal::text($game['description']??$game['catalog_description']??'',2200).' '.implode('. ',$facts),'tags'=>$tags,'image'=>$image];
        }
    }
}
