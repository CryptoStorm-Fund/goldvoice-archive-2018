<?php
set_time_limit(0);
//$info=$redis->info('memory');
//print_r($info);
$max_id=0;
$max_id=$redis->get('id:transfers');
$per=100;
$start_time=time();
$end_time=$start_time+55;
while(time()<$end_time){
	if($max_id){
		$q=$db->sql("SELECT * FROM `transfers` WHERE `id`>'".$max_id."' ORDER BY `id` ASC LIMIT ".$per);
	}
	else{
		$q=$db->sql("SELECT * FROM `transfers` ORDER BY `id` ASC LIMIT ".$per);
	}
	while($m=$db->row($q)){
		if($m['id']>$max_id){
			$max_id=$m['id'];
		}
		foreach($m as $k=>$v){
			if($v){
				$redis->hset('transfers:'.$m['id'],$k,$v);
			}
		}
		$redis->zadd('transfers_from:'.$m['from'],$m['time'],$m['id']);
		$redis->zadd('transfers_to:'.$m['to'],$m['time'],$m['id']);
		$redis->zadd('transfers_way:'.$m['from'].':'.$m['to'],$m['time'],$m['id']);
		$redis->zadd('transfers_to_currency:'.$m['to'].':'.$m['currency'],$m['time'],$m['id']);
		usleep(15);
	}
	$test_max_id=$redis->get('id:transfers');
	if($test_max_id<$max_id){
		$redis->set('id:transfers',$max_id);
	}
}
//$redis->save();
print $max_id;
exit;