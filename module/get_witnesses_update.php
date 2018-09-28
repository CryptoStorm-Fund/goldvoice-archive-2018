<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
if(!$config['blockchain_parse']){
	exit;
}
if($config['blockchain_parse_only_blocks']){
	exit;
}
set_time_limit(65);

if(!$admin){
	ob_end_clean();
	ignore_user_abort(true);
	header("Connection: close");
	fastcgi_finish_request();
}
$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);

$end_user=false;
$parse_times=0;
$current_user='';
$find_users=0;
while(!$end_user){
	$result_arr=$web->execute_method('lookup_witness_accounts',array($current_user,1000),false,true);
	foreach($result_arr as $k=>$v){
		if($v!=$current_user){
			$current_user=$v;
			$user_id=get_user_id($v);
			if($user_id){
				if(3!=$user_id){
					if(0==$db->table_count('witnesses',"WHERE `user`='".$db->prepare($user_id)."'")){
						$db->sql("INSERT INTO `witnesses` (`user`,`parse_priority`) VALUES ('".$db->prepare($user_id)."',1)");
						$find_users++;
					}
				}
			}
		}
	}
	$parse_times++;
	if(1==count($result_arr['result'])){
		$end_user=true;
	}
	if(10<$parse_times){
		$end_user=true;
	}
}
//print 'New witnesses: $find_users='.$find_users;

$q=$db->sql("SELECT `user` FROM `witnesses` WHERE `parse_priority`=1 OR `parse_time`<'".($start_time-3600)."' ORDER BY `parse_priority` DESC, `parse_time` ASC");
while($m=$db->row($q)){
	$login=get_user_login($m['user']);
	if($login){
		$user_arr=$web->execute_method('get_witness_by_account',array($login),false,true);
		if($user_arr['id']){
			/*$client->send(ws_method("get_witness_by_account",array($login)));
			$result=$client->receive();
			$result_arr=json_decode($result,true);*/
			$date=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['last_sbd_exchange_update']);
			$last_sbd_exchange_update=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
			$date=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['created']);
			$created=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
			$new_user_arr=array(
				'time'=>$created,
				'last_sbd_exchange_update'=>$last_sbd_exchange_update,
				'url'=>$user_arr['url'],
				'votes'=>$user_arr['votes'],
				'total_missed'=>$user_arr['total_missed'],
				'last_confirmed_block_num'=>$user_arr['last_confirmed_block_num'],
				'signing_key'=>$user_arr['signing_key'],
				'base'=>$user_arr['sbd_exchange_rate']['base'],
				'quote'=>$user_arr['sbd_exchange_rate']['quote'],
				'running_version'=>$user_arr['running_version'],
				'account_creation_fee'=>$user_arr['props']['account_creation_fee'],
				'maximum_block_size'=>$user_arr['props']['maximum_block_size'],
				'sbd_interest_rate'=>$user_arr['props']['sbd_interest_rate'],
				'parse_time'=>time(),
				'parse_priority'=>'0',
			);
			$update_arr=array();
			foreach($new_user_arr as $k=>$v) {
				$update_arr[]='`'.$k.'`=\''.$db->prepare($v).'\'';
			}
			$update_str=implode(', ',$update_arr);
			$db->sql("UPDATE `witnesses` SET ".$update_str." WHERE `user`='".$m['user']."'");
			usleep(300);
		}
	}
}
exit;