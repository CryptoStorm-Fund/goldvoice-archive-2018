<?php
//print 'Syncing...';exit;
ob_start();
$host=mb_strtolower($_SERVER['HTTP_HOST']);
$look_user='';
$page_subtype='';
$replace['menu'].='<nav><div class="menu">';
if('goldvoice.club'!=$host){
	$look_user=str_replace('.goldvoice.club','',$host);
	$look_user=str_replace('-dot-','.',$look_user);
	if('www'==$look_user){
		header('location:https://goldvoice.club/');
		exit;
	}
	$look_user_id=get_user_id($look_user);
	if($look_user_id){
		$t->open('person.tpl','index');
		//$look_user_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`='".$look_user_id."' AND `status`=0");
		$look_user_arr=$redis->hgetall('users:'.$look_user);
		if(0!=$look_user_arr['status']){
			unset($look_user_arr);
			header('location:https://goldvoice.club/');
			exit;
		}
		if(''==$look_user_arr['name']){
			$look_user_arr['name']=$look_user_arr['login'];
		}
		$replace['title']=$look_user_arr['name'].' - '.$replace['title'];
		if($look_user_arr['about']){
			$replace['description']=htmlspecialchars($look_user_arr['about']);
		}
		//adsense
		if($look_user_arr['ad_adsense_client']){
				$replace['head_addon'].='
				<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
				<script>
				(adsbygoogle = window.adsbygoogle || []).push({
					google_ad_client: "'.htmlspecialchars($look_user_arr['ad_adsense_client']).'",
					enable_page_level_ads: true
				});
				</script>';
		}
		print '<div class="page" itemscope itemtype="http://schema.org/Person">';
		if($look_user_arr['avatar']){
			print '<a class="person user-avatar" href="https://goldvoice.club/@'.$look_user_arr['login'].'/"><img src="https://i.goldvoice.club/100x100a/'.$look_user_arr['avatar'].'" itemprop="image" alt="'.$look_user_arr['name'].'"></a>';
			$replace['head_addon'].='
			<link rel="image_src" href="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
			<meta property="og:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
			<meta name="twitter:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />';
		}
		print '<h1 class="person page_title" itemprop="name">'.$look_user_arr['name'].'</h1>';
		print '<p><a itemprop="sameAs" href="https://goldvoice.club/@'.$look_user_arr['login'].'/">'.$l10n['profile']['profile_link'].' @'.$look_user_arr['login'].'</a></p>';
		if($look_user_arr['about']){
			print '<p><i class="fa fa-fw fa-info-circle" aria-hidden="true" title="'.$l10n['profile']['meta_about'].'"></i> <span itemprop="disambiguatingDescription">'.htmlspecialchars($look_user_arr['about']).'</span><p>';
		}
		if($look_user_arr['website']){
			print '<p><i class="fa fa-fw fa-link" aria-hidden="true" title="'.$l10n['profile']['meta_website'].'"></i> <a href="'.htmlspecialchars($look_user_arr['website']).'" target="_blank" rel="nofollow" itemprop="homeLocation">'.htmlspecialchars($look_user_arr['website']).'</a></p>';
		}
		if($look_user_arr['location']){
			print '<p><i class="fa fa-fw fa-map-o" aria-hidden="true" title="'.$l10n['profile']['meta_location'].'"></i> <span itemprop="homeLocation">'.htmlspecialchars($look_user_arr['location']).'</span><p>';
		}
		if($look_user_arr['telegram']){
			print '<p><i class="fa fa-fw fa-telegram" aria-hidden="true" title="Telegram"></i> <a itemprop="telegram" href="tg://resolve?domain='.htmlspecialchars($look_user_arr['telegram']).'">'.htmlspecialchars($look_user_arr['telegram']).'</a><p>';
		}
		print '</div>';

		print '<div class="posts-list" itemid="http://'.htmlspecialchars($look_user).'.goldvoice.club/" itemscope="" itemtype="http://schema.org/LiveBlogPosting">';
		$coverageStartTime=$db->select_one('posts','time',"WHERE `author`='".$look_user_id."' ORDER BY `id` ASC");
		$coverageEndTime=$db->select_one('posts','time',"WHERE `author`='".$look_user_id."' ORDER BY `id` DESC");
		print '<time class="schema" itemprop="coverageStartTime" content="'.date(DATE_ISO8601,$coverageStartTime).'" /></time>';
		print '<time class="schema" itemprop="coverageEndTime" content="'.date(DATE_ISO8601,$coverageEndTime).'"></time>';
		$perpage=100;
		$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`author`='".$look_user_arr['id']."' AND `posts`.`status`!=1 AND `posts`.`parent_post`=0 ORDER BY `posts`.`id` DESC LIMIT ".$perpage." OFFSET 0";
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			$m['author_login']=$redis->get('user_login:'.$m['author']);
			$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
			$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
			print preview_post($m,$look_user_arr['id'],-1,'https://goldvoice.club');
			$count++;
		}
		print '</div>';

		$content=ob_get_contents();
		$page_type='person page-max-wrapper';
		$replace['page']='<div class="'.$page_type.'">'.$content.'</div>';
		if($look_user_arr['cover']){
			$replace['page-profile']=' profile';
			$replace['page-cover']='<div class="person page-cover" style="background-image:url(\'https://i.goldvoice.club/0x0/'.$look_user_arr['cover'].'\')"></div>';
		}
	}
	else{
		header('location:https://goldvoice.club/');
		exit;
	}
}
else{
if('~witnesses'==$path_array[1]){
	header('location:https://goldvoice.club/witnesses/');
	exit;
}
if('market'==$path_array[1]){
	header('location:https://golos.blog/market/');
	exit;
}
if('hot'==$path_array[1]){
	if($path_array[2]){
		header('location:https://goldvoice.club/tags/'.$path_array[2].'/');
	}
	else{
		header('location:https://goldvoice.club/tags/');
	}
	exit;
}
if('created'==$path_array[1]){
	if($path_array[2]){
		header('location:https://goldvoice.club/tags/'.$path_array[2].'/');
	}
	else{
		header('location:https://goldvoice.club/');
	}
	exit;
}
if('vox-populi'==$path_array[1]){
	if(!$path_array[2]){
		$path_array[1]='groups';
		$path_array[2]='vox-populi';
	}
}
if('trending'==$path_array[1]){
	if($path_array[2]){
		header('location:https://goldvoice.club/tags/'.$path_array[2].'/');
	}
	else{
		header('location:https://goldvoice.club/tags/');
	}
	exit;
}
if('promoted'==$path_array[1]){
	if($path_array[2]){
		header('location:https://goldvoice.club/tags/'.$path_array[2].'/');
	}
	else{
		header('location:https://goldvoice.club/categories/goldvoice/');
	}
	exit;
}
if(!$config['blockchain_parse']){
	$replace['page-before'].='<div style="padding:8px;margin:8px 0;font-size:16px;color:#000;background:#fa8;text-align:center;">'.$l10n['global']['site_maintenance'].'</div>';
}
if('test'==$path_array[1]){
	$replace['title']='Session test';
	print '<h1 class="page_title">Session test</h1>';
	print '<hr>';
	$session_id=$_COOKIE['session_id'];
	print '<p>Current session cookie: '.$session_id.'</p>';
	$check_session_id=$redis->zscore('sessions_cookie',$session_id);
	print '<p>Session ID: '.$check_session_id.'</p>';
	print '<p>Session assigned User: '.$session_arr['user'].'</p>';
	print '<p>User by ID: '.get_user_login($session_arr['user']).'</p>';
	print '<p>ID by User: '.get_user_id(get_user_login($session_arr['user'])).'</p>';
	$session_action_time=$redis->zscore('sessions_action_time',$check_session_id);
	print '<p>Session action time: '.$session_action_time.' ('.date('d.m.Y H:i:s',$session_action_time).')</p>';
	$user_action_time=$redis->zscore('users_action_time',get_user_login($session_arr['user']));
	print '<p>User action time: '.$user_action_time.' ('.date('d.m.Y H:i:s',$user_action_time).')</p>';
}
if('login'==$path_array[1]){
	if($auth){
		header('location:/feed/');
		exit;
	}
	$replace['title']=$l10n['login']['page_title'].' - '.$replace['title'];
	print '<h1 class="page_title">'.$l10n['login']['page_title'].'</h1>';
	print '<hr>';
	print '<p>'.$l10n['login']['page_descr'].'</p>';
	print '<form action="/" method="POST" class="login-form">
<div class="login_error">'.$l10n['errors']['login'].'</div>
<input type="text" name="login" placeholder="'.$l10n['modals']['input_login'].'">
<input type="password" name="posting_key" placeholder="'.$l10n['modals']['form_posting_key'].'">
<input type="button" name="login-button" value="'.$l10n['modals']['form_login'].'">
<p>'.$l10n['modals']['attention'].'</p>
</form>';
}
else
if('registration'==$path_array[1]){
	$replace['menu_collapsed']=true;
	$reg_cost=3;
	$public=true;
	$error=false;
	$invite_code=$path_array[2];
	$invite_struct=array();
	if($invite_code){
		$invite_struct=$db->sql_row("SELECT * FROM `invite_struct` WHERE `code`='".$db->prepare($invite_code)."' LIMIT 1");
		if($invite_struct['id']){
			if(0==$invite_struct['status']){
				$error=$l10n['registration']['invite_disabled'];
			}
			else{
				if(0==$invite_struct['public']){
					$public=false;
				}
				//$user_reg_balance=$db->select_one('users','reg_balance',"WHERE `id`='".$invite_struct['user']."'");
				$user_reg_balance=$redis->hget('users:'.get_user_login($invite_struct['user']),'reg_balance');
				if($user_reg_balance<$reg_cost){
					$error=$l10n['registration']['invite_balance_low'];
				}
			}
		}
		else{
			$error=$l10n['registration']['invite_error'];
		}
	}
	if(!$admin){
		if($auth){
			if($session_arr['user']!=$invite_struct['user']){
				header('location:/');
				exit;
			}
		}
	}
	$replace['title']=$l10n['registration']['page_title'].' - '.$replace['title'];
	$replace['descr']='';
	print PHP_EOL.PHP_EOL.'<h1>'.$l10n['registration']['page_title'].'</h1>';
	if($public){
		$sum_balance=0;
		$q=$db->sql("SELECT * FROM `invite_struct` WHERE `status`=1 AND `public`=1");
		while($m=$db->row($q)){
			//$sum_balance+=$db->select_one('users','reg_balance',"WHERE `id`='".$m['user']."'");
			$sum_balance+=$redis->hget('users:'.get_user_login($m['user']),'reg_balance');
		}
		if($admin){
			print '<p>'.$l10n['registration']['public_balance'].': '.round($sum_balance,3).' GOLOS</p>';
		}
		if($sum_balance<$reg_cost){
			$error=$l10n['registration']['public_balance_low'];
		}
	}
	if($public){
		$fp=file_get_contents('http://api.stopforumspam.org/api?ip='.$ip.'&json');
		$fp_arr=json_decode($fp,true);
		if(1==$fp_arr['ip']['appears']){
			$error=$l10n['registration']['ip_report'].' <a href="http://api.stopforumspam.org/api?ip='.$ip.'">StopForumSpam</a> '.$l10n['registration']['ip_report2'].'. '.$l10n['registration']['change_ip'].'.';
		}
		/** /
		if(checkdnsrr(rip($ip).'.dnsbl.spfbl.net','A')){
			$error='Ваш IP-адрес отмечен в базе <a href="http://www.dnsbl.info/dnsbl-database-check.php">DNSBL</a> как спамный. Смените IP для корректной регистрации.';
		}
		/*
		*/
		$fp=file_get_contents('http://www.spamrats.com/lookup.php?ip='.$ip);
		$fp=substr($fp,strpos($fp,'<h2>List Status</h2>'));
		$fp=substr($fp,0,strpos($fp,'</div>'));
		if(false!==strpos($fp,'<p class="error"')){
			$error=$l10n['registration']['ip_report'].' <a href="http://www.dnsbl.info/dnsbl-database-check.php">DNSBL</a> '.$l10n['registration']['ip_report2'].'. '.$l10n['registration']['change_ip'].'.';
		}
	}

	if($error){
		print '<p class="red">'.$error.'</p>';
	}
	else{
		/*
		if($invite_struct['id']){
			print '<p><strong>'.htmlspecialchars($invite_struct['name']).'</strong> &mdash; '.htmlspecialchars($invite_struct['descr']).'</p>';
		}*/
		print '<form class="registration-form" action="" onsubmit="return check_registration_form();" method="POST">';
		print '<p><input class="bubble" type="text" name="login"> &mdash; '.$l10n['registration']['form_login'].' <span class="red">*</span> ('.$l10n['registration']['form_login_symbols'].')</p>';
		print '<p><input class="bubble pass" type="text"> &mdash; '.$l10n['registration']['form_password'].' <span class="red">*</span> ('.$l10n['registration']['form_password_length'].')</p>';
		print '<input type="hidden" name="code" value="'.htmlspecialchars($invite_code).'">';
		print '<input type="hidden" name="owner" value="">';
		print '<input type="hidden" name="active" value="">';
		print '<input type="hidden" name="posting" value="">';
		print '<input type="hidden" name="memo" value="">';
		print '<p><input class="bubble" type="text" name="name"> &mdash; '.$l10n['registration']['form_name'].'</p>';
		print '<p><input class="bubble" type="text" name="about"> &mdash; '.$l10n['registration']['form_about'].'</p>';
		print '<p><input class="bubble" type="text" name="location"> &mdash; '.$l10n['registration']['form_location'].'</p>';
		print '<p class="red">'.$l10n['registration']['form_attention'].'</p>';
		print '<div class="important"></div>';
		print '<p class="approve"><label><input type="checkbox" name="approve"> &mdash; '.$l10n['registration']['form_approve'].'</label></p>';
		print '<p><input class="action-button" type="submit" name="registration" value="'.$l10n['registration']['form_submit'].'"></p>';
		print '</form>';
		print '<div class="subscribe-history"></div>';
	}
}
else
if('invites'==$path_array[1]){
	if(!$auth){
		header('location:/');
		exit;
	}
	$replace['menu_collapsed']=true;
	if($_POST['create_invite_project']){
		if($_POST['name']){
			if($_POST['subscribes']){
				$subscribes='';
				$subscribes_arr=explode(',',$_POST['subscribes']);
				$subscribes_arr2=array();
				foreach($subscribes_arr as $k=>$v){
					$v=strtolower(trim($v," \r\n\t@,"));
					if($v){
						$subscribes_arr2[]=htmlspecialchars($v);
					}
				}
				$subscribes=implode(',',$subscribes_arr2);
				$code=md5('GoldVoice'.$subscribes.time());
				$public=0;
				if('on'==$_POST['public']){
					$public=1;
				}
				$db->sql("INSERT INTO `invite_struct` (`user`,`name`,`descr`,`subscribes`,`status`,`public`,`code`) VALUES ('".$session_arr['user']."','".$db->prepare(htmlspecialchars($_POST['name']))."','".$db->prepare(htmlspecialchars($_POST['descr']))."','".$db->prepare($subscribes)."',1,".$public.",'".$code."')");
			}
		}
		header('location:/invites/');
		exit;
	}
	if($_GET['change_public']){
		$invite_id=intval($_GET['change_public']);
		$public=$db->select_one('invite_struct','public',"WHERE `id`='".$invite_id."' AND `user`='".$session_arr['user']."'");
		if(1==$public){
			$public=0;
		}
		else{
			$public=1;
		}
		$db->sql("UPDATE `invite_struct` SET `public`='".$public."' WHERE `id`='".$invite_id."' AND `user`='".$session_arr['user']."'");
		header('location:/invites/');
		exit;
	}
	if($_GET['change_status']){
		$invite_id=intval($_GET['change_status']);
		$status=$db->select_one('invite_struct','status',"WHERE `id`='".$invite_id."' AND `user`='".$session_arr['user']."'");
		if(1==$status){
			$status=0;
		}
		else{
			$status=1;
		}
		$db->sql("UPDATE `invite_struct` SET `status`='".$status."' WHERE `id`='".$invite_id."' AND `user`='".$session_arr['user']."'");
		header('location:/invites/');
		exit;
	}
	$replace['title']='Система инвайтов - '.$replace['title'];
	$replace['descr']='';
	print '<a href="/invites/balance/" class="right action-button">Пополнить баланс</a>';
	print '<a href="/invites/add/" class="right action-button">Добавить проект</a>';
	print '<a href="/invites/" class="right action-button">Проекты</a>';
	print '<h1 class="page_title">Система инвайтов</h1>';
	print '<p>Ваш текущий баланс для регистраций: '.round($redis->hget('users:'.get_user_login($session_arr['user']),'reg_balance'),3).'</p><hr>';
	//$db->select_one('users','reg_balance',"WHERE `id`='".$session_arr['user']."'")
	if(''==$path_array[2]){
		print '<h2>Проекты</h2>';
		print '<table><thead><tr><th>Проект</th><th>Описание</th><th>Аккаунты участники</th><th>Тип</th><th>Статус</th><th>Инвайт-код</th></tr></thead><tbody>';
		$invite_struct_arr=array();
		$invite_struct_list=array();
		$q=$db->sql("SELECT * FROM `invite_struct` WHERE `user`='".$session_arr['user']."' ORDER BY `id` DESC");
		while($m=$db->row($q)){
			$invite_struct_list[]=$m['id'];
			$invite_struct_arr[$m['id']]=$m['name'];
			print '<tr>';
			print '<td>'.$m['name'].'</td>';
			print '<td>'.$m['descr'].'</td>';
			$subscribes_arr=explode(',',$m['subscribes']);
			$subscribes_arr2=array();
			foreach($subscribes_arr as $k=>$v){
				$subscribes_arr2[]='<a href="/@'.$v.'/">'.$v.'</a>';
			}
			print '<td>'.implode('<br>',$subscribes_arr2).'</td>';
			print '<td><a href="/invites/?change_public='.$m['id'].'">'.($m['public']?'Публичный':'Приватный').'</a></td>';
			print '<td><a href="/invites/?change_status='.$m['id'].'">'.($m['status']?'Запущен':'Остановлен').'</a></td>';
			print '<td><a href="/registration/'.$m['code'].'/">'.$m['code'].'</a></td>';
			print '</tr>';
		}
		print '</tbody></table>';
		if(0<count($invite_struct_list)){
			print '<hr><h3>История регистраций</h3>';
			print '<table><thead><tr><tr><th>Проект</th><th>Пользователь</th><th>Подписка</th><th>Сумма списания</th><th>Результат</th><th>Дата</th></tr></thead><tbody>';
			$q=$db->sql("SELECT * FROM `reg_subscribes` WHERE `invite` IN (".implode(',',$invite_struct_list).") ORDER BY `id` DESC");
			while($m=$db->row($q)){
				print '<tr>';
				print '<td>'.$invite_struct_arr[$m['invite']].'</td>';
				print '<td><a href="/@'.get_user_login($m['user1']).'/" target="_blank" data-id="'.$m['user1'].'">@'.get_user_login($m['user1']).'</a></td>';
				print '<td><a href="/@'.get_user_login($m['user2']).'/" target="_blank">@'.get_user_login($m['user2']).'</a></td>';
				print '<td>'.$m['amount'].'</td>';
				print '<td>'.(2==$m['status']?'<span class="red">Отписан</span>':'').(1==$m['status']?'<span class="green">Подписан</span>':'').'</td>';
				print '<td>'.(2==$m['status']?'<span class="timestamp" data-timestamp="'.$m['unsubscribe_time'].'">'.date('d.m.Y H:i:s',$m['unsubscribe_time']).'</span>':'').(1==$m['status']?'<span class="timestamp" data-timestamp="'.$m['subscribe_time'].'">'.date('d.m.Y H:i:s',$m['subscribe_time']).'</span>':'').'</td>';
				print '</tr>';
			}
			print '</tbody></table>';
		}
	}
	elseif('add'==$path_array[2]){
		print '<h2>Добавить проект</h2>';
		print '<form action="" method="POST">';
		print '<p><input type="text" name="name"> &mdash; Название проекта</p>';
		print '<p><input type="text" name="descr"> &mdash; Описание проекта</p>';
		print '<p><input type="text" name="subscribes"> &mdash; Логины аккаунтов через запятую</p>';
		print '<p><label><input type="checkbox" name="public"> &mdash; Проект участвует в публичной программе регистраций</label></p>';
		print '<p><em>Инвайт-код формируется автоматически. Вы увидите его после создания проекта.</em></p>';
		print '<input type="submit" class="action-button" name="create_invite_project" value="Создать инвайт-код для проекта">';
		print '</form>';
	}
	elseif('balance'==$path_array[2]){
		print '<h2>Баланс для регистраций</h2>';
		print '<div class="unlock-active-key"></div>';
		print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
			print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
			print '<p class="wallet-balances">';
				print 'Токенов на кошельке: <span class="wallet-balance-set" rel="golos" data-asset="GOLOS">0</span> Golos';
			print '</p>';

			print '<div class="wallet-transfer">';
				print '<p><input type="text" name="recipient" value="goldvoice" disabled="disabled"> &mdash; Получатель</p>';
				print '<p><select name="asset">';
				print '<option value="GOLOS">GOLOS</option>';
				print '</select> &mdash; Ассет</p>';
				print '<p><input type="text" name="amount"> &mdash; Количество</p>';
				print '<p><input type="text" name="memo" value="reg" disabled="disabled"> &mdash; Заметка</p>';
				print '<p><em>Внимание! Время поступления не мгновенное, обновите страницу через минуту.</em></p>';
				print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Пополнить баланс</div>';
			print '</div>';
		print '</form>';
		print '<hr><h2>История поступлений</h2>';
		print '<table><thead><tr><th>Дата</th><th>Пользователь</th><th>Количество</th><th>Статус платежа</th></tr></thead><tbody>';
		$q=$db->sql("SELECT * FROM `reg_transfers` WHERE `user`='".$session_arr['user']."' ORDER BY `id` DESC");
		while($m=$db->row($q)){
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.get_user_login($m['user']).'</td>';
			print '<td>'.$m['amount'].'</td>';
			print '<td>'.($m['status']?'Начислен':'Обрабатывается').'</td>';
			print '</tr>';
		}
		print '</tbody></table>';
	}
}
else
if('toto-wallet'==$path_array[1]){
	$replace['menu_collapsed']=true;
	$replace['title']='Кошелек Тото - '.$replace['title'];
	$replace['descr']='';
	print '<h1 class="page_title">Кошелек Тото</h1>';
	print '<div class="unlock-active-key" data-preset-login="toto-" data-action="update_transfers_history"></div>';
	print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
	print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
	//print '<div class="wallet-withdraw-vesting action-button right"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i> Начать понижение Силы Голоса</div>';
	//print '<div class="wallet-stop-withdraw-vesting action-button right"><i class="fa fa-fw fa-times" aria-hidden="true"></i> Остановить понижение Силы Голоса</div>';
	print '<h2>Баланс</h2>';
	print '<p class="wallet-balances">';
	print '<span class="wallet-balance-set" rel="golos" data-asset="GOLOS">0</span> Golos';
	//print '<span class="wallet-balance-set" rel="golos_power">0</span> Силы Голоса <span class="wallet-withdraw-vesting-status"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i></span><br>';
	//print '<span class="wallet-balance-set" rel="gbg" data-asset="GBG">0</span> GBG<br>';
	//print '<div class="wallet-savings-balances"><i class="fa fa-fw fa-lock" aria-hidden="true"></i> Сейф: <span class="wallet-savings-balance" rel="golos">0</span> Golos <span class="wallet-savings-withdraw" rel="golos" data-asset="GOLOS"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i></span>, <span class="wallet-savings-balance" rel="gbg">0</span> GBG <span class="wallet-savings-withdraw" rel="gbg" data-asset="GBG"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i></span>';
	/*print '<div class="wallet-savings-withdraw-form"><em>Внимание! Операции вывода токенов из Сейфа занимает 3 дня.</em><br>Вывести из Сейфа: <input type="text" name="amount" placeholder="Количество"> <select name="asset">';
	foreach($currencies_arr2 as $k=>$v){
		print '<option value="'.$v.'">'.$v.'</option>';
	}
	print '</select> <div class="wallet-savings-withdraw-action action-button"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i> Запустить процесс вывода</div></div>';
	print '</div>';
	print '<div class="wallet-savings-cancel"><p>Для отмены вывода токенов из Сейфа, введите код запроса:</p><input type="text" name="request_code" placeholder="Код запроса"> <div class="wallet-savings-cancel-action action-button"><i class="fa fa-fw fa-times" aria-hidden="true"></i> Отменить</div></div>';
	*/
	print '</p>';

	print '<hr><h2>Выполнить перевод</h2>';
	print '<div class="wallet-transfer">';
	print '<p><input type="text" name="recipient"> &mdash; Получатель</p>';
	print '<p style="display:none;"><select name="asset" disabled="disabled">';
	print '<option value="GOLOS">GOLOS</option>';
	print '</select> &mdash; Ассет</p>';
	//print '<p class="wallet-vesting"><label><input type="checkbox" name="vesting"> &mdash; Перевод в Силу Голоса</label></p>';
	//print '<p class="wallet-savings"><label><input type="checkbox" name="savings"> &mdash; Перевод в Сейф</label></p>';
	print '<p><input type="text" name="amount"> &mdash; Количество GOLOS</p>';
	print '<p><input type="text" name="memo"> &mdash; Заметка</p>';
	print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Отправить перевод</div>';
	print '</div>';

	print '</form>';
	/*
	print '<hr><h2>История платежей</h2>';
	print '<input class="bubble small-size right" type="text" name="wallet-history-filter-amount2" placeholder="До&hellip;" tabindex="3">';
	print '<input class="bubble small-size right" type="text" name="wallet-history-filter-amount1" placeholder="От&hellip;" tabindex="2">';
	print '<input class="bubble right" type="text" name="wallet-history-filter" placeholder="Фильтр&hellip;" tabindex="1">';
	print '<div class="action-button wallet-history-filter-all"><i class="fa fa-fw fa-globe" aria-hidden="true"></i> Все</div>';
	print '<div class="action-button wallet-history-filter-in"><i class="fa fa-fw fa-arrow-circle-down" aria-hidden="true"></i> Входящие</div>';
	print '<div class="action-button wallet-history-filter-out"><i class="fa fa-fw fa-arrow-circle-up" aria-hidden="true"></i> Исходящие</div>';
	print '<div class="wallet-history"><table><thead><tr><th>Дата</th><th>Отправитель</th><th>Получатель</th><th>Количество</th><th>Ассет</th><th>Заметка</th></tr></thead><tbody><tr><td colspan="6"><center>Вам необходимо разблокировать кошелек своим приватным активным ключом</center></td></tr></tbody></table></div>';
	*/

	print '<hr><h2>История переводов</h2>';
	print '<table class="transfers_history" data-user="'.$session_arr['public_profile']['login'].'" data-way="any" data-target="'.$session_arr['public_profile']['login'].'" data-currency="1"><thead><tr><th>Дата</th><th>Отправитель</th><th>Получатель</th><th>Количество</th><th>Ассет</th><th>Заметка</th></tr></thead><tbody>';
	print '</tbody></table>';
/*
	$replace['menu_collapsed']=true;
	$replace['title']='Игра Тото totogame.io - '.$replace['title'];
	$replace['descr']='';
	print '<h1 class="page_title">Игра Тото totogame.io</h1><p>Игра &laquo;Тото&raquo; <a href="/@rps/">@toto</a> (<a href="https://goldvoice.club/@toto/totostart/" target="_blank">инструкция</a>)</p>';
	print '<hr><div class="unlock-active-key" data-preset-login="toto-"></div>';
	print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
		print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
		print '<p class="wallet-balances">';
			print 'Токенов на кошельке: <span rel="golos" data-asset="GOLOS">0</span> GOLOS<br>';
		print '</p>';
		print '<div class="wallet-transfer">';
			print '<p><input type="text" name="recipient" value="toto" disabled="disabled"> &mdash; Получатель, <select name="asset" disabled="disabled">';
			print '<option value="GOLOS">GOLOS</option>';
			print '</select> &mdash; Ассет</p>';
			print '<p>Размер ставки: <input type="hidden" name="amount" data-autoclear="false" value="1">
			<span class="selectable selected" rel="amount" data-input="amount" data-value="1">1 GBG</span>
			<span class="selectable" rel="amount" data-input="amount" data-value="11">11 GBG</span>
			<span class="selectable" rel="amount" data-input="amount" data-value="21">21 GBG</span>
			</p>';
			print '<p>Ваш выбор: <input type="hidden" name="memo" value="✊">
			<span class="selectable selected" rel="memo" data-input="memo" data-value="✊">✊ — Камень</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="✌️">✌️ — Ножницы</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="✋">✋ — Бумага</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="🦎">🦎 — Ящерица</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="🖖">🖖 — Спок</span>
			<!--
			-->
			</p>';
			print '<div class="wallet-send-action" data-action="update_transfers_history" data-action-onload="true"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Сыграть с @toto</div>';
		print '</div>';
	print '</form>';
	print '<hr><h2>История переводов</h2>';
	print '<table class="transfers_history" data-user="'.$session_arr['public_profile']['login'].'" data-way="both" data-target="rps" data-currency="1"><thead><tr><th>Дата</th><th>Отправитель</th><th>Получатель</th><th>Количество</th><th>Ассет</th><th>Заметка</th></tr></thead><tbody>';

	$transfers_arr1=$redis->zrevrange('transfers_way:'.$session_arr['user'].':90039','0','50');
	$transfers_arr2=$redis->zrevrange('transfers_way:90039:'.$session_arr['user'],'0','50');
	$transfers_arr=array_merge($transfers_arr1,$transfers_arr2);
	$transfers_arr=array_unique($transfers_arr);
	rsort($transfers_arr);
	//print_r($transfers_arr);
	foreach($transfers_arr as $transfer_id){
		$m=$redis->hgetall('transfers:'.$transfer_id);
		print '<tr data-tansfer-id="'.$m['id'].'">';
		print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
		print '<td>'.get_user_login($m['from']).'</td>';
		print '<td>'.get_user_login($m['to']).'</td>';
		print '<td>'.$m['amount'].'</td>';
		print '<td>'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</td>';
		print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
		print '</tr>';
	}

	print '</tbody></table>';
		*/
}
else
if('rps'==$path_array[1]){
	$replace['menu_collapsed']=true;
	$replace['title']='Rock, Paper, Scissors, Lizard, Spock - '.$replace['title'];
	$replace['descr']='';
	print '<h1 class="page_title">✂️ Rock, Paper, Scissors, Lizard, Spock</h1><p>Игра &laquo;Камень-Ножницы-Бумага&raquo; проекта <a href="/@rps/">@rps</a> (<a href="https://goldvoice.club/@rps/ya-rps-bot-krupe-s-kotorym-vy-mozhete-sygrat-v-na-golose/" target="_blank">инструкция</a>, <a href="https://goldvoice.club/@rps/apgreid-bota-rps-rasshirennaya-versiya-igry-i-izmeneniya-v-pravilakh/" target="_blank">обновление</a>)</p>';
	print '<hr><div class="unlock-active-key"></div>';
	print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
		print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
		print '<p class="wallet-balances">';
			print 'Токенов на кошельке: <span rel="gbg" data-asset="GBG">0</span> GBG<br>';
		print '</p>';

		print '<div class="wallet-transfer">';
			print '<p><input type="text" name="recipient" value="rps" disabled="disabled"> &mdash; Получатель, <select name="asset" disabled="disabled">';
			print '<option value="GBG">GBG</option>';
			print '</select> &mdash; Ассет</p>';
			print '<p>Размер ставки: <input type="hidden" name="amount" data-autoclear="false" value="1">
			<span class="selectable selected" rel="amount" data-input="amount" data-value="1">1 GBG</span>
			<span class="selectable" rel="amount" data-input="amount" data-value="11">11 GBG</span>
			<span class="selectable" rel="amount" data-input="amount" data-value="21">21 GBG</span>
			</p>';
			print '<p>Ваш выбор: <input type="hidden" name="memo" value="✊">
			<span class="selectable selected" rel="memo" data-input="memo" data-value="✊">✊ — Камень</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="✌️">✌️ — Ножницы</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="✋">✋ — Бумага</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="🦎">🦎 — Ящерица</span>
			<span class="selectable" rel="memo" data-input="memo" data-value="🖖">🖖 — Спок</span>
			<!--
			-->
			</p>';
			print '<div class="wallet-send-action" data-action="update_transfers_history" data-action-onload="true"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Сыграть с @rps</div>';
		print '</div>';
	print '</form>';

	print '<hr><h2>История переводов</h2>';
	print '<table class="transfers_history" data-user="'.$session_arr['public_profile']['login'].'" data-way="both" data-target="rps" data-currency="2"><thead><tr><th>Дата</th><th>Отправитель</th><th>Получатель</th><th>Количество</th><th>Ассет</th><th>Заметка</th></tr></thead><tbody>';
	$transfers_arr1=$redis->zrevrange('transfers_way:'.$session_arr['user'].':90039','0','50');
	$transfers_arr2=$redis->zrevrange('transfers_way:90039:'.$session_arr['user'],'0','50');
	$transfers_arr=array_merge($transfers_arr1,$transfers_arr2);
	$transfers_arr=array_unique($transfers_arr);
	rsort($transfers_arr);
	foreach($transfers_arr as $transfer_id){
		$m=$redis->hgetall('transfers:'.$transfer_id);
		print '<tr data-tansfer-id="'.$m['id'].'">';
		print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
		print '<td>'.get_user_login($m['from']).'</td>';
		print '<td>'.get_user_login($m['to']).'</td>';
		print '<td>'.$m['amount'].'</td>';
		print '<td>'.($currencies_arr2[$m['currency']]?$currencies_arr2[$m['currency']]:'&mdash;').'</td>';
		print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
		print '</tr>';
	}
	print '</tbody></table>';
}
else
if('uplift'==$path_array[1]){
	$replace['title']='UpLift - '.$replace['title'];
	$replace['descr']='';
	if('history'==$path_array[2]){
		$replace['title']='История - '.$replace['title'];
		print '<a href="/uplift/" class="right">&larr; Вернуться назад</a>';
		print '<h1 class="page_title"><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> История за неделю &mdash; UpLift</h1><hr>';
		$week_time=time()-3600*24*7;
		$bid_arr=array();
		$currency_arr=array();
		$transfers_arr=$redis->zrevrangebyscore('transfers_to:88044','+inf',$week_time);
		foreach($transfers_arr as $transfer_id){
			$m=$redis->hgetall('transfers:'.$transfer_id);
			preg_match_all('~\/@(.*)\/(.*)$~iUs',$m['memo'],$matches);
			if($matches[1][0]){
				$hash=md5($matches[1][0].trim($matches[2][0],'/'));
				$bid_arr[$hash]+=$m['amount'];
				$currency_arr[$hash]=$m['currency'];
			}
		}
/*
		$q=$db->sql("SELECT * FROM `transfers` WHERE `to`=88044 AND `currency`=2 AND `time`>'".$week_time."'");
		while($m=$db->row($q)){
			preg_match_all('~\/@(.*)\/(.*)$~iUs',$m['memo'],$matches);
			if($matches[1][0]){
				$bid_arr[md5($matches[1][0].trim($matches[2][0],'/'))]+=$m['amount'];
			}
		}
*/
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `user`=88048 AND `time`>='".($week_time)."' ORDER BY `id` DESC");
		print '<table><thead><tr><th>Дата</th><th>Ставка</th><th>Процент</th><th>Пост</th></tr></thead><tbody>';
		$last_time=time();
		while($m=$db->row($q)){
			if(($last_time - $m['time'])>1200){
				/*
				$q2=$db->sql("SELECT * FROM `comments_votes` WHERE `user`=88048 AND `time`>='".($last_time)."' AND `time`<='".$m['time']."' ORDER BY `id` DESC");
				while($m2=$db->row($q2)){
					$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m2['comment']."'");
					$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$comment_arr['post']."'");
					$post_author_login=get_user_login($post_arr['author']);
					print '<tr>';
					print '<td><span class="timestamp" data-timestamp="'.$m2['time'].'">'.date('d.m.Y H:i:s',$m2['time']).'</span></td>';
					print '<td>'.round($m2['weight']/100,2).'%</td>';
					print '<td>'.text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/#'.$comment_arr['permlink'].'').'</td>';
					print '</tr>';
				}*/
				print '<tr><td colspan="4"><center><b>Окно ставок</b></center></td></tr>';
			}
			$last_time=$m['time'];
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['post']."'");
			$post_author_login=get_user_login($post_arr['author']);
			$hash=md5($post_author_login.$post_arr['permlink']);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.($bid_arr[$hash]).' '.$currencies_arr2[$currency_arr[$hash]].'</td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			//$bid_cost=$db->sql_row("SELECT SUM(`amount`) as `amount_sum` FROM `transfers` WHERE `to`=88044 AND `currency`=2 AND `time`>'".$week_time."' AND `time`<'".$bid_window_time."' AND `memo` LIKE '%/@".$db->prepare($post_author_login)."/".$db->prepare($post_arr['permlink'])."%'");
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/'),'<a>').'</td>';
			print '</tr>';
		}
		print '</tbody></table>';
	}
	else{
		print '<a href="/uplift/history/" class="right action-button"><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> История</a>';
		print '<h1 class="page_title">UpLift</h1><p>Игра &laquo;Перебей ставку&raquo; проекта <a href="/@uplift/">@uplift</a> (при поддержке <a href="/@whalepunk/">@whalepunk</a>)</p>';
		print '<hr><div class="unlock-active-key"></div>';
		print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
			print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
			print '<p class="wallet-balances">';
				print 'Токенов на кошельке: <span class="wallet-balance-set" rel="gbg" data-asset="GBG">0</span> GBG<br>';
			print '</p>';

			print '<div class="wallet-transfer">';
				print '<p><input type="text" name="recipient" value="uplift" disabled="disabled"> &mdash; Получатель</p>';
				print '<p><select name="asset" disabled="disabled">';
				print '<option value="GBG">GBG</option>';
				print '</select> &mdash; Ассет</p>';
				print '<p><input type="text" name="amount"> &mdash; Количество</p>';
				print '<p><input type="text" name="memo"> &mdash; Заметка (url поста)</p>';
				print '<p><em>Внимание! Время поступления не мгновенное, обновите страницу через минуту.</em></p>';
				print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Заплатить @uplift</div>';
			print '</div>';
		print '</form>';
		print '<hr><h2>История последних ставок</h2>';
		//$booster_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`=88048");
		$booster_arr=$redis->hgetall('users:88048');
		print '<p>Текущая энергия для голосования аккаунта @whalepunk: <span class="user_voting_power" data-login="whalepunk">'.round($booster_arr['voting_power']/100,2).'%</span></p>';
		$ready_time=(10000-$booster_arr['voting_power'])*43.2;//100% recovery 120 hours = 7200 minutes = 432000 seconds
		print '<p>Энергия восстановится примерно через: <span class="user_voting_power_recovery" data-login="whalepunk">'.round($ready_time/60,1).'</span> минут'.($admin?', <span class="user_voting_power_recovery_sec" data-login="whalepunk">'.ceil($ready_time).'</span> секунд':'').'</p>';
		$last_upvote_post_time=$db->select_one('posts_votes','time',"WHERE `user` = '88044' ORDER BY `id` DESC")-60;
		$last_upvote_post_time=$db->select_one('posts_votes','time',"WHERE `user` = '88044' AND `time`>'".($last_upvote_post_time-120)."' ORDER BY `id` ASC");
		$last_upvote_comment_time=$db->select_one('comments_votes','time',"WHERE `user` = '88044' ORDER BY `id` DESC");
		$last_upvote_time=max($last_upvote_comment_time,$last_upvote_post_time);
		print '<p>Последний голос от @uplift был: <span class="timestamp" data-timestamp="'.$last_upvote_time.'">'.date('d.m.Y H:i:s',$last_upvote_time).'</span></p>';
		$buf=$cache->get('uplift_bids_table');
		$buf=false;
		if($buf){
			print $buf;
		}
		else{
			$buf='<table><thead><tr><th>Дата</th><th>Количество</th><th>Предполагаемый процент</th><th>Заметка</th></tr></thead><tbody>';
			$sum_amount=0;
			$transfers_arr=$redis->zrevrangebyscore('transfers_to:88044','+inf',$last_upvote_time);
			foreach($transfers_arr as $transfer_id){
				$amount=$redis->hget('transfers:'.$transfer_id,'amount');
				if($amount>0.001){
					$sum_amount+=$amount;
				}
			}
			foreach($transfers_arr as $transfer_id){
				$m=$redis->hgetall('transfers:'.$transfer_id);
				$buf.='<tr>';
				$buf.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
				$buf.='<td>'.$m['amount'].' '.$currencies_arr2[$m['currency']].'</td>';
				$buf.='<td>'.round($m['amount']*100/$sum_amount,2).'%</td>';
				$buf.='<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
				$buf.='</tr>';
			}
			/*
			$sum_amount=$db->sql_row("SELECT SUM(`amount`) AS `sum_amount` FROM `transfers` WHERE `to`=88044 AND `currency`=2 AND `time`>='".$last_upvote_time."' AND `amount`>0.001 ORDER BY `id` DESC");
			$q=$db->sql("SELECT * FROM `transfers` WHERE `to`=88044 AND `currency`=2 AND `time`>='".$last_upvote_time."' AND `amount`>0.001 ORDER BY `id` DESC");
			while($m=$db->row($q)){
				$buf.='<tr>';
				$buf.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
				$buf.='<td>'.$m['amount'].' GBG</td>';
				$buf.='<td>'.round($m['amount']*100/$sum_amount['sum_amount'],2).'%</td>';
				$buf.='<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
				$buf.='</tr>';
			}*/
			$buf.='</tbody></table>';
			$cache->set('uplift_bids_table',$buf,10);
			print $buf;
		}
		print '<hr><h2>Предыдущие апы</h2>';
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `user`=88048 AND `time`>='".($last_upvote_time-600)."' ORDER BY `id` DESC");
		print '<table><thead><tr><th>Дата</th><th>Процент</th><th>Пост</th></tr></thead><tbody>';
		while($m=$db->row($q)){
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['post']."'");
			$post_author_login=get_user_login($post_arr['author']);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/'),'<a>').'</td>';
			print '</tr>';
		}
		$q=$db->sql("SELECT * FROM `comments_votes` WHERE `user`=88048 AND `time`>='".($last_upvote_time-600)."' ORDER BY `id` DESC");
		while($m=$db->row($q)){
			$post_author_login=get_user_login($post_arr['author']);
			$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['comment']."'");
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$comment_arr['post']."'");
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/#'.$comment_arr['permlink'].''),'<a>').'</td>';
			print '</tr>';
		}
		print '</tbody></table>';
		print '<hr><h2>История переводов</h2>';
		print '<table><thead><tr><th>Дата</th><th>Количество</th><th>Заметка</th></tr></thead><tbody>';
		$transfers_arr=$redis->zrevrange('transfers_way:'.$session_arr['user'].':88044',0,100);
		foreach($transfers_arr as $transfer_id){
			$m=$redis->hgetall('transfers:'.$transfer_id);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.$m['amount'].' '.$currencies_arr2[$m['currency']].'</td>';
			print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
			print '</tr>';
		}
		/*
		$q=$db->sql("SELECT * FROM `transfers` WHERE `from`='".$session_arr['user']."' AND `to`=88044 AND `currency`=2 ORDER BY `id` DESC LIMIT 100");
		while($m=$db->row($q)){
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.$m['amount'].' GBG</td>';
			print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
			print '</tr>';
		}*/
		print '</tbody></table>';
	}
}
else
if('booster'==$path_array[1]){
	$replace['title']='Booster - '.$replace['title'];
	$replace['descr']='';
	if('history'==$path_array[2]){
		$replace['title']='История - '.$replace['title'];
		print '<a href="/booster/" class="right">&larr; Вернуться назад</a>';
		print '<h1 class="page_title"><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> История за неделю &mdash; Booster</h1><hr>';
		$week_time=time()-3600*24*7;
		$bid_arr=array();
		$currency_arr=array();
		$transfers_arr=$redis->zrevrangebyscore('transfers_to:55087','+inf',$week_time);
		foreach($transfers_arr as $transfer_id){
			$m=$redis->hgetall('transfers:'.$transfer_id);
			preg_match_all('~\/@(.*)\/(.*)$~iUs',$m['memo'],$matches);
			if($matches[1][0]){
				$hash=md5($matches[1][0].trim($matches[2][0],'/'));
				$bid_arr[$hash]+=$m['amount'];
				$currency_arr[$hash]=$m['currency'];
			}
		}
		/*
		$q=$db->sql("SELECT * FROM `transfers` WHERE `to`=55087 AND `currency`=2 AND `time`>'".$week_time."'");
		while($m=$db->row($q)){
			preg_match_all('~\/@(.*)\/(.*)$~iUs',$m['memo'],$matches);
			if($matches[1][0]){
				$bid_arr[md5($matches[1][0].trim($matches[2][0],'/'))]+=$m['amount'];
			}
		}
		*/
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `user`=60288 AND `time`>='".($week_time)."' ORDER BY `id` DESC");
		print '<table><thead><tr><th>Дата</th><th>Ставка</th><th>Процент</th><th>Пост</th></tr></thead><tbody>';
		$last_time=time();
		while($m=$db->row($q)){
			if(($last_time - $m['time'])>1200){
				/*
				$q2=$db->sql("SELECT * FROM `comments_votes` WHERE `user`=60288 AND `time`>='".($last_time)."' AND `time`<='".$m['time']."' ORDER BY `id` DESC");
				while($m2=$db->row($q2)){
					$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m2['comment']."'");
					$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$comment_arr['post']."'");
					$post_author_login=get_user_login($post_arr['author']);
					print '<tr>';
					print '<td><span class="timestamp" data-timestamp="'.$m2['time'].'">'.date('d.m.Y H:i:s',$m2['time']).'</span></td>';
					print '<td>'.round($m2['weight']/100,2).'%</td>';
					print '<td>'.text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/#'.$comment_arr['permlink'].'').'</td>';
					print '</tr>';
				}*/
				print '<tr><td colspan="4"><center><b>Окно ставок</b></center></td></tr>';
			}
			$last_time=$m['time'];
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['post']."'");
			$post_author_login=get_user_login($post_arr['author']);
			$hash=md5($post_author_login.$post_arr['permlink']);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.$bid_arr[$hash].' '.$currencies_arr2[$currency_arr[$hash]].'</td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			//$bid_cost=$db->sql_row("SELECT SUM(`amount`) as `amount_sum` FROM `transfers` WHERE `to`=55087 AND `currency`=2 AND `time`>'".$week_time."' AND `time`<'".$bid_window_time."' AND `memo` LIKE '%/@".$db->prepare($post_author_login)."/".$db->prepare($post_arr['permlink'])."%'");
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/'),'<a>').'</td>';
			print '</tr>';
		}
		print '</tbody></table>';
	}
	else{
		print '<a href="/booster/history/" class="right action-button"><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> История</a>';
		print '<h1 class="page_title">Booster</h1><p>Игра &laquo;Перебей ставку&raquo; проекта <a href="/@booster/">@booster</a> (при поддержке <a href="/@coinbank/">@coinbank</a>)</p>';
		print '<hr><div class="unlock-active-key"></div>';
		print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
			print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
			print '<p class="wallet-balances">';
				print 'Токенов на кошельке: <span class="wallet-balance-set" rel="gbg" data-asset="GBG">0</span> GBG<br>';
			print '</p>';

			print '<div class="wallet-transfer">';
				print '<p><input type="text" name="recipient" value="booster" disabled="disabled"> &mdash; Получатель</p>';
				print '<p><select name="asset" disabled="disabled">';
				print '<option value="GBG">GBG</option>';
				print '</select> &mdash; Ассет</p>';
				print '<p><input type="text" name="amount"> &mdash; Количество</p>';
				print '<p><input type="text" name="memo"> &mdash; Заметка (url поста)</p>';
				print '<p><em>Внимание! Время поступления не мгновенное, обновите страницу через минуту.</em></p>';
				print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Заплатить @booster</div>';
			print '</div>';
		print '</form>';
		print '<hr><h2>История последних ставок</h2>';
		//$booster_arr=$db->sql_row("SELECT * FROM `users` WHERE `id`=60288");
		$booster_arr=$redis->hgetall('users:60288');
		print '<p>Текущая энергия для голосования аккаунта @coinbank: <span class="user_voting_power" data-login="coinbank">'.round($booster_arr['voting_power']/100,2).'%</span></p>';
		$ready_time=(10000-$booster_arr['voting_power'])*43.2;//100% recovery 120 hours = 7200 minutes = 432000 seconds
		print '<p>Энергия восстановится примерно через: <span class="user_voting_power_recovery" data-login="coinbank">'.round($ready_time/60,1).'</span> минут'.($admin?', <span class="user_voting_power_recovery_sec" data-login="coinbank">'.ceil($ready_time).'</span> секунд':'').'</p>';
		$last_upvote_post_time=$db->select_one('posts_votes','time',"WHERE `user` = '55087' ORDER BY `id` DESC")-60;
		$last_upvote_post_time=$db->select_one('posts_votes','time',"WHERE `user` = '55087' AND `time`>'".($last_upvote_post_time-120)."' ORDER BY `id` ASC");
		$last_upvote_comment_time=$db->select_one('comments_votes','time',"WHERE `user` = '55087' ORDER BY `id` DESC");
		$last_upvote_time=max($last_upvote_comment_time,$last_upvote_post_time);
		print '<p>Последний голос от @booster был: <span class="timestamp" data-timestamp="'.$last_upvote_time.'">'.date('d.m.Y H:i:s',$last_upvote_time).'</span></p>';

		$buf=$cache->get('booster_bids_table');
		if($buf){
			print $buf;
		}
		else{
			$buf='<table><thead><tr><th>Дата</th><th>Количество</th><th>Предполагаемый процент</th><th>Заметка</th></tr></thead><tbody>';
			$sum_amount=0;
			$transfers_arr=$redis->zrevrangebyscore('transfers_to:55087','+inf',$last_upvote_time);
			foreach($transfers_arr as $transfer_id){
				$amount=$redis->hget('transfers:'.$transfer_id,'amount');
				if($amount>0.001){
					$sum_amount+=$amount;
				}
			}
			foreach($transfers_arr as $transfer_id){
				$m=$redis->hgetall('transfers:'.$transfer_id);
				$buf.='<tr>';
				$buf.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
				$buf.='<td>'.$m['amount'].' '.$currencies_arr2[$m['currency']].'</td>';
				$buf.='<td>'.round($m['amount']*100/$sum_amount,2).'%</td>';
				$buf.='<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
				$buf.='</tr>';
			}
			/*
			$sum_amount=$db->sql_row("SELECT SUM(`amount`) AS `sum_amount` FROM `transfers` WHERE `to`=55087 AND `currency`=2 AND `time`>='".$last_upvote_time."' AND `amount`>0.001 ORDER BY `id` DESC");
			$sum_amount+=$db->sql_row("SELECT SUM(`amount`) AS `sum_amount` FROM `transfers` WHERE `to`=55087 AND `currency`=1 AND `time`>='".$last_upvote_time."' AND `amount`>0.001 ORDER BY `id` DESC");
			$q=$db->sql("SELECT * FROM `transfers` WHERE `to`=55087 AND (`currency`=2 OR `currency`=1) AND `time`>='".$last_upvote_time."' AND `amount`>0.001 ORDER BY `id` DESC");
			while($m=$db->row($q)){
				$buf.='<tr>';
				$buf.='<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
				$buf.='<td>'.$m['amount'].' '.(1==$m['currency']?'GOLOS':'GBG').'</td>';
				$buf.='<td>'.round($m['amount']*100/$sum_amount['sum_amount'],2).'%</td>';
				$buf.='<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
				$buf.='</tr>';
			}
			*/
			$buf.='</tbody></table>';
			$cache->set('booster_bids_table',$buf,10);
			print $buf;
		}
		print '<hr><h2>Предыдущие апы</h2>';
		$q=$db->sql("SELECT * FROM `posts_votes` WHERE `user`=60288 AND `time`>='".($last_upvote_time-600)."' ORDER BY `id` DESC");
		print '<table><thead><tr><th>Дата</th><th>Процент</th><th>Пост</th></tr></thead><tbody>';
		while($m=$db->row($q)){
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['post']."'");
			$post_author_login=get_user_login($post_arr['author']);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/'),'<a>').'</td>';
			print '</tr>';
		}
		$q=$db->sql("SELECT * FROM `comments_votes` WHERE `user`=60288 AND `time`>='".($last_upvote_time-600)."' ORDER BY `id` DESC");
		while($m=$db->row($q)){
			$post_author_login=get_user_login($post_arr['author']);
			$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['comment']."'");
			$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$comment_arr['post']."'");
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.round($m['weight']/100,2).'%</td>';
			print '<td>'.strip_tags(text_to_view('https://goldvoice.club/@'.$post_author_login.'/'.$post_arr['permlink'].'/#'.$comment_arr['permlink'].''),'<a>').'</td>';
			print '</tr>';
		}
		print '</tbody></table>';
		print '<hr><h2>История переводов</h2>';
		print '<table><thead><tr><th>Дата</th><th>Количество</th><th>Заметка</th></tr></thead><tbody>';
		$transfers_arr=$redis->zrevrange('transfers_way:'.$session_arr['user'].':55087',0,100);
		foreach($transfers_arr as $transfer_id){
			$m=$redis->hgetall('transfers:'.$transfer_id);
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.$m['amount'].' '.$currencies_arr2[$m['currency']].'</td>';
			print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
			print '</tr>';
		}
		/*
		$q=$db->sql("SELECT * FROM `transfers` WHERE `from`='".$session_arr['user']."' AND `to`=55087 AND `currency`=2 ORDER BY `id` DESC LIMIT 100");
		while($m=$db->row($q)){
			print '<tr>';
			print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
			print '<td>'.$m['amount'].' GBG</td>';
			print '<td>'.strip_tags(text_to_view($m['memo']),'<a>').'</td>';
			print '</tr>';
		}
		*/
		print '</tbody></table>';
	}
}
else
if('sign'==$path_array[1]){
	$replace['title']='Подпись - '.$replace['title'];
	$replace['descr']='';
	if('transfer'==$path_array[2]){
		$replace['title']='Перевод - '.$replace['title'];
		$to=$_GET['to'];
		$amount=urldecode($_GET['amount']);
		$amount_arr=explode(' ',$amount);
		$amount_arr[0]=str_replace(',','.',$amount_arr[0]);
		$asset_amount=round(floatval($amount_arr[0]),3);
		$asset_type=$amount_arr[1];
		$memo=$_GET['memo'];
		print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
		print '<h2 class="subpage_title">Баланс</h2>';
		print '<p class="wallet-balances">';
		print '<span rel="golos" data-asset="GOLOS">0</span> Golos<br>';
		print '<span rel="golos_power">0</span> Силы Голоса <span class="wallet-withdraw-vesting-status"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i></span><br>';
		print '<span rel="gbg" data-asset="GBG">0</span> GBG</p>';
		print '<div class="unlock-active-key"></div>';
		print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
		print '<h1 class="page_title">Подтвердите перевод</h1><hr>';
		print '<div class="wallet-transfer">';
		print '<p><input type="text" name="recipient" value="'.htmlspecialchars($to).'" disabled> &mdash; Получатель</p>';
		print '<p><select name="asset" disabled>';
		foreach($currencies_arr2 as $k=>$v){
			print '<option value="'.$v.'"'.($v==$asset_type?' selected':'').'>'.$v.'</option>';
		}
		print '</select> &mdash; Ассет</p>';
		print '<p><input type="text" name="amount" value="'.htmlspecialchars($asset_amount).'" disabled> &mdash; Количество</p>';
		print '<p><input type="text" name="memo" value="'.htmlspecialchars($memo).'" disabled> &mdash; Заметка</p>';
		print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Отправить перевод</div>';
		print '<div class="wallet-send-success"><i class="fa fa-fw fa-check" aria-hidden="true"></i> Перевод успешно совершен</div>';
		print '</div>';
		print '</form>';
	}
	if('toto-transfer'==$path_array[2]){
		$replace['title']='Перевод Тото - '.$replace['title'];
		$to=$_GET['to'];
		$amount=urldecode($_GET['amount']);
		$amount_arr=explode(' ',$amount);
		$amount_arr[0]=str_replace(',','.',$amount_arr[0]);
		$asset_amount=round(floatval($amount_arr[0]),3);
		$asset_type=$amount_arr[1];
		$memo=$_GET['memo'];
		print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
		print '<h2 class="subpage_title">Баланс</h2>';
		print '<p class="wallet-balances">';
		print '<span rel="golos" data-asset="GOLOS">0</span> Golos<br>';
		print '<div class="unlock-active-key"></div>';
		print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
		print '<h1 class="page_title">Подтвердите перевод Тото</h1><hr>';
		print '<div class="wallet-transfer">';
		print '<p><input type="text" name="recipient" value="'.htmlspecialchars($to).'" disabled> &mdash; Получатель</p>';
		print '<p style="display:none;"><select name="asset" disabled="disabled">';
		print '<option value="GOLOS">GOLOS</option>';
		print '</select> &mdash; Ассет</p>';
		print '<p><input type="text" name="amount" value="'.htmlspecialchars($asset_amount).'" disabled> &mdash; Количество GOLOS</p>';
		print '<p><input type="text" name="memo" value="'.htmlspecialchars($memo).'" disabled> &mdash; Заметка</p>';
		print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Отправить перевод</div>';
		print '<div class="wallet-send-success"><i class="fa fa-fw fa-check" aria-hidden="true"></i> Перевод успешно совершен</div>';
		print '</div>';
		print '</form>';
	}
}
else
if('export'==$path_array[1]){
	if('transfers'==$path_array[2]){
		$user_login=$_GET['account'];
		$user_id=get_user_id($user_login);
		if($user_id){
			$currency=(int)$_GET['currency'];
			$buf=$cache->get('export:transfers:'.$user_id.($currency?':'.$currency:''));
			if(!$buf){
				$transfers_arr=$redis->zrangebyscore('transfers_to:'.$user_id,time()-7776000,'+inf');
				//rsort($transfers_arr);
				//$transfers_arr=array_reverse($transfers_arr,true);
				foreach($transfers_arr as $transfer_id){
					$ignore=false;
					$m=$redis->hgetall('transfers:'.$transfer_id);
					if($currency){
						if($m['currency']!=$currency){
							$ignore=true;
						}
					}
					if(!$ignore){
						//print_r($m);
						$result_arr=array('from'=>get_user_login($m['from']),'unixtime'=>$m['time'],'datetime'=>date('d.m.Y H:i:s',$m['time']),'amount'=>(float)$m['amount'],'currency'=>$currencies_arr2[$m['currency']],'memo'=>$m['memo']);
						$global_arr[]=$result_arr;
						unset($result_arr);
						unset($m);
					}
				}
				$buf=json_encode($global_arr,JSON_UNESCAPED_UNICODE);
				$cache->set('export:transfers:'.$user_id.($currency?':'.$currency:''),$buf,300);
			}
			print $buf;
		}
	}
	exit;
}
else
if('wallet'==$path_array[1]){
	$replace['title']='Кошелек - '.$replace['title'];
	$replace['descr']='';
	print '<h1 class="page_title">Кошелек</h1>';
	print '<div class="unlock-active-key"></div>';
	print '<form action="" method="POST" class="wallet_action" onsubmit="return false;" autocomplete="off">';
	print '<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>';
	print '<div class="wallet-withdraw-vesting action-button right"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i> Начать понижение Силы Голоса</div>';
	print '<div class="wallet-stop-withdraw-vesting action-button right"><i class="fa fa-fw fa-times" aria-hidden="true"></i> Остановить понижение Силы Голоса</div>';
	print '<h2>Баланс</h2>';
	print '<p class="wallet-balances">';
	print '<span class="wallet-balance-set" rel="golos" data-asset="GOLOS">0</span> Golos<br>';
	print '<span class="wallet-balance-set" rel="golos_power">0</span> Силы Голоса <span class="wallet-withdraw-vesting-status"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i></span><br>';
	print '<span class="wallet-balance-set" rel="gbg" data-asset="GBG">0</span> GBG<br>';
	print '<div class="wallet-savings-balances"><i class="fa fa-fw fa-lock" aria-hidden="true"></i> Сейф: <span class="wallet-savings-balance" rel="golos">0</span> Golos <span class="wallet-savings-withdraw" rel="golos" data-asset="GOLOS"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i></span>, <span class="wallet-savings-balance" rel="gbg">0</span> GBG <span class="wallet-savings-withdraw" rel="gbg" data-asset="GBG"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i></span>';
	print '<div class="wallet-savings-withdraw-form"><em>Внимание! Операции вывода токенов из Сейфа занимает 3 дня.</em><br>Вывести из Сейфа: <input type="text" name="amount" placeholder="Количество"> <select name="asset">';
	foreach($currencies_arr2 as $k=>$v){
		print '<option value="'.$v.'">'.$v.'</option>';
	}
	print '</select> <div class="wallet-savings-withdraw-action action-button"><i class="fa fa-fw fa-level-up" aria-hidden="true"></i> Запустить процесс вывода</div></div>';
	print '</div>';
	print '<div class="wallet-savings-cancel"><p>Для отмены вывода токенов из Сейфа, введите код запроса:</p><input type="text" name="request_code" placeholder="Код запроса"> <div class="wallet-savings-cancel-action action-button"><i class="fa fa-fw fa-times" aria-hidden="true"></i> Отменить</div></div>';
	print '</p>';

	print '<hr><h2>Выполнить перевод</h2>';
	print '<div class="wallet-transfer">';
	print '<p><input type="text" name="recipient"> &mdash; Получатель</p>';
	print '<p><select name="asset">';
	foreach($currencies_arr2 as $k=>$v){
		print '<option value="'.$v.'">'.$v.'</option>';
	}
	print '</select> &mdash; Ассет</p>';
	print '<p class="wallet-vesting"><label><input type="checkbox" name="vesting"> &mdash; Перевод в Силу Голоса</label></p>';
	print '<p class="wallet-savings"><label><input type="checkbox" name="savings"> &mdash; Перевод в Сейф</label></p>';
	print '<p><input type="text" name="amount"> &mdash; Количество</p>';
	print '<p><input type="text" name="memo"> &mdash; Заметка</p>';
	print '<div class="wallet-send-action"><i class="fa fa-fw fa-credit-card" aria-hidden="true"></i> Отправить перевод</div>';
	print '</div>';
	print '</form>';
	print '<hr><h2>История платежей</h2>';
	print '<input class="bubble small-size right" type="text" name="wallet-history-filter-amount2" placeholder="До&hellip;" tabindex="3">';
	print '<input class="bubble small-size right" type="text" name="wallet-history-filter-amount1" placeholder="От&hellip;" tabindex="2">';
	print '<input class="bubble right" type="text" name="wallet-history-filter" placeholder="Фильтр&hellip;" tabindex="1">';
	print '<div class="action-button wallet-history-filter-all"><i class="fa fa-fw fa-globe" aria-hidden="true"></i> Все</div>';
	print '<div class="action-button wallet-history-filter-in"><i class="fa fa-fw fa-arrow-circle-down" aria-hidden="true"></i> Входящие</div>';
	print '<div class="action-button wallet-history-filter-out"><i class="fa fa-fw fa-arrow-circle-up" aria-hidden="true"></i> Исходящие</div>';
	print '<div class="wallet-history"><table><thead><tr><th>Дата</th><th>Отправитель</th><th>Получатель</th><th>Количество</th><th>Ассет</th><th>Заметка</th></tr></thead><tbody><tr><td colspan="6"><center>Вам необходимо разблокировать кошелек своим приватным активным ключом</center></td></tr></tbody></table></div>';
}
else
if('witnesses'==$path_array[1]){
	$replace['title']='Делегаты Голоса - '.$replace['title'];
	$replace['description']='';
	/*
	if('polls'==$path_array[2]){
		$sum_votes=$db->select_one('witnesses','SUM(`votes`)',"WHERE `time`>0");
		$replace['title']='Система голосования - '.$replace['title'];
		if($path_array[3]){
			$url=$db->prepare($path_array[3]);
			$m=$db->sql_row("SELECT * FROM `witnesses_polls` WHERE `url`='".$url."'");
			if($m['id']){
				$ended=false;
				if($m['end_block']>0){
					$ended=true;
				}
				$replace['title']=htmlspecialchars($m['name']).' - '.$replace['title'];
				print '<a href="/witnesses/polls/" class="right">Система голосования</a>';
				print '<h1>'.($ended?'[ЗАВЕРШЕН] ':'').''.htmlspecialchars($m['name']).'</h1>';
				print '<div class="witness-poll-card">';
				print '<div class="witness-poll-text">'.text_to_view($m['descr']).'</div>';
				$buf_login=get_user_login($m['user']);
				print '<div class="witness-poll-addon">Инициатор опроса: <a href="/@'.htmlspecialchars($buf_login).'/">'.htmlspecialchars($buf_login).'</a></div>';
				print '<div class="witness-poll-addon">Начало опроса с блока: #'.$m['start_block'].'</div>';
				print '<div class="witness-poll-addon">Начало голосования: <span class="timestamp" data-timestamp="'.$m['start_time'].'">'.date('d.m.Y H:i:s',$m['start_time']).'</div>';
				print '<div class="witness-poll-addon">Конец голосования: <span class="timestamp" data-timestamp="'.$m['end_time'].'">'.date('d.m.Y H:i:s',$m['end_time']).'</div>';
				print '</div>';
				$options_arr=explode('|',$m['options']);
				$options_str_arr=array();
				$witness_auth=false;
				if(!$ended){
					if($auth){
						if(0!=$db->table_count('witnesses',"WHERE `user`='".$session_arr['user']."'")){
							$witness_auth=true;
						}
						if($witness_auth){
							print '<h2>Проголосовать</h2>';
							print '<div class="witness-poll-vote-card">';
							$num=1;
							foreach($options_arr as $k=>$v){
								$options_str_arr[$num]=$v;
								print '<div class="witness-poll-vote-select"><label>#'.$num.' <input type="radio" name="witness-poll-vote-option" value="'.$num.'"> &mdash; '.htmlspecialchars($v).'</label></div>';
								$num++;
							}
							print '<input type="button" class="vote_witnesses_poll" value="Проголосовать">';
							print '</div>';
						}
					}
				}
				else{
					$num=1;
					foreach($options_arr as $k=>$v){
						$options_str_arr[$num]=$v;
						$num++;
					}
				}
				print '<h2>Статистика по голосованию</h2>';
				print '<p>Суммарный вес голосов Делегатов: '.$sum_votes.' (100%)</p>';
				$options_vote_arr=array();
				$options_count_arr=array();
				$votes_str='';
				$current_votes=0;
				$q=$db->sql("SELECT * FROM `witnesses_votes` WHERE `poll`='".$m['id']."'");
				while($vote=$db->row($q)){
					$options_count_arr[$vote['option']]++;
					if($ended){
						$options_vote_arr[$vote['option']]+=$vote['end_votes'];
						$current_votes+=$vote['end_votes'];
						$votes_str.='<p>Блок #'.$vote['block'].', Делегат <a href="/@'.htmlspecialchars(get_user_login($vote['user'])).'/">@'.htmlspecialchars(get_user_login($vote['user'])).'</a>, вес на момент окончания голосования: '.$vote['end_votes'].' ('.round(($vote['end_votes']*100/$sum_votes),2).'% от всех делегатов) , голос за вариант #'.$vote['option'].';</p>';
					}
					else{
						$options_vote_arr[$vote['option']]+=$vote['start_votes'];
						$current_votes+=$vote['start_votes'];
						$votes_str.='<p>Блок #'.$vote['block'].', Делегат <a href="/@'.htmlspecialchars(get_user_login($vote['user'])).'/">@'.htmlspecialchars(get_user_login($vote['user'])).'</a>, вес на момент голосования: '.$vote['start_votes'].' ('.round(($vote['start_votes']*100/$sum_votes),2).'% от всех делегатов) , голос за вариант #'.$vote['option'].';</p>';
					}
				}
				foreach($options_str_arr as $k=>$v){
					print '<p>#'.$k.': '.htmlspecialchars($v).' &mdash; голосов '.$options_vote_arr[$k].' ('.round($options_vote_arr[$k]*100/$sum_votes,2).'% от всех делегатов), количество проголосовавших делегатов: '.$options_count_arr[$k].'</p>';
				}
				$inactive_votes=$sum_votes-$current_votes;
				$inactive_percent=round($inactive_votes*100/$sum_votes,2);
				print '<p>Воздержались: '.$inactive_votes.' ('.$inactive_percent.'% от всех делегатов)</p>';
				if($ended){
					print '<hr><h3>Результат голосования</h3>';
					if($inactive_percent>90){
						print '<p>Вес голосов менее 10% от суммарного веса всех делегатов. Это значит, что опрос завершился без какого либо решения.</p>';
					}
					else{
						arsort($options_vote_arr);
						$win_k=0;
						foreach($options_vote_arr as $k=>$v){
							$win_k=$k;
							break;
						}
						print 'В результате голосования наибольший вес набрал вариант: #'.$win_k.' &mdash; <b>'.$options_str_arr[$win_k].'</b>.';
					}
				}
				print '<hr><h3>Голоса делегатов</h3>'.$votes_str;
			}
		}
		else{
			$replace['description']='Список опросов для делегатов';
			print '<a href="/witnesses/" class="right">Делегаты Голоса</a>';
			print '<h1 class="page_title">Система голосования</h1>';
			print '<p>Суммарный вес голосов Делегатов: '.$sum_votes.'</p>';
			print '<hr>';
			print '<h2>Список опросов</h2>';
			$q=$db->sql("SELECT * FROM `witnesses_polls` ORDER BY `end_time` ASC");
			while($m=$db->row($q)){
				print '<div class="witness-poll-card">';
				print '<a class="witness-poll-name" href="/witnesses/polls/'.$m['url'].'/">#'.$m['id'].' '.$m['name'].'</a>';
				print '<div class="witness-poll-text">'.text_to_view($m['descr']).'</div>';
				$buf_login=get_user_login($m['user']);
				print '<div class="witness-poll-addon">Инициатор опроса: <a href="/@'.htmlspecialchars($buf_login).'/">'.htmlspecialchars($buf_login).'</a></div>';
				print '<div class="witness-poll-addon">Начало голосования с блока: #'.$m['start_block'].'</div>';
				print '<div class="witness-poll-addon">Конец голосования: <span class="timestamp" data-timestamp="'.$m['end_time'].'">'.date('d.m.Y H:i:s',$m['end_time']).'</div>';
				print '</div>';
			}
			print '<hr>';
			print '<h2>Создание нового опроса</h2>';
			print '<form action="" method="POST" class="witnesses_polls" onsubmit="return false;">';
			print '<input type="text" name="url" placeholder="URL">';
			print '<input type="text" name="name" placeholder="Заголовок">';
			print '<textarea name="descr" placeholder="Описание опроса"></textarea>';
			print '<textarea name="options" placeholder="Список вариантов ответов в опросе разделенные символом |"></textarea>';
			print '<input type="text" name="days" placeholder="Длительность в днях (по-умолчанию и не менее 14 дней)">';
			print '<input type="button" class="create_witnesses_poll" value="Отправить опрос в блокчейн">';
			print '</form>';
		}
	}
	*/
	if(''==$path_array[2]){
		$replace['title']='Делегаты Голоса - '.$replace['title'];
		$replace['description']='Список делегатов Голоса (с указанием активности за неделю)';
		$sum_votes=$db->select_one('witnesses','SUM(`votes`)',"WHERE `time`>0");
		//print '<a href="/witnesses/polls/" class="right">Система голосования</a>';
		print '<h1 class="page_title">Делегаты Голоса</h1>';
		print '<hr>';
		print '<div class="unlock-active-key"></div>';
		print '<p>Доля рассчитывается от суммарного веса голосов за Делегатов: '.$sum_votes.'</p>';
		print '<p><em>Внимание! Вы можете проголосовать максимум за 30 делегатов.<br>Неактивными делегатами считаются те, кто не подписывает блоки или не публикут прайс-фид более 30 дней.</em></p>';
		print '<div class="unvote-bad-witnesses">Снять голоса с неактивных Делегатов</div>';
		$last_block_id=$db->select_one('blocks','id',"ORDER BY `id` DESC");
		$block_range=$last_block_id-28800;
		$q=$db->sql("SELECT * FROM `witnesses` WHERE `votes`!=0 ORDER BY `votes` DESC");
		$num=1;
		$check_back_30_days=time()-2592000;
		print '<table><thead><tr><th width="7.5%">Место</th><th width="7.5%">Доля</th><th width="10%">Голосовать</th><th>Аккаунт</th><th width="15%">Блоков за месяц</th><th width="20%">Примерное вознаграждение за сутки</th></tr></thead><tbody>';
		while($m=$db->row($q)){
			$witness_login=get_user_login($m['user']);
			$blocks_period=$db->table_count('blocks',"WHERE `id`>'".$block_range."' AND `witness_user`='".$db->prepare($m['user'])."'");
			$sg_payout=$blocks_period;
			if($num>19){
				$sg_payout=$sg_payout*0.590;
			}
			else{
				$sg_payout=$sg_payout*0.118;
			}
			$witness_url='/@'.htmlspecialchars($witness_login).'/';
			if($m['url']){
				$witness_url=htmlspecialchars($m['url']);
				$witness_url=str_replace('https://golos.io/','https://goldvoice.club/',$witness_url);
			}
			print '<tr class="witness-item-tr'.($blocks_period==0?' witness-inactive':'').'">';
			print '<td>'.$num.'</td>';
			print '<td>'.round($m['votes']*100/$sum_votes,3).'%</td>';
			print '<td class="center"><div class="witness-action inactive" data-witness-login="'.htmlspecialchars($witness_login).'" data-blocks-period="'.$blocks_period.'"><i class="fa fa-fw fa-chevron-up" aria-hidden="true"></i></div></td>';
			print '<td><a href="'.$witness_url.'" target="_blank">@'.htmlspecialchars($witness_login).'</a></td>';

			print '<td>'.$blocks_period.'</td>';
			print '<td>~'.$sg_payout.' СГ</td>';
			print '</tr>';
			$num++;
		}
		print '</tbody></table>';
		print '<hr>';
		print '<h1 class="page_title">Майнеры</h1>';
		print '<p>Выводятся только ТОП-10 активных майнеров (за 30 дней).</p>';
		$q=$db->sql("SELECT * FROM `witnesses` WHERE `time`=0 ORDER BY `votes` DESC");
		$miners_arr=[];
		while($m=$db->row($q)){
			$witness_login=get_user_login($m['user']);
			$blocks_week=$db->table_count('blocks',"WHERE `id`>'".$block_range."' AND `witness_user`='".$db->prepare($m['user'])."'");
			if($blocks_week){
				$miners_arr[$m['user']]=$blocks_week;
			}
		}
		arsort($miners_arr);
		$num=1;
		foreach($miners_arr as $miner_id=>$blocks_week){
			$witness_login=get_user_login($miner_id);
			print '<div class="witness-item'.($blocks_week==0?' witness-inactive':'').'">';
			print '#'.$num.' ';
			//print '<div class="witness-addon">Вознаграждение за неделю: ~'.($blocks_week*0.1).' СГ</div>';
			print '<div class="witness-addon last">Подписано блоков за неделю: '.$blocks_week.'</div>';
			//print '<div class="witness-addon last">Подписано блоков за неделю: '.$blocks_week.'</div>';
			$witness_url='/@'.htmlspecialchars($witness_login).'/';
			print '<a href="'.$witness_url.'" target="_blank">@'.htmlspecialchars($witness_login).'</a>';
			print '</div>';
			$num++;
			if($num>10){
				break;
			}
		}
		/*
		print '<p>На данной странице выводятся активные делегаты за последние 7 дней.</p>';
		$last_block_id=$db->select_one('blocks','id',"ORDER BY `id` DESC");
		$block_range=$last_block_id-201600;
		$q=$db->sql("SELECT DISTINCT(`witness`) as `witness` FROM `blocks` WHERE `id`>'".$block_range."'");
		$whitness_active_arr=array();
		while($m=$db->row($q)){
			$whitness_active_arr[$m['witness']]=$db->table_count('blocks',"WHERE `id`>'".$block_range."' AND `witness`='".$db->prepare($m['witness'])."'");
		}
		arsort($whitness_active_arr);
		foreach($whitness_active_arr as $witness => $blocks){
			print '<div class="witness-item">';
			print '<div class="witness-addon">Подписано блоков: '.$blocks.'</div>';
			print '<div class="witness-addon last">Подписано блоков: '.$blocks.'</div>';
			print '<div class="witness-action" data-witness-login="'.htmlspecialchars($witness).'"><i class="fa fa-fw fa-chevron-up" aria-hidden="true"></i></div> <a href="/@'.htmlspecialchars($witness).'/" target="_blank">@'.htmlspecialchars($witness).'</a>';
			print '</div>';
		}
		//print_r($db->history());
		*/
	}
}
else
if('checkpoints'==$path_array[1]){
	$replace['title']='Golos Node Checkpoints - '.$replace['title'];
	print '<h1 class="page_title">Checkpoints для Голоса</h1>';
	print '<hr>';
	print '<p>На данной странице выполняются запросы к медиа-блокчейну Голос и выводятся чекпойнты, которые можно вставить в конфигурационный файл ноды (config.ini). Это <a href="https://github.com/GolosChain/golos/blob/v0.16.4/libraries/chain/database.cpp#L2983">позволяет исключить различные проверки</a> при повторном запуске ноды с параметром replay.</p><hr>';
	print '<div class="checkpoint-result"></div>';
print "<script>
function wait_ms(ms){
	var start = new Date().getTime();
	var end = start;
	while(end < start + ms) {
		end = new Date().getTime();
	}
}
function check_checkpoint(current_checkpoint){
	gate.api.getBlockHeader((1+current_checkpoint), function(err, result) {
		if(result.previous){
			$('.checkpoint-result').html($('.checkpoint-result').html()+'<p>checkpoint = ['+current_checkpoint+', \"'+result.previous+'\"]</p>');
		}
	});
}
function recalc_checkpoints(head_num){
	var head_num_part=parseInt(parseFloat(head_num)/250000)*250000;
	var i=250000;
	while(i<=head_num_part){
		check_checkpoint(i);
		while(window.checkpoint_wait){
			wait_ms(100);
		}
		i=i+250000;
	}
	if(head_num-3>head_num_part){
		check_checkpoint(head_num-3);
		check_checkpoint(head_num-2);
		check_checkpoint(head_num-1);
	}
}
$().ready(function(){
	gate.api.getDynamicGlobalProperties(function(err, result) {
		recalc_checkpoints(result.head_block_number);
	});
});
</script>";
}
else
if(''==$path_array[1]){
	$replace['head_addon'].=PHP_EOL.'<link rel="canonical" href="https://goldvoice.club/" />';
	$replace['head_addon'].=PHP_EOL.'<link rel="alternate" type="application/rss+xml" title="Global RSS GoldVoice.club" href="https://goldvoice.club/rss.xml" />';
	$online_users_count=$redis->zcount('users_action_time',(time()-300),'+inf');
	print '<h2 class="subpage_title">'.$l10n['home']['users_online'].': '.$online_users_count.'</h2>';
	print '<div class="online-users">';
	$online_users_limit=16;
	if($auth){
		$online_users_limit=8;
	}
	$online_users_arr=$redis->zrevrangebyscore('users_action_time','+inf',(time()-300),array('limit'=>array('0',$online_users_limit)));
	foreach($online_users_arr as $user_login){
		$m=$redis->hgetall('users:'.$user_login);
		if(''==$m['avatar']){
			$m['avatar']='https://goldvoice.club/images/noava50.png';
		}
		if(''==$m['name']){
			$m['name']=$m['login'];
		}
		if(false!==mb_strpos($m['name'],' ')){
			$m['name']=mb_substr($m['name'],0,mb_strpos($m['name'],' '));
		}
		print '<div class="user-list-item"><a class="user-avatar" href="/@'.htmlspecialchars($m['login']).'/"><img src="https://i.goldvoice.club/50x50a/'.htmlspecialchars($m['avatar']).'" alt=""></a><a class="user-name" href="/@'.htmlspecialchars($m['login']).'/">'.htmlspecialchars($m['name']).'</a></div>';
	}
	print '</div></div>';
	print '<div class="page">';
	print '<h2 class="subpage_title">'.$l10n['home']['popular_tags'].'</h2>';

	print '<div class="post-tags center">';
	print '<a class="tag" href="/tags/ru--privetstvie/">приветствие</a>';
	print '<a class="tag" href="/tags/ru--znakomstvo/">знакомство</a>';
	print '<a class="tag" href="/tags/ru--puteshestviya/">путешествия</a>';
	print '<a class="tag" href="/tags/ru--tvorchestvo/">творчество</a>';
	print '<a class="tag" href="/tags/ru--muzyka/">музыка</a>';
	print '<a class="tag" href="/tags/ru--stikhi/">стихи</a>';
	print '<a class="tag" href="/tags/ru--rasskaz/">рассказ</a>';
	print '<a class="tag" href="/tags/ru--iskusstvo/">искусство</a>';
	print '<a class="tag" href="/tags/ru--fotografiya/">фотография</a>';

	print '<a class="tag" href="/tags/ru--obrazovanie/">образование</a>';
	print '<a class="tag" href="/tags/ru--psikhologiya/">психология</a>';
	print '<a class="tag" href="/tags/ru--yekonomika/">экономика</a>';
	print '<a class="tag" href="/tags/ru--filosofiya/">философия</a>';
	print '<a class="tag" href="/tags/ru--zdorovxe/">здоровье</a>';
	print '<a class="tag" href="/tags/ru--zhiznx/">жизнь</a>';
	print '<a class="tag" href="/tags/ru--istoriya/">история</a>';
	print '<a class="tag" href="/tags/ru--otzyv/">отзыв</a>';

	print '<a class="tag" href="/tags/ru--programmirovanie/">программирование</a>';
	print '<a class="tag" href="/tags/ru--konkurs/">конкурс</a>';
	print '<a class="tag" href="/tags/ru--blokcheijn/">блокчейн</a>';
	print '<a class="tag" href="/tags/ru--kriptovalyuta/">криптовалюта</a>';
	print '<a class="tag" href="/tags/ru--statistika/">статистика</a>';
	print '<a class="category" href="/tags/">все тэги</a>';
	print '<a class="category" href="/categories/mapala/">mapala</a>';
	print '</div>';

	print '</div>';
	print '<div class="action-button posts-list-filter-button right"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
	print '<h1 class="new-posts-title">'.$l10n['home']['new_posts'].'</h1>';
	print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
	/* + new posts list */
	print '<div class="posts-list">';
	$buf=$cache->get('index_feed');
	if($buf){
		print $buf;
	}
	else{
		$buf='';
		$perpage=50;
		$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`status`=0 AND `parent_post`=0 ORDER BY `posts`.`id` DESC LIMIT ".$perpage." OFFSET 0";
		//, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar`
		//RIGHT JOIN `users` ON `posts`.`author`=`users`.`id`
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			$m['author_login']=$redis->get('user_login:'.$m['author']);
			$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
			$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
			$buf.=preview_post($m);
		}
		$cache->set('index_feed',$buf,50);
		print $buf;
	}

	print '<div class="load-more-indicator" data-action="new-posts"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
	print '</div>';
	/* - new posts list */
}
elseif('tags'==$path_array[1]){
	if(''==$path_array[2]){
		$replace['title']=$l10n['tags']['page_title'].' - '.$replace['title'];
		print '<h1 class="page_title">'.$l10n['tags']['page_title'].'</h1>';
		$q=$db->sql('SELECT * FROM `tags` WHERE `status`=0 ORDER BY `posts` DESC LIMIT 500');
		print '<table><thead><tr><th>'.$l10n['tables_head']['name'].'</th><th>'.$l10n['tables_head']['posts'].'</th></tr></thead><tbody>';
		while($m=$db->row($q)){
			if($m['en']){
				print PHP_EOL.'<tr>';
				print '<td><a href="/tags/'.htmlspecialchars($m['en']).'/">'.htmlspecialchars($m['ru']?$m['ru']:$m['en']).'</a></td>';
				print '<td>'.$m['posts'].'</td>';
				print '</tr>';
			}
		}
		print '</tbody></table>';
	}
	else{
		$tag=$db->prepare(urldecode($path_array[2]));
		$tag_arr=$db->sql_row("SELECT * FROM `tags` WHERE `en`='".$tag."'");
		if($tag_arr['id']){
			$replace['title']=htmlspecialchars($tag_arr['ru']?$tag_arr['ru']:$tag_arr['en']).' - '.$replace['title'];
			$replace['head_addon'].=PHP_EOL.'<link rel="alternate" type="application/rss+xml" title="Tag: #'.htmlspecialchars($tag_arr['ru']?$tag_arr['ru']:$tag_arr['en']).' - RSS GoldVoice.club" href="https://goldvoice.club/rss.xml?tag='.htmlspecialchars($tag_arr['en']).'" />';
			print '<h1 class="page_title"><a href="/tags/" title="'.$l10n['tags']['back_link'].'">&larr;</a> # '.htmlspecialchars($tag_arr['ru']?$tag_arr['ru']:$tag_arr['en']).'</h1>';
			print '</div>';
			/* + tag posts list */
			print '<div class="posts-list">';
			$perpage=50;
			$sql="SELECT `post` FROM `posts_tags` WHERE `tag`='".$tag_arr['id']."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post((int)$m['post'],0,0);
			}
			print '<div class="load-more-indicator" data-action="tag-posts" data-tag="'.htmlspecialchars($tag_arr['en']).'"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
			print '</div>';
			/* - tag posts list */
		}
	}
}
elseif('notifications'==$path_array[1]){
	if(!$auth){
		header('HTTP/1.0 403 Forbidden');
				print '<h1>'.$l10n['403']['page_title'].'</h1>';
				print '<p>'.$l10n['403']['status_2'].'</p>';
	}
	else{
		if(''==$path_array[2]){
			$unread_count=$db->table_count('notifications',"WHERE `user`='".$session_arr['user']."' AND `type` NOT IN (4,5,10,11) AND `status`=0");
			$title_addon='';
			$type='`type`!=0';
			if('new'==$_GET['type']){
				$type='`status`=0';
				$title_addon=$l10n['notifications']['tabs_new'];
			}
			if('replies'==$_GET['type']){
				$type='`type` IN (4,5,10,11)';
				$title_addon=$l10n['notifications']['tabs_replies'];
			}
			if('repost'==$_GET['type']){
				$type='`type`=13';
				$title_addon=$l10n['notifications']['tabs_repost'];
			}
			if('wallet'==$_GET['type']){
				$type='`type`=12';
				$title_addon=$l10n['notifications']['tabs_wallet'];
			}
			if('social'==$_GET['type']){
				$type='`type` IN (1,2,3,14,15)';
				$title_addon=$l10n['notifications']['tabs_social'];
			}
			if('post'==$_GET['type']){
				$type='`type` IN (6,8)';
				$title_addon=$l10n['notifications']['tabs_post'];
			}
			if('comment'==$_GET['type']){
				$type='`type` IN (7,9)';
				$title_addon=$l10n['notifications']['tabs_comment'];
			}
			$replace['title']=$l10n['notifications']['page_title'].' - '.$replace['title'];
			if($title_addon){
				$replace['title']=$title_addon.' - '.$replace['title'];
			}
			print '<div class="action-button right clear-notifications"><i class="fa fa-fw fa-bell-slash-o" aria-hidden="true"></i> '.$l10n['notifications']['mark_as_read'].'</div>';
			print '<h1 class="page_title">'.$l10n['notifications']['page_title'].($title_addon?' &mdash; '.$title_addon:'').'</h1>';
			print '<div class="tabs-list">
			<a class="tabs-item'.(''==$_GET['type']?' selected':'').'" href="/notifications/">'.$l10n['notifications']['tabs_all'].'</a>
			<a class="tabs-item'.('new'==$_GET['type']?' selected':'').'" href="/notifications/?type=new">'.$l10n['notifications']['tabs_new'].'</a>
			<a class="tabs-item'.('replies'==$_GET['type']?' selected':'').'" href="/notifications/?type=replies">'.$l10n['notifications']['tabs_replies'].'</a>
			<a class="tabs-item'.('social'==$_GET['type']?' selected':'').'" href="/notifications/?type=social">'.$l10n['notifications']['tabs_social'].'</a>
			<a class="tabs-item'.('wallet'==$_GET['type']?' selected':'').'" href="/notifications/?type=wallet">'.$l10n['notifications']['tabs_wallet'].'</a>
			<a class="tabs-item'.('repost'==$_GET['type']?' selected':'').'" href="/notifications/?type=repost">'.$l10n['notifications']['tabs_repost'].'</a>
			<a class="tabs-item'.('post'==$_GET['type']?' selected':'').'" href="/notifications/?type=post">'.$l10n['notifications']['tabs_post'].'</a>
			<a class="tabs-item'.('comment'==$_GET['type']?' selected':'').'" href="/notifications/?type=comment">'.$l10n['notifications']['tabs_comment'].'</a>
			</div>';
			$num=0;
			$q=$db->sql("SELECT * FROM `notifications` WHERE `user`='".$session_arr['user']."' AND ".$type." ORDER BY `id` DESC LIMIT 500");
			while($m=$db->row($q)){
				$num++;
				$notify_html='';
				$print_datestamp=true;
				if(1==$m['type']){
					$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
					$notify_html.='<a href="'.$user_link.'" data-id="'.$m['target'].'">'.$user_login.'</a> '.$l10n['notifications']['type_1'].'</a>';
				}
				if(2==$m['type']){
					$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
					$notify_html.='<a href="'.$user_link.'" data-id="'.$m['target'].'">'.$user_login.'</a> '.$l10n['notifications']['type_2'].'</a>';
				}
				if(3==$m['type']){
					$user_login='@'.htmlspecialchars(get_user_login($m['target'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($m['target'])).'/';
					$notify_html.='<a href="'.$user_link.'" data-id="'.$m['target'].'">'.$user_login.'</a> '.$l10n['notifications']['type_3'].'</a>';
				}
				if(4==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
					//, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar`
					//RIGHT JOIN `users` ON `posts`.`author`=`users`.`id`
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);

						//$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> оставил <a href="'.$comment_link.'">комментарий</a> к посту <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
						//$notify_html.='<div class="notify-comment">'.text_to_view($notify_type_arr['body']).'</div>';
						$print_datestamp=false;
						$notify_html.=comment_to_view($notify_type_arr['id']);
					}
				}
				if(5==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$parent_comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['parent']."'");
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);
						$parent_comment_link=$post_link.'#'.htmlspecialchars($parent_comment_arr['permlink']);
						//$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> оставил <a href="'.$comment_link.'">ответ</a> на <a href="'.$parent_comment_link.'">комментарий</a>';
						//$notify_html.='<div class="notify-comment">'.text_to_view($notify_type_arr['body']).'</div>';
						$print_datestamp=false;
						$notify_html.=comment_to_view($notify_type_arr['id']);
					}
				}
				if(6==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `posts_votes` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['vote_with_power'].' '.($notify_type_arr['weight']/100).'% '.$l10n['notifications']['for_the_post'].' <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
					}
				}
				if(7==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `comments_votes` WHERE `id`='".$m['target']."'");
					$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['comment']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$comment_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$comment_link=$post_link.'#'.htmlspecialchars($comment_arr['permlink']);
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['vote_with_power'].' '.($notify_type_arr['weight']/100).'% '.$l10n['notifications']['for_the'].' <a href="'.$comment_link.'">'.$l10n['notifications']['comment'].'</a>';
					}
				}
				if(8==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `posts_votes` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['flag_with_power'].' '.($notify_type_arr['weight']/100).'% '.$l10n['notifications']['to_post'].' <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
					}
				}
				if(9==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `comments_votes` WHERE `id`='".$m['target']."'");
					$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$notify_type_arr['comment']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$comment_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['user'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$comment_link=$post_link.'#'.htmlspecialchars($comment_arr['permlink']);
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['flag_with_power'].' '.($notify_type_arr['weight']/100).'% '.$l10n['notifications']['for_the'].' <a href="'.$comment_link.'">'.$l10n['notifications']['comment'].'</a>';
					}
				}
				if(10==$m['type']){
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$m['target']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars($post_arr['author_login']).'';
						$user_link='/@'.htmlspecialchars($post_arr['author_login']).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_10'].' <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
					}
				}
				if(11==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$comment_link=$post_link.'#'.htmlspecialchars($notify_type_arr['permlink']);
						//$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> упомянул вас в <a href="'.$comment_link.'">комментарии</a>';
						//$notify_html.='<div class="notify-comment">'.text_to_view($notify_type_arr['body']).'</div>';

						$print_datestamp=false;
						$notify_html.=comment_to_view($notify_type_arr['id']);
					}
				}
				if(12==$m['type']){
					//$notify_type_arr=$db->sql_row("SELECT * FROM `transfers` WHERE `id`='".$m['target']."'");
					$notify_type_arr=$redis->hgetall('transfers:'.$m['target']);
					$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['from'])).'';
					$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['from'])).'/';
					$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_12'].' '.$notify_type_arr['amount'].' '.$currencies_arr2[$notify_type_arr['currency']].' '.$l10n['notifications']['with_memo'].' <div class="notify-comment">'.text_to_view(htmlspecialchars($notify_type_arr['memo'])).'</div>';
				}
				if(13==$m['type']){
					$notify_type_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$m['target']."'");
					$post_arr=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$notify_type_arr['parent_post']."' AND `posts`.`status`!=1");
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$user_login='@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'';
						$user_link='/@'.htmlspecialchars(get_user_login($notify_type_arr['author'])).'/';
						$post_link='/@'.htmlspecialchars($post_arr['author_login']).'/'.htmlspecialchars($post_arr['permlink']).'/';
						$notify_html.='<a href="'.$user_link.'">'.$user_login.'</a> '.$l10n['notifications']['type_13'].' <a href="'.$post_link.'">'.htmlspecialchars($post_arr['post_title']).'</a>';
					}
				}
				if(14==$m['type']){
					$user_voter=get_user_login($m['target']);
					$user_login='@'.htmlspecialchars($user_voter).'';
					$user_link='/@'.htmlspecialchars($user_voter).'/';
					$notify_html.='<a href="'.$user_link.'" data-id="'.$m['target'].'">'.$user_login.'</a> '.$l10n['notifications']['type_14'].'';
				}
				if(15==$m['type']){
					$user_voter=get_user_login($m['target']);
					$user_login='@'.htmlspecialchars($user_voter).'';
					$user_link='/@'.htmlspecialchars($user_voter).'/';
					$notify_html.='<a href="'.$user_link.'" data-id="'.$m['target'].'">'.$user_login.'</a> '.$l10n['notifications']['type_15'].'';
				}
				if($notify_html){
					print '<div class="notify'.(1==$m['status']?'':' notify-unread').'">';
					if($print_datestamp){
						print '<span class="timestamp right text-grey text-small" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span>';
					}
					print $notify_html;
					print '</div>';
				}
				/** /
				if(0==$m['status']){
					$db->sql("UPDATE `notifications` SET `status`=1 WHERE `id`='".$m['id']."'");
				}
				/**/
			}
			if(0==$num){
				print '<p>'.$l10n['notifications']['none'].'.</p>';
			}
		}
	}
}
elseif('categories'==$path_array[1]){
	if(''==$path_array[2]){
		$replace['title']=$l10n['categories']['page_title'].' - '.$replace['title'];
		print '<h1 class="page_title">'.$l10n['categories']['page_title'].'</h1>';
		$q=$db->sql('SELECT * FROM `categories` WHERE `status`=0 ORDER BY `posts` DESC LIMIT 500');
		print '<table><thead><tr><th>'.$l10n['tables_head']['name'].'</th><th>'.$l10n['tables_head']['posts'].'</th></tr></thead><tbody>';
		while($m=$db->row($q)){
			if($m['name']){
				print PHP_EOL.'<tr>';
				print '<td><a href="/categories/'.htmlspecialchars($m['name']).'/">'.htmlspecialchars($m['name']).'</a></td>';
				print '<td>'.$m['posts'].'</td>';
				print '</tr>';
			}
		}
		print '</tbody></table>';
	}
	else{
		$cat=$db->prepare($path_array[2]);
		$cat_arr=$db->sql_row("SELECT * FROM `categories` WHERE `name`='".$cat."'");
		if($cat_arr['id']){
			$replace['title']=$l10n['categories']['name'].': '.htmlspecialchars($cat_arr['name']).' - '.$replace['title'];
			print '<h1 class="page_title"><a href="/categories/" title="'.$l10n['categories']['back_link'].'">&larr;</a> '.$l10n['categories']['name'].': '.htmlspecialchars($cat_arr['name']).'</h1>';
			print '</div>';
			/* + category posts list */
			print '<div class="posts-list">';
			$perpage=50;
			$sql="SELECT `post` FROM `posts_categories` WHERE `category`='".$cat_arr['id']."' ORDER BY `id` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post((int)$m['post'],0,0);
			}
			print '<div class="load-more-indicator" data-action="category-posts" data-category="'.htmlspecialchars($cat_arr['name']).'"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
			print '</div>';
			/* - category posts list */
		}
	}
}
elseif('passwords'==$path_array[1]){
	print '<h1 class="page_title"><i class="fa fa-fw fa-key" aria-hidden="true"></i> '.$l10n['settings']['manage_passwords'].'</h1><hr>';
	print '<p>Главный пароль который вы получили при регистрации &mdash; может использоваться для восстановления доступа к аккаунту и для формирования разных ключей. На данный момент используются ключи 5 назначений.
	<ul><li>Owner ключ &mdash; для изменения пароля и изменения родительского аккаунта (ключ владельца);</li>
	<li>Posting ключ &mdash; для голосования за посты, написание постов и комментариев (авторизация на сайте происходит по данному постинг ключу);</li>
	<li>Active ключ &mdash; для управления кошельком (переводы токенов-ассетов), голосованием за делегатов (активный ключ);</li>
	<li>Memo ключ &mdash; для шифрования заметок (ключ для заметок используется крайне редко);</li>
	<li>Sign ключ &mdash; для подписи блоков (используется делегатами для подписания блоков нодой, обычно формируется случайным образом).</li></ul></p>';
	print '<p>Ваш главный ключ должен быть длинным и надежно сохранен (лучше на бумаге), так как его можно сравнить с ключами от квартиры. Он хранится только у вас, и с его помощью можно получить все остальные пароли.</p>';
	print '<p>В данном разделе вы можете ввести ваш главный пароль и получить список ключей, описанных выше (предварительно происходит проверка на совпадение с вашими публичными ключами, чтобы убедиться в валидности введенного пароля).</p>';
	print '<p>Логин аккаунта: <input type="text" name="generate_wif_user" value="'.$session_arr['public_profile']['login'].'"></p>';
	print '<p>Главный пароль: <input type="password" name="generate_wif_password"></p>';
	print '<h3>Публичные ключи</h3>';
	print '<ul class="public_wif_list">
	<li>Owner: <span class="public_wif_owner">&hellip;</span></li>
	<li>Posting: <span class="public_wif_posting">&hellip;</span></li>
	<li>Active: <span class="public_wif_active">&hellip;</span></li>
	<li>Memo: <span class="public_wif_memo">&hellip;</span></li>
	<li>Sign: <span class="public_wif_sign">&hellip;</span></li>
	</ul>';
	print '<h3>Приватные ключи</h3>';
	print '<ul class="generate_wif_list">
	<li>Owner: <span class="generate_wif_owner">&hellip;</span></li>
	<li>Posting: <span class="generate_wif_posting">&hellip;</span></li>
	<li>Active: <span class="generate_wif_active">&hellip;</span></li>
	<li>Memo: <span class="generate_wif_memo">&hellip;</span></li>
	<li>Sign: <span class="generate_wif_sign">&hellip;</span></li>
	</ul>';
	print '<hr><p><em>Внимание! Никогда не сообщайте и не передавайте свой Главный пароль или Ключ Владельца. Используйте свои ключи только на сайтах и сервисах, которые заслуживают ваше доверие.</em></p>';
}
elseif('profile'==$path_array[1]){
	if(!$auth){
		header('HTTP/1.0 403 Forbidden');
				print '<h1>'.$l10n['403']['page_title'].'</h1>';
				print '<p>'.$l10n['403']['status_2'].'</p>';
	}
	else{
		$replace['title']='Управление профилем - '.$replace['title'];
		print '<h1 class="page_title"><i class="fa fa-fw fa-user" aria-hidden="true"></i> Управление профилем</h1><hr>';
		//print '<div class="unlock-active-key"></div>';
		print '<form class="profile-update" action="" method="POST" onsubmit="return false" autocomplete="off">';
		print '<input id="upload-file" type="file">';
		print '<p>Отображаемое имя (публичное)<br>
			<input type="text" class="bubble" placeholder="Псевдоним или Имя Фамилия" name="name"></p>';
		print '<p>Пол<br>
			<select class="bubble" name="gender"><option value="">Не указан</option><option value="male">Мужской</option><option value="female">Женский</option></select></p>';

		print '<p>Местоположение<br>
			<input type="text" class="bubble" placeholder="Страна, Область, Город" name="location"></p>';
		print '<p>О блоге, авторе, проекте<br>
			<input type="text" class="bubble" placeholder="" name="about"></p>';
		print '<p>Веб-сайт (при наличии)<br>
			<input type="text" class="bubble" placeholder="" name="website"></p>';
		print '<p>Телеграм<br>
			<input type="text" class="bubble" placeholder="Логин" name="telegram"></p>';
		print '<p>Аватарка (соотношение сторон 1:1, квадратная)<br>
			<input type="text" class="bubble" placeholder="URL-изображения" name="avatar"> <span class="action-button profile-update-upload-avatar"><i class="fa fa-fw fa-upload" aria-hidden="true"></i> Загрузить</span></p>';
		print '<p>Обложка блога (рекомендуемый размер 1920x200 пикселей)<br>
			<input type="text" class="bubble" placeholder="URL-изображения" name="cover"> <span class="action-button profile-update-upload-cover"><i class="fa fa-fw fa-upload" aria-hidden="true"></i> Загрузить</span></p>';
		print '<p>Цвет ленты в блоге<br>
		<div class="profile-select-background-color" rel="383331"></div>
		<div class="profile-select-background-color" rel="46413f"></div>
		<div class="profile-select-background-color" rel="615d5c"></div>
		<div class="profile-select-background-color" rel="7d7c7b"></div>
		<div class="profile-select-background-color" rel="dc9a33"></div>
		<div class="profile-select-background-color" rel="3d48e2"></div>
		<div class="profile-select-background-color" rel="8e1a1a"></div>
		<div class="profile-select-background-color" rel="25a1de"></div>
		<div class="profile-select-background-color" rel="1bc0d2"></div>
		<div class="profile-select-background-color" rel="179e1c"></div>
		<div class="profile-select-background-color" rel="237126"></div>
		<div class="profile-select-background-color" rel="ce3898"></div>
		<div class="profile-select-background-color" rel="cb80d8"></div>
		<div class="profile-select-background-color" rel="ec7219"></div>
		<div class="profile-select-background-color" rel="a3ad22"></div>
		<div class="profile-select-background-color" rel="ff9c6c"></div>
		<div class="profile-select-background-color" rel="ff9c6c"></div>
		<br>
			<input type="text" class="bubble" placeholder="HEX" name="background_color"></p>';
		print '<hr><h3>Реклама в блоге</h3>';
		print '<p>Предпочитаемый тип рекламы<br>
			<select class="bubble" name="ad_type"><option value="">Не указан / Автоматически</option>
				<option value="1">Adsense</option>
				<option value="2">A-Ads</option>
			</select></p>';
		print '<p>a-ads.com id<br>
			<input type="text" class="bubble" placeholder="" name="ad_a_ads_id"></p>';
		print '<p>Adsense client<br>
			<input type="text" class="bubble" placeholder="" name="ad_adsense_client"></p>';
		print '<p>Adsense slot<br>
			<input type="text" class="bubble" placeholder="" name="ad_adsense_slot"></p>';
		print '<p><label><input type="checkbox" name="ad_ignore_cashout_time"> &mdash; Показывать рекламу всегда (без необходимости ждать завершения выплат за пост)</label></p>';
		print '<hr><h3>SEO</h3>';
		print '<p><label><input type="checkbox" name="seo_show_comments"> &mdash; Отображать комментарии к вашему блогу для гостей (неавторизованных юзеров)</label></p>';
		print '<p><label><input type="checkbox" name="seo_index_comments"> &mdash; Разрешить индексацию комментариев поисковыми системами</label></p>';
		print '<p><em>Внимание! Ваш профиль будет записан в блокчейн и не может быть удален оттуда.</em></p>';
		print '<div class="profile-update-action"><i class="fa fa-fw fa-id-card" aria-hidden="true"></i> Сохранить профиль</div>';
		print '</form>';
	}
}
elseif('settings'==$path_array[1]){
	if(!$auth){
		header('HTTP/1.0 403 Forbidden');
				print '<h1>'.$l10n['403']['page_title'].'</h1>';
				print '<p>'.$l10n['403']['status_2'].'</p>';
	}
	else{
		$replace['title']=$l10n['settings']['page_title'].' - '.$replace['title'];
		print '<a href="/passwords/" class="action-button right"><i class="fa fa-fw fa-key" aria-hidden="true"></i> '.$l10n['settings']['manage_passwords'].'</a>';
		print '<h1 class="page_title"><i class="fa fa-fw fa-cogs" aria-hidden="true"></i> '.$l10n['settings']['page_title'].'</h1><hr>';
		print '<p><select class="default_currency_select">';
		print '<option value="GOLOS">'.$l10n['settings']['default_currency_golos'].'</option>';
		print '<option value="GBG">'.$l10n['settings']['default_currency_gbg'].'</option>';
		print '<option value="STEEM">STEEM</option>';
		print '<option value="SBD">SBD</option>';
		print '<option value="BTC">'.$l10n['settings']['default_currency_btc'].'</option>';
		print '<option value="ETH">'.$l10n['settings']['default_currency_eth'].'</option>';
		print '<option value="USD">'.$l10n['settings']['default_currency_usd'].'</option>';
		print '<option value="RUB">'.$l10n['settings']['default_currency_rub'].'</option>';
		print '<option value="XRP">XRP</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['default_currency_select'].'</p>';

		print '<p><select class="feed_view_mode_select">';
		print '<option value="all">'.$l10n['view_mode']['all'].'</option>';
		print '<option value="only_posts">'.$l10n['view_mode']['posts'].'</option>';
		print '<option value="only_reposts">'.$l10n['view_mode']['reposts'].'</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['feed_view_mode_select'].'</p>';

		print '<p><select class="hide_flag_action">';
		print '<option value="0">'.$l10n['settings']['hide_flag_action_0'].'</option>';
		print '<option value="1">'.$l10n['settings']['hide_flag_action_1'].'</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['hide_flag_action'].'</p>';

		print '<p><select class="hide_tags_preview_action">';
		print '<option value="0">Показывать тэги</option>';
		print '<option value="1">Скрывать тэги</option>';
		print '</select>';
		print ' &mdash; Отображение тэгов при просмотре ленты постов</p>';

		print '<p><select class="blogpost_show_menu_action">';
		print '<option value="0">Переместить меню в шапку (широкий пост)</option>';
		print '<option value="1">Показывать меню (узкий пост)</option>';
		print '</select>';
		print ' &mdash; Отображение меню при просмотре постов в блогах</p>';

		print '<p><select class="adult_filter_select">';
		print '<option value="0">'.$l10n['settings']['adult_filter_select_0'].'</option>';
		print '<option value="1">'.$l10n['settings']['adult_filter_select_1'].'</option>';
		print '<option value="2">'.$l10n['settings']['adult_filter_select_2'].'</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['adult_filter_select'].'</p>';

		print '<p><select class="post_percent_select">';
		print '<option value="100">100%</option>';
		print '<option value="90">90%</option>';
		print '<option value="80">80%</option>';
		print '<option value="70">70%</option>';
		print '<option value="60">60%</option>';
		print '<option value="50">50%</option>';
		print '<option value="40">40%</option>';
		print '<option value="30">30%</option>';
		print '<option value="20">20%</option>';
		print '<option value="10">10%</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['post_percent_select'].'</p>';

		print '<p><select class="comment_percent_select">';
		print '<option value="100">100%</option>';
		print '<option value="90">90%</option>';
		print '<option value="80">80%</option>';
		print '<option value="70">70%</option>';
		print '<option value="60">60%</option>';
		print '<option value="50">50%</option>';
		print '<option value="40">40%</option>';
		print '<option value="30">30%</option>';
		print '<option value="20">20%</option>';
		print '<option value="10">10%</option>';
		print '</select>';
		print ' &mdash; '.$l10n['settings']['comment_percent_select'].'</p>';
		print '<br><h2>'.$l10n['global']['multi_accounts'].'</h2><hr>';
		print '<p>'.$l10n['settings']['multi_accounts_descr'].'</p>';
		print '<h3>'.$l10n['settings']['multi_accounts_add'].'</h3>';
		print '<form action="" method="POST" onsubmit="return false" autocomplete="off">';
		print '<p><input type="text" class="bubble" name="multi-account-login"> &mdash; '.$l10n['settings']['multi_accounts_login'].'</p>';
		print '<p><input type="password" class="bubble" name="multi-account-posting-key" autocomplete="new-password"> &mdash; '.$l10n['settings']['multi_accounts_private_posting'].'</p>';
		print '<p><input type="password" class="bubble" name="multi-account-active-key" autocomplete="new-password"> &mdash; '.$l10n['settings']['multi_accounts_private_active'].'</p>';
		print '</form>';
		print '<p><div class="add-multi-account">'.$l10n['settings']['multi_accounts_add_button'].'</div></p>';
		print '<h3>Список подключенных аккаунтов</h3>';
		print '<div class="multi-account-list"></div>';
	}
}
elseif('feed'==$path_array[1]){
	if(!$auth){
		header('HTTP/1.0 403 Forbidden');
				print '<h1>'.$l10n['403']['page_title'].'</h1>';
				print '<p>'.$l10n['403']['status_2'].'</p>';
	}
	else{
		$replace['title']=$l10n['feed']['page_title'].' - '.$replace['title'];
		print '<h1 class="page_title"><i class="fa fa-fw fa-newspaper-o" aria-hidden="true"></i> '.$l10n['feed']['page_title'].'</h1>';
		print '</div>';
		print '<a class="feed_view_mode right">'.$l10n['view_mode']['loading'].'</a>';
		print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
		print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
		/* + feed posts list */
		print '<div class="posts-list">';
		$perpage=100;
		/**/
		$feed_arr=redis_read_feed((int)$session_arr['user'],0,$perpage);
		foreach($feed_arr as $feed_id){
			print preview_post((int)$feed_id);
		}
		/** /
		$sql="SELECT `post` FROM `feed` WHERE `user`='".$session_arr['user']."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
		$q=$db->sql($sql);
		while($m=$db->row($q)){
			print preview_post((int)$m['post']);
		}
		/**/
		print '<div class="load-more-indicator" data-action="feed-posts" data-user-login="'.$session_arr['public_profile']['login'].'"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
		print '</div>';
		/* - feed posts list */
	}
}
elseif('groups'==$path_array[1]){
	$group_url=$path_array[2];
	$group_arr=$db->sql_row("SELECT * FROM `groups` WHERE `url`='".$db->prepare($group_url)."' AND `status`=0");
	if($group_arr['id']){
		$replace['title']='Группы - '.$replace['title'];
		$replace['title']=$group_arr['name'].' - '.$replace['title'];
		$replace['head_addon'].='<link rel="canonical" href="https://goldvoice.club/groups/'.$group_arr['url'].'/"/>';
		if($group_arr['logo']){
			print '<div class="group-logo"><img src="'.$group_arr['logo'].'" alt=""></div>';
		}
		print '<h1 class="page_title"><i class="fa fa-fw fa-users" aria-hidden="true"></i> '.$group_arr['name'].'</h1><hr class="noclear">';
		if($group_arr['descr']){
			print '<div class="group-descr">'.text_to_view($group_arr['descr']).'</div>';
		}
		print '<div class="clear"></div></div>';
		print '<a class="feed_view_mode right">'.$l10n['view_mode']['loading'].'</a>';
		print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
		print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
		print '<div class="posts-list-filter-preset">';
		print '<div class="tag" data-type="single" data-tag="">Все</div>';
		$q=$db->sql("SELECT * FROM `group_tags` WHERE `group`='".$group_arr['id']."' AND `status`=0 ORDER BY `sort` ASC");
		while($m=$db->row($q)){
			print '<div class="tag" data-type="'.$m['tag_type'].'" data-tag="'.$m['tag_en'].'" title="'.$m['tag_descr'].'">'.$m['tag_name'].('single'!=$m['tag_type']?' <i class="fa fa-external-link" aria-hidden="true"></i>':'').'</div>';
		}
		print '</div>';
		/* + feed posts list */
		print '<div class="posts-list">';
		$buf=$cache->get('groups_'.$group_arr['id']);
		if($buf){
			print $buf;
		}
		else{
			$buf='';
			$perpage=200;
			$sql="SELECT `post` FROM `group_feed` WHERE `group`='".$group_arr['id']."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				$buf.=preview_post((int)$m['post']);
			}
			$cache->set('groups_'.$group_arr['id'],$buf,30);
			print $buf;
		}
		print '<div class="load-more-indicator" data-action="group-feed" data-group-id="'.$group_arr['id'].'"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
		print '</div>';
		/* - feed posts list */
	}
	else{
		$replace['title']='Группы - '.$replace['title'];
		print '<h1 class="page_title"><i class="fa fa-fw fa-users" aria-hidden="true"></i> Группы</h1><hr>';
		$q=$db->sql("SELECT * FROM `groups` WHERE `status`=0 ORDER BY `name` ASC");
		while($m=$db->row($q)){
			print '<p><a href="/groups/'.$m['url'].'/">'.$m['name'].'</a></p>';
		}
	}
}
elseif('popular'==$path_array[1]){
	if('date'==$path_array[2]){
		$replace['title']='Популярное по датам - '.$replace['title'];
		if($path_array[3]){
			$date=$path_array[3];
			$date_arr=date_parse_from_format('d-m-Y',$date);
			$start_time=mktime(0,0,0,$date_arr['month'],$date_arr['day'],$date_arr['year']);
			$end_time=mktime(0,0,0,$date_arr['month'],$date_arr['day']+1,$date_arr['year'])-1;
			print '<a href="/popular/date/" class="right">&larr; Вернуться</a>';
			print '<h1 class="page_title"><i class="fa fa-fw fa-fire" aria-hidden="true"></i> Популярное по датам &mdash; '.date('d.m.Y',$start_time).'</h1>';
			$replace['title']=date('d.m.Y',$start_time).' - '.$replace['title'];

			print '</div>';
			print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
			print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
			print '<div class="posts-list">';
			$perpage=100;
			if($date==date('d-m-Y')){
				$sql="SELECT `id`,`parent_post`, CAST(`pending_payout` as double) as `payout_num` FROM `posts` WHERE `time`>='".$start_time."' AND `time`<='".$end_time."' AND `status`=0 AND `parent_post`=0 ORDER BY `payout_num` DESC LIMIT ".$perpage." OFFSET 0";
			}
			else{
				$sql="SELECT `id`,`parent_post`, CAST(`payout` as double) as `payout_num` FROM `posts` WHERE `time`>='".$start_time."' AND `time`<='".$end_time."' AND `status`=0 AND `parent_post`=0 ORDER BY `payout_num` DESC LIMIT ".$perpage." OFFSET 0";
			}
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				print preview_post((int)$m['id']);
			}
			print '</div>';
		}
		else{
			print '<a href="/popular/" class="right">&larr; Вернуться</a>';
			print '<h1 class="page_title"><i class="fa fa-fw fa-fire" aria-hidden="true"></i> Популярное по датам</h1><hr>';
			$cur_year=date('Y');
			$first_time=$db->select_one('posts','time','ORDER BY `id` ASC');
			$first_year=date('Y',$first_time);
			for($year=$cur_year;$year>=$first_year;$year--){
				print '<center><h2>'.$year.' год</h2><br>';
				$start_time=mktime(0,0,0,1,1,$year);
				if($start_time<$first_time){
					$start_time=$first_time;
				}
				$end_time=mktime(0,0,0,1,1,$year+1)-1;
				if($end_time>time()){
					$end_time=time();
				}
				$dates_arr=array();
				while($start_time<=$end_time){
					$str='<a href="/popular/date/'.date('d-m-Y',$start_time).'/" style="display:inline-block;width:18%;text-align:center;margin-bottom:16px;">'.date('d.m.Y',$start_time).'</a>';
					if('01'==date('d',$start_time)){
						$str.='<hr>';
					}
					$dates_arr[]=$str;
					$start_time+=86400;
				}
				$dates_arr=array_reverse($dates_arr);
				foreach($dates_arr as $date_str){
					print $date_str;
				}
				print '</center>';
			}
		}
	}
	else{
		$replace['title']='Популярное - '.$replace['title'];
		print '<a href="/popular/date/" class="action-button right"><i class="fa fa-fw fa-calendar" aria-hidden="true"></i> По датам</a>';
		print '<h1 class="page_title"><i class="fa fa-fw fa-fire" aria-hidden="true"></i> Популярное</h1>';
		print '</div>';
		print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
		print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
		print '<div class="posts-list">';
		$buf=$cache->get('popular');
		if($buf){
			print $buf;
		}
		else{
			$buf='';
			/* + feed posts list */
			$perpage=100;
			$sql="SELECT `id`,`parent_post`, CAST(`pending_payout` as double) as `payout_num` FROM `posts` WHERE `time`>'".(time()-86400)."' AND `status`=0 AND `parent_post`=0 ORDER BY `payout_num` DESC LIMIT ".$perpage." OFFSET 0";
			$q=$db->sql($sql);
			while($m=$db->row($q)){
				$buf.=preview_post((int)$m['id']);
			}
			$cache->set('popular',$buf,30);
			print $buf;
		}
		print '</div>';
	}
}
elseif('about'==$path_array[1]){
	$replace['page-before']='<div class="page-max-wrapper">
	<div class="page no-padding">
		<section class="half">
			<div class="page no-background no-margin">
				<center><canvas id="screen" style="display:inline-block;width:368px;height:368px;z-index:1;"></canvas></center>
				<script src="/js/pixi.min.js"></script>
				<script src="/js/logo-animation.js"></script>
			</div>
		</section>
		<section class="half purple-bg">
			<div class="page no-background no-margin white-color">
				<center style="margin-top:32px;">
					<h1>Добро пожаловать в клуб!<h1>
				</center>
				<p>Социальная сеть GoldVoice.club основана на медиа-блокчейне Голос. Цель проекта - обеспечить сообщество удобным и привычным интерфейсом с уклоном на существующие соц. сети (вдохновляемся Twitch, ЖЖ, Вконтакте).</p>
				<p>Несмотря на текущий статус <strong>αlpha</strong> — развитие возможностей социальной сети идет полным ходом. Все новости проекта публикуются на отдельном аккаунте <a href="/@goldvoice/">@goldvoice</a>.</p>
				<p>Подключайтесь — тут найдется место каждому, кто ценит интересный и качественный контент.
				<p></p>
			</div>
		</section>
	</div>
</div>';
	print '<h1>Ключевые отличия от тонких клиентов</h1>';
	print '<p>Тонкие клиенты взаимодействуют только с API на стороне браузера и не имеют свою базу данных.</p>';
	print '<p>Многие участники сообщества не раз задавались вопросами:<ul>
<li>Почему я не могу посмотреть список постов, которые я апал?</li>
<li>Где я могу отфильтровать тэги, мне надоело смотреть, что люди злоупотребляют тэгом &laquo;открытый-код&raquo;, хотя постят фоточки?!</li>
<li>Почему мне не приходят письма раз в неделю о самых интересных постах от людей на которых я подписан?</li>
<li>Я хочу получать уведомления о днях рождениях друзей! В этом же смысл социального общения!</li>
<li>Где мне искать пользователей? По интересам?</li>
<li>Почему при репосте я не могу написать заметку, чем мне пост понравился?</li>
</ul></p>';
	print '<p>Все эти проблемы связаны с тем, что клиентом к блокчейну служит сам браузер. Он не может быть всегда в сети, иметь одинаковые данные на нескольких устройствах. Именно поэтому родилась идея сделать RICH-клиент для медиа-блокчейна &laquo;Голос&raquo;. Прослойка, которая будет хранить, складировать, сортировать данные для пользователя.</p>';
	print '<h2>А это безопасно?</h2>';
	print '<p>Конечно! Все взаимодействие с блокчейном происходит так же как и в тонких клиентах. Браузер не передает никакой информации серверу, кроме случайно-сгенерированного кода для проброса авторизации в виде куки-сессии (полностью автоматизированный аналог &laquo;код через смс&raquo;).</p>';
	print '<p>Прослойка находится на сервере, который смотрит и фиксирует действия пользователя в медиа-блокчейне &laquo;Голос&raquo;. Это позволяет выйти за рамки границ блокчейна, убрать необходимость быть в сети для обработки поступающих в вашу ленту данных.</p>';
	print '<h2>Почему в виде социальной сети?</h2>';
	print '<p>Это лишь вопрос времени, когда появятся социальные сети полностью на блокчейне, но есть ли в этом необходимость? Существующие системы уже отлично подходят для этого. Существует лишь одна проблема — где хранить данные. Распределенные блокчейны для хранения данных пока не могут отвечать современным требованиям интернета. Поэтому в ближайшем будущем мы врядли увидим децентрализированные блокчейны для хранения данных (например: ipfs), которые смогут сравниться по скорости отдачи контента с существующими сетями доставки контента (CDN).</p>';
	print '<p>Медиа-блокчейн &laquo;Голос&raquo; представляет отличное место для создания совершенно новой социальной сети на новых базисах. Проект GoldVoice.club берет на себя смелость стать такой социальной сетью. Разрушить привычные границы.</p>';
	print '<h2>В чем принципиальное отличие?</h2>';
	print '<p>Большинство социальных сетей — коммерческие проекты, которые используют слепки пользователей для тарегитинговых рекламных целей. Пользователи не получают с этого ничего, кроме удобной площадки для общения и потребления контента. Владельцы популярных групп, профилей продают рекламу напрямую рекламодателям, таким образом добавляя монетизацию за рамками самой социальной сети (или их насильно заставляют это делать через внутреннюю биржу социальной сети).</p>';
	print '<p>В планах разрешить эту несправедливость (как и отсутствие выплат за контент старше 7 дней). Есть конкретные планы на этот счет, шаг за шагом проект будет развиваться.</p>';
	print '<p>Постепенно система будет пополняться игрофикацией, основанной на личном рейтинге автора и голосующих по конкретным направлениями. В планах также добавить возможность фильтровать неинтересный для вас контент основанный на бальной системе тэгов.</p>';
	print '<p>Социальный клуб GoldVoice основан на блокчейне. Любой желающий может проверить данные в самом блокчейне, удалить данные невозможно. Естественно, в рамках социальной сети обработанные данные хранятся централизовано. Это позволяет исключить риски закрытия и блокировки проекта из-за правовых норм (таких как dmca, спам, боты, жалобы на запрещенный контент). Все подобные запросы будут изучены и если запрос будет подтвержден &mdash; проект скроет данные поста/пользователя из социальной сети. Социальная сеть на блокчейне — публичный проект, который отражает все данные расположенные в блокчейне. Но без цензуры нелегального контента невозможно взаимодействие с внешним миром.</p>';
	print '<p>В тот же момент &mdash; проект не получает и не хранит никаких персональных данных. Вообще. Все взаимодействие пользователя происходит с медиа-блокчейном &laquo;Голос&raquo;.</p>';
	print '<p>GoldVoice.club &mdash; отражение блокчейна в реальном мире. Подписывайтесь на <a href="/@goldvoice/">@goldvoice</a>, чтобы быть в курсе нововведений и статистике по проекту.</p>';
}
elseif('add-post'==$path_array[1]){
	$post_arr=array();
	$replace['title']=$l10n['global']['add_post'].' - '.$replace['title'];
	print '<div class="post-energy right gray"><i class="fa fa-fw fa-bolt" aria-hidden="true"></i> Энергия нового поста: <span>&hellip;</span></div>';
	print '<h1 class="page_title">'.$l10n['global']['add_post'].'</h1><hr>
<script src="https://cloud.tinymce.com/stable/tinymce.min.js?apiKey=mesccghhml1c93rcjus20gg7r0k3unckbvvqi51illo0heti"></script>
<script>
$(document).ready(function(){
window.ondragover = function(e) {e.preventDefault(); show_modal(\'drop-images\');}
window.ondrop = function(e) {e.preventDefault(); try_upload(e.dataTransfer.files[0]);}
	setTimeout(function(){post_draft_autosave();},100);
});
';
print '
</script>
<div class="add-post">
<input type="text" name="post_title" placeholder="'.$l10n['add_post']['title'].'" value="'.htmlspecialchars($post_arr['title']).'">
<input type="text" name="post_image" placeholder="'.$l10n['add_post']['cover_image'].'" value="'.htmlspecialchars($post_arr['image']).'">
<textarea name="post_text" rows="25">'.htmlspecialchars($post_arr['body']).'</textarea>
<input id="upload-file" type="file">
<a class="link_upload_file"><i class="fa fa-fw fa-file-image-o" aria-hidden="true"></i> '.$l10n['add_post']['upload_image'].'</a>
<a class="wysiwyg_activate"><i class="fa fa-fw fa-pencil-square" aria-hidden="true"></i> '.$l10n['add_post']['wysiwyg'].'</a>
<input type="text" name="post_tags" placeholder="'.$l10n['add_post']['tags'].'" value="'.htmlspecialchars($post_tags).'">
<a class="show_post_addon"><i class="fa fa-fw fa-cogs" aria-hidden="true"></i> '.$l10n['add_post']['addons'].'</a>
<div class="post_addon">
URL:<br>
<input type="text" name="post_url" placeholder="'.$l10n['add_post']['url'].'">
<hr>
<div class="post_geo">
<a class="get_post_geo"><i class="fa fa-fw fa-map-marker" aria-hidden="true"></i> '.$l10n['add_post']['add_geo'].'</a>
<a class="clear_post_geo"><i class="fa fa-fw fa-times" aria-hidden="true"></i> '.$l10n['add_post']['remove_geo'].'</a>
<span></span>
</div>
<hr>
<label><input type="checkbox" class="post_autovote_action"> &mdash; Автоматически проголосовать за пост</label>
</div>
<input type="button" class="post-action" value="'.$l10n['add_post']['post_button'].'">
<input type="button" class="post-clear-action" value="'.$l10n['add_post']['reset_button'].'">
</div>
';
}
elseif('search'==$path_array[1]){
	$query=$db->prepare(htmlspecialchars($_GET['q']));
	print '<h1 class="page_title">'.$l10n['global']['search_title'].' &laquo;'.$query.'&raquo;</h1><hr>';
	print '<input type="text" id="search-user-list" value="'.$l10n['global']['search_filter'].'" onfocus="if(\''.$l10n['global']['search_filter'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['search_filter'].'\';}">';
	print '<hr>';
	if(3<=mb_strlen($query)){
		$q=$db->sql("SELECT `id`,`login`,`name`,`avatar`,`location`,`about` FROM `users` WHERE (`login` LIKE '%".$query."%' OR `name` LIKE '%".$query."%' OR `location` LIKE '%".$query."%' OR `about` LIKE '%".$query."%') ORDER BY `name` ASC, `login` ASC LIMIT 3000");
		print '<div class="user-list-wide">';
		while($m=$db->row($q)){
			if(''==$m['avatar']){
				$m['avatar']='https://goldvoice.club/images/noava120.png';
			}
			$user_name_none=false;
			if(''==$m['name']){
				$m['name']=$m['login'];
				$user_name_none=true;
			}
			$subscribed=in_array($m['id'],$session_arr['subscribed']);
			$ignored=in_array($m['id'],$session_arr['ignored']);
			$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
			$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
			if(in_array($m['id'],$session_arr['friends'])){
				$subscribed=true;
				$subscribed_by=true;
			}
			$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'].' '.$m['about'];
			print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
			print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
			print '<div class="user-info">';
			if($session_arr['user']!=$m['id']){
				print '<div class="user-card-actions"></div>';
			}
			print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
			if(!$user_name_none){
				print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
			}
			if($m['location']){
				print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true" title="'.$l10n['profile']['meta_location'].'"></i> '.htmlspecialchars($m['location']).'</div>';
			}
			if($m['about']){
				print '<div class="user-about"><i class="fa fa-fw fa-info-circle" aria-hidden="true" title="'.$l10n['profile']['meta_about'].'"></i> '.htmlspecialchars($m['about']).'</div>';
			}
			print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
			print '</div>';
			print '</div>';
		}
		print '<div class="user-list-search-result"></div>';
		print '</div>';
	}
	else{
		print '<p>'.$l10n['global']['search_help'].'</p>';
	}
}
else{
	if('@'==mb_substr($path_array[2],0,1)){
		if($path_array[3]){
			$look_user=mb_substr($path_array[2],1);
			$look_user_id=get_user_id($look_user);
			if(0!=$db->table_count('posts',"WHERE `author`='".$look_user_id."' AND `permlink`='".$db->prepare($path_array[3])."'")){
				header('location:/'.htmlspecialchars(mb_strtolower($path_array[2])).'/'.htmlspecialchars($path_array[3]).'/');
			}
			else{
				if(0!=$db->table_count('comments',"WHERE `author`='".$look_user_id."' AND `permlink`='".$db->prepare($path_array[3])."'")){
					$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `author`='".$look_user_id."' AND `permlink`='".$db->prepare($path_array[3])."'");
					$post_permlink=$db->select_one('posts','permlink',"WHERE `id`='".$comment_arr['post']."'");
					$post_author=$db->select_one('posts','author',"WHERE `id`='".$comment_arr['post']."'");
					$post_author_login=get_user_login($post_author);
					header('location:/'.htmlspecialchars('@'.mb_strtolower($post_author_login)).'/'.htmlspecialchars($post_permlink).'/#'.htmlspecialchars($comment_arr['permlink']));
				}
			}
			exit;
		}
	}
	if('@'==mb_substr($path_array[1],0,1)){
		$look_user=mb_substr($path_array[1],1);
		//$look_user_arr=$db->sql_row("SELECT * FROM `users` WHERE `login`='".$db->prepare($look_user)."' AND `status`!=1");
		$look_user_arr=$redis->hgetall('users:'.$look_user);
		if(1===$look_user_arr['status']){
			unset($look_user_arr);
		}
		$replace['description']='';
		if($look_user_arr['id']){
			if('@'.$look_user_arr['login']!=$path_array[1]){
				$path_array[1]=mb_strtolower($path_array[1]);
				$new_path=implode('/',$path_array);
				header('location:'.htmlspecialchars($new_path).'');
				exit;
			}
			$replace['title']='@'.$look_user_arr['login'].' - '.$replace['title'];

			if(3==$look_user_arr['status']){
				$replace['title']='403 - '.$replace['title'];
				header('HTTP/1.0 403 Forbidden');
				print '<h1>'.$l10n['403']['page_title'].'</h1>';
				print '<p>'.$l10n['403']['user_status_3'].'</p>';
			}
			if(2==$look_user_arr['status']){
				if($auth){
					$look_user_arr['status']=0;
				}
				else{
					$replace['title']='403 - '.$replace['title'];
					header('HTTP/1.0 403 Forbidden');
					print '<h1>'.$l10n['403']['page_title'].'</h1>';
					print '<p>'.$l10n['403']['status_2'].'</p>';
				}
			}
			if(0==$look_user_arr['status']){
				$look_user_action=false;
				$look_user_admin=false;
				$look_user_online=false;
				$look_yourself=false;
				if($session_arr['user']==$look_user_arr['id']){
					$look_user_action=true;
					$look_yourself=true;
				}
				if($admin){
					$look_user_admin=true;
				}
				if($look_user_arr['background_color']){
					$replace['head_addon'].=PHP_EOL."<script>var blogger_background_color='#".$look_user_arr['background_color']."';</script>";
				}
				if(''==$path_array[2]){
					$replace['head_addon'].=PHP_EOL.'<link rel="alternate" type="application/rss+xml" title="@'.htmlspecialchars($look_user_arr['login']).' - RSS GoldVoice.club" href="https://goldvoice.club/rss.xml?user='.htmlspecialchars($look_user_arr['login']).'" />';
					if(''==$look_user_arr['avatar']){
						$look_user_arr['avatar']='https://goldvoice.club/images/noava.png';
					}
					$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=1");
					while($m=$db->row($q)){
						$look_user_arr['friends'][]=$m['user_2'];
					}
					$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=0");
					while($m=$db->row($q)){
						$look_user_arr['subscribed'][]=$m['user_2'];
					}
					$subscribed=in_array($look_user_arr['id'],$session_arr['subscribed']);
					$ignored=in_array($look_user_arr['id'],$session_arr['ignored']);
					$ignored_by=in_array($look_user_arr['id'],$session_arr['ignored_by']);
					$subscribed_by=in_array($look_user_arr['id'],$session_arr['subscribed_by']);
					if(in_array($look_user_arr['id'],$session_arr['friends'])){
						$subscribed=true;
						$subscribed_by=true;
					}
					$replace['page-profile']=' profile';
					$replace['page-cover']='<div class="page-cover"'.($look_user_arr['cover']?' style="background-image:url(\'https://i.goldvoice.club/0x0/'.htmlspecialchars($look_user_arr['cover']).'\')"':' style="height:94px;"').'>';
					$replace['page-cover'].='</div>';
					$replace['page-cover'].='<div class="profile-line">';
					$replace['page-cover'].='<div class="content-wrapper">';
					$replace['page-cover'].='<div class="user-card" data-user-login="'.htmlspecialchars($look_user_arr['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'" data-telegram="'.htmlspecialchars($look_user_arr['telegram']).'">';
					if(!$look_user_action){//смотрим чужой профиль
						$replace['page-cover'].='<div class="user-card-actions"></div>';
					}
					$replace['page-cover'].='</div>';

					$replace['page-cover'].='<div class="profile-avatar" style="background-image:url(\'https://i.goldvoice.club/0x0/'.htmlspecialchars($look_user_arr['avatar']).'\')"></div>';
					$replace['page-cover'].='<div class="profile-links">';
					if(($auth)&&(!$look_yourself)){
						$friends_circle=array();
						foreach($look_user_arr['friends'] as $k=>$user_id){
							if(in_array($user_id,$session_arr['friends'])){
								$friends_circle[]=$user_id;
								unset($look_user_arr['friends'][$k]);
							}
						}
						if(0<count($friends_circle)){
							$replace['page-cover'].='<a href="/@'.$look_user_arr['login'].'/friends-circle/">'.$l10n['profile']['friends_circle'].' &ndash; '.count($friends_circle).'</a>';
						}
					}
					$replace['page-cover'].='<a href="/@'.$look_user_arr['login'].'/friends/">'.$l10n['profile']['friends'].' &ndash; '.count($look_user_arr['friends']).'</a>';
					if($look_yourself){
						$friends_online_count=0;
						foreach($look_user_arr['friends'] as $friend_id){
							if(redis_user_online(get_user_login($friend_id))){
								$friends_online_count++;
							}
						}
						//$friends_online_count=$db->sql_row('SELECT count(*) as `count` FROM `users_links` RIGHT JOIN `users` ON `users`.`id`=`users_links`.`user_2` AND `users`.`action_time`>'.(time()-300).'  WHERE `user_1`='.$session_arr['user'].' AND `value`=1 AND `mutually`=1');
						if(0!=$friends_online_count){
							$replace['page-cover'].='<a href="/@'.$look_user_arr['login'].'/friends-online/">'.$l10n['profile']['friends_online'].' &ndash; '.$friends_online_count.'</a>';
						}
					}
					$replace['page-cover'].='<a href="/@'.$look_user_arr['login'].'/subscribes/">'.$l10n['profile']['subscribes'].' &ndash; '.count($look_user_arr['subscribed']).'</a>';
					$replace['page-cover'].='</div>';

					$replace['page-cover'].='</div>';
					$replace['page-cover'].='</div>';

					$dynamic_page_start=true;
					$dynamic_page_end=true;
					print '<div class="page">';
					$replace['head_addon'].='

					<link rel="image_src" href="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
					<meta property="og:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
					<meta name="twitter:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />';
					$look_user_arr['action_time']=$redis->zscore('users_action_time',$look_user_arr['login']);
					if($look_user_arr['action_time']<$look_user_arr['last_post_time']){
						$look_user_arr['action_time']=$look_user_arr['last_post_time'];
					}
					if($look_user_arr['action_time']>(time()-300)){
						$look_user_online=true;
					}
					$look_user_last_action=ceil((time()-(int)$look_user_arr['action_time'])/60);
					if($look_user_last_action<60){
						$look_user_last_action.=' '.$l10n['global']['minutes'];
					}
					else{
						$buf=ceil($look_user_last_action/60);
						if($buf<24){
							$look_user_last_action=$buf.' '.$l10n['global']['hours'];
						}
						else{
							$buf=ceil($buf/24);
							$look_user_last_action=$buf.' '.$l10n['global']['days'];
						}
					}
					if('0 минуты'==$look_user_last_action){
						$look_user_last_action=$l10n['global']['minute'];
					}
					if(!$look_user_arr['name']){
						$look_user_arr['name']=$look_user_arr['login'];
					}
					if($look_user_arr['about']){
						$replace['description']=htmlspecialchars($look_user_arr['about']);
					}

					if($look_user_online){
						print '<div class="online_status" title="'.$l10n['profile']['last_activity'].': '.$look_user_last_action.' '.$l10n['global']['time_back'].'"><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i> '.$l10n['profile']['online'].'</div>';
					}
					else{
						print '<div class="offline_status" title="'.$l10n['profile']['last_activity'].': '.$look_user_last_action.' '.$l10n['global']['time_back'].'"><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i> '.$l10n['profile']['offline'].'</div>';
					}
					$gender_info='';
					if(1==$look_user_arr['gender']){
						$gender_info='<span class="profile-gender"><i class="fa fa-fw fa-mars" aria-hidden="true"></i></span>';
					}
					if(2==$look_user_arr['gender']){
						$gender_info='<span class="profile-gender"><i class="fa fa-fw fa-venus" aria-hidden="true"></i></span>';
					}
					print '<h1 class="page_title">'.htmlspecialchars($look_user_arr['name']).$gender_info.'</h1><hr>';
					$profile_addon='';
					if($look_user_arr['about']){
						$profile_addon.='<p><i class="fa fa-fw fa-info-circle" aria-hidden="true" title="'.$l10n['profile']['meta_about'].'"></i> '.htmlspecialchars($look_user_arr['about']).'<p>';
					}
					if($look_user_arr['website']){
						$profile_addon.='<p><i class="fa fa-fw fa-link" aria-hidden="true" title="'.$l10n['profile']['meta_website'].'"></i> <a href="'.htmlspecialchars($look_user_arr['website']).'" target="_blank" rel="nofollow">'.htmlspecialchars($look_user_arr['website']).'</a></p>';
					}
					if($look_user_arr['location']){
						$profile_addon.='<p><i class="fa fa-fw fa-map-o" aria-hidden="true" title="'.$l10n['profile']['meta_location'].'"></i> '.htmlspecialchars($look_user_arr['location']).'<p>';
					}
					if($look_user_arr['telegram']){
						$profile_addon.='<p><i class="fa fa-fw fa-telegram" aria-hidden="true" title="Telegram"></i> <a href="tg://resolve?domain='.htmlspecialchars($look_user_arr['telegram']).'">'.htmlspecialchars($look_user_arr['telegram']).'</a><p>';
					}
					if($profile_addon){
						print $profile_addon.'<hr>';
					}
					print '<div class="user-balance" data-login="'.$look_user_arr['login'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> ';
					$sg_balance=(float)$look_user_arr['vesting_shares']*(float)$currencies_price['sg_per_vests'];
					$sg_balance=round($sg_balance,3);
						print '<div class="user-balance-golos"><span>'.$look_user_arr['balance'].'</span> '.$l10n['assets']['golos_amount'].'</div>';
						print '<div class="user-balance-gbg"><span>'.$look_user_arr['sbd_balance'].'</span> '.$l10n['assets']['gbg_amount'].'</div>';
						print '<div class="user-balance-sg"><span class="user-balance-sg-amount">'.$sg_balance.'</span> '.$l10n['assets']['golos_power_amount'].'<span class="user-balance-powerdown"><i class="fa fa-fw fa-level-down" aria-hidden="true"></i></span></div>';
					print PHP_EOL.'<div class="user-balance-summary">&hellip;</div>';
					print '</div>';
					if((0!=$look_user_arr['savings_balance'])||(0!=$look_user_arr['savings_sbd_balance'])){
						print PHP_EOL.'<div class="user-savings"><i class="fa fa-fw fa-lock" aria-hidden="true"></i> ';
						print '<div class="user-balance-savings-golos"><span>'.$look_user_arr['savings_balance'].'</span> '.$l10n['assets']['golos_amount'].'</div>';
						print '<div class="user-balance-savings-gbg"><span>'.$look_user_arr['savings_sbd_balance'].'</span> '.$l10n['assets']['gbg_amount'].'</div>';
						print '</div>';
					}
					else{
						print PHP_EOL.'<div class="user-savings hide"><i class="fa fa-fw fa-lock" aria-hidden="true"></i> ';
						print '<div class="user-balance-savings-golos"><span>0</span> '.$l10n['assets']['golos_amount'].'</div>';
						print '<div class="user-balance-savings-gbg"><span>0</span> '.$l10n['assets']['gbg_amount'].'</div>';
						print '</div>';
					}
					print '<hr>';
					print '<div class="blog_stats">';
						//$user_post_count=$db->table_count('posts',"WHERE `author`='".$look_user_arr['id']."' AND `parent_post`='0' AND `status`!=1");
						$user_post_count=$look_user_arr['pc'];
						if($user_post_count){
							print '<div class="blog_stat blog_stat_posts"><span>'.$user_post_count.'</span>'.$l10n['profile']['posts_count'].'</div>';
						}
						//$user_repost_count=$db->table_count('posts',"WHERE `author`='".$look_user_arr['id']."' AND `parent_post`!='0' AND `status`!=1");
						$user_repost_count=$look_user_arr['rc'];
						if($user_repost_count){
							print '<div class="blog_stat blog_stat_reposts"><span>'.$user_repost_count.'</span>'.$l10n['profile']['reposts_count'].'</div>';
						}
						//$user_comment_count=$db->table_count('comments',"WHERE `author`='".$look_user_arr['id']."' AND `status`!=1");
						$user_comment_count=$look_user_arr['cc'];
						if($user_comment_count){
							print '<a class="blog_stat blog_stat_comments"  href="/@'.$look_user_arr['login'].'/comments/"><span>'.$user_comment_count.'</span>'.$l10n['profile']['comments_count'].'</a>';
						}
						//$user_upvote_count=$db->table_count('posts_votes',"WHERE `user`='".$look_user_arr['id']."' AND `weight`>0");
						//$user_upvote_count+=$db->table_count('comments_votes',"WHERE `user`='".$look_user_arr['id']."' AND `weight`>0");
						$user_upvote_count=$look_user_arr['uc'];
						if($user_upvote_count){
							print '<a class="blog_stat blog_stat_upvotes" href="/@'.$look_user_arr['login'].'/upvotes/"><span>'.$user_upvote_count.'</span>'.$l10n['profile']['upvotes_count'].'</a>';
						}
						//$user_flag_count=$db->table_count('posts_votes',"WHERE `user`='".$look_user_arr['id']."' AND `weight`<0");
						//$user_flag_count+=$db->table_count('comments_votes',"WHERE `user`='".$look_user_arr['id']."' AND `weight`<0");
						$user_flag_count=$look_user_arr['dc'];
						if($user_flag_count){
							print '<a class="blog_stat blog_stat_flags" href="/@'.$look_user_arr['login'].'/flags/"><span>'.$user_flag_count.'</span>'.$l10n['profile']['flags_count'].'</a>';
						}
					print '</div>';
					print '</div>';
					print '<a class="blog_view_mode right">'.$l10n['view_mode']['loading'].'</a>';
					print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
					print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
					print '<div class="posts-list">';
					/* + user posts list */
					$perpage=50;
					$offset=0;
					$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`author`='".$look_user_arr['id']."' AND `posts`.`status`!=1 ORDER BY `posts`.`id` DESC LIMIT ".$perpage." OFFSET 0";
					$q=$db->sql($sql);
					while($m=$db->row($q)){
						$m['author_login']=$redis->get('user_login:'.$m['author']);
						$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
						$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
						print preview_post($m,$look_user_arr['id']);
					}
					print '<div class="load-more-indicator" data-user="'.$look_user_arr['id'].'" data-action="user-posts"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
					/* - user posts list */
					print '</div>';
				}
				elseif('friends'==$path_array[2]){
					$replace['title']=$l10n['profile']['all_friends'].' - '.$replace['title'];
					$invites_count=$db->table_count('users_links',"WHERE `user_2`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=0");
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/invites/" class="user-title-invites">'.$l10n['profile']['friends_invites'].': '.$invites_count.'</a><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> '.$l10n['profile']['all_friends'].'</h1>';
					print '<hr>';
					print '<input type="text" id="search-user-list" value="'.$l10n['global']['user_search'].'" onfocus="if(\''.$l10n['global']['user_search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['user_search'].'\';}">';
					print '<hr>';
					$q=$db->sql("SELECT `users_links`.`user_2` as `id` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=1 LIMIT 5000");
					print '<div class="user-list-wide">';
					while($m1=$db->row($q)){
						$m=$redis->hgetall('users:'.get_user_login($m1['id']));
						if(''==$m['avatar']){
							$m['avatar']='https://goldvoice.club/images/noava120.png';
						}
						$user_name_none=false;
						if(''==$m['name']){
							$m['name']=$m['login'];
							$user_name_none=true;
						}
						$subscribed=in_array($m['id'],$session_arr['subscribed']);
						$ignored=in_array($m['id'],$session_arr['ignored']);
						$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
						$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
						if(in_array($m['id'],$session_arr['friends'])){
							$subscribed=true;
							$subscribed_by=true;
						}
						$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'];
						print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
						print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
						print '<div class="user-info">';
						if($session_arr['user']!=$m['id']){
							print '<div class="user-card-actions"></div>';
						}
						print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
						if(!$user_name_none){
							print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
						}
						if($m['location']){
							print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true"></i> '.htmlspecialchars($m['location']).'</div>';
						}
						print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
						print '</div>';
						print '</div>';
					}
					print '<div class="user-list-search-result"></div>';
					print '</div>';
				}
				elseif('friends-circle'==$path_array[2]){
					$replace['title']=$l10n['profile']['friends_circle'].' - '.$replace['title'];
					$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=1 LIMIT 5000");
					while($m=$db->row($q)){
						$look_user_arr['friends'][]=$m['user_2'];
					}
					$friends_circle=array();
					foreach($look_user_arr['friends'] as $k=>$user_id){
						if(in_array($user_id,$session_arr['friends'])){
							$friends_circle[]=$user_id;
							unset($look_user_arr['friends'][$k]);
						}
					}
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> '.$l10n['profile']['friends_circle'].'</h1>';
					print '<hr>';
					print '<input type="text" id="search-user-list" value="'.$l10n['global']['user_search'].'" onfocus="if(\''.$l10n['global']['user_search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['user_search'].'\';}">';
					print '<hr>';
					print '<div class="user-list-wide">';
					foreach($friends_circle as $user_id){
						//$m=$db->sql_row("SELECT `id`,`login`,`name`,`avatar`,`location` FROM `users` WHERE `id`='".$db->prepare($user_id)."'");
						$m=$redis->hgetall('users:'.get_user_login($user_id));
						if(''==$m['avatar']){
							$m['avatar']='https://goldvoice.club/images/noava120.png';
						}
						$user_name_none=false;
						if(''==$m['name']){
							$m['name']=$m['login'];
							$user_name_none=true;
						}
						$subscribed=in_array($m['id'],$session_arr['subscribed']);
						$ignored=in_array($m['id'],$session_arr['ignored']);
						$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
						$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
						if(in_array($m['id'],$session_arr['friends'])){
							$subscribed=true;
							$subscribed_by=true;
						}
						$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'];
						print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
						print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
						print '<div class="user-info">';
						if($session_arr['user']!=$m['id']){
							print '<div class="user-card-actions"></div>';
						}
						print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
						if(!$user_name_none){
							print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
						}
						if($m['location']){
							print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true"></i> '.htmlspecialchars($m['location']).'</div>';
						}
						print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
						print '</div>';
						print '</div>';
					}
					print '<div class="user-list-search-result"></div>';
					print '</div>';
				}
				elseif('subscribes'==$path_array[2]){
					$replace['title']='Подписки - '.$replace['title'];
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> Подписки</h1>';
					print '<hr>';
					print '<input type="text" id="search-user-list" value="'.$l10n['global']['user_search'].'" onfocus="if(\''.$l10n['global']['user_search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['user_search'].'\';}">';
					print '<hr>';
					$q=$db->sql("SELECT `users_links`.`user_2` as `id` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=0 LIMIT 5000");
					print '<div class="user-list-wide">';
					while($m1=$db->row($q)){
						$m=$redis->hgetall('users:'.get_user_login($m1['id']));
						if(''==$m['avatar']){
							$m['avatar']='https://goldvoice.club/images/noava120.png';
						}
						$user_name_none=false;
						if(''==$m['name']){
							$m['name']=$m['login'];
							$user_name_none=true;
						}
						$subscribed=in_array($m['id'],$session_arr['subscribed']);
						$ignored=in_array($m['id'],$session_arr['ignored']);
						$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
						$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
						if(in_array($m['id'],$session_arr['friends'])){
							$subscribed=true;
							$subscribed_by=true;
						}
						$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'];
						print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
						print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
						print '<div class="user-info">';
						if($session_arr['user']!=$m['id']){
							print '<div class="user-card-actions"></div>';
						}
						print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
						if(!$user_name_none){
							print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
						}
						if($m['location']){
							print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true"></i> '.htmlspecialchars($m['location']).'</div>';
						}
						print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
						print '</div>';
						print '</div>';
					}
					print '<div class="user-list-search-result"></div>';
					print '</div>';
				}
				elseif('invites'==$path_array[2]){
					$replace['title']=$l10n['profile']['friends_invites'].' - '.$replace['title'];
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> '.$l10n['profile']['friends_invites'].'</h1>';
					print '<hr>';
					print '<input type="text" id="search-user-list" value="'.$l10n['global']['user_search'].'" onfocus="if(\''.$l10n['global']['user_search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['user_search'].'\';}">';
					print '<hr>';
					$q=$db->sql("SELECT `users_links`.`user_1` as `id` FROM `users_links` WHERE `user_2`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=0 LIMIT 5000");
					print '<div class="user-list-wide">';
					while($m1=$db->row($q)){
						$m=$redis->hgetall('users:'.get_user_login($m1['id']));
						if(''==$m['avatar']){
							$m['avatar']='https://goldvoice.club/images/noava120.png';
						}
						$user_name_none=false;
						if(''==$m['name']){
							$m['name']=$m['login'];
							$user_name_none=true;
						}
						$subscribed=in_array($m['id'],$session_arr['subscribed']);
						$ignored=in_array($m['id'],$session_arr['ignored']);
						$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
						$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
						if(in_array($m['id'],$session_arr['friends'])){
							$subscribed=true;
							$subscribed_by=true;
						}
						$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'];
						print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
						print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
						print '<div class="user-info">';
						if($session_arr['user']!=$m['id']){
							print '<div class="user-card-actions"></div>';
						}
						print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
						if(!$user_name_none){
							print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
						}
						if($m['location']){
							print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true"></i> '.htmlspecialchars($m['location']).'</div>';
						}
						print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
						print '</div>';
						print '</div>';
					}
					print '<div class="user-list-search-result"></div>';
					print '</div>';
				}
				elseif('friends-online'==$path_array[2]){
					$replace['title']=$l10n['profile']['friends_online'].' - '.$replace['title'];
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> '.$l10n['profile']['friends_online'].'</h1>';
					print '<hr>';
					print '<input type="text" id="search-user-list" value="'.$l10n['global']['user_search'].'" onfocus="if(\''.$l10n['global']['user_search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['user_search'].'\';}">';
					print '<hr>';
					$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=1");
					while($m=$db->row($q)){
						$look_user_arr['friends'][]=$m['user_2'];
					}
					$friends_online_count=0;
					//$q=$db->sql("SELECT `users_links`.`user_2` as `id`, `users`.`login`, `users`.`name`, `users`.`avatar`, `users`.`location`, `users`.`action_time` FROM `users_links` RIGHT JOIN `users` ON `users_links`.`user_2`=`users`.`id` AND `users`.`action_time`>'".(time()-300)."' WHERE `user_1`='".$look_user_arr['id']."' AND `value`=1 AND `mutually`=1 ORDER BY `users`.`name` ASC, `users`.`login` ASC LIMIT 5000");
					print '<div class="user-list-wide">';
					//while($m=$db->row($q)){
					foreach($look_user_arr['friends'] as $friend_id){
						if(redis_user_online(get_user_login($friend_id))){
							$friends_online_count++;
							$m=$redis->hgetall('users:'.get_user_login($friend_id));
							if(''==$m['avatar']){
								$m['avatar']='https://goldvoice.club/images/noava120.png';
							}
							$user_name_none=false;
							if(''==$m['name']){
								$m['name']=$m['login'];
								$user_name_none=true;
							}
							$subscribed=in_array($m['id'],$session_arr['subscribed']);
							$ignored=in_array($m['id'],$session_arr['ignored']);
							$ignored_by=in_array($m['id'],$session_arr['ignored_by']);
							$subscribed_by=in_array($m['id'],$session_arr['subscribed_by']);
							if(in_array($m['id'],$session_arr['friends'])){
								$subscribed=true;
								$subscribed_by=true;
							}
							$search_text='@'.$m['login'].' '.$m['name'].' '.$m['location'];
							print '<div class="user-list-item user-card" data-user-login="'.htmlspecialchars($m['login']).'" data-subscribed="'.($subscribed?1:0).'" data-ignored="'.($ignored?1:0).'" data-subscribed-by="'.($subscribed_by?1:0).'" data-ignored-by="'.($ignored_by?1:0).'">';
							print '<a href="/@'.$m['login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/80x80a/'.$m['avatar'].'" alt=""></a>';
							print '<div class="user-info">';
							if($session_arr['user']!=$m['id']){
								print '<div class="user-card-actions"></div>';
							}
							print '<a href="/@'.$m['login'].'/" class="user-name">'.($user_name_none?'@':'').htmlspecialchars($m['name']).'</a>';
							if(!$user_name_none){
								print '<a href="/@'.$m['login'].'/" class="user-login">@'.$m['login'].'</a>';
							}
							if($m['location']){
								print '<div class="user-location"><i class="fa fa-fw fa-map-o" aria-hidden="true"></i> '.htmlspecialchars($m['location']).'</div>';
							}
							print '<div class="user-search-text">'.htmlspecialchars($search_text).'</div>';
							print '</div>';
							print '</div>';
						}
					}
					print '<div class="user-list-search-result"></div>';
					print '</div>';
				}
				elseif('feed'==$path_array[2]){
					$replace['title']=$l10n['feed']['page_title'].' - '.$replace['title'];
					print '<h1 class="page_title"><i class="fa fa-fw fa-newspaper-o" aria-hidden="true"></i> '.$l10n['feed']['page_title'].' @'.$look_user_arr['login'].'</h1>';
					print '</div>';
					print '<a class="feed_view_mode right">'.$l10n['view_mode']['loading'].'</a>';
					print '<div class="action-button posts-list-filter-button"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Настроить фильтр по тэгам<span></span></div>';
					print '<div class="posts-list-filter page"><h2 class="subpage_title"><i class="fa fa-fw fa-filter" aria-hidden="true"></i> Фильтр по тэгам</h2><form action="" method="POST" onsubmit="return false" autocomplete="off"></form><hr><h3>Показывать</h3><div class="posts-list-filter-show"></div><hr><h3>Скрывать</h3><div class="posts-list-filter-hide"></div></div>';
					print '<div class="posts-list">';
					/* + feed posts list */
					$perpage=100;
					$feed_arr=redis_read_feed($look_user_arr['id'],0,$perpage);
					foreach($feed_arr as $feed_id){
						$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$feed_id."' AND `posts`.`status`!=1");
						$m['author_login']=$redis->get('user_login:'.$m['author']);
						$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
						$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
						print preview_post($m);
						$count++;
					}
					/*$sql="SELECT `post` FROM `feed` WHERE `user`='".$look_user_arr['id']."' ORDER BY `post` DESC LIMIT ".$perpage." OFFSET 0";
					$q=$db->sql($sql);
					while($m=$db->row($q)){
						print preview_post((int)$m['post']);
					}
					*/
					print '<div class="load-more-indicator" data-action="feed-posts" data-user-login="'.$look_user_arr['login'].'"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
					/* - feed posts list */
				}
				elseif('comments'==$path_array[2]){//on1x
					$user_comment_count=$db->table_count('comments',"WHERE `author`='".$look_user_arr['id']."' AND `status`!=1");
					print '<h1 class="page_title">'.$l10n['comments']['page_title'].' <i class="fa fa-fw fa-comments-o" aria-hidden="true"></i> <span class="comments-count">'.$user_comment_count.'</span></h1><hr>';
					if(1!=$look_user_arr['seo_index_comments']){
						print '<noindex>';
					}
					$perpage=100;
					$offset=0;
					$pages_count=ceil($user_comment_count/$perpage);
					$page=1;
					if($_GET['page']){
						$page=(int)$_GET['page'];
					}
					if($page<1){
						$page=1;
					}
					if($page>$pages_count){
						$page=$pages_count;
					}
					$offset=$perpage*($page-1);
					print '<div class="comments" id="comments">';
					$comment_q=$db->sql("SELECT `comments`.`id` FROM `comments` WHERE `author`='".$look_user_arr['id']."' AND `comments`.`status`!=1 ORDER BY `id` DESC LIMIT ".$perpage." OFFSET ".$offset);
					while($comment=$db->row($comment_q)){
						print comment_to_view($comment['id']);
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
						$post_url=$path_array[2];
						$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`json_metadata` as `json_metadata`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format`, `users`.`login` as `author_login`, `users`.`name` as `author_name`, `users`.`avatar` as `author_avatar` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` RIGHT JOIN `users` ON `posts`.`author`=`users`.`id` WHERE `posts`.`id`='".$db->prepare($comment['post'])."' AND `posts`.`status`!=1 LIMIT 1";
						$q=$db->sql($sql);
						$post_arr=$db->row($q);
						print '<div class="comment comment-card clearfix" data-id="'.$comment['id'].'" data-author="'.$comment['author_login'].'" data-permlink="'.$comment['permlink'].'" data-parent="'.$comment['parent'].'" data-allow-votes="'.$comment['allow_votes'].'" data-allow-replies="'.$comment['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'" data-level="0" id="'.htmlspecialchars($comment['permlink']).'">';
						print '<div class="comment-anchor"><a href="/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/#'.htmlspecialchars($comment['permlink']).'">#</a></div>';
						print '<div class="comment-avatar"><a href="/@'.$comment['author_login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$comment['author_avatar'].'" alt=""></a></div>';
						print '<div class="comment-user"><a href="/@'.$comment['author_login'].'/">'.$comment['author_name'].'</a><span class="deep-gray small"> в теме <a href="/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/">'.htmlspecialchars($post_arr['post_title']).'</a></span></div>';
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
					print '</div>';
					if(1!=$look_user_arr['seo_index_comments']){
						print '</noindex>';
					}
					if($pages_count>1){
						if($page>1){
							print '<a href="/@'.$look_user_arr['login'].'/comments/?page='.($page-1).'">&larr; Предыдущая страница</a>';
						}
					}
					if($page<$pages_count){
						print '<a class="right" href="/@'.$look_user_arr['login'].'/comments/?page='.($page+1).'">Следующая страница &rarr;</a>';
					}
				}
				elseif('upvotes'==$path_array[2]){
					$replace['title']=$l10n['menu']['upvotes'].' - '.$replace['title'];
					$upvotes_count=$look_user_arr['uc'];
					$flags_count=$look_user_arr['dc'];
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/flags/" class="right">'.$l10n['menu']['flags'].' ('.$flags_count.')</a><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> '.$l10n['menu']['upvotes'].' ('.$upvotes_count.')</h1>';
					print '</div><div class="posts-list">';
					/* + user upvotes list */
					$perpage=20;
					$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts_votes` LEFT JOIN `posts` ON `posts`.`id`=`posts_votes`.`post` AND `posts`.`status`!=1 LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts_votes`.`user`='".$look_user_arr['id']."' AND `posts_votes`.`weight`>0 ORDER BY `posts_votes`.`post` DESC LIMIT ".$perpage." OFFSET 0";// AND `posts`.`id`<'".$last_id."'
					$q=$db->sql($sql);
					while($m=$db->row($q)){
						$m['author_login']=$redis->get('user_login:'.$m['author']);
						$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
						$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
						print preview_post($m);
					}
					print '<div class="load-more-indicator" data-user="'.$look_user_arr['id'].'" data-action="user-upvotes"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
					/* - user upvotes list */
				}
				elseif('flags'==$path_array[2]){
					$replace['title']='Флаги - '.$replace['title'];
					$upvotes_count=$look_user_arr['uc'];
					$flags_count=$look_user_arr['dc'];
					print '<h1 class="page_title"><a href="/@'.$look_user_arr['login'].'/upvotes/" class="right">Закладки ('.$upvotes_count.')</a><a href="/@'.$look_user_arr['login'].'/" class="user-profile-back" title="'.$l10n['profile']['back_link'].'">&larr;</a> Флаги ('.$flags_count.')</h1>';
					print '</div><div class="posts-list">';
					/* + user upvotes list */
					$perpage=20;
					$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts_votes` LEFT JOIN `posts` ON `posts`.`id`=`posts_votes`.`post` AND `posts`.`status`!=1 LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts_votes`.`user`='".$look_user_arr['id']."' AND `posts_votes`.`weight`<0 ORDER BY `posts_votes`.`post` DESC LIMIT ".$perpage." OFFSET 0";// AND `posts`.`id`<'".$last_id."'
					$q=$db->sql($sql);
					while($m=$db->row($q)){
						$m['author_login']=$redis->get('user_login:'.$m['author']);
						$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
						$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
						print preview_post($m);
					}
					print '<div class="load-more-indicator" data-user="'.$look_user_arr['id'].'" data-action="user-flags"><i class="fa fa-fw fa-spinner" aria-hidden="true"></i> '.$l10n['global']['load_more'].'</div>';
					/* - user flags list */
				}
				else{
					$post_url=$path_array[2];
					$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`json_metadata` as `json_metadata`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`author`='".$look_user_arr['id']."' AND `posts`.`permlink`='".$db->prepare($post_url)."' AND `posts`.`status`!=1 LIMIT 1";
					$q=$db->sql($sql);
					$post_arr=$db->row($q);
					if($post_arr['id']){
						$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
						$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
						$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');
						$replace['menu_collapsed']=true;
						$page_subtype=' blogpost';
						if(1==$_COOKIE['blogpost_show_menu']){
							$replace['menu_collapsed']=false;
						}
						$post_arr['post_title']=str_replace('\\','\\\\',$post_arr['post_title']);
						$post_arr['post_body']=str_replace('\\','\\\\',$post_arr['post_body']);
						$owner=false;
						if($look_user_arr['id']==$session_arr['user']){
							$owner=true;
						}
						if($path_array[3]){
							if('stats'==$path_array[3]){
								$replace['title']='Статистика - '.$replace['title'];
								$replace['descr']='';
								print '<a href="/@'.$look_user_arr['login'].'/'.$post_url.'/" class="right">&larr; Вернуться</a>';
								print '<h1 class="page_title">Статистика поста &laquo;'.htmlspecialchars($post_arr['post_title']).'&raquo;</h1>';
								print '<div class="tabs-list">
								<a class="tabs-item'.(''==$path_array[4]?' selected':'').'" href="/@'.$look_user_arr['login'].'/'.$post_url.'/stats/">Список голосов</a>
								<a class="tabs-item'.('reposts'==$path_array[4]?' selected':'').'" href="/@'.$look_user_arr['login'].'/'.$post_url.'/stats/reposts/">Список репостов</a>
								</div>';
								if('reposts'==$path_array[4]){
									if($owner){
										print '<hr>';
										print '<h2>Рассылка бонуса</h2>';
										print '<div class="unlock-active-key"></div>';
										print '
										<div class="payback-repost-form">
										<div class="wallet_action">
										<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>
										<p class="wallet-balances">
											Токенов на кошельке: <span class="wallet-balance-set" rel="golos" data-asset="GOLOS" data-set-amount="payback-repost-amount" data-set-asset="payback-repost-asset">0</span> Golos, <span class="wallet-balance-set" rel="gbg" data-asset="GBG" data-set-amount="payback-repost-amount" data-set-asset="payback-repost-asset">0</span> GBG
										</p>
										</div>
										<p><select name="payback-repost-asset">';
										foreach($currencies_arr2 as $k=>$v){
											print '<option value="'.$v.'">'.$v.'</option>';
										}
										print '</select> &mdash; Ассет для рассылки бонуса</p>
										<p><input name="payback-repost-amount" value="0.000"> &mdash; Количество</p>

										<p><select name="payback-repost-type">
											<option value="each">Отправить каждому указанное количество токенов</option>
											<option value="split_auditory">Разделить между всеми по аудитории</option>
											<option value="split_active_auditory">Разделить между всеми по активной аудитории</option>
											<option value="split_auditory_power">Разделить между всеми по СГ аудитории</option>
											<option value="split_active_auditory_power">Разделить между всеми по СГ активной аудитории</option>
										</select> &mdash; Тип рассылки</p>

										<p><input name="payback-repost-comment"> &mdash; Комментарий к платежу</p>
										<p><label><input type="checkbox" name="payback-repost-comment-link"> &mdash; Добавить к комментарию ссылку на пост</label></p>
										<input type="hidden" name="post-link" value="https://goldvoice.club/@'.htmlspecialchars(get_user_login($post_arr['author'])).'/'.htmlspecialchars($post_url).'/">
										<p>Стоп-лист (список пользователей исключенных из рассылки бонусов, разделитель пробел):<br><textarea name="payback-repost-stop-list" placeholder=""></textarea></p>
										</div>
										<p><em><strong>Внимание!</strong> Проверьте форму, после отправки все поля будут заблокированы для изменения. Рассылка занимает время процессора, если вкладка подзависнет, просто дайте ей время. Скорее всего часть переводов блокчейн отвергнет из-за анти-спам системы. Подождите пару минут и снова нажмите на кнопку, чтобы продолжить рассылку.</em></p>
										<div class="payback-repost-action">Разослать бонус</div>';
									}
									print '<hr>';
									print '<h2>Список репостов</h2>';
									print '<table class="post-reposts-stats" data-author="'.htmlspecialchars(get_user_login($post_arr['author'])).'" data-permlink="'.htmlspecialchars($post_url).'"><thead><tr><th width="15%">Дата</th><th width="15%">Пользователь</th><th width="15%">Аудитория</th><th>СГ аудитории</th><th width="15%">Активной аудитории</th><th>СГ активной аудитории</th><th class="payback-preview" width="10%">Бонус</th></tr></thead><tbody>';
									$q=$db->sql("SELECT * FROM `posts` WHERE `parent_post`='".$post_arr['id']."' ORDER BY `id` ASC");
									while($m=$db->row($q)){
										$user_login=get_user_login($m['author']);
										$user_auditory=0;
										$user_auditory_power=0;
										$user_active_auditory=0;
										$user_active_auditory_power=0;
										$q2=$db->sql("SELECT `user_1`  FROM `users_links` WHERE `user_2`='".$m['author']."' AND `value`=1");
										while($m2=$db->row($q2)){
											$user2_login=get_user_login($m2['user_1']);
											$m2['vesting_shares']=$redis->hget('users:'.$user2_login,'vesting_shares');
											$m2['action_time']=$redis->hget('users:'.$user2_login,'action_time');
											$user_auditory++;
											$user_auditory_power+=(float)$m2['vesting_shares'];
											if($m2['action_time']>(time()-1209600)){
												$user_active_auditory++;
												$user_active_auditory_power+=(float)$m2['vesting_shares'];
											}
										}
										print '<tr>';
										print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
										print '<td class="voter" data-voter="'.htmlspecialchars($user_login).'"><a href="/@'.htmlspecialchars($user_login).'/" target="_blank">@'.htmlspecialchars($user_login).'</a></td>';
										print '<td rel="auditory">'.$user_auditory.'</td>';
										print '<td rel="auditory_power">'.$user_auditory_power.'</td>';
										print '<td rel="active_auditory">'.$user_active_auditory.'</td>';
										print '<td rel="active_auditory_power">'.$user_active_auditory_power.'</td>';
										print '<td class="payback-preview payback-bonus">&hellip;</td>';
										print '</tr>';
									}
									print '</tbody></table>';
									print '<p><em>Активной аудиторией являются аккаунты, проявившие активность в блокчейне за последние 2 недели (14 дней).</em></p>';
								}
								else{
									if($owner){
										print '<hr>';
										print '<h2>Рассылка бонуса</h2>';
										print '<div class="unlock-active-key"></div>
										<div class="wallet_action">
										<div class="wallet-refresh action-button right"><i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Обновить</div>
										<p class="wallet-balances">
											Токенов на кошельке: <span class="wallet-balance-set" rel="gbg" data-asset="GBG" data-set-amount="payback-size">0</span> GBG
										</p>
										</div>';
										print '<p>Многие пользователи устанавливают дополнительный софт, чтобы рассылать часть полученного вознаграждения кураторам, поддерживающих пост. Вы можете сделать это просто введя приватный active ключ и заполнив форму ниже:</p>
										<div class="payback-form">
										<p><span class="payback-payment-full">&hellip;</span> &mdash; Полученное вознаграждение за пост (100%)</p>
										<p><span class="payback-payment-part">&hellip;</span> &mdash; Полученное вознаграждение в GBG (50% от общего)</p>
										<p><input name="payback-percent" value="50"> &mdash; Сколько процентов от полученных GBG пойдут в бонус кураторам</p>
										<p><input name="payback-size" value="0.000"> &mdash; Сумма GBG, которая будет разослана кураторам</p>
										<p><input name="payback-threshold" value="0.001"> &mdash; Порог минимального бонуса (отсекает ботов и новые аккаунты)</p>
										<p><label><input type="checkbox" name="payback-ignore-threshold"> &mdash; Рассылать минимальный бонус в любом случае</label></p>
										<p><input name="payback-comment"> &mdash; Комментарий к платежу</p>
										<p><label><input type="checkbox" name="payback-comment-link"> &mdash; Добавить к комментарию ссылку на пост</label></p>
										<input type="hidden" name="post-link" value="https://goldvoice.club/@'.htmlspecialchars(get_user_login($post_arr['author'])).'/'.htmlspecialchars($post_url).'/">
										<p>Стоп-лист (список пользователей исключенных из рассылки бонусов, разделитель пробел):<br><textarea name="payback-stop-list" placeholder=""></textarea></p>
										</div>
										<p><em>Выплаты бонусов менее 0.001 GBG рекомендуется отключать, чтобы не нагружать сеть и не поощрать ботов.<br>Из рассылки программно отключен сам автор и список пользователей из стоп-листа.</em></p>
										<p><em><strong>Внимание!</strong> Проверьте форму, после отправки все поля будут заблокированы для изменения. Рассылка занимает время процессора, если вкладка подзависнет, просто дайте ей время. Скорее всего часть переводов блокчейн отвергнет из-за анти-спам системы. Подождите пару минут и снова нажмите на кнопку, чтобы продолжить рассылку.</em></p>
										<div class="payback-action">Разослать бонус</div>';
									}
									print '<hr>';
									print '<h2>Список голосов</h2>';
									print '<table class="post-votes-stats" data-author="'.htmlspecialchars(get_user_login($post_arr['author'])).'" data-permlink="'.htmlspecialchars($post_url).'"><thead><tr><th width="20%">Дата</th><th width="25%">Пользователь</th><th width="10%">Процент</th><th>Вес</th><th class="payback-preview" width="10%">Бонус</th></tr></thead><tbody>';
									$q=$db->sql("SELECT * FROM `posts_votes` WHERE `post`='".$post_arr['id']."' ORDER BY `time` ASC");
									while($m=$db->row($q)){
										$user_login=get_user_login($m['user']);
										print '<tr>';
										print '<td><span class="timestamp" data-timestamp="'.$m['time'].'">'.date('d.m.Y H:i:s',$m['time']).'</span></td>';
										print '<td class="voter" data-voter="'.htmlspecialchars($user_login).'"><a href="/@'.htmlspecialchars($user_login).'/" target="_blank">@'.htmlspecialchars($user_login).'</a></td>';
										print '<td>'.round($m['weight']/100,2).'%</td>';
										print '<td class="weight">&hellip;</td>';
										print '<td class="payback-preview payback-bonus">&hellip;</td>';
										print '</tr>';
									}
									print '</tbody></table>';
								}
							}
							else
							if('edit'==$path_array[3]){
								if($admin){$owner=true;}
								if($owner){
								$post_id=$post_arr['id'];
								$post_tags='';
								$post_tags_arr=array();
								$q=$db->sql("SELECT `tag` FROM `posts_tags` WHERE `post`='".(int)$post_id."' ORDER BY `id` ASC");
								while($m=$db->row($q)){
									$post_tags_arr[]=$db->select_one('tags','en',"WHERE `id`='".$m['tag']."'");
								}
								$post_tags=implode(',',$post_tags_arr);
								$json_metadata=json_decode($post_arr['json_metadata'],true);
								$replace['title']=$l10n['add_post']['edit_post'].' - '.$replace['title'];
								$replace['descr']='';
								print '<h1>'.$l10n['add_post']['edit_post'].'</h1>
<script src="https://cloud.tinymce.com/stable/tinymce.min.js?apiKey=mesccghhml1c93rcjus20gg7r0k3unckbvvqi51illo0heti"></script>
<script>
$(document).ready(function(){
window.ondragover = function(e) {e.preventDefault(); show_modal(\'drop-images\');}
window.ondrop = function(e) {e.preventDefault(); try_upload(e.dataTransfer.files[0]);}
set_post_geo(\''.htmlspecialchars(addslashes($json_metadata['geo']['lat'])).'\',\''.htmlspecialchars(addslashes($json_metadata['geo']['lng'])).'\',\''.htmlspecialchars(addslashes($json_metadata['geo']['address'])).'\');
});
';
print '
</script>
<div class="add-post">
<input type="hidden" name="post_parent_permlink" value="'.htmlspecialchars($post_arr['parent_permlink']).'">
<input type="text" name="post_title" placeholder="'.$l10n['add_post']['title'].'" value="'.htmlspecialchars($post_arr['post_title']).'">
<input type="text" name="post_image" placeholder="'.$l10n['add_post']['cover_image'].'" value="'.htmlspecialchars($post_arr['post_image']).'">
<textarea name="post_text" rows="25">'.htmlspecialchars($post_arr['post_body']).'</textarea>
<input id="upload-file" type="file"/>
<a class="link_upload_file"><i class="fa fa-fw fa-file-image-o" aria-hidden="true"></i> '.$l10n['add_post']['upload_image'].'</a>
<a class="wysiwyg_activate"><i class="fa fa-fw fa-pencil-square" aria-hidden="true"></i> '.$l10n['add_post']['wysiwyg'].'</a>
<input type="text" name="post_tags" placeholder="'.$l10n['add_post']['tags'].'" value="'.htmlspecialchars($post_tags).'">
<a class="show_post_addon"><i class="fa fa-fw fa-cogs" aria-hidden="true"></i> '.$l10n['add_post']['addons'].'</a>
<div class="post_addon">
<div class="post_geo">
<a class="get_post_geo"><i class="fa fa-fw fa-map-marker" aria-hidden="true"></i> '.$l10n['add_post']['add_geo'].'</a>
<a class="clear_post_geo"><i class="fa fa-fw fa-times" aria-hidden="true"></i> '.$l10n['add_post']['remove_geo'].'</a>
<span></span>
</div>
<hr>
URL:<br>
<input type="text" name="post_url" value="'.$post_arr['permlink'].'" disabled="disabled">
</div>
<input type="button" class="post-action" data-edit="1" value="'.$l10n['add_post']['post_button'].'">
</div>
';
								}
								else{
									header('location:/@'.$look_user_arr['login'].'/'.$post_url.'/');
									exit;
								}
							}
							else{
								header('location:/@'.$look_user_arr['login'].'/'.$post_url.'/');
								exit;
							}
						}
						else{
							if(3==$post_arr['status']){
								$replace['title']='403 - '.$replace['title'];
								header('HTTP/1.0 403 Forbidden');
								print '<h1>'.$l10n['403']['page_title'].'</h1>';
								print '<p>'.$l10n['403']['status_3'].'</p>';
							}
							else
							if(2==$post_arr['status']){
								if($auth){
									$post_arr['status']=0;
								}
								else{
									$replace['title']='403 - '.$replace['title'];
									header('HTTP/1.0 403 Forbidden');
									print '<h1>'.$l10n['403']['page_title'].'</h1>';
									print '<p>'.$l10n['403']['status_2'].'</p>';
								}
							}
							else
							if(0==$post_arr['status']){
								$replace['title']=htmlspecialchars($post_arr['post_title']).' - '.$replace['title'];
								$payout_decline=false;
								$payout_inpower=false;
								if(0==$post_arr['percent_steem_dollars']){
									$payout_inpower=true;
								}
								if(0==$post_arr['max_accepted_payout']){
									if(10000==$post_arr['percent_steem_dollars']){
										$payout_decline=true;
									}
								}
								$vote=false;
								$flag=false;
								$vote_weight=0;
								$vote_time=0;
								if($auth){
									$user_vote=$db->sql_row("SELECT `time`,`weight` FROM `posts_votes` WHERE `post`='".$post_arr['id']."' AND `user`='".$session_arr['user']."'");
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

								$votes_count=$post_arr['upvotes'];
								$flags_count=$post_arr['downvotes'];
								print PHP_EOL.'<div class="post-card clearfix" itemid="https://goldvoice.club/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/" itemscope itemtype="http://schema.org/BlogPosting" data-id="'.$post_arr['id'].'" data-author="'.$post_arr['author_login'].'" data-permlink="'.$post_arr['permlink'].'" data-allow-votes="'.$post_arr['allow_votes'].'" data-allow-replies="'.$post_arr['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'">';
								print '<span class="schema" itemprop="mainEntityOfPage" itemscope="" itemType="https://schema.org/WebPage" itemid="https://goldvoice.club/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/"></span>';

								print '<h1 class="page_title" itemprop="headline">'.$post_arr['post_title'].'</h1><hr>';

								$text=text_to_view($post_arr['post_body'],($post_arr['post_format']=='markdown'?true:false));
								$replace['description']=mb_substr(strip_tags($text),0,250).'...';
								$replace['description']=str_replace("\n",' ',$replace['description']);
								$replace['description']=str_replace('  ',' ',$replace['description']);

								$replace['head_addon'].='
								<meta property="og:url" content="https://goldvoice.club/@'.$look_user_arr['login'].'/'.$post_arr['permlink'].'/" />
								<meta name="og:title" content="'.htmlspecialchars($post_arr['post_title']).'" />
								<meta name="twitter:title" content="'.htmlspecialchars($post_arr['post_title']).'" />
								<meta name="twitter:card" content="summary_large_image" />';
								if($post_arr['post_image']){
									if(!preg_match('~^https://~iUs',$post_arr['post_image'])){
										$post_arr['post_image']='https://i.goldvoice.club/0x0/'.$post_arr['post_image'];
									}
									$replace['head_addon'].='
<link rel="image_src" href="'.$post_arr['post_image'].'" />
<meta property="og:image" content="'.$post_arr['post_image'].'" />
<meta name="twitter:image" content="'.$post_arr['post_image'].'" />';
									print '<img src="'.$post_arr['post_image'].'" itemprop="image" class="schema">';
								}

								print '<main><div class="content-body" itemprop="articleBody">'.$text.'</div></main>';
								print '</b></strong></em></i>';

								$json_metadata=json_decode($post_arr['json_metadata'],true);
								if($json_metadata['geo']['address']){
									print '<div><i class="fa fa-fw fa-map-marker" aria-hidden="true"></i> <a href="https://yandex.ru/maps/?ll='.(float)$json_metadata['geo']['lng'].','.(float)$json_metadata['geo']['lat'].'&z=17" class="post-geo-location" target="_blank">'.htmlspecialchars($json_metadata['geo']['address']).'</a>';
									print '</div>';
								}
								$post_tags='';
								$post_tags_html_arr=array();
								$post_tags_arr=array();
								$q=$db->sql("SELECT * FROM `posts_tags` WHERE `post`='".(int)$post_arr['id']."' ORDER BY `id` ASC");
								while($m=$db->row($q)){
									$tag_arr=$db->sql_row("SELECT `en`,`ru` FROM `tags` WHERE `id`='".$m['tag']."'");
									$post_tags_arr[$tag_arr['en']]=$tag_arr['ru']?$tag_arr['ru']:$tag_arr['en'];
								}
								foreach($post_tags_arr as $k=>$v){
									if($v){
										$post_tags_html_arr[]='<a class="tag" href="/tags/'.htmlspecialchars($k).'/" itemprop="keywords" content="'.htmlspecialchars($v).'">'.htmlspecialchars($v).'</a>';
									}
								}
								if($post_tags_html_arr){
									$post_tags=implode('',$post_tags_html_arr);
									print '<div class="post-tags">';
									print $post_tags;
									print '</div>';
								}
								if($owner){
									print '<hr>';
									print '<div class="post-owner-actions"><i class="fa fa-fw fa-bar-chart" aria-hidden="true"></i> <a href="/@'.$post_arr['author_login'].'/'.$post_url.'/stats/">Статистика</a>, <i class="fa fa-fw fa-pencil" aria-hidden="true"></i> <a href="/@'.$post_arr['author_login'].'/'.$post_url.'/edit/">Редактировать пост</a></div>';
								}
								print '<hr>';

								print '<div class="post-line heavy">';
								if(''==$post_arr['author_avatar']){
									$post_arr['author_avatar']='https://goldvoice.club/images/noava32.png';
								}
								if(''==$post_arr['author_name']){
									$post_arr['author_name']='@'.$post_arr['author_login'];
								}
								print '<div class="post-author" itemprop="author" itemscope="" itemtype="http://schema.org/Person"><a href="/@'.$post_arr['author_login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$post_arr['author_avatar'].'" alt="" itemprop="image"></a><a href="/@'.$post_arr['author_login'].'/" itemprop="url"><span itemprop="name">'.$post_arr['author_name'].'</span></a></div>';
								print '<div class="post-datestamp"><span class="post-date" data-timestamp="'.$post_arr['time'].'" itemprop="datePublished dateModified" content="'.date(DATE_ISO8601,$post_arr['time']).'">'.date('d.m.Y H:i',$post_arr['time']).'</span><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i></div>';
								print '</div>';

								print '<div class="post-info heavy">';
									if($auth){
										$repost=(0!=$db->table_count('posts',"WHERE `author`='".$session_arr['user']."' AND `parent_post`='".$post_arr['id']."'")?'1':'0');
										print '<div class="post-repost repost-action'.($repost?' reposted':'').'" title="'.($repost?$l10n['repost_card']['reposted']:$l10n['repost_card']['repost']).'"><i class="fa fa-fw fa-retweet" aria-hidden="true"></i></div>';
									}
									print '<div class="post-share share-action" title="Share it!"><i class="fa fa-fw fa-share-alt" aria-hidden="true"></i></div>';
									print '<div class="post-payments'.((float)$post_arr['payout']?' payout':'').'" data-cashout-time="'.$post_arr['cashout_time'].'" data-payout="'.$post_arr['payout'].'" data-curator-payout="'.$post_arr['curator_payout'].'" data-pending-payout="'.$post_arr['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
									if($auth){
										print '<div class="post-flags flag-action"'.($flag?' title="'.$l10n['flag_card']['time'].'  '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
									}
									else{
										print '<div class="post-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
									}
									if($auth){
										print '<div class="post-upvotes upvote-action"'.($vote?' title="'.$l10n['upvote_card']['time'].' '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
									}
									else{
										print '<div class="post-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
									}
									print '<div class="schema" itemprop="publisher" itemscope="" itemtype="http://schema.org/Organization"><a href="https://goldvoice.club/" itemprop="url"><span itemprop="logo" itemscope="" itemtype="https://schema.org/ImageObject"><img src="/favicon/favicon-96x96.png" itemprop="url image"></span><span itemprop="name">GoldVoice club</span></a></div>';
								print '</div>';

								print '</div>';
								print '</div>';

								//adsense
								$ad_type=$look_user_arr['ad_type'];
								if($ad_type==0){
									if($look_user_arr['ad_a_ads_id']){
										$ad_type=2;
									}
									if($look_user_arr['ad_adsense_client']&&$look_user_arr['ad_adsense_slot']){
										$ad_type=1;
									}
								}
								if(1==$ad_type){
									if(($post_arr['cashout_time']<time())||($look_user_arr['ad_ignore_cashout_time'])){
										/*if(false==strpos($look_user_arr['ad_adsense_client'],'ca-pub-')){
											$look_user_arr['ad_adsense_client']='ca-pub-'.$look_user_arr['ad_adsense_client'];
										}*/
										print '
										<div class="blog_payout_alt"><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
										<ins class="adsbygoogle"
											style="display:block"
											data-style="display:inline-block;width:728px;height:90px"
											data-ad-client="'.htmlspecialchars($look_user_arr['ad_adsense_client']).'"
											data-ad-slot="'.htmlspecialchars($look_user_arr['ad_adsense_slot']).'"
											data-ad-format="auto"></ins>
										<script>
										(adsbygoogle = window.adsbygoogle || []).push({});
										</script></div>';
									}
								}
								if(2==$ad_type){
									if(($post_arr['cashout_time']<time())||($look_user_arr['ad_ignore_cashout_time'])){
										print '
										<div class="blog_payout_alt">
										<iframe data-aa="'.htmlspecialchars($look_user_arr['ad_a_ads_id']).'" src="//acceptable.a-ads.com/'.htmlspecialchars($look_user_arr['ad_a_ads_id']).'" scrolling="no" style="border:0px; padding:0;overflow:hidden" allowtransparency="true"></iframe></div>';
									}
								}

								print '<div class="page">';
								print '<div class="comments" id="comments">';
								if($auth){
									print '<a class="post-reply reply-action" data-post-id="'.$post_arr['id'].'">'.$l10n['comments']['add_comment'].' <i class="fa fa-fw fa-commenting-o" aria-hidden="true"></i></a>';
								}
								print '<h2 class="subpage_title">'.$l10n['comments']['page_title'].' <i class="fa fa-fw fa-comments-o" aria-hidden="true"></i> <span class="comments-count">'.$post_arr['comments'].'</span></h2><hr>';
								if((!$auth)&&(!$look_user_arr['seo_show_comments'])){
									print '<p>'.$l10n['comments']['no_auth'].'</p>';
								}
								else{
									if(1!=$look_user_arr['seo_index_comments']){
										print '<noindex>';
									}
									$comment_q=$db->sql("SELECT `comments`.* FROM `comments` WHERE `post`='".$post_arr['id']."' AND `comments`.`status`!=1 ORDER BY `sort` ASC");
									while($comment=$db->row($comment_q)){
										$comment['author_login']=$redis->get('user_login:'.$comment['author']);
										$comment['author_name']=$redis->hget('users:'.$comment['author_login'],'name');
										$comment['author_avatar']=$redis->hget('users:'.$comment['author_login'],'avatar');

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
										print '<div class="comment comment-card clearfix" data-id="'.$comment['id'].'" data-author="'.$comment['author_login'].'" data-permlink="'.$comment['permlink'].'" data-parent="'.$comment['parent'].'" data-allow-votes="'.$comment['allow_votes'].'" data-allow-replies="'.$comment['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'" data-level="'.$comment['level'].'" id="'.htmlspecialchars($comment['permlink']).'">';
										print '<div class="comment-anchor"><a href="#'.htmlspecialchars($comment['permlink']).'">#</a></div>';
										print '<div class="comment-avatar"><a href="/@'.$comment['author_login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$comment['author_avatar'].'" alt=""></a></div>';
										print '<div class="comment-user"><a href="/@'.$comment['author_login'].'/">'.$comment['author_name'].'</a></div>';
										print '<div class="comment-text">';
										print text_to_view($comment['body']);
										print '</div>';
										print '<div class="comment-info">';
											print '<a class="comment-reply reply-action" data-comment-id="'.$comment['id'].'"><span class="l10n" data-cat="comment_card" data-name="reply"></span> <i class="fa fa-fw fa-commenting-o" aria-hidden="true"></i></a>';
											print '<span class="comment-date" data-timestamp="'.$comment['time'].'">'.date('d.m.Y H:i',$comment['time']).'</span>';
											print '<div class="comment-payments'.((float)$comment['payout']?' payout':'').'" data-comment-payout="'.$comment['payout'].'" data-comment-curator-payout="'.$comment['curator_payout'].'" data-comment-pending-payout="'.$comment['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
											if($auth){
												print '<div class="comment-flags flag-action"><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
											}
											else{
												print '<div class="comment-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
											}
											if($auth){
												print '<div class="comment-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
											}
											else{
												print '<div class="comment-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
											}
										print '</div>';
										print '</div>';
									}
									print '</div>';
									print '<div class="new-comments"></div>';
									if(1!=$look_user_arr['seo_index_comments']){
										print '</noindex>';
									}
								}
							}
						}
					}
					else{
						if('@'==mb_substr($path_array[1],0,1)){
							$look_user=mb_substr($path_array[1],1);
							$look_user_id=get_user_id($look_user);
							$comment_url=$path_array[2];
							if(0!=$db->table_count('comments',"WHERE `author`='".$look_user_id."' AND `permlink`='".$db->prepare($comment_url)."'")){
								$comment_arr=$db->sql_row("SELECT * FROM `comments` WHERE `author`='".$look_user_id."' AND `permlink`='".$db->prepare($comment_url)."'");
								print '<main>'.comment_to_view($comment_arr['id']).'</main>';
								$replace['title']='Комментарий - '.$replace['title'];
								$replace['description']=strip_tags(text_to_view($comment_arr['body']));
								$replace['head_addon'].='
								<link rel="image_src" href="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
								<meta property="og:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />
								<meta name="twitter:image" content="https://i.goldvoice.club/0x0/'.$look_user_arr['avatar'].'" />';
								$post_permlink=$db->select_one('posts','permlink',"WHERE `id`='".$comment_arr['post']."'");
								$post_author=$db->select_one('posts','author',"WHERE `id`='".$comment_arr['post']."'");
								$post_author_login=get_user_login($post_author);
								$canonical_url='/'.htmlspecialchars('@'.mb_strtolower($post_author_login)).'/'.htmlspecialchars($post_permlink).'/#'.htmlspecialchars($comment_arr['permlink']);
								$replace['head_addon'].='<link rel="alternate" href="https://goldvoice.club'.$canonical_url.'" />';
							}
							else{
								$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);
								$post_arr=$web->execute_method('get_content',array($look_user,$comment_url),false);
								if($post_arr['body']){
									//print_r($post_arr);
									print PHP_EOL.'<div class="post-card clearfix" itemid="https://goldvoice.club/@'.$post_arr['author'].'/'.$post_arr['permlink'].'/" itemscope itemtype="http://schema.org/BlogPosting" data-id="'.$post_arr['id'].'" data-author="'.$post_arr['author'].'" data-permlink="'.$post_arr['permlink'].'" data-allow-votes="'.$post_arr['allow_votes'].'" data-allow-replies="'.$post_arr['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'">';
									print '<span class="schema" itemprop="mainEntityOfPage" itemscope="" itemType="https://schema.org/WebPage" itemid="https://goldvoice.club/@'.$post_arr['author'].'/'.$post_arr['permlink'].'/"></span>';

									print '<h1 class="page_title" itemprop="headline">'.$post_arr['title'].'</h1><hr>';

									$text=text_to_view($post_arr['body'],($post_arr['post_format']=='markdown'?true:false));
									$replace['description']=mb_substr(strip_tags($text),0,250).'...';
									$replace['description']=str_replace("\n",' ',$replace['description']);
									$replace['description']=str_replace('  ',' ',$replace['description']);

									$replace['head_addon'].='
									<meta property="og:url" content="https://goldvoice.club/@'.$post_arr['author'].'/'.$post_arr['permlink'].'/" />
									<meta name="og:title" content="'.htmlspecialchars($post_arr['title']).'" />
									<meta name="twitter:title" content="'.htmlspecialchars($post_arr['title']).'" />';

									print '<main><div class="content-body" itemprop="articleBody">'.$text.'</div></main>';
									print '</b></strong></em></i>';
									print '<hr>';

									print '<div class="post-line heavy">';
									if(''==$post_arr['author_avatar']){
										$post_arr['author_avatar']='https://goldvoice.club/images/noava32.png';
									}
									if(''==$post_arr['author_name']){
										$post_arr['author_name']='@'.$post_arr['author'];
									}
									$date=date_parse_from_format('Y-m-d\TH:i:s',$post_arr['created']);
									$post_arr['time']=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
									print '<div class="post-author" itemprop="author" itemscope="" itemtype="http://schema.org/Person"><a href="/@'.$post_arr['author'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$post_arr['author_avatar'].'" alt="" itemprop="image"></a><a href="/@'.$post_arr['author'].'/" itemprop="url"><span itemprop="name">'.$post_arr['author'].'</span></a></div>';
									print '<div class="post-datestamp"><span class="post-date" data-timestamp="'.$post_arr['time'].'" itemprop="datePublished dateModified" content="'.date(DATE_ISO8601,$post_arr['time']).'">'.date('d.m.Y H:i',$post_arr['time']).'</span><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i></div>';
									print '</div>';

									print '<div class="post-info heavy">';
										if($auth){
											$repost=(0!=$db->table_count('posts',"WHERE `author`='".$session_arr['user']."' AND `parent_post`='".$post_arr['id']."'")?'1':'0');
											print '<div class="post-repost repost-action'.($repost?' reposted':'').'" title="'.($repost?$l10n['repost_card']['reposted']:$l10n['repost_card']['repost']).'"><i class="fa fa-fw fa-retweet" aria-hidden="true"></i></div>';
										}
										print '<div class="post-share share-action" title="Share it!"><i class="fa fa-fw fa-share-alt" aria-hidden="true"></i></div>';
										print '<div class="post-payments'.((float)$post_arr['payout']?' payout':'').'" data-cashout-time="'.$post_arr['cashout_time'].'" data-payout="'.$post_arr['payout'].'" data-curator-payout="'.$post_arr['curator_payout'].'" data-pending-payout="'.$post_arr['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
										if($auth){
											print '<div class="post-flags flag-action"'.($flag?' title="'.$l10n['flag_card']['time'].'  '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
										}
										else{
											print '<div class="post-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
										}
										if($auth){
											print '<div class="post-upvotes upvote-action"'.($vote?' title="'.$l10n['upvote_card']['time'].' '.date('d.m.Y H:i',$vote_time).'"':'').'><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
										}
										else{
											print '<div class="post-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
										}
										print '<div class="schema" itemprop="publisher" itemscope="" itemtype="http://schema.org/Organization"><a href="https://goldvoice.club/" itemprop="url"><span itemprop="logo" itemscope="" itemtype="https://schema.org/ImageObject"><img src="/favicon/favicon-96x96.png" itemprop="url image"></span><span itemprop="name">GoldVoice club</span></a></div>';
									print '</div>';

									print '</div>';
									print '</div>';
								}
							}
						}
						//check comment exist
					}
				}
			}
		}
		else{
			header('HTTP/1.1 404 Not Found');
			print '<h1>'.$l10n['404']['user_title'].'</h1>';
			print '<p>'.$l10n['404']['user_descr'].'</p>';
		}
	}
}
$last_block_time=$db->select_one('blocks','time',"ORDER BY `id` DESC");
$last_block_time_offset=time()-$last_block_time;
$ads_arr=array(
	0=>array(
		'link'=>'https://goldvoice.club/@vpodessa/golos-fest-v-odesse-priglashenie/',
		'img'=>'/uploads/goldvoice/golosfest-odessa.png',
		'expire'=>1524895200//28.04.2018 6:00
	),
	1=>array(
		'link'=>'https://goldvoice.club/@goldvoice/arqute-gas/',
		'img'=>'/uploads/goldvoice/arqute_150_300.png',
		'expire'=>1525240800//02.05.2018 6:00
	),
);
$ads_result_arr=array();
foreach($ads_arr as $ads){
	if($ads['expire']>time()){
		$ads_result_arr[]='<a href="'.$ads['link'].'" class="expand-hide"><img src="'.$ads['img'].'" alt=""></a>';
	}
}
$random_ads=$ads_result_arr[array_rand($ads_result_arr)];
if($auth){
	//$replace['menu'].='<hr>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/"><i class="fa fa-fw fa-home'.('/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['home'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/feed/"><span class="notify-feed-count">0</span><i class="fa fa-fw fa-newspaper-o'.('/feed/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['feed'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/popular/"><i class="fa fa-fw fa-fire'.('/popular/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['popular'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/vox-populi/"><i class="fa fa-fw fa-users'.('/vox-populi/'==$path?' current':'').'" aria-hidden="true"></i> Vox Populi</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/notifications/?type=replies"><span class="notify-replies-count">0</span><i class="fa fa-fw fa-reply'.(('/notifications/'==$path)&&('replies'==$_GET['type'])?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['replies'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left menu-my-page menu-add-offset" href="/@'.$session_arr['public_profile']['login'].'/"><i class="fa fa-fw fa-user-circle'.('/@'.$session_arr['public_profile']['login'].'/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['my_page'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left menu-my-friends" href="/@'.$session_arr['public_profile']['login'].'/friends/"><i class="fa fa-fw fa-address-book-o'.('/@'.$session_arr['public_profile']['login'].'/friends/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['friends'].'</a>';
	/*$replace['menu'].='<a class="button button-line button-left" href="/@'.$session_arr['public_profile']['login'].'/upvotes/"><i class="fa fa-fw fa-thumbs-up'.('/@'.$session_arr['public_profile']['login'].'/upvotes/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['upvotes'].'</a>';*/
	$replace['menu'].='<a role="link" class="button button-line button-left menu-my-page menu-add-offset" href="/wallet/"><i class="fa fa-fw fa-credit-card'.('/wallet/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['wallet'].'</a>';
	if(''==$session_arr['public_profile']['witnesses_proxy']){
		$replace['menu'].='<a role="link" class="button button-line button-left" href="/witnesses/"><i class="fa fa-fw fa-check-square-o'.('/witnesses/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['witnesses'].'</a>';
	}
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/settings/"><i class="fa fa-fw fa-cogs'.('/settings/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['settings'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/profile/"><i class="fa fa-fw fa-user'.('/profile/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['profile'].'</a>';
	$replace['menu'].='<div class="search-box"><input type="text" class="search-textbox" value="'.$l10n['global']['search'].'" onfocus="if(\''.$l10n['global']['search'].'\'==this.value){this.value=\'\';}" onblur="if(\'\'==this.value){this.value=\''.$l10n['global']['search'].'\';}"></div>';
	$replace['menu'].=$random_ads;
	$replace['menu'].='<hr><div class="additional_links">
<a role="link" href="https://goldvoice.club/about/">'.$l10n['footer']['about'].'</a>
<a role="link" href="https://t.me/goldvoice" target="_blank">'.$l10n['footer']['telegram'].'</a>
<a role="link" class="change_languange">'.$l10n['footer']['select_language'].'</a>
<div class="select_languange"><a role="link" href="?change_l10n=ru">Русский</a><a role="link" href="?change_l10n=en">English</a></div>
'.($admin?'<div>Data delay: '.$last_block_time_offset.'</div>':'').'
</div>';
}
else{
	//$replace['menu'].='<hr>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/"><i class="fa fa-fw fa-home'.('/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['home'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/popular/"><i class="fa fa-fw fa-fire'.('/popular/'==$path?' current':'').'" aria-hidden="true"></i> '.$l10n['menu']['popular'].'</a>';
	$replace['menu'].='<a role="link" class="button button-line button-left" href="/vox-populi/"><i class="fa fa-fw fa-users'.('/vox-populi/'==$path?' current':'').'" aria-hidden="true"></i> Vox Populi</a>';
	$replace['menu'].=$random_ads;
	$replace['menu'].='<div class="menu-message">'.$l10n['menu']['need_auth'].'</div>';
	$replace['menu'].='<hr><div class="additional_links">
<a role="link" href="https://goldvoice.club/about/">'.$l10n['footer']['about'].'</a>
<a role="link" href="https://t.me/goldvoice" target="_blank">'.$l10n['footer']['telegram'].'</a>
<a role="link" class="change_languange">'.$l10n['footer']['select_language'].'</a>
<div class="select_languange"><a role="link" href="?change_l10n=ru">Русский</a><a role="link" href="?change_l10n=en">English</a></div>
</div>';
}
$replace['menu'].='</div></nav>';
if('about'==$path_array[1]){
	$replace['menu_collapsed']=true;
	//$replace['menu']='';
}
if('login'==$path_array[1]){
	$replace['menu_collapsed']=true;
	//$replace['menu']='';
}
if('witnesses'==$path_array[1]){
	$replace['menu_collapsed']=true;
	//$replace['menu']='';
}
if('wallet'==$path_array[1]){
	$replace['menu_collapsed']=true;
	//$replace['menu']='';
}
/*
<a class="button button-line button-left" href=""><i class="fa fa-fw fa-users" aria-hidden="true"></i> Сообщества</a>
<a class="button button-line button-left" href=""><i class="fa fa-fw fa-music" aria-hidden="true"></i> Музыка</a>
<a class="button button-line button-left" href="/settings/"><i class="fa fa-fw fa-cogs" aria-hidden="true"></i> Настройки</a>
*/

$page_type='page-max-wrapper';
if($replace['menu']){
	if(!$replace['menu_collapsed']){
		$page_type='page-wrapper';
	}
	else{
		$replace['menu']=str_replace('<div class="menu">','<div class="menu collapsed">',$replace['menu']);
	}
}
if($replace['page-part-1']){
	$page_type='page-full-wrapper';
}

if($admin){
	if(1==$_GET['debug']){
		print_r($db->history());
	}
}

$content=ob_get_contents();


if($last_block_time_offset>80){
	$content='<div style="padding:15px;color:red;font-size:12px;text-align:center;">Server sync... Block time in database: '.$last_block_time.', server time: '.time().', offset: '.(time()-$last_block_time).'</div>'.$content;
}

$replace['page-part-2'].='
<div class="page-type '.$page_type.$page_subtype.'"'.('login'==$path_array[1]?' id="modal-login"':'').'>
	'.($dynamic_page_start?'':'<div class="page">').'
	'.$content.'
	'.($dynamic_page_end?'':'</div>').'
</div>
';

if($replace['menu']){
	$replace['page'].=$replace['menu'];
}
if($replace['page-part-1']){
	$replace['page'].=$replace['page-part-1'];
}
if($replace['page-part-2']){
	$replace['page'].=$replace['page-part-2'];
}
}
ob_end_clean();