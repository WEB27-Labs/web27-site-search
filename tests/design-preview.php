<?php
ob_start();require __DIR__.'/admin-providers.php';ob_end_clean();
$GLOBALS['locale']=$argv[1]??'en_US';
?><!doctype html><html><head><meta charset="utf-8"><style><?=file_get_contents(dirname(__DIR__).'/assets/search.css')?></style></head><body><?php $a->render('ux');?><script><?=file_get_contents(dirname(__DIR__).'/assets/preview.js')?></script></body></html>
