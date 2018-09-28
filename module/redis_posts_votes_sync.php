<?php
exit;
set_time_limit(0);
if(!$config['blockchain_parse']){
	exit;
}
//$info=$redis->info('memory');
//print_r($info);
$max_id=0;
$max_id=$redis->get('id:posts_votes:delete');
$per=100;
$start_time=time();
$end_time=$start_time+55;
while(time()<$end_time){
	if($max_id){
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `id`>'".$max_id."' ORDER BY `id` ASC LIMIT ".$per);
	}
	else{
		$q=$db->sql("SELECT * FROM `posts_votes` ORDER BY `id` ASC LIMIT ".$per);
	}
	while($m=$db->row($q)){
		if($m['id']>$max_id){
			$max_id=$m['id'];
		}
		$redis->del('posts_votes:'.$m['id'],$k,$v);
		$redis->del('posts_votes_index:'.$m['post'].':'.$m['user']);
		$redis->del('posts_votes_time_by_post:'.$m['post']);

		$redis->del('posts_votes_by_user:'.$m['user']);
		$redis->del('posts_votes_by_post:'.$m['post']);

		$redis->del('posts_upvotes_by_user:'.$m['user']);
		$redis->del('posts_downvotes_by_user:'.$m['user']);
	}
	$test_max_id=$redis->get('id:posts_votes:delete');
	if($test_max_id<$max_id){
		$redis->set('id:posts_votes:delete',$max_id);
	}
}
/*
$max_id=0;
$max_id=$redis->get('id:posts_votes');
$per=100;
$start_time=time();
$end_time=$start_time+55;
while(time()<$end_time){
	if($max_id){
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `id`>'".$max_id."' ORDER BY `id` ASC LIMIT ".$per);
	}
	else{
		$q=$db->sql("SELECT * FROM `posts_votes` ORDER BY `id` ASC LIMIT ".$per);
	}
	while($m=$db->row($q)){
		if($m['id']>$max_id){
			$max_id=$m['id'];
		}
		foreach($m as $k=>$v){
			if($v){
				$redis->hset('posts_votes:'.$m['id'],$k,$v);
			}
		}
		$redis->set('posts_votes_index:'.$m['post'].':'.$m['user'],$m['id']);
		$redis->zadd('posts_votes_time_by_post:'.$m['post'],$m['time'],$m['id']);

		$redis->zadd('posts_votes_by_user:'.$m['user'],$m['weight'],$m['id']);
		$redis->zadd('posts_votes_by_post:'.$m['post'],$m['weight'],$m['id']);

		$redis->zadd('posts_upvotes_by_user:'.$m['user'],$m['post'],$m['post']);
		$redis->zadd('posts_downvotes_by_user:'.$m['user'],$m['post'],$m['post']);
		usleep(15);
	}
	$test_max_id=$redis->get('id:posts_votes');
	if($test_max_id<$max_id){
		$redis->set('id:posts_votes',$max_id);
	}
}
*/
//$redis->save();
print $max_id;
exit;