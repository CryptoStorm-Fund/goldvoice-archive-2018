<?php
set_time_limit(0);
$q=$db->sql("SELECT * FROM `users` WHERE `reg_balance`<>0");
while($m=$db->row($q)){
	$redis->hset('users:'.$m['login'],'reg_balance',$m['reg_balance']);
}
//$redis->save();
exit;