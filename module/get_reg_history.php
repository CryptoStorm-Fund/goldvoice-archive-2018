<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
set_time_limit(65);
if(!$admin){
	ob_end_clean();
	ignore_user_abort(true);
	header("Connection: close");
	fastcgi_finish_request();
}
$creation_fee=1.0;
$start_time=time();
$end_time=$start_time+59;
while(time()<$end_time){
	$q=$db->sql("SELECT * FROM `reg_history` WHERE `time`<'".(time() - 300)."' AND `status`=0");
	while($m=$db->row($q)){
		if(0!=get_user_id($m['login'])){
			if(1==$m['public']){
				$invite_arr=array();
				$count=0;
				$user_id=get_user_id($m['login']);
				$q2=$db->sql("SELECT * FROM `reg_subscribes` WHERE `user1`='".$user_id."' AND `status`=1");
				while($m2=$db->row($q2)){
					$invite_arr[$m2['invite']]++;
					$count++;
				}
				$reg_cost=$creation_fee;
				$cost_per=round($reg_cost/$count,3);
				$db->sql("UPDATE `reg_subscribes` SET `amount`='".$cost_per."' WHERE `user1`='".$user_id."' AND `status`=1");
				foreach($invite_arr as $invite_id=>$cost_count){
					$final_cost=$cost_count*$cost_per;
					$invite_struct=$db->sql_row("SELECT * FROM `invite_struct` WHERE `id`='".$invite_id."'");
					$reg_balance=(float)$redis->hget('users:'.get_user_login($invite_struct['user']),'reg_balance');
					$reg_balance-=round($final_cost,3);
					$redis->hset('users:'.get_user_login($invite_struct['user']),'reg_balance',$reg_balance);
					//$db->sql("UPDATE `users` SET `reg_balance`=`reg_balance`-'".round($final_cost,3)."' WHERE `id`='".$invite_struct['user']."'");
				}
			}
			else{
				$invite_struct=$db->sql_row("SELECT * FROM `invite_struct` WHERE `id`='".$m['invite']."'");
				$reg_balance=(float)$redis->hget('users:'.get_user_login($invite_struct['user']),'reg_balance');
				$reg_balance-=$creation_fee;
				$redis->hset('users:'.get_user_login($invite_struct['user']),'reg_balance',$reg_balance);
				//$db->sql("UPDATE `users` SET `reg_balance`=`reg_balance`-'3.0' WHERE `id`='".$invite_struct['user']."'");
			}
			$db->sql("UPDATE `reg_history` SET `status`=1 WHERE `id`='".$m['id']."'");
		}
		else{
			$db->sql("UPDATE `reg_history` SET `status`=2 WHERE `id`='".$m['id']."'");
		}
	}
	usleep(4000);
}
exit;
