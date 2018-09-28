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
set_time_limit(61);

$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);

$start_time=time();
$end_time=$start_time+59;
$check_time=$start_time-18000;
while(time()<$end_time){
	$user_login=redis_get_ulist('update_users2');
	if($user_login){
		print 'look '.$user_login.PHP_EOL;
		$block_info2=$web->execute_method('get_accounts',array($user_login),true);
		if($block_info2[0]['name']==$user_login){
			$user_arr=$block_info2[0];
			$date=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['created']);
			$reg_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
			$date=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['last_post']);
			$last_post=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
			$gender=0;
			$json_metadata=array();
			if($user_arr['json_metadata']){
				$json_metadata=json_decode($user_arr['json_metadata'],true);
				if($json_metadata['profile']['background_color']){
					$json_metadata['profile']['background_color']=strtolower(preg_replace('~([^a-z0-9]*)~iUs','',$json_metadata['profile']['background_color']));
				}
				//old adsense struct
				if($json_metadata['profile']['adsense']['ad_client']){
					$json_metadata['profile']['ad']['adsense_client']=strtolower(preg_replace('~([^a-z0-9\-]*)~iUs','',$json_metadata['profile']['adsense']['ad_client']));
				}
				if($json_metadata['profile']['adsense']['ad_slot']){
					$json_metadata['profile']['ad']['adsense_slot']=strtolower(preg_replace('~([^a-z0-9\-]*)~iUs','',$json_metadata['profile']['adsense']['ad_slot']));
				}
				if($json_metadata['profile']['adsense']['ignore_cashout_time']){
					$json_metadata['profile']['ad']['ignore_cashout_time']=(1==$json_metadata['profile']['adsense']['ignore_cashout_time']?1:0);
				}

				//new ads struct
				if($json_metadata['profile']['ad']['type']){
					$json_metadata['profile']['ad']['type']=intval($json_metadata['profile']['ad']['type']);
				}
				if($json_metadata['profile']['ad']['a_ads_id']){
					$json_metadata['profile']['ad']['a_ads_id']=intval($json_metadata['profile']['ad']['a_ads_id']);
				}
				if($json_metadata['profile']['ad']['adsense_client']){
					$json_metadata['profile']['ad']['adsense_client']=strtolower(preg_replace('~([^a-z0-9\-]*)~iUs','',$json_metadata['profile']['ad']['adsense_client']));
				}
				if($json_metadata['profile']['ad']['adsense_slot']){
					$json_metadata['profile']['ad']['adsense_slot']=strtolower(preg_replace('~([^a-z0-9\-]*)~iUs','',$json_metadata['profile']['ad']['adsense_slot']));
				}
				if($json_metadata['profile']['ad']['ignore_cashout_time']){
					$json_metadata['profile']['ad']['ignore_cashout_time']=(1==$json_metadata['profile']['ad']['ignore_cashout_time']?1:0);
				}
				if($json_metadata['profile']['seo']['show_comments']){
					$json_metadata['profile']['seo']['show_comments']=(1==$json_metadata['profile']['seo']['show_comments']?1:0);
				}
				if($json_metadata['profile']['seo']['index_comments']){
					$json_metadata['profile']['seo']['index_comments']=(1==$json_metadata['profile']['seo']['index_comments']?1:0);
				}
				if('male'==$json_metadata['profile']['gender']){
					$gender=1;
				}
				if('female'==$json_metadata['profile']['gender']){
					$gender=2;
				}
			}
			//print_r($user_arr);exit;
			$new_user_arr=array(
				'id'=>$user_arr['id'],
				'reg_time'=>$reg_time,
				'balance'=>substr($user_arr['balance'],0,strpos($user_arr['balance'],' ')),
				'sbd_balance'=>substr($user_arr['sbd_balance'],0,strpos($user_arr['sbd_balance'],' ')),
				'vesting_shares'=>substr($user_arr['vesting_shares'],0,strpos($user_arr['vesting_shares'],' ')),
				'savings_balance'=>substr($user_arr['savings_balance'],0,strpos($user_arr['savings_balance'],' ')),
				'savings_sbd_balance'=>substr($user_arr['savings_sbd_balance'],0,strpos($user_arr['savings_sbd_balance'],' ')),
				'voting_power'=>$user_arr['voting_power'],
				'last_post_time'=>$last_post,
				'witnesses_proxy'=>$user_arr['proxy'],
				'creator'=>$user_arr['recovery_account'],
				'reputation'=>$user_arr['reputation'],
				'reputation_short'=>floor(max(log10((int)$user_arr['reputation'])-9,0)*(($user_arr['reputation']<0?-1:1)*9)+25),
				'parse_time'=>time(),
				'gender'=>$gender,
				'avatar'=>$json_metadata['profile']['profile_image'],
				'cover'=>$json_metadata['profile']['cover_image'],
				'telegram'=>$json_metadata['profile']['telegram'],
				'background_color'=>$json_metadata['profile']['background_color'],
				'ad_type'=>$json_metadata['profile']['ad']['type'],
				'ad_a_ads_id'=>$json_metadata['profile']['ad']['a_ads_id'],
				'ad_adsense_client'=>$json_metadata['profile']['ad']['adsense_client'],
				'ad_adsense_slot'=>$json_metadata['profile']['ad']['adsense_slot'],
				'ad_ignore_cashout_time'=>$json_metadata['profile']['ad']['ignore_cashout_time'],
				'seo_show_comments'=>$json_metadata['profile']['seo']['show_comments'],
				'seo_index_comments'=>$json_metadata['profile']['seo']['index_comments'],
				'name'=>$json_metadata['profile']['name'],
				'about'=>$json_metadata['profile']['about'],
				'location'=>$json_metadata['profile']['location'],
				'website'=>$json_metadata['profile']['website'],
				'login'=>$user_login,
			);
			$redis->set('user_login:'.$user_arr['id'],$user_login);
			foreach($new_user_arr as $k=>$v){
				$redis->hset('users:'.$user_login,$k,$v);
			}

			$update_arr=array();
			foreach($new_user_arr as $k=>$v) {
				$update_arr[]='`'.$k.'`=\''.$db->prepare($v).'\'';
			}
			$update_str=implode(', ',$update_arr);
			$db->sql("UPDATE `users` SET ".$update_str." WHERE `login`='".$db->prepare($user_login)."'");
			//$db->sql("UPDATE `users` SET `action_time`='".$last_post."' WHERE `login`='".$db->prepare($user_login)."' AND `last_post_time`>`action_time`");
		}
	}
	unset($user_login);
	usleep(200);
}
exit;