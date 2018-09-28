<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
set_time_limit(65);
ob_end_clean();
/*
ignore_user_abort(true);
header("Connection: close");
fastcgi_finish_request();*/
$start_time=time();
$end_time=$start_time+59;

$first_id=$db->select_one('transactions','id',"WHERE `parsed_time`!=0 ORDER BY `id` ASC");
$last_id=$db->select_one('transactions','id',"WHERE `parsed_time`!=0 ORDER BY `id` DESC");
$first_arr=$db->sql_row("SELECT `time`,`block` FROM `transactions` WHERE `id`='".$first_id."'");
$last_arr=$db->sql_row("SELECT `time`,`block` FROM `transactions` WHERE `id`='".$last_id."'");

$last_archive=$db->sql_row("SELECT * FROM `archives` ORDER BY `id` DESC LIMIT 1");
$last_archive_table='archive_'.$last_archive['id'];

$assign=false;
if($first_arr['time']<=$last_archive['time_end']){
	if($first_arr['block']<=$last_archive['block_end']){
		$assign=true;
	}
}
/*
print_r($first_arr);
print_r($last_archive);
print $first_id.PHP_EOL;
print $last_id.PHP_EOL;
print $assign.PHP_EOL;
exit;
*/
/*
if($last_arr['time']>=$last_archive['time_start']){
	if($last_arr['block']>=$last_archive['block_start']){
		if($last_arr['time']<=$last_archive['time_end']){
			if($last_arr['block']<=$last_archive['block_end']){
				$assign=true;
			}
		}
	}
}*/

if($assign){
	$archive_id=$last_archive['id'];
	$archive_table=$last_archive_table;
	$first_id=$db->select_one('transactions','id',"WHERE `parsed_time`>='".$last_archive['time_start']."' ORDER BY `id` ASC");
	$last_id=$db->select_one('transactions','id',"WHERE `parsed_time`<='".$last_archive['time_end']."' ORDER BY `id` DESC");
	if(!$last_id){
		$last_id=$first_id;
	}
}
else{
	if(0==$last_archive['finished']){
		$db->sql("REPAIR TABLE `transactions`");
		$db->sql("UPDATE `archives` SET `finished`=1 WHERE `id`='".$last_archive['id']."'");
	}
	$count=$db->table_count('transactions',"WHERE `id`>='".$first_id."' AND `id`<='".$last_id."'");
	$count=intval($count);
	if($count<1000000){
		exit;
	}
	$db->sql("INSERT INTO `archives` (`time_start`,`time_end`,`block_start`,`block_end`) VALUES ('".$first_arr['time']."','".$last_arr['time']."','".$first_arr['block']."','".$last_arr['block']."')");
	$archive_id=$db->last_id();
	$archive_table='archive_'.$archive_id;
	$db->sql("CREATE TABLE `".$archive_table."` (  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,  `block` int NOT NULL,  `time` int NOT NULL,  `parsed_time` int NOT NULL,  `operations` blob NOT NULL) ENGINE='MyISAM';");
	$db->sql("ALTER TABLE `".$archive_table."` ADD INDEX `time` (`time`);");
}


/*
print_r($first_arr);
print_r($last_archive);
print $first_id.PHP_EOL;
print $last_id.PHP_EOL;
print $assign.PHP_EOL;
print $archive_id.PHP_EOL;
print_r($db->history());
exit;
*/

if($archive_id){
	$count=$db->table_count('transactions',"WHERE `id`>='".$first_id."' AND `id`<='".$last_id."'");
	$count=intval($count);
	while(time()<$end_time){
		$q=$db->sql("SELECT `id`,`block`,`time`,`parsed_time`,`operations` FROM `transactions` WHERE `id`>='".$first_id."' AND `id`<='".$last_id."' AND `parsed_time`!=0 ORDER BY `id` ASC LIMIT 1");
		while($m=$db->row($q)){
			$db->sql("DELETE FROM `transactions` WHERE `id`='".$m['id']."'");
			$db->sql("INSERT INTO `".$archive_table."` (`block`,`time`,`parsed_time`,`operations`) VALUES ('".$m['block']."','".$m['time']."','".$m['parsed_time']."','".$db->prepare(gzencode($m['operations'],9))."')");
		}
		$count=$count-1;
		if($count<0){
			exit;
		}
	}
}
exit;