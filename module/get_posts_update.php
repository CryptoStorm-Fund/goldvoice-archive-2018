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

//exit;
$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);

$start_time=time();
$end_time=$start_time+59;
$parse_times=0;
while(time()<$end_time){
	//$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `posts` WHERE (`payout_parse_priority`=1 OR `payout_parse_time`<'".($start_time-18000)."') AND `permlink`!='' AND `time`>".(time()-90000)." ORDER BY `payout_parse_priority` DESC, `payout_parse_time` ASC LIMIT 25");//за последние 25 часов когда посты в приоритете ИЛИ они не парсились более 5 минут
	for($i=0;$i<100;$i++){
		$post_id=redis_get_ulist('update_posts');
		if($post_id){
			$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `posts` WHERE `id`='".$post_id."'");
			while($m=$db->row($q)){
				if($m['permlink']){
					$m['login']=get_user_login($m['author']);
					/*
					$client->send(ws_method("get_content",array($m['login'],$m['permlink'])));
					$result=$client->receive();
					$result_arr=json_decode($result,true);
					$block_info=$result_arr['result'];
					*/
					//$block_info=$web->execute_method('database_api','get_content',array($m['login'],$m['permlink']),false);
					$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);

					if($block_info['permlink']==$m['permlink']){
						$payout=$block_info['total_payout_value'];
						$curator_payout=$block_info['curator_payout_value'];
						$pending_payout=$block_info['pending_payout_value'];
						$date=date_parse_from_format('Y-m-d\TH:i:s',$block_info['cashout_time']);
						$cashout_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
						$db->sql("UPDATE `posts_data` SET `body`='".$db->prepare($block_info['body'])."' WHERE `post`='".$m['id']."'");
						$db->sql("UPDATE `posts` SET `cashout_time`='".$cashout_time."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
					}
					$parse_times++;
					usleep(100);
				}
			}
		}
	}
	$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `posts` WHERE `permlink`!='' AND `time`>".($start_time-90000)." AND `status`!=1 ORDER BY `payout_parse_time` ASC LIMIT 20");//свежие за последние 25 часов
	while($m=$db->row($q)){
		if($m['permlink']){
			$m['login']=get_user_login($m['author']);
			/*
			$client->send(ws_method("get_content",array($m['login'],$m['permlink'])));
			$result=$client->receive();
			$result_arr=json_decode($result,true);
			$block_info=$result_arr['result'];
			*/
			//$block_info=$web->execute_method('database_api','get_content',array($m['login'],$m['permlink']),false);
			$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);
			if($block_info['permlink']==$m['permlink']){
				$payout=$block_info['total_payout_value'];
				$curator_payout=$block_info['curator_payout_value'];
				$pending_payout=$block_info['pending_payout_value'];
				$date=date_parse_from_format('Y-m-d\TH:i:s',$block_info['cashout_time']);
				$cashout_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
				$db->sql("UPDATE `posts_data` SET `body`='".$db->prepare($block_info['body'])."' WHERE `post`='".$m['id']."'");
				$db->sql("UPDATE `posts` SET `cashout_time`='".$cashout_time."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
			}
			else{
				$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+900 WHERE `id`='".$db->prepare($m['id'])."'");
			}
			$parse_times++;
			usleep(250);
		}
		else{
			$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+150000000 WHERE `id`='".$db->prepare($m['id'])."'");//это репост
		}
	}

	$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `posts` WHERE `permlink`!='' AND `time`>".($start_time-864000)." AND `time`<".($start_time-90000)." AND `status`!=1 ORDER BY `payout_parse_time` ASC LIMIT 20");//за последние 10 дней, кроме последних 25 часов
	while($m=$db->row($q)){
		if($m['permlink']){
			$m['login']=get_user_login($m['author']);
			/*
			$client->send(ws_method("get_content",array($m['login'],$m['permlink'])));
			$result=$client->receive();
			$result_arr=json_decode($result,true);
			$block_info=$result_arr['result'];
			*/
			$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);
			//$block_info=$web->execute_method('database_api','get_content',array($m['login'],$m['permlink']),false);
			if($block_info['permlink']==$m['permlink']){
				$payout=$block_info['total_payout_value'];
				$curator_payout=$block_info['curator_payout_value'];
				$pending_payout=$block_info['pending_payout_value'];
				$date=date_parse_from_format('Y-m-d\TH:i:s',$block_info['cashout_time']);
				$cashout_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
				$db->sql("UPDATE `posts_data` SET `body`='".$db->prepare($block_info['body'])."' WHERE `post`='".$m['id']."'");
				$db->sql("UPDATE `posts` SET `cashout_time`='".$cashout_time."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
			}
			else{
				$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+900 WHERE `id`='".$db->prepare($m['id'])."'");
			}
			$parse_times++;
			usleep(250);
		}
		else{
			$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+150000000 WHERE `id`='".$db->prepare($m['id'])."'");//это репост
		}
	}

	$q=$db->sql("SELECT `id`,`time`,`author`,`permlink` FROM `posts` WHERE `permlink`!='' AND `time`>=".($start_time-8640000)." AND `time`<=".($start_time-864000)." AND `status`!=1 ORDER BY `payout_parse_time` ASC LIMIT 10");//старше 10 дней
	while($m=$db->row($q)){
		if($m['permlink']){
			$m['login']=get_user_login($m['author']);
			/*
			$client->send(ws_method("get_content",array($m['login'],$m['permlink'])));
			$result=$client->receive();
			$result_arr=json_decode($result,true);
			$block_info=$result_arr['result'];
			*/
			$block_info=$web->execute_method('get_content',array($m['login'],$m['permlink']),false);
			//$block_info=$web->execute_method('database_api','get_content',array($m['login'],$m['permlink']),false);
			if($block_info['permlink']==$m['permlink']){
				$payout=$block_info['total_payout_value'];
				$curator_payout=$block_info['curator_payout_value'];
				$pending_payout=$block_info['pending_payout_value'];
				$date=date_parse_from_format('Y-m-d\TH:i:s',$block_info['cashout_time']);
				$cashout_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
				$db->sql("UPDATE `posts_data` SET `body`='".$db->prepare($block_info['body'])."' WHERE `post`='".$m['id']."'");
				$db->sql("UPDATE `posts` SET `cashout_time`='".$cashout_time."', `payout_parse_time`='".time()."', `payout_parse_time`='".time()."', `pending_payout`='".$db->prepare($pending_payout)."', `payout`='".$db->prepare($payout)."', `curator_payout`='".$db->prepare($curator_payout)."' WHERE `id`='".$db->prepare($m['id'])."'");
			}
			else{
				$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+900 WHERE `id`='".$db->prepare($m['id'])."'");
			}
			$parse_times++;
			usleep(250);
		}
		else{
			$db->sql("UPDATE `posts` SET `payout_parse_time`=`payout_parse_time`+150000000 WHERE `id`='".$db->prepare($m['id'])."'");//это репост
		}
	}
	sleep(1);
}
exit;