<?php
final class MG_Platform {public static function games(): array {return apply_filters('mg_games_registry',[]);}}
final class MG_Modules {
 public static function catalog(): array {return array_filter(MG_Platform::games(),static fn($g)=>empty($g['catalog_hidden']));}
 public static function catalog_genres(): array {return ['arcade'=>'Аркады','board'=>'Настольные'];}
 public static function catalog_genre(string $slug,array $g): string {return $slug==='arkanoid'?'arcade':'board';}
}
add_filter('mg_games_registry',static function($g){$g['arkanoid']=['title'=>'Арканоид','description'=>'Неоновая аркада','url'=>home_url('/?mg_fw_arkanoid=1'),'max_players'=>1,'external'=>true,'modes'=>[]];return $g;});
