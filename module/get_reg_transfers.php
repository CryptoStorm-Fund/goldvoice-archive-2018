<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
set_time_limit(59);
if(!$admin){
	ob_end_clean();
	ignore_user_abort(true);
	header("Connection: close");
	fastcgi_finish_request();
}
$start_time=time();
$end_time=$start_time+59;
while(time()<$end_time){
	$q=$db->sql("SELECT * FROM `reg_transfers` WHERE `status`=0");
	while($m=$db->row($q)){
		$db->sql("UPDATE `reg_transfers` SET `status`=1 WHERE `id`='".$m['id']."'");
		$reg_balance=(float)$redis->hget('users:'.get_user_login($m['user']),'reg_balance');
		$reg_balance+=(float)$m['amount'];
		$redis->hset('users:'.get_user_login($m['user']),'reg_balance',$reg_balance);
		//$db->sql("UPDATE `users` SET `reg_balance`=`reg_balance`+".$m['amount']." WHERE `id`='".$m['user']."'");
	}
	usleep(1000);
}
exit;
