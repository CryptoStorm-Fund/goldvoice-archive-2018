<?php
$config=array();
include('blockchain_parse.php');//true - работаем, fasle - на паузе. Используем паузу для изменений в обработке данных, чтобы новые не нарушали архитектуру.
$db_arr=array(//slave array
	'127.0.0.1',
);
$config['db_host']=$db_arr[array_rand($db_arr)];
if(false!==strpos($_SERVER['REQUEST_URI'],'cron_parse_password')){
	$config['db_host']='127.0.0.1';//master db - only for works
}
$config['db_login']='goldvoice';
$config['db_password']='';
$config['db_base']='goldvoice';

$config['server_timezone']='Etc/GMT';

$config['blockchain_node']='wss://ws.golos.io';
$config['blockchain_jsonrpc']='https://api.golos.blckchnd.com/';
$config['domain']='goldvoice.club';

$site_root=$_SERVER['DOCUMENT_ROOT'];
$site_root.='';