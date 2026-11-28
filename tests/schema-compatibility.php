<?php
use WEB27\SiteSearch\{Knowledge,Universal};
$dir=sys_get_temp_dir().'/w27-schema-'.bin2hex(random_bytes(8));
mkdir($dir.'/wp-admin/includes',0700,true);
file_put_contents($dir.'/wp-admin/includes/upgrade.php','<?php');
define('ABSPATH',$dir.'/');
register_shutdown_function(static function() use ($dir){unlink($dir.'/wp-admin/includes/upgrade.php');rmdir($dir.'/wp-admin/includes');rmdir($dir.'/wp-admin');rmdir($dir);});
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;}
function dbDelta($sql){global $wpdb;$wpdb->ddl[]=$sql;preg_match('/CREATE TABLE (\S+)/',$sql,$m);$name=$m[1];if(preg_match('/^\s*vector\s+longtext/im',$sql))throw new RuntimeException('Unquoted reserved identifier');if(!in_array($name,$wpdb->fail,true))$wpdb->tables[$name]=true;}
final class SchemaDB {
    public string $prefix='wp_';
    public array $tables=[],$ddl=[],$fail=[];
    function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4';}
    function esc_like($v){return addcslashes($v,'_%\\');}
    function prepare($sql,...$args){return [$sql,$args];}
    function get_var($q){$table=stripslashes($q[1][0]);return isset($this->tables[$table])?$table:null;}
}
require dirname(__DIR__).'/Knowledge.php';
require dirname(__DIR__).'/Universal.php';
function check($ok,$name){if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
function setup($options=[],$tables=[]){$GLOBALS['options']=$options;$GLOBALS['wpdb']=new SchemaDB;$GLOBALS['wpdb']->tables=array_fill_keys($tables,true);}
setup();Knowledge::install();Universal::install();
check(get_option('w27ss_schema')==='3'&&get_option(Universal::SCHEMA)==='1','fresh schema markers after tables exist');
check(count($wpdb->tables)===3&&get_option('w27ss_cursor')===0,'fresh tables and cursor');
check(str_contains($wpdb->ddl[0],'`vector` longtext NULL')&&str_contains($wpdb->ddl[2],'`vector` longtext NOT NULL'),'reserved column quoted in both DDL statements');
setup(['w27ss_schema'=>'2','w27ss_cursor'=>-1],[Knowledge::table(),Knowledge::log()]);Knowledge::install();
check(get_option('w27ss_schema')==='3'&&get_option('w27ss_cursor')===-1,'existing completed index cursor preserved');
setup(['w27ss_schema'=>'2','w27ss_cursor'=>-1],[Knowledge::log()]);Knowledge::install();
check(isset($wpdb->tables[Knowledge::table()])&&get_option('w27ss_cursor')===0,'recover missing chunk table after failed prior install');
setup();$wpdb->fail=[Knowledge::table(),Universal::table()];Knowledge::install();Universal::install();
check(get_option('w27ss_schema')===false&&get_option(Universal::SCHEMA)===false,'failed creation not marked complete');
$wpdb->fail=[];Knowledge::install();Universal::install();
check(get_option('w27ss_schema')==='3'&&get_option(Universal::SCHEMA)==='1','retry succeeds after creation failure');
$n=count($wpdb->ddl);Knowledge::install();Universal::install();check(count($wpdb->ddl)===$n,'no repeated migration after success');
