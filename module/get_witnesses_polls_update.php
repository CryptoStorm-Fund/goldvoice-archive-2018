<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
if(!$config['blockchain_parse']){
	exit;
}
$current_block=$db->select_one('blocks','id','ORDER BY `id` DESC');
$q=$db->sql("SELECT * FROM `witnesses_polls` WHERE `end_block`=0 AND `end_time`<'".time()."'");
while($m=$db->row($q)){
	$q2=$db->sql("SELECT * FROM `witnesses_votes` WHERE `poll`='".$m['id']."' AND `end_votes`=0");
	while($m2=$db->row($q2)){
		$db->sql("UPDATE `witnesses_votes` SET `end_votes`='".$db->select_one('witnesses','votes',"WHERE `user`='".$m2['user']."'")."' WHERE `id`='".$m2['id']."'");
	}
	$db->sql("UPDATE `witnesses_polls` SET `end_block`='".$current_block."' WHERE `id`='".$m['id']."'");
}
exit;