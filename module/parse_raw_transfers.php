<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}

$currencies_arr=array('GOLOS'=>1,'GBG'=>2);
$q=$db->sql("SELECT * FROM `raw_operations` WHERE `name`='transfer' ORDER BY `id` ASC LIMIT 7500");
while($m=$db->row($q)){
	$time=$m['time'];
	$arr=unserialize(gzdecode($m['data']));
	$from_id=get_user_id($arr['from']);
	$to_id=get_user_id($arr['to']);
	$amount_arr=explode(' ',$arr['amount']);
	$amount=(float)$amount_arr[0];
	$currency_str=$amount_arr[1];
	$currency=-1;
	if($currencies_arr[$currency_str]){
		$currency=$currencies_arr[$currency_str];
	}
	$memo=$db->prepare($arr['memo']);
	$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$time."','".$from_id."','".$to_id."','".$amount."','".$currency."','".$memo."')");
	$db->sql("DELETE FROM `raw_operations` WHERE `id`='".$m['id']."'");
}
exit;