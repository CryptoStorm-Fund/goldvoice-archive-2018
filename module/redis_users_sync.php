<?php
set_time_limit(0);
$info=$redis->info('memory');
print_r($info);
$offset=0;
if($_GET['offset']){
	$offset=(int)$_GET['offset'];
}
$q=$db->sql("SELECT * FROM `users` LIMIT 10000 OFFSET ".$offset);
while($m=$db->row($q)){
	$redis->set('user_login:'.$m['id'],$m['login']);
	foreach($m as $k=>$v){
		if($v){
			$redis->hset('users:'.$m['login'],$k,$v);
		}
	}
	/*$user_id=$redis->zscore('users_id',$m['login']);
	//print 'look '.$m['login'].', find id: '.$user_id;
	if(!$user_id){
		$redis->zadd('users_id',$m['id'],$m['login']);
	}
	*/
}
//$redis->save();
exit;