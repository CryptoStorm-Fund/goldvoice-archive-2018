<?php
set_time_limit(0);
$q=$db->sql("SELECT `id`,`login` FROM `users`");
$count1=0;
$count2=0;
while($m=$db->row($q)){
	$user_login=$redis->get('user_login:'.$m['id']);
	if(!$user_login){
		$count1++;
		$redis->set('user_login:'.$m['id'],$m['login']);
		if(!$redis->exists('users:'.$m['login'])){
			$redis->hset('users:'.$m['login'],'id',$m['id']);
			$redis->hset('users:'.$m['login'],'login',$m['login']);
			redis_add_ulist('update_users2',$m['login']);
			$count2++;
		}
	}
}
print 'index errors: '.$count1.', data errors: '.$count2;
//$redis->save();
exit;