<?php
header("Content-type:text/html; charset=UTF-8");
if('registration'==$path_array[2]){
	$code=htmlspecialchars($_POST['code']);
	$invite_struct=array();
	$public=true;
	if(''!=$code){
		$invite_struct=$db->sql_row("SELECT * FROM `invite_struct` WHERE `code`='".$db->prepare($code)."' AND `status`=1");
		if(0==$invite_struct['public']){
			$public=false;
		}
		if(0==$invite_struct['status']){
			print 'closed';
			exit;
		}
	}
	if(0!=$db->table_count('reg_history',"WHERE `ip`='".$db->prepare($ip)."' AND `time`>'".(time() - 86400)."'")){
		if(!$admin){
			if($invite_struct['id']){
				if($session_arr['user']!=$invite_struct['user']){
					print 'ip';
					exit;
				}
			}
			else{
				print 'ip';
				exit;
			}
		}
	}
	if($public){
		if($session_arr['user']!=$invite_struct['user']){
			$fp=file_get_contents('http://api.stopforumspam.org/api?ip='.$ip.'&json');
			$fp_arr=json_decode($fp,true);
			if(1==$fp_arr['ip']['appears']){
				print 'spam';
				exit;
			}
			/** /
			if(checkdnsrr(rip($ip).'.dnsbl.spfbl.net','A')){
				print 'spam';
				exit;
			}
			/*
			*/
			$fp=file_get_contents('http://www.spamrats.com/lookup.php?ip='.$ip);
			$fp=substr($fp,strpos($fp,'<h2>List Status</h2>'));
			$fp=substr($fp,0,strpos($fp,'</div>'));
			if(false!==strpos($fp,'<p class="error"')){
				print 'spam';
				exit;
			}
		}
	}
	$login=$_POST['login'];
	$owner=$_POST['owner'];
	$active=$_POST['active'];
	$posting=$_POST['posting'];
	$memo=$_POST['memo'];
	$name=htmlspecialchars($_POST['name']);
	$about=htmlspecialchars($_POST['about']);
	$location=htmlspecialchars($_POST['location']);
	print 'ok';
	ignore_user_abort(true);
	header("Connection: close");
	fastcgi_finish_request();
	$ip_black_list=array('109.198.122.197','87.229.133.202','46.165.37.108','188.165.27.226','163.172.125.135','163.172.122.131','92.222.212.60','94.23.70.241','178.33.34.238','51.15.149.137','51.15.149.178','185.147.82.209','94.23.147.74','91.142.85.159','163.172.114.151','188.165.27.225','163.172.126.254','163.172.127.88','185.147.82.207','178.32.55.52','5.196.171.99','95.82.239.219','84.237.97.94','51.255.135.111','185.147.82.205','163.172.125.135','198.50.154.197','91.142.85.85','185.147.82.208','185.162.92.50','185.162.92.7','91.142.85.166','158.69.68.231','158.69.83.126','158.69.83.204','54.37.30.97','51.15.199.243','163.172.133.130','147.135.135.233','213.32.74.240','165.227.166.88','103.75.117.130','27.0.232.104','139.59.70.63','103.75.118.106','188.166.78.125','128.199.45.9','128.199.154.124','185.140.114.161','185.140.114.200','185.186.76.161','185.186.76.132','83.170.70.122','158.69.82.254','163.172.182.18','207.154.224.181','54.37.76.181','27.0.232.59','27.0.232.18','139.59.14.48','80.211.168.24','185.140.114.225','188.166.73.27','31.173.87.197','147.135.135.239','54.37.30.93','54.37.77.88','139.59.3.153','103.75.118.126','141.105.70.253','141.105.70.247','139.59.112.84');
	if(in_array($ip,$ip_black_list)){
		exit;
	}
	include('./class/digital_web.php');
	$web=new digital_web();
	$creation_fee='1.000 GOLOS';
	$action_arr=array('method'=>'reg','login'=>$login,'owner'=>$owner,'active'=>$active,'posting'=>$posting,'memo'=>$memo,'name'=>$name,'about'=>$about,'location'=>$location,'creation_fee'=>$creation_fee);
	$web->get_url('https://he.goldvoice.club/api/API_PASS/',false,$action_arr);
	$db->sql("INSERT INTO `reg_history` (`time`,`login`,`invite`,`public`,`ip`,`status`) VALUES ('".time()."','".$db->prepare($login)."','".$invite_struct['id']."','".$public."','".$db->prepare($ip)."',0)");
}
if('check_login'==$path_array[2]){
	$login=$_POST['login'];
	$login_id=get_user_id($login);
	$login_arr=$db->sql_row("SELECT `id` FROM `users` WHERE `id`='".$login_id."'");
	if($login_arr['id']){
		print '{"status":"ok"}';

	}
	else{
		print '{"status":"none"}';
	}
}
if('reg_subscribe_to_list'==$path_array[2]){
	$login=$_POST['login'];
	$login_id=get_user_id($login);
	if($login_id){
		$arr=array();
		$q=$db->sql("SELECT * FROM `reg_subscribes` WHERE `user1`='".$login_id."' AND `status`=0");
		while($m=$db->row($q)){
			$arr[]=get_user_login($m['user2']);
		}
		$arr=array_unique($arr);
		print json_encode(array('status'=>'ok','list'=>$arr));

	}
	else{
		print '{"status":"none"}';
	}
}
if('transfers_history_table'==$path_array[2]){
	$user_login=$db->prepare($_POST['user']);
	$user_id=get_user_id($user_login);
	if($user_id!=0){
		$transfers_arr1=$redis->zrevrange('transfers_to:'.$user_id,'0','500');
		$transfers_arr2=$redis->zrevrange('transfers_from:'.$user_id,'0','500');
		$transfers_arr=array_merge($transfers_arr1,$transfers_arr2);
		$transfers_arr=array_unique($transfers_arr);
		rsort($transfers_arr);
		foreach($transfers_arr as $transfer_id){
			$m=$redis->hgetall('transfers:'.$transfer_id);
			print '<tr class="wallet-history-'.($m['from']==$user_id?'out':'in').'">';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td><span class="wallet-recipient-set">'.get_user_login($m['from']).'</span></td>';
			print '<td><span class="wallet-recipient-set">'.get_user_login($m['to']).'</span></td>';
			print '<td rel="amount"><span class="wallet-balance-set">'.((float)$m['amount']!=0?$m['amount']:'&mdash;').'</span></td>';
			print '<td><span class="wallet-asset-set">'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</span></td>';
			print '<td class="wallet-memo-set">'.strip_tags(text_to_view($m['memo'],false,true),'<a>').'</td>';
			print '</tr>';
		}
		/*
		$q=$db->sql("SELECT * FROM `transfers` WHERE `from`='".$user_id."' OR `to`='".$user_id."' ORDER BY `id` DESC LIMIT 1000");
		while($m=$db->row($q)){
			print '<tr class="wallet-history-'.($m['from']==$user_id?'out':'in').'">';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td><span class="wallet-recipient-set">'.get_user_login($m['from']).'</span></td>';
			print '<td><span class="wallet-recipient-set">'.get_user_login($m['to']).'</span></td>';
			print '<td rel="amount"><span class="wallet-balance-set">'.((float)$m['amount']!=0?$m['amount']:'&mdash;').'</span></td>';
			print '<td><span class="wallet-asset-set">'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</span></td>';
			print '<td class="wallet-memo-set">'.text_to_view($m['memo']).'</td>';
			print '</tr>';
		}
		*/
	}
}
if('transfers_history'==$path_array[2]){
	$user_login=$db->prepare($_POST['user']);
	$user_id=get_user_id($user_login);
	$target_login=$db->prepare($_POST['target']);
	$target_id=get_user_id($target_login);
	$way=$db->prepare($_POST['way']);
	$currency=(int)$_POST['currency'];
	$transfer_id=(int)$_POST['transfer_id'];

	$transfers_arr=array();
	$transfer_id_score=0;
	if('from'==$way){
		if($transfer_id){
			$transfer_id_score=$redis->zscore('transfers_way:'.$user_id.':'.$target_id,$transfer_id);
		}
		else{
			$transfers_id_arr=$redis->zrevrange('transfers_way:'.$user_id.':'.$target_id,0,100);
			$transfer_id=$transfers_id_arr[count($transfers_id_arr)];
			$transfer_id_score=$redis->zscore('transfers_way:'.$user_id.':'.$target_id,$transfer_id);
		}
		$transfers_arr=$redis->zrevrangebyscore('transfers_way:'.$user_id.':'.$target_id,'+inf','('.$transfer_id_score);
	}
	if('to'==$way){
		if($transfer_id){
			$transfer_id_score=$redis->zscore('transfers_way:'.$target_id.':'.$user_id,$transfer_id);
		}
		else{
			$transfers_id_arr=$redis->zrevrange('transfers_way:'.$target_id.':'.$user_id,0,100);
			$transfer_id=$transfers_id_arr[count($transfers_id_arr)];
			$transfer_id_score=$redis->zscore('transfers_way:'.$target_id.':'.$user_id,$transfer_id);
		}
		$transfers_arr=$redis->zrevrangebyscore('transfers_way:'.$target_id.':'.$user_id,'+inf','('.$transfer_id_score);
	}
	if('both'==$way){
		if($transfer_id){
			$transfer_id_score=$redis->zscore('transfers_way:'.$user_id.':'.$target_id,$transfer_id);
			if(!$transfer_id_score){
				$transfer_id_score=$redis->zscore('transfers_way:'.$target_id.':'.$user_id,$transfer_id);
			}
		}
		else{
			$transfers_id_arr1=$redis->zrevrange('transfers_way:'.$user_id.':'.$target_id,0,100);
			$transfers_id_arr2=$redis->zrevrange('transfers_way:'.$target_id.':'.$user_id,0,100);
			$transfers_id_arr=array_merge($transfers_id_arr1,$transfers_id_arr2);
			$transfers_id_arr=array_unique($transfers_id_arr);
			rsort($transfers_id_arr);
			$transfer_id=$transfers_id_arr[count($transfers_id_arr)];
			$transfer_id_score=$redis->zscore('transfers_way:'.$user_id.':'.$target_id,$transfer_id);
			if(!$transfer_id_score){
				$transfer_id_score=$redis->zscore('transfers_way:'.$target_id.':'.$user_id,$transfer_id);
			}
		}
		$transfers_arr1=$redis->zrevrangebyscore('transfers_way:'.$user_id.':'.$target_id,'+inf','('.$transfer_id_score);
		$transfers_arr2=$redis->zrevrangebyscore('transfers_way:'.$target_id.':'.$user_id,'+inf','('.$transfer_id_score);
		$transfers_arr=array_merge($transfers_arr1,$transfers_arr2);
		$transfers_arr=array_unique($transfers_arr);
		rsort($transfers_arr);
	}
	if('any'==$way){
		if($transfer_id){
			$transfer_id_score=$redis->zscore('transfers_from:'.$user_id,$transfer_id);
			if($target_id){
				if(!$transfer_id_score){
					$transfer_id_score=$redis->zscore('transfers_to:'.$target_id,$transfer_id);
				}
			}
		}
		else{
			$transfers_id_arr=$redis->zrevrange('transfers_from:'.$user_id,0,100);
			$transfer_id=$transfers_id_arr[count($transfers_id_arr)];
			$transfer_id_score=$redis->zscore('transfers_from:'.$user_id,$transfer_id);
			if($target_id){
				if(!$transfer_id_score){
					$transfers_id_arr=$redis->zrevrange('transfers_to:'.$target_id,0,100);
					$transfer_id=$transfers_id_arr[count($transfers_id_arr)];
					$transfer_id_score=$redis->zscore('transfers_to:'.$target_id,$transfer_id);
				}
			}
		}
		$transfers_arr1=array();
		if($user_id){
			$transfers_arr1=$redis->zrevrangebyscore('transfers_from:'.$user_id,'+inf','('.$transfer_id_score);
		}
		$transfers_arr2=array();
		if($target_id){
			$transfers_arr2=$redis->zrevrangebyscore('transfers_to:'.$target_id,'+inf','('.$transfer_id_score);
		}
		$transfers_arr=array_merge($transfers_arr1,$transfers_arr2);
		$transfers_arr=array_unique($transfers_arr);
		rsort($transfers_arr);
	}
	foreach($transfers_arr as $transfer_id){
		$m=$redis->hgetall('transfers:'.$transfer_id);
		if((0==$currency)||($currency==$m['currency'])){
			$res='';
			$res.='<tr data-tansfer-id="'.$m['id'].'">';
			$res.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			$res.='<td>'.get_user_login($m['from']).'</td>';
			$res.='<td>'.get_user_login($m['to']).'</td>';
			$res.='<td>'.$m['amount'].'</td>';
			$res.='<td>'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</td>';
			$res.='<td>'.strip_tags(text_to_view($m['memo'],false,true),'<a>').'</td>';
			$res.='</tr>';
			$result_arr[]=$res;
		}
	}
	/*
	$currency_str='';
	if($currency){
		$currency_str=' AND `currency`=\''.$currency.'\'';
	}
	if('both'==$way){
		$sql="SELECT * FROM `transfers` WHERE `id`>'".$transfer_id."' AND ((`from`='".$user_id."' AND `to`='".$target_id."') OR (`from`='".$target_id."' AND `to`='".$user_id."'))".$currency_str." ORDER BY `id` ASC LIMIT 100";
	}
	if('from'==$way){
		$sql="SELECT * FROM `transfers` WHERE `id`>'".$transfer_id."' AND `from`='".$user_id."' AND `to`='".$target_id."'".$currency_str." ORDER BY `id` ASC LIMIT 100";
	}
	if('to'==$way){
		$sql="SELECT * FROM `transfers` WHERE `id`>'".$transfer_id."' AND `from`='".$target_id."' AND `to`='".$user_id."'".$currency_str." ORDER BY `id` ASC LIMIT 100";
	}
	if('any'==$way){
		$from_str='';
		if($user_id){
			$from_str=' AND `from`=\''.$user_id.'\'';
		}
		$to_str='';
		if($target_id){
			$to_str=' AND `to`=\''.$target_id.'\'';
		}
		$sql="SELECT * FROM `transfers` WHERE `id`>'".$transfer_id."'".$from_str.$to_str.$currency_str." ORDER BY `id` ASC LIMIT 100";
	}
	$q=$db->sql($sql);
	$result_arr=array();
	while($m=$db->row($q)){
		$res='';
		$res.='<tr data-tansfer-id="'.$m['id'].'">';
		$res.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
		$res.='<td>'.get_user_login($m['from']).'</td>';
		$res.='<td>'.get_user_login($m['to']).'</td>';
		$res.='<td>'.$m['amount'].'</td>';
		$res.='<td>'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</td>';
		$res.='<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
		$res.='</tr>';
		$result_arr[]=$res;
	}
	$result_arr=array_reverse($result_arr);
	*/
	print implode('',$result_arr);
}
if('publish_feed'==$path_array[2]){
	$gbg_rub=(float)$currencies_price['gbg']['real_rub'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	$real_gold_feed=$golos_rub/$gbg_rub;


	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=1");//GBG 30 days
	$gbg_rub=(float)$arr['sum']/(float)$arr['count'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	$avg_feed=$golos_rub/$gbg_rub;

	$actual_feed=$avg_feed*1.5;//цена фида на 50% меньше от биржевого
	if($actual_feed>$real_gold_feed){
		$actual_feed=$real_gold_feed;
	}
	print round($actual_feed,3);
	exit;
}
if('publish_feed_reverse'==$path_array[2]){
	$gbg_rub=(float)$currencies_price['gbg']['real_rub'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	$real_gold_feed=$golos_rub/$gbg_rub;


	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=1");//GBG 30 days
	$gbg_rub=(float)$arr['sum']/(float)$arr['count'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	$avg_feed=$golos_rub/$gbg_rub;

	$actual_feed=$avg_feed*1.5;//цена фида на 50% меньше от биржевого
	if($actual_feed>$real_gold_feed){
		$actual_feed=$real_gold_feed;
	}
	print round(1/$actual_feed,3);
	exit;
}
if('comment_body'==$path_array[2]){
	$id=(int)$path_array[3];
	print $db->select_one('comments','body',"WHERE `id`='".$id."'");
	exit;
}
if('text_to_view'==$path_array[2]){
	$text=$_POST['text'];
	print text_to_view($text);
	exit;
}
if('comment_html_body'==$path_array[2]){
	$id=(int)$path_array[3];
	print text_to_view($db->select_one('comments','body',"WHERE `id`='".$id."'"));
	exit;
}
if('publish_feed_avg'==$path_array[2]){
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=1");//GBG 30 days
	$gbg_rub=(float)$arr['sum']/(float)$arr['count'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	print round($golos_rub/$gbg_rub,3);
	exit;
}
if('publish_feed_real_gold'==$path_array[2]){
	$gbg_rub=(float)$currencies_price['gbg']['real_rub'];
	$arr=$db->sql_row("SELECT SUM(`price_rub`) as `sum`, COUNT(*) as `count` FROM `currency_prices` WHERE `time`>'".(time()-2592000)."' AND `currency`=2");//GOLOS 30 days
	$golos_rub=(float)$arr['sum']/(float)$arr['count'];
	print round($golos_rub/$gbg_rub,3);
	exit;
}
if('publish_feed_real_gold_actual'==$path_array[2]){
	$gbg_rub=(float)$currencies_price['gbg']['real_rub'];
	$golos_rub=(float)$currencies_price['golos']['rub'];
	$real_gold_feed=round($golos_rub/$gbg_rub,3);
	print $real_gold_feed;
	exit;
}
if('publish_feed_actual'==$path_array[2]){
	print round((float)$currencies_price['golos']['gbg'],3);
	exit;
}
if('notifications_list'==$path_array[2]){
	if($auth){
		$data='';
		$count=$db->table_count('notifications',"WHERE `user`='".$session_arr['user']."' AND `status`='0'");
		$num=0;
		$q=$db->sql("SELECT * FROM `notifications` WHERE `user`='".$session_arr['user']."' AND `status`='0' ORDER BY `id` DESC LIMIT 10");
		while($m=$db->row($q)){
			$notify_html='';
			if(1==$m['type']){
				$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
				$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_1'].'</a>';
			}
			if(2==$m['type']){
				$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
				$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_2'].'</a>';
			}
			if(3==$m['type']){
				$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
				$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_3'].'</a>';
			}
			if(4==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['left_a'].' <a href="'.$comment_link.'">'.$l10n['notifications']['response'].'</a> '.$l10n['notifications']['to_the'].' <a href="'.$post_link.'">'.$l10n['notifications']['type_4_post'].'</a>';
				}
			}
			if(5==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$parent_comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['parent']."'");
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);
					$parent_comment_link=$post_link.'#'.htmlspecialchars($parent_comment_arr['permlink']);
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['left_a'].' <a href="'.$comment_link.'">'.$l10n['notifications']['response'].'</a> '.$l10n['notifications']['to_the'].' <a href="'.$parent_comment_link.'">'.$l10n['notifications']['type_5_comment'].'</a>';
				}
			}
			if(6==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `posts_votes` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['vote_for_the'].' <a href="'.$post_link.'">'.$l10n['notifications']['type_6_post'].'</a>';
				}
			}
			if(7==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `comments_votes` WHERE `id`='".$m['target']."'");
				$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['comment']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$comment_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$comment_link=$post_link.'#'.htmlspecialchars($comment_arr['permlink']);
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['vote_for_the'].' <a href="'.$comment_link.'">'.$l10n['notifications']['type_7_comment'].'</a>';
				}
			}
			if(8==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `posts_votes` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_8'].' <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
				}
			}
			if(9==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `comments_votes` WHERE `id`='".$m['target']."'");
				$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['comment']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$comment_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$comment_link=$post_link.'#'.htmlspecialchars($comment_arr['permlink']);
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_9'].' <a href="'.$comment_link.'">'.$l10n['notifications']['type_9_comment'].'</a>';
				}
			}
			if(10==$m['type']){
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$m['target']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars($post_arr['author_login']).'';
					$user_link='/@'.htmlspecialchars($post_arr['author_login']).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['mentioned_you'].' <a href="'.$post_link.'">'.$l10n['notifications']['type_10_post'].'</a>';
				}
			}
			if(11==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['mentioned_you'].' <a href="'.$comment_link.'">'.$l10n['notifications']['type_11_comment'].'</a>';
				}
			}
			if(12==$m['type']){
				//$notify_type_arr=$db->sql_row("SELECT * FROM `transfers` WHERE `id`='".$m['target']."'");
				$notify_type_arr=$redis->hgetall('transfers:'.$m['target']);
				$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['from'])).'';
				$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['from'])).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_12'].' '.$notify_type_arr['amount'].' '.$currencies_arr2[$notify_type_arr['currency']].'</a>';
			}
			if(13==$m['type']){
				$notify_type_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['target']."'");
				$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$notify_type_arr['parent_post']."' AND `posts`.`status`!=1");
				if($post_arr['id']){
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
					$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['shared_your'].' <a href="'.$post_link.'">'.$l10n['notifications']['type_13_post'].'</a>';
				}
			}
			if(14==$m['type']){
				$user_voter=get_user_login($m['target']);
				$user_login='@'.htmlspecialchars($user_voter).'';
				$user_link='/@'.htmlspecialchars($user_voter).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_14'].'';
			}
			if(15==$m['type']){
				$user_voter=get_user_login($m['target']);
				$user_login='@'.htmlspecialchars($user_voter).'';
				$user_link='/@'.htmlspecialchars($user_voter).'/';
				$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_15'].'';
			}
			if($notify_html){
				$data.='<div class="notify'.(1==$m['status']?'':' notify-unread').'">';
				$data.=$notify_html;
				$data.='</div>';
			}
		}
		if(!$data){
			$data.='<center>'.$l10n['notifications']['none'].'</center>';
		}
		else{
			$data='<a class="clear-notifications"><i class="fa fa-fw fa-bell-slash-o" aria-hidden="true"></i></a><a href="/notifications/?type=new" class="button button-line">'.$l10n['notifications']['new_count'].': '.$count.'</a>'.$data;
		}
		print '{"result":"'.addslashes($data).'","count":"'.$count.'"}';
	}
	else{
		print '{"result":"0","count":"0"}';
	}
}
if('feed_new_posts_count'==$path_array[2]){
	if($auth){
		$id=(float)$_POST['id'];
		$count=redis_unread_feed($session_arr['user'],$id);
		//$count=$db->table_count('feed',"WHERE `user`='".$session_arr['user']."' AND `post`>'".$id."'");
		if(99<$count){
			$count='&gt;99';
		}
		print '{"result":"'.$count.'"}';
	}
	else{
		print '{"result":"0"}';
	}
}
if('new_replies_count'==$path_array[2]){
	if($auth){
		$id=(float)$_POST['id'];
		$count=$db->table_count('notifications',"WHERE `user`='".$session_arr['user']."' AND `type` IN (4,5,10,11) AND `status`=0");
		if(99<$count){
			$count='&gt;99';
		}
		print '{"result":"'.$count.'"}';
	}
	else{
		print '{"result":"0"}';
	}
}
if('clear_notifications'==$path_array[2]){
	if($auth){
		if('1'==$_POST['confirm']){
			$db->sql("UPDATE `notifications` SET `status`=1 WHERE `user`='".$session_arr['user']."'");
		}
	}
}
if('geolocation_name'==$path_array[2]){
	if($auth){
		$lat=(float)$_POST['lat'];
		$lng=(float)$_POST['lng'];
		$name=$db->select_one('geolocation_name','name',"WHERE `lat`='".$lat."' AND `lng`='".$lng."'");
		if(!$name){
			$url='https://maps.googleapis.com/maps/api/geocode/json?latlng='.$lat.','.$lng.'&language=ru&key=AIzaSyCRXHqjsiLhFmD-iLT8R5AEUpN5aX8PiLs';
			$fp=file_get_contents($url);
			$json=json_decode($fp,true);
			$name=$json['results'][1]['formatted_address'];
			$db->sql("INSERT INTO `geolocation_name` (`lat`,`lng`,`name`) VALUES ('".$lat."','".$lng."','".$db->prepare($name)."')");
		}
		print '{"result":"'.addslashes($name).'"}';
	}
	else{
		print '{"result":"auth"}';
	}
}
if('load_new_comments'==$path_array[2]){
	header('HTTP/1.1 200 Ok');
	if($auth){
		$post_id=(int)$_POST['post_id'];
		$last_id=(int)$_POST['last_id'];
		$comment_q=$db->sql("SELECT `comments`.*, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `comments` RIGHT JOIN `users` ON `comments`.`author`=`users`.`id` WHERE `comments`.`post`='".$post_id."' AND `comments`.`status`!=1 AND `comments`.`id`>'".$last_id."' ORDER BY `sort` ASC");
		while($comment=$db->row($comment_q)){
			print comment_to_view($comment['id'],true,true);
			/*
			$payout_decline=false;
			$payout_inpower=false;
			if(0==$comment['percent_steem_dollars']){
				$payout_inpower=true;
			}
			if(0==$comment['max_accepted_payout']){
				if(10000==$comment['percent_steem_dollars']){
					$payout_decline=true;
				}
			}
			$vote=false;
			$flag=false;
			$vote_weight=0;
			$vote_time=0;
			if($auth){
				$user_vote=$db->sql_row("SELECT `time`,`weight` FROM `comments_votes` WHERE `comment`='".$comment['id']."' AND `user`='".$session_arr['user']."'");
				$vote_weight=(double)$user_vote['weight'];
				$vote_time=(int)$user_vote['time'];
				if(0<$vote_weight){
					$vote=true;
				}
				if(0>$vote_weight){
					$flag=true;
				}
				unset($user_vote);
			}
			$votes_count=$db->table_count('comments_votes',"WHERE `comment`='".$comment['id']."' AND `weight`>0");
			$flags_count=$db->table_count('comments_votes',"WHERE `comment`='".$comment['id']."' AND `weight`<0");
			if(''==$comment['author_avatar']){
				$comment['author_avatar']='https://goldvoice.club/images/noava50.png';
			}
			if(''==$comment['author_name']){
				$comment['author_name']=$comment['author_login'];
			}
			print '<div class="comment comment-card clearfix new" data-id="'.$comment['id'].'" data-author="'.$comment['author_login'].'" data-permlink="'.$comment['permlink'].'" data-parent="'.$comment['parent'].'" data-allow-votes="'.$comment['allow_votes'].'" data-allow-replies="'.$comment['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'" data-level="'.$comment['level'].'">';
			print '<div class="comment-avatar"><a href="/@'.$comment['author_login'].'/" class="user-avatar"><img src="https://imgp.golos.io/50x50/'.$comment['author_avatar'].'"></a></div>';
			print '<div class="comment-user"><a href="/@'.$comment['author_login'].'/">'.$comment['author_name'].'</a></div>';
			print '<div class="comment-text">';
			print text_to_view($comment['body']);
			print '</div>';
			print '<div class="comment-info">';
				print '<a class="comment-reply reply-action" data-comment-id="'.$comment['id'].'">'.$l10n['comment_card']['reply'].' <i class="fa fa-fw fa-commenting-o" aria-hidden="true"></i></a>';
				print '<span class="comment-date" data-timestamp="'.$comment['time'].'">'.date('d.m.Y H:i',$comment['time']).'</span>';
				print '<div class="comment-payments'.((float)$comment['payout']?' payout':'').'" data-comment-payout="'.$comment['payout'].'" data-comment-curator-payout="'.$comment['curator_payout'].'" data-comment-pending-payout="'.$comment['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
				if($auth){
					print '<div class="comment-flags flag-action"'.($flag?' title="'.$l10n['flag_card']['comment_time'].' '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
				}
				else{
					print '<div class="comment-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
				}
				if($auth){
					print '<div class="comment-upvotes upvote-action"'.($vote?' title="'.$l10n['upvote_card']['comment_time'].' '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
				}
				else{
					print '<div class="comment-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
				}
			print '</div>';
			print '</div>';
			*/
		}
	}
}
if('load_more'==$path_array[2]){
	$action=$_POST['action'];
	if('new-posts'==$action){
		$last_id=(int)$_POST['last_id'];
		$count=0;
		/* + new posts list */
		$perpage=30;
		$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`status`=0 AND `parent_post`=0 AND `posts`.`id`<'".$last_id."' ORDER BY `posts`.`id` DESC LIMIT ".$perpage." OFFSET 0";
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			print preview_post($m);
			$count++;
		}
		/* - new posts list */
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('feed-posts'==$action){
		$last_id=(int)$_POST['last_id'];
		$user_id=$session_arr['user'];
		if(isset($_POST['user'])){
			$user_id=get_user_id($_POST['user']);
		}
		$count=0;
		$perpage=25;
		/**/
		$feed_arr=redis_read_feed($user_id,$last_id,$perpage);
		foreach($feed_arr as $feed_id){
			$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$feed_id."' AND `posts`.`status`!=1");
			print preview_post($m);
			$count++;
		}
		/* + feed posts list * /
		$sql="SELECT `post` FROM `feed` WHERE `user`='".$user_id."' AND `post`<'".$last_id."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$m['post']."' AND `posts`.`status`!=1");
			print preview_post($m);
			$count++;
		}
		/* - feed posts list */
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('group-feed'==$action){
		$last_id=(int)$_POST['last_id'];
		$group_id=(int)$_POST['group_id'];
		$count=0;
		/* + feed posts list */
		$perpage=100;
		$sql="SELECT `post` FROM `group_feed` WHERE `group`='".$group_id."' AND `post`<'".$last_id."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$m['post']."' AND `posts`.`status`!=1");
			print preview_post($m);
			$count++;
		}
		/* - feed posts list */
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('tag-posts'==$action){
		$count=0;
		$tag=$db->prepare($_POST['tag']);
		$tag_arr=$db->sql_row("SELECT * FROM `tags` WHERE `en`='".$tag."'");
		if($tag_arr['id']){
			$last_id=(int)$_POST['last_id'];
			/* + tag posts list */
			$perpage=30;
			$sql="SELECT `post` FROM `posts_tags` WHERE `tag`='".$tag_arr['id']."' AND `post`<'".$last_id."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post((int)$m['post'],0,0);
				$count++;
			}
			/* - tag posts list */
		}
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('category-posts'==$action){
		$count=0;
		$cat=$db->prepare($_POST['category']);
		$cat_arr=$db->sql_row("SELECT * FROM `categories` WHERE `name`='".$cat."'");
		if($cat_arr['id']){
			$last_id=(int)$_POST['last_id'];
			/* + category posts list */
			$perpage=30;
			$sql="SELECT `post` FROM `posts_categories` WHERE `category`='".$cat_arr['id']."' AND `post`<'".$last_id."' ORDER BY `id` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post((int)$m['post'],0,0);
				$count++;
			}
			/* - category posts list */
		}
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('user-posts'==$action){
		$user_id=(int)$_POST['user_id'];
		$last_id=(int)$_POST['last_id'];
		$count=0;
		$look_user_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`='".$db->prepare($user_id)."' AND `status`!=1");
		if($look_user_arr['id']){
			/* + user posts list */
			$perpage=20;
			$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`author`='".$look_user_arr['id']."' AND `posts`.`status`!=1 AND `posts`.`id`<'".$last_id."' ORDER BY `posts`.`id` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post($m,$look_user_arr['id']);
				$count++;
			}
			/* - user posts list */
		}
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('user-upvotes'==$action){
		$user_id=(int)$_POST['user_id'];
		$last_id=(int)$_POST['last_id'];
		$count=0;
		$look_user_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`='".$db->prepare($user_id)."' AND `status`!=1");
		if($look_user_arr['id']){
			/* + user upvotes list */
			$perpage=20;
			$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts_votes` LEFT JOIN `posts` ON `posts`.`id`=`posts_votes`.`post` AND `posts`.`status`!=1 LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts_votes`.`user`='".$look_user_arr['id']."' AND `posts_votes`.`weight`>0 AND `posts_votes`.`post`<'".$last_id."' ORDER BY `posts_votes`.`post` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post($m);
				$count++;
			}
			/* - user upvotes list */
		}
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
	if('user-flags'==$action){
		$user_id=(int)$_POST['user_id'];
		$last_id=(int)$_POST['last_id'];
		$count=0;
		$look_user_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`='".$db->prepare($user_id)."' AND `status`!=1");
		if($look_user_arr['id']){
			/* + user upvotes list */
			$perpage=20;
			$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts_votes` LEFT JOIN `posts` ON `posts`.`id`=`posts_votes`.`post` AND `posts`.`status`!=1 LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts_votes`.`user`='".$look_user_arr['id']."' AND `posts_votes`.`weight`<0 AND `posts_votes`.`post`<'".$last_id."' ORDER BY `posts_votes`.`post` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post($m);
				$count++;
			}
			/* - user upvotes list */
		}
		if(0==$count){
			print 'none';
		}
		sleep(1);
	}
}
if('check_url'==$path_array[2]){
	$author_login=$_POST['author'];
	$author_id=get_user_id($author_login);
	$permlink=$_POST['permlink'];
	$post_arr=$db->sql_row("SELECT `id` FROM `posts` WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
	if($post_arr['id']){
		$post_data_arr=$db->sql_row("SELECT `id` FROM `posts_data` WHERE `post`='".$db->prepare($post_arr['id'])."'");
		if($post_data_arr['id']){
			print '{"status":"ok"}';
		}
		else{
			print '{"status":"none"}';
		}
	}
	else{
		print '{"status":"none"}';
	}
}
if('post_exist'==$path_array[2]){
	$author_login=$_POST['author'];
	$author_id=get_user_id($author_login);
	$permlink=$_POST['permlink'];
	$post_arr=$db->sql_row("SELECT `id` FROM `posts` WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
	if($post_arr['id']){
		$post_data_arr=$db->sql_row("SELECT `id` FROM `posts_data` WHERE `post`='".$db->prepare($post_arr['id'])."'");
		if($post_data_arr['id']){
			print '{"status":"ok"}';
			if(0==$db->table_count('posts_votes',"WHERE `post`='".$post_arr['id']."' AND `user`='55479'")){
				include('./class/digital_web.php');
				$web=new digital_web();
				$vote_weight=4000;
				$action_delay=1600;
				$vote_preset=array(
					'goldvoice'=>10000,
					'analytics'=>10000,
					'hiddenengine'=>10000,
					'cryptostorm'=>10000,
					'viz.world'=>10000,
				);
				if(array_key_exists($author_login,$vote_preset)){
					$vote_weight=$vote_preset[$author_login];
					$action_delay=300;
				}
				$action_arr=array('method'=>'vote','target_login'=>$author_login,'target_permlink'=>$permlink,'vote_weight'=>$vote_weight,'action_delay'=>$action_delay);
				$web->get_url('https://he.goldvoice.club/api/API_PASS/',false,$action_arr);
			}
		}
		else{
			print '{"status":"none"}';
		}
	}
	else{
		print '{"status":"none"}';
	}
}
if('create_session'==$path_array[2]){
	$key=$_POST['key'];
	$cookie_time=time();
	$cookie=md5($cookie_time.'GoldVoice'.$key).md5($key.'CLUB'.date('d.m.Y'));
	//$db->sql("DELETE FROM `sessions` WHERE `ip`='".$db->prepare($ip)."' AND `cookie`='".$cookie."'");
	$check_session_id=$redis->zscore('sessions_cookie',$cookie);
	if($check_session_id){
		$check_ip=$redis->hget('sessions:'.$check_session_id,'ip');
		if($check_ip==$ip){
			$redis->del('sessions:'.$check_session_id);
		}
	}
	$new_id=$redis->incr('id:sessions');
	if($new_id){
		$redis->zadd('sessions_cookie',$new_id,$cookie);
		$redis->zadd('sessions_key',$new_id,$key);
		$redis->hset('sessions:'.$new_id,'id',$new_id);
		$redis->hset('sessions:'.$new_id,'time',$cookie_time);
		$redis->hset('sessions:'.$new_id,'ip',$ip);
		$redis->hset('sessions:'.$new_id,'key',$key);
		$redis->hset('sessions:'.$new_id,'cookie',$cookie);
	}
	//$db->sql("INSERT INTO `sessions` (`time`,`ip`,`key`,`cookie`) VALUES ('".$cookie_time."','".$db->prepare($ip)."','".$db->prepare($key)."','".$cookie."')");
	header('HTTP/1.1 200 Ok');
	print $cookie;
}
if('user_profile'==$path_array[2]){
	$login=$_POST['login'];
	if(!$login){
		if($auth){
			print json_encode($session_arr['public_profile']);
		}
		else{
			$session_id=$_COOKIE['session_id'];
			$check_session_id=$redis->zscore('sessions_cookie',$session_id);
			if($check_session_id){
				$check_ip=$redis->hget('sessions:'.$check_session_id,'ip');
				if($check_ip==$ip){
					$session_arr=$redis->hgetall('sessions:'.$check_session_id);
				}
			}
			//$session_arr=$db->sql_row("SELECT * FROM `sessions` WHERE `cookie`='".$db->prepare($session_id)."' AND `ip`='".$db->prepare($ip)."' ORDER BY `id` DESC LIMIT 1");
			$current_time_offset=time();
			$session_arr['time']=intval($session_arr['time'])+90;
			if($current_time_offset>$session_arr['time']){
				print '{"error":"rebuild_session","error_str":"'.$current_time_offset.', '.$session_arr['time'].'"}';
			}
			else{
				print '{"error":"wait","error_str":"'.$current_time_offset.', '.$session_arr['time'].'"}';
			}
		}
	}
	else{
		$buf=$db->sql_row('SELECT `id`,`login`,`name`,`avatar`,`reg_time`,`birthday`,`last_post_time`,`action_time`,`timezone`,`balance`,`sbd_balance`,`vesting_shares`,`reputation`,`reputation_short`,`about`,`location`,`birth_location`,`website` FROM `users` WHERE `login`=\''.$db->prepare($login).'\' AND `status`!=1');
		print json_encode($buf);
	}
	usleep(1000);
}
exit;