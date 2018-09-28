<?php
//exit;
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
$start_time=time();
$end_time=$start_time+55;
$parse_times=0;
while(time()<$end_time){
	for($i=0;$i<100;$i++){
		$comment_id=redis_get_ulist('update_comments');
		if($comment_id){
			$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `comments` WHERE `id`='".$comment_id."'");
			while($m=$db->row($q)){
				if($m['permlink']){
					$m['login']=get_user_login($m['author']);
					$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);
					if($block_info['permlink']==$m['permlink']){
						$payout=$block_info['total_payout_value'];
						$curator_payout=$block_info['curator_payout_value'];
						$pending_payout=$block_info['pending_payout_value'];
						$db->sql("UPDATE `comments` SET `body`='".$db->prepare($block_info['body'])."', `json_metadata`='".$db->prepare($block_info['json_metadata'])."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
					}
					else{
						$db->sql("UPDATE `comments` SET `payout_parse_time`=`payout_parse_time`+900 WHERE `id`='".$db->prepare($m['id'])."'");
					}
					$parse_times++;
					usleep(50);
				}
				else{
					$db->sql("UPDATE `comments` SET `payout_parse_time`=`payout_parse_time`+150000000 WHERE `id`='".$db->prepare($m['id'])."'");//это репост
				}
			}
		}
		usleep(50);
	}
	$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `comments` WHERE `time`<=".($start_time-180000)." ORDER BY `payout_parse_time` ASC LIMIT 5");
	while($m=$db->row($q)){
		if($m['permlink']){
			$m['login']=get_user_login($m['author']);
			$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);
			if($block_info['permlink']==$m['permlink']){
				$payout=$block_info['total_payout_value'];
				$curator_payout=$block_info['curator_payout_value'];
				$pending_payout=$block_info['pending_payout_value'];
				$db->sql("UPDATE `comments` SET `body`='".$db->prepare($block_info['body'])."', `json_metadata`='".$db->prepare($block_info['json_metadata'])."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
			}
			else{
				$db->sql("UPDATE `comments` SET `payout_parse_time`=`payout_parse_time`+900 WHERE `id`='".$db->prepare($m['id'])."'");
			}
			$parse_times++;
			usleep(200);
		}
		else{
			$db->sql("UPDATE `comments` SET `payout_parse_time`=`payout_parse_time`+150000000 WHERE `id`='".$db->prepare($m['id'])."'");//это репост
		}
	}
	usleep(200);
}
exit;