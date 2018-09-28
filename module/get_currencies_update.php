<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
/*
if(!$config['blockchain_parse']){
	exit;
}*/
//set_time_limit(65);
/*
ob_end_clean();
ignore_user_abort(true);
header("Connection: close");
fastcgi_finish_request();*/
$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);

$result_arr=$web->execute_method('get_dynamic_global_properties',array(),false,true);
$total_vesting_fund_steem=str_replace(' GOLOS','',$result_arr['total_vesting_fund_steem']);
$total_vesting_shares=str_replace(' GESTS','',$result_arr['total_vesting_shares']);
$sg_per_vests=(float)$total_vesting_fund_steem/(float)$total_vesting_shares;
$currencies_price=array();
$currencies_price=unserialize($cache->get('currencies_price'));
$currencies_price['sg_per_vests']=$sg_per_vests;
//1 GBG
$gbg_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/golos-gold/?convert=rub');
$gbg_arr=json_decode($gbg_global,true);
if($gbg_arr){
	$gbg_price_btc=floatval($gbg_arr[0]['price_btc']);
	$gbg_price_usd=floatval($gbg_arr[0]['price_usd']);
	$gbg_price_rub=floatval($gbg_arr[0]['price_rub']);
	$stamp_time=time();
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',1,'".$db->prepare($gbg_price_btc)."','".$db->prepare($gbg_price_usd)."','".$db->prepare($gbg_price_rub)."','1')");
	$currencies_price['gbg']=array(
		'btc'=>$gbg_price_btc,
		'usd'=>$gbg_price_usd,
		'rub'=>$gbg_price_rub,
	);
}
$metall_cost_url='http://www.cbr.ru/scripts/xml_metall.asp?date_req1='.date('d/m/Y',time()-(86400*2)).'&date_req2='.date('d/m/Y');
$metall_cost=file_get_contents($metall_cost_url);
$metall_cost_xml=new SimpleXMLElement($metall_cost);
$metall_cost_json=json_encode($metall_cost_xml);
$metall_cost_array=json_decode($metall_cost_json,TRUE);
if($metall_cost_array){
	$metall_cost=floatval(str_replace(',','.',$metall_cost_array['Record'][0]['Buy']))/1000;
	$currencies_price['gbg']['real_rub']=$metall_cost;//rub
}
//print_r($metall_cost_url);exit;
//2 GOLOS
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/golos/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',2,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	$currencies_price['gbg']['golos']=$gbg_price_currency;
	$currencies_price['golos']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}
sleep(1);
//3 BTC
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/bitcoin/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',3,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	//$currencies_price['gbg']['btc']=$gbg_price_currency;
	$currencies_price['btc']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}
sleep(1);
//4 ETH
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/ethereum/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',4,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	$currencies_price['gbg']['eth']=$gbg_price_currency;
	$currencies_price['eth']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}
sleep(1);
//5 STEEM
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/steem/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',5,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	$currencies_price['gbg']['steem']=$gbg_price_currency;
	$currencies_price['steem']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}
sleep(1);
//6 SBD
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/steem-dollars/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',6,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	$currencies_price['gbg']['sbd']=$gbg_price_currency;
	$currencies_price['sbd']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}

//7 SBD
$currency_global=file_get_contents('https://api.coinmarketcap.com/v1/ticker/ripple/?convert=rub');
$currency_arr=json_decode($currency_global,true);
if($currency_arr){
	$currency_price_btc=floatval($currency_arr[0]['price_btc']);
	$currency_price_usd=floatval($currency_arr[0]['price_usd']);
	$currency_price_rub=floatval($currency_arr[0]['price_rub']);
	$currency_price_gbg=round($currency_price_btc/$gbg_price_btc,8);
	$gbg_price_currency=round($gbg_price_btc/$currency_price_btc,8);
	$db->sql("INSERT INTO `currency_prices` (`time`,`currency`,`price_btc`,`price_usd`,`price_rub`,`price_gbg`) VALUES ('".$stamp_time."',7,'".$db->prepare($currency_price_btc)."','".$db->prepare($currency_price_usd)."','".$db->prepare($currency_price_rub)."','".$db->prepare($currency_price_gbg)."')");
	$currencies_price['gbg']['xrp']=$gbg_price_currency;
	$currencies_price['xrp']=array(
		'btc'=>$currency_price_btc,
		'usd'=>$currency_price_usd,
		'rub'=>$currency_price_rub,
		'gbg'=>$currency_price_gbg,
	);
}

if(!$currencies_price['gbg']['real_rub']){
	$currencies_price['gbg']['real_rub']=2.4;
}
$cache->set('currencies_price',serialize($currencies_price),7200);
print_r($db->history());
exit;