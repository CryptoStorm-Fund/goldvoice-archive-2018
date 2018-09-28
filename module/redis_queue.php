<?php
exit;
if('cron_parse_password'!=$path_array[2]){
	exit;
}

set_time_limit(64);

/*
ob_end_clean();
ignore_user_abort(true);
header("Connection: close");
fastcgi_finish_request();*/

$start_time=time();
$end_time=$start_time+56;
while(time()<$end_time){
	$count=$redis->lLen('update_users:queue');
	while($count>0){
		$data=redis_get_queue('update_users');
		if($data[0]){
			if(!$data[1]){
				$db->sql("UPDATE `users` SET `parse_priority`='1' WHERE `id`='".$data[0]."'");
			}
			else{
				$db->sql("UPDATE `users` SET `parse_priority`='1', `action_time`='".$data[1]."' WHERE `id`='".$data[0]."'");
			}
			$count--;
		}
		unset($data);
	}

	$count=$redis->lLen('update_posts:queue');
	while($count>0){
		$data=redis_get_queue('update_posts');
		if($data[0]){
			$db->sql("UPDATE `posts` SET `payout_parse_priority`='1' WHERE `id`='".$data[0]."'");
			$count--;
		}
		unset($data);
	}

	$count=$redis->lLen('posts_votes:queue');
	while($count>0){
		$data=redis_get_queue('posts_votes');
		if($data[0]){
			$block_time=$data[3];
			$weight=$data[4];
			$author_id=$data[1];
			$user_id=$data[2];
			$db->sql("INSERT INTO `posts_votes` (`post`,`post_author`,`user`,`time`,`weight`) VALUES ('".$data[0]."','".$data[1]."','".$data[2]."','".$data[3]."','".$data[4]."')");
			$find_post_vote=$db->last_id();
			if($weight>0){
				if($author_id!=$user_id){
					if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
						redis_add_queue('notifications',array($block_time,(int)$author_id,6,(int)$find_post_vote));
					}
				}
			}
			else{
				if($author_id!=$user_id){
					if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
						redis_add_queue('notifications',array($block_time,(int)$author_id,8,(int)$find_post_vote));
					}
				}
			}
			$count--;
		}
		unset($data);
	}

	$count=$redis->lLen('feed:queue');
	while($count>0){
		$data=redis_get_queue('feed');
		if($data[0]){
			$db->sql("INSERT INTO `feed` (`user`,`post`) VALUES ('".$data[0]."','".$data[1]."')");
			$count--;
		}
		unset($data);
	}

	$count=$redis->lLen('notifications:queue');
	while($count>0){
		$data=redis_get_queue('notifications');
		if($data[0]){
			$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$data[0]."','".(int)$data[1]."',".$data[2].",'".(int)$data[3]."')");
			$count--;
		}
		unset($data);
	}
}
exit;