<?php
set_time_limit(0);
$info=$redis->info('memory');
print_r($info);
$offset=0;
if($_GET['offset']){
	$offset=(int)$_GET['offset'];
}
$q=$db->sql("SELECT * FROM `sessions`");
$max_id=0;
while($m=$db->row($q)){
	if($m['id']>$max_id){
		$max_id=$m['id'];
	}
	$redis->zadd('sessions_cookie',$m['id'],$m['cookie']);
	$redis->zadd('sessions_key',$m['id'],$m['key']);
	foreach($m as $k=>$v){
		if($v){
			$redis->hset('sessions:'.$m['id'],$k,$v);
		}
	}
}
$test_max_id=$redis->get('id:sessions');
if($test_max_id<$max_id){
	$redis->set('id:sessions',$max_id);
}
$redis->save();
exit;