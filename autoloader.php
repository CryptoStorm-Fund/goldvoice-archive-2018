<?php
putenv('TZ='.$config['server_timezone']);
date_default_timezone_set($config['server_timezone']);
include($site_root.'/class/db.php');
include($site_root.'/class/sdb.php');
include($site_root.'/class/template.php');
include($site_root.'/class/cache.php');
include($site_root.'/class/dme.php');
include($site_root.'/class/parsedown.php');
include($site_root.'/class/parsedownextra.php');
include($site_root.'/class/golos_jsonrpc.php');

$db=new DataManagerDatabase($config['db_host'],$config['db_login'],$config['db_password']);
$db->db($config['db_base'],'utf8mb4');
if(!$db->link){
	print '<html><head></head><body>Server restarting... <!-- '.$config['db_host'].'  --></body></html>';
	exit;
}
$sdb=new DataManagerSuperDatabase;
$t=new DataManagerTemplate($site_root.'/templates/');
$cache=new DataManagerCache;
$time=time();
$parsedown = new Parsedown();
$parsedownextra = new ParsedownExtra();

//exit;
$redis=new Redis();
$redis->connect('127.0.0.1');
$redis->auth('');

if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
	$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
}
else{
	$ip=$_SERVER['REMOTE_ADDR'];
}

$script_change_time=filemtime('./js/app2.js');
$css_change_time=filemtime('./css/app.css');