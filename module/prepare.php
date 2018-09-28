<?php
if($_GET['change_l10n']){
	$preferred_lang=$_GET['change_l10n'];
	if(1==$db->table_count('languages',"WHERE `code2`='".$db->prepare($preferred_lang)."' AND `localization`=1")){
		$lang=$preferred_lang;
		@setcookie('l10n',$preferred_lang,31536000+time(),'/','goldvoice.club');
	}
	header('location:'.$path);
	exit;
}
$default_lang='ru';
$default_lang_id=1;
$lang=$default_lang;
if(isset($_COOKIE['l10n'])){
	$user_lang=$db->prepare($_COOKIE['l10n']);
	if(1==$db->table_count('languages',"WHERE `code2`='".$db->prepare($user_lang)."' AND `localization`=1")){
		$lang=$user_lang;
	}
}
else{
	$preferred_lang='ru';
	$preferred_langs_arr=['ru-RU'=>'ru','ru'=>'ru','en-US'=>'en','en'=>'en'];
	if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){
		$lang_max=0.0;
		$user_langs=explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
		foreach($user_langs as $user_lang){
			$user_lang=explode(';',$user_lang);
			$q=isset($user_lang[1])?(float)$user_lang[1]:1.0;
			if($q>$lang_max){
				$lang_max=$q;
				$preferred_lang=$user_lang[0];
			}
		}
		$preferred_lang=trim($preferred_lang);
		if($preferred_langs_arr[$preferred_lang]){
			$preferred_lang=$preferred_langs_arr[$preferred_lang];
		}
	}
	if(1==$db->table_count('languages',"WHERE `code2`='".$db->prepare($preferred_lang)."' AND `localization`=1")){
		$lang=$preferred_lang;
		@setcookie('l10n',$preferred_lang,31536000+time(),'/','goldvoice.club');
	}
}

$lang_arr=$db->sql_row("SELECT * FROM `languages` WHERE `code2`='".$db->prepare($lang)."' AND `localization`=1");
$l10n=array();//localization
$q=$db->sql("SELECT * FROM `localization` WHERE `lang`='".$lang_arr['id']."'");
while($m=$db->row($q)){
	$l10n[$m['cat']][$m['name']]=$m['value'];
}
$q=$db->sql("SELECT * FROM `localization` WHERE `lang`='".$default_lang_id."'");
while($m=$db->row($q)){
	if(!$l10n[$m['cat']][$m['name']]){
		$l10n[$m['cat']][$m['name']]=$m['value'];
	}
}
$replace['script_change_time']=$script_change_time;
$replace['css_change_time']=$css_change_time;
$replace['title']='GoldVoice.club';
$replace['keywords']='';
$replace['description']=$l10n['global']['head_description'];
$replace['counters']='';
$replace['head_addon']='';
$replace['page-cover']='';
$replace['page-before']='';
$replace['menu']='';
$replace['page']='';
$replace['page-part-1']='';
$replace['page-part-2']='';
$replace['page-profile']='';
$replace['user_block']='<a href="/login/" class="menu-login right-button">'.$l10n['menu']['login'].'</a><a href="/registration/" target="_blank" class="right-button">'.$l10n['menu']['registration'].'</a>';
$image_format_arr=array('1'=>'200x114','2'=>'200x114','3'=>'200x200','4'=>'200x200','5'=>'518x290','6'=>'518x290');
$currencies_arr=array('GOLOS'=>1,'GBG'=>2);
$currencies_arr2=array(1=>'GOLOS',2=>'GBG');

$admin=false;
$auth=false;
$dynamic_page_start=false;
$dynamic_page_end=false;

function redis_add_ulist($name,$id){//unique list in set
	global $redis;
	if(!$redis->sismember($name.':ulist',$id)){
		$redis->sadd($name.':ulist',$id);
	}
}
function redis_get_ulist($name){
	global $redis;
	return $redis->spop($name.':ulist');
}
function redis_add_feed($user,$post){
	global $redis;
	$redis->zadd('feed:'.$user,(int)$post,(int)$post);
	$user_login=get_user_login($user);
	$user_action_time=$redis->zscore('users_action_time',$user_login);
	if(0!=$user_action_time){
		$offset=time()-$user_action_time;
		/*
		2592000 = 30 days for 10 000 count
		*/
		$count_limit=10000 - ceil($offset/260);
		if($count_limit<10){
			$count_limit=10;
		}
		if($count_limit>5000){
			$count_limit=5000;
		}
		$offset_id=$redis->zrevrangebyscore('feed:'.$user,'+inf','-inf',array('limit'=>array($count_limit,'1')));
		if($offset_id){
			$redis->zremrangebyscore('feed:'.$user,'-inf','('.$offset_id);
		}
	}
	$redis->expire('feed:'.$user,2592000);
	return false;
	//$redis->lpush('feed:'.$user,$post);
}
function redis_read_feed($user,$post=0,$count=100){
	global $redis;
	if(0==$post){
		return $redis->zrevrangebyscore('feed:'.$user,'+inf','-inf',array('limit'=>array('0',$count)));
	}
	return $redis->zrevrangebyscore('feed:'.$user,'('.$post,'-inf',array('limit'=>array('0',$count)));
}
function redis_unread_feed($user,$post=0){
	global $redis;
	if(0==$post){
		return $redis->zcount('feed:'.$user,'-inf','+inf');
	}
	else{
		return $redis->zcount('feed:'.$user,'('.$post,'+inf');
	}
}
function redis_add_queue($queue_id,$data){
	global $redis;
	$redis->lPush($queue_id.':queue',serialize($data));
}
function redis_get_queue($queue_id){
	global $redis;
	return unserialize($redis->lPop($queue_id.':queue'));
}
function redis_user_online($login){
	global $redis;
	$action_time=$redis->zscore('users_action_time',$login);
	if($action_time>(time()-300)){
		return true;
	}
	else{
		return false;
	}
}
function preview_post($m,$look_user_id=0,$status=-1,$domain=''){
	global $l10n,$db,$redis,$auth,$session_arr,$image_format_arr;
	$ret='';
	$status_str='`posts`.`status`!=1';
	if(-1!=$status){
		$status_str='`posts`.`status`='.(int)$status;
	}
	if(is_int($m)){
		$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$m."' AND ".$status_str);
		$m['author_login']=$redis->get('user_login:'.$m['author']);
		$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
		$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
	}
	$reblog=false;
	if(0!=$m['parent_post']){//reblog
		$reblog=true;
		$reblog_time=$m['time'];
		$reblog_user=$m['author_login'];
		$reblog_comment=$db->select_one('posts_reblog_comment','comment',"WHERE `post`='".$m['id']."'");
		$reblog_id=$m['id'];
		$m=$db->sql_row("SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$m['parent_post']."' AND ".$status_str);
		$m['author_login']=$redis->get('user_login:'.$m['author']);
		$m['author_name']=$redis->hget('users:'.$m['author_login'],'name');
		$m['author_avatar']=$redis->hget('users:'.$m['author_login'],'avatar');
	}
	if(($m['id'])&&($m['post_title'])){
		$m['post_title']=str_replace('\\','\\\\',$m['post_title']);
		$m['post_body']=str_replace('\\','\\\\',$m['post_body']);
		$payout_decline=false;
		$payout_inpower=false;
		if(0==$m['percent_steem_dollars']){
			$payout_inpower=true;
		}
		if(0==$m['max_accepted_payout']){
			if(10000==$m['percent_steem_dollars']){
				$payout_decline=true;
			}
		}
		$vote=false;
		$flag=false;
		$vote_weight=0;
		$vote_time=0;
		if($auth){
			$user_vote=$db->sql_row("SELECT `time`,`weight` FROM `posts_votes` WHERE `post`='".$m['id']."' AND `user`='".$session_arr['user']."'");
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
		$votes_count=$m['upvotes'];
		$flags_count=$m['downvotes'];
		//$votes_count=$db->table_count('posts_votes',"WHERE `post`='".$m['id']."' AND `weight`>0");
		//$flags_count=$db->table_count('posts_votes',"WHERE `post`='".$m['id']."' AND `weight`<0");
		$ret.=PHP_EOL.PHP_EOL.'<aside><div class="page post-card'.($m['adult']?' post-adult':'').' clearfix" data-id="'.$m['id'].'"'.($reblog?' data-reblog-id="'.$reblog_id.'" data-reblog-user="'.$reblog_user.'"':'').' data-author="'.$m['author_login'].'" data-permlink="'.$m['permlink'].'" data-reblog="'.($reblog?'1':'0').'" data-allow-votes="'.$m['allow_votes'].'" data-allow-replies="'.$m['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'"  itemprop="liveBlogUpdate" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$ret.='<span class="schema" itemprop="mainEntityOfPage" itemscope="" itemType="https://schema.org/WebPage" itemid="https://goldvoice.club/@'.$m['author_login'].'/'.$m['permlink'].'/"></span>';
		if($reblog){
			$ret.='<div class="post-reblog-info"><div class="post-reblog-date" data-timestamp="'.$reblog_time.'">'.date('d.m.Y H:i',$reblog_time).'</div><i class="fa fa-fw fa-retweet" aria-hidden="true"></i> <span class="l10n" data-cat="global" data-name="repost_by"></span> <span class="repost-userlist"></span> @'.$reblog_user.''.(''!=$reblog_comment?'<div class="post-reblog-comment">'.htmlspecialchars($reblog_comment).'</div>':'').'</div><hr>';
		}
		$ret.='<a href="'.$domain.'/@'.$m['author_login'].'/'.$m['permlink'].'/" class="internal-link block">';
		$ret.='<div class="post-preview-title" itemprop="headline">'.$m['post_title'].'</div><hr>';
		if($m['adult']){
			$ret.='<div class="post-adult-title l10n" data-cat="global" data-name="adult_title"></div>';
			$ret.='<div class="post-adult-addon l10n" data-cat="global" data-name="adult_descr"></div>';
		}
		//image_format: 1 = 200x114 left, 2 = 200x114 right, 3 = 200x200 left, 4 = 200x200 right, 5 = 518x290 top, 6 = 518x290 bottom
		$image_format=0;
		if($m['post_image']){
			$image_format=1;
			if($m['image_format']){
				$image_format=(int)$m['image_format'];
			}
			if($image_format>6){
				$image_format=1;
			}
		}

		$preview_text=text_to_view($m['post_body']);

		$preview_text=str_replace('<br>',"\n",$preview_text);
		$preview_text=str_replace('<hr>',"\n",$preview_text);
		$preview_text=htmlspecialchars_decode($preview_text);
		$preview_text=str_replace('&nbsp;',' ',$preview_text);
		$preview_text=str_replace('<br />',"\n",$preview_text);
		$preview_text=str_replace('<br/>',"\n",$preview_text);
		$preview_text=str_replace('<p>',"\n",$preview_text);
		$preview_text=str_replace('</p>',"\n",$preview_text);
		$preview_text=mb_ereg_replace("\r",'',$preview_text);
		$preview_text=mb_ereg_replace("\t",'',$preview_text);
		$preview_text=str_replace('  ',' ',$preview_text);
		$preview_text=mb_ereg_replace("\n\n","\n",$preview_text);
		$preview_text=mb_ereg_replace("\n\n","\n",$preview_text);
		$preview_text=strip_tags($preview_text);
		$preview_text=trim($preview_text,"\r\n\t ");
		$preview_text_arr=explode("\n",$preview_text);
		$preview_text_final=$preview_text_arr[0];
		if(mb_strlen($preview_text_final)<250){
			if($preview_text_arr[1]){
				$preview_text_final.='</p><p>'.(mb_strlen($preview_text_arr[1])>250?mb_substr($preview_text_arr[1],0,255,'utf-8').'&hellip;':$preview_text_arr[1]);
			}
		}
		if(mb_strlen($preview_text_final)<250){
			if($preview_text_arr[2]){
				$preview_text_final.='</p><p>'.(mb_strlen($preview_text_arr[2])>250?mb_substr($preview_text_arr[2],0,255,'utf-8').'&hellip;':$preview_text_arr[2]);
			}
		}
		else{
			$preview_text_final=mb_substr($preview_text_final,0,255,'utf-8').'&hellip;';
		}
		if($preview_text_final){
			$preview_text_final='<p>'.$preview_text_final.'</p>';
			if($image_format){
				if(6!=$image_format)
					$ret.='<div class="cover-image-'.$image_format.'"><img src="https://i.goldvoice.club/'.$image_format_arr[$image_format].'/'.htmlspecialchars($m['post_image']).'" alt="" itemprop="image"></div>';
			}
			$ret.='<div class="post-preview-text'.($image_format?' cover-image-exist-'.$image_format:'').'" itemprop="articleBody">';
			$ret.=$preview_text_final;
			$ret.='</div>';
			if(6==$image_format){
				$ret.='<div class="cover-image-'.$image_format.'"><img src="https://i.goldvoice.club/'.$image_format_arr[$image_format].'/'.htmlspecialchars($m['post_image']).'" alt="" itemprop="image"></div>';
			}
		}
		else{
			if($m['post_image']){
				$ret.='<div class="cover-image-full" itemprop="articleBody" content="Embed"><img src="https://i.goldvoice.club/768x512/'.htmlspecialchars($m['post_image']).'" alt="" itemprop="image"></div>';
			}
		}
		$ret.='</a>';
		$tags_list='';
		$q=$db->sql("SELECT `pt`.*, `tags`.`en`, `tags`.`ru` FROM `posts_tags` as `pt` RIGHT JOIN `tags` ON `tags`.`id`=`pt`.`tag` WHERE `post`=".$m['id']." LIMIT 5");
		while($m2=$db->row($q)){
			if($m2['en']){
				$view_tag=(''==$m2['ru']?$m2['en']:$m2['ru']);
				$tags_list.='<a href="/tags/'.htmlspecialchars($m2['en']).'/" class="tag" rel="'.htmlspecialchars($m2['en']).'" itemprop="keywords" content="'.htmlspecialchars($view_tag).'">'.htmlspecialchars($view_tag).'</a>';
			}
		}
		if(''!=$tags_list){
			$ret.='<div class="post-tags">'.$tags_list.'</div>';
		}
		if($image_format || $preview_text_final){
			$ret.='<hr>';
		}
		if($look_user_id!=$m['author']){
			$ret.='<div class="post-line">';
			if(''==$m['author_avatar']){
				$m['author_avatar']='https://goldvoice.club/images/noava50.png';
			}
			if(''==$m['author_name']){
				$m['author_name']='@'.$m['author_login'];
			}
			$ret.='<div class="post-author"><a href="/@'.$m['author_login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$m['author_avatar'].'" alt=""></a><a href="/@'.$m['author_login'].'/" data-user-login="'.$m['author_login'].'">'.$m['author_name'].'</a></div>';
			$ret.='<div class="post-datestamp"><span class="post-date" data-timestamp="'.$m['time'].'" itemprop="datePublished dateModified" content="'.date(DATE_ISO8601,$m['time']).'">'.date('d.m.Y H:i',$m['time']).'</span><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i></div>';
			$ret.='</div>';
		}
		if($look_user_id==$m['author']){
			$ret.='<div class="post-info wide">';
			$ret.='<div class="post-datestamp"><span class="post-date" data-timestamp="'.$m['time'].'" itemprop="datePublished dateModified" content="'.date(DATE_ISO8601,$m['time']).'">'.date('d.m.Y H:i',$m['time']).'</span><i class="fa fa-fw fa-clock-o" aria-hidden="true"></i></div>';
		}
		else{
			$ret.='<div class="post-info">';
		}
		$ret.='<div class="post-comments"><i class="fa fa-fw fa-comments-o" aria-hidden="true"></i> '.$m['comments'].'</div>';
		$ret.='<div class="post-payments'.((float)$m['payout']?' payout':'').'" data-cashout-time="'.$m['cashout_time'].'" data-payout="'.$m['payout'].'" data-curator-payout="'.$m['curator_payout'].'" data-pending-payout="'.$m['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
		if($auth){
			$ret.='<div class="post-flags flag-action"><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
		}
		else{
			$ret.='<div class="post-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
		}
		if($auth){
			$ret.='<div class="post-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
		}
		else{
			$ret.='<div class="post-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
		}
		$ret.='<div class="schema" itemprop="author" itemscope="" itemtype="http://schema.org/Person"><img src="https://i.goldvoice.club/32x32a/'.$m['author_avatar'].'" alt="" itemprop="image"><span content="'.$domain.'/@'.$m['author_login'].'/" itemprop="url"></span><span itemprop="name">'.$m['author_name'].'</span></div>';
		$ret.='<div class="schema" itemprop="publisher" itemscope="" itemtype="http://schema.org/Organization"><span content="https://goldvoice.club/" itemprop="url"></span><span itemprop="logo" itemscope="" itemtype="https://schema.org/ImageObject"><img src="/favicon/favicon-96x96.png" itemprop="url image"></span><span itemprop="name">GoldVoice club</span></div>';
		$ret.='</div>';
		$ret.='</div></aside>';
	}
	return $ret;
}
function repair_html_tags($html){
	//$tidy=new tidy();
	//$html=$tidy->repairString($html);
	/*
	libxml_use_internal_errors(true);
	$doc = new DOMDocument('1.0','UTF-8');
	$doc->encoding="UTF-8";
	@$doc->loadHTML('<meta charset="utf-8">'.$html);
	return $doc->saveHTML();*/

	$tags_counter=array();
	preg_match_all('~<(.*)>~iUs',$html,$matches);
	foreach($matches[1] as $k=>$v){
		$v=trim($v," \r\n\t");
		$ignore=false;
		if('/'==mb_substr($v,mb_strlen($v)-1)){
			$ignore=true;
		}
		if(!$ignore){
			$v_arr=explode(' ',$v);
			if('/'==$v_arr[0][0]){
				$tag_buf=trim($v_arr[0]," \r\n\t/");
				if(!isset($tags_counter[$tag_buf])){
					$tags_counter[$tag_buf]=0;
				}
				$tags_counter[$tag_buf]--;
			}
			else{
				$tag_buf=trim($v_arr[0]," \r\n\t");
				if(!isset($tags_counter[$tag_buf])){
					$tags_counter[$tag_buf]=0;
				}
				$tags_counter[$tag_buf]++;
			}
		}
	}
	unset($tags_counter['img']);
	unset($tags_counter['br']);
	unset($tags_counter['hr']);
	unset($tags_counter['input']);
	foreach($tags_counter as $k=>$v){
		$v=(int)$v;
		if(0>$v){
			for($i=$v;$i<0;$i++){
				$html='<'.$k.'>'.$html;
			}
		}
		if(0<$v){
			for($i=$v;$i>0;$i--){
				$html.='</'.$k.'>';
			}
		}
	}
	/*
	$open_tag=substr_count($html,'<');
	$close_tag=substr_count($html,'>');
	$tag_repair=(int)$close_tag-(int)$open_tag;
	if(0>$tag_repair){
		for($i=$tag_repair;$i<0;$i++){
			$html='<'.$html;
		}
	}
	if(0<$tag_repair){
		for($i=$tag_repair;$i>0;$i--){
			$html.='>';
		}
	}*/
	return $html;
}
function clear_html_tag($html,$tag){
	preg_match_all('~<'.$tag.'(.*)>~iUs',$html,$matches);
	foreach($matches[0] as $k=>$v){
		$html=str_replace($v,'',$html);
	}
	return $html;
}
function clear_html_tags($text){
	$allowed_attr_arr=array('href','target','src','alt','width','style','id','class','colspan','rowspan');
	$allowed_style_arr=array('text-align','float','text-indent','clear','margin-left','margin-right','margin-top','padding-left','margin-bottom','display','list-style-type','text-decoration','color','font-style','font-size');
	$allowed_class_arr=array('spoiler','pull-left','pull-right','language-markup','language-javascript','language-css','language-php','language-ruby','language-python','language-java','language-c','language-csharp','language-cpp','text-justify');
	$denied_tags=array('script','style');
	preg_match_all('~<(.[^>]*)>~iUs',$text,$matches);
	foreach($matches[1] as $match_k=>$match){
		$full_match=$matches[0][$match_k];
		$closing=false;
		$tag_name=$match;
		if(false!==strpos($match,' ')){
			$tag_name=substr($match,0,strpos($match,' '));
		}
		if('/'==$tag_name[0]){
			$closing=true;
			$tag_name=substr($tag_name,1);
		}
		if(in_array($tag_name,$denied_tags)){
			$full_match='';
		}
		else{
			preg_match_all('~(.[^= ]*)="(.*)"~iUs',$match,$attr_arr);
			foreach($attr_arr[1] as $attr_k=>$attr){
				$attr=trim($attr);
				if(!in_array($attr,$allowed_attr_arr)){
					$change=true;
					if('iframe'==$tag_name){
						if('height'==$attr){
							$change=false;
						}
						if('frameborder'==$attr){
							$change=false;
						}
						if('allowfullscreen'==$attr){
							$change=false;
						}
					}
					if($change){
						$full_match=str_replace($attr_arr[0][$attr_k],'',$full_match);
					}
				}
				if('style'==$attr){
					$full_styles=$attr_arr[2][$attr_k];
					$styles_arr=explode(';',$full_styles);
					foreach($styles_arr as $style_k=>$style){
						if($style){
							$style_arr=explode(':',$style);
							$style_arr[0]=trim($style_arr[0]);
							$style_arr[1]=trim($style_arr[1]);
							if(!in_array($style_arr[0],$allowed_style_arr)){
								unset($styles_arr[$style_k]);
							}
						}
					}
					$full_styles=implode(';',$styles_arr);
					$full_match=str_replace($attr_arr[2][$attr_k],$full_styles,$full_match);
				}
				if('class'==$attr){
					$full_classes=$attr_arr[2][$attr_k];
					$classes_arr=explode(' ',$full_classes);
					foreach($classes_arr as $class_k=>$class){
						if($class){
							$class=trim($class);
							if(!in_array($class,$allowed_class_arr)){
								unset($classes_arr[$class_k]);
							}
						}
					}
					$full_classes=implode(' ',$classes_arr);
					if($attr_arr[2][$attr_k]!=$full_classes){
						$full_match=str_replace($attr_arr[2][$attr_k],$full_classes,$full_match);
					}
				}
			}
			preg_match_all('~(.[^= ]*)=""~iUs',$full_match,$attr_arr);
			foreach($attr_arr[0] as $free_attr){
				$full_match=str_replace($free_attr,'',$full_match);
			}
		}
		$text=str_replace($matches[0][$match_k],$full_match,$text);
	}
	return $text;
}
function text_to_view($text,$set_markdown=false,$remove_images=false){
	global $parsedownextra;

	$replace_arr=array();
	$replace_num=1;
	//$text=str_replace(' class="text-justify"',' style="text-align:justify"',$text);
	//$text=str_replace(' class="pull-left"',' style="float:left"',$text);
	//$text=str_replace(' class="pull-right"',' style="float:right"',$text);
	$text=clear_html_tags($text);
	$markdown=true;
	if(false!==strpos($text,'<html>')){
		$markdown=false;
	}
	elseif(false!==strpos($text,'</p>')){
		$markdown=false;
	}
	elseif(false!==strpos($text,'</li>')){
		$markdown=false;
	}
	elseif(false!==strpos($text,'</code>')){
		$markdown=false;
	}
	if($set_markdown){
		$markdown=true;
	}
	$text=str_replace('https://golos.io/','https://goldvoice.club/',$text);
	$text=str_replace('https://www.golos.io/','https://goldvoice.club/',$text);
	$text=str_replace('https://golos.blog/','https://goldvoice.club/',$text);
	$text=str_replace('https://www.golos.blog/','https://goldvoice.club/',$text);


	/* remove steem/golos images gates */
	$text=preg_replace('~https:\/\/imgp\.golos\.io\/([x0-9]*)\/~is','',$text);
	$text=preg_replace('~https:\/\/steemitimages\.com\/([x0-9]*)\/~is','',$text);
	/* convert tags to replacer arr */
	preg_match_all('~<img (.*)>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		preg_match('~src=(\"|\')(.*)(\"|\')~iUs',$matches[1][$k],$img_arr);
		if($img_arr[2]){
			//$new_img='<img src="https://imgp.golos.io/0x0/'.$img_arr[2].'">';
			$new_img_src='src="https://i.goldvoice.club/0x0/'.$img_arr[2].'"';
			if(preg_match('~^https://goldvoice\.club/~iUs',$img_arr[2])){
				$new_img_src='src="'.$img_arr[2].'"';
			}
			if(preg_match('~^https://i.goldvoice\.club/~iUs',$img_arr[2])){
				$new_img_src='src="'.$img_arr[2].'"';
			}
			/*
			if(preg_match('~^https://~iUs',$img_arr[2])){
				$new_img_src='src="'.$img_arr[2].'"';
			}*/
			if(preg_match('~^https://imgp\.golos\.io/~iUs',$img_arr[2])){
				$new_img_src='src="'.$img_arr[2].'"';
			}
			if(preg_match('~^https://images\.golos\.io/~iUs',$img_arr[2])){
				$new_img_src='src="'.$img_arr[2].'"';
			}
			$new_img=str_replace($img_arr[0],$new_img_src,$match);
			$replace_arr[$replace_num]=$new_img;
			$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}


	preg_match_all('~<a(.*)>(.*)</a>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	/* convert &#0000; to links */
	preg_match_all('~&#([0-9a-z]*);~ius',$text,$matches);
	foreach($matches[0] as $k=>$match){
		if($matches[1][$k]){
			$replace_arr[$replace_num]=$matches[0][$k];
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}
	/* convert :#hex; to replacer */
	preg_match_all('~(:|: )#([0-9abcdef]*);~ius',$text,$matches);
	foreach($matches[0] as $k=>$match){
		if($matches[2][$k]){
			$replace_arr[$replace_num]='#'.$matches[2][$k];
			$text=str_replace($match,':{replacerQarrQ'.$replace_num.'};',$text);
			$replace_num++;
		}
	}
	/* convert :#hex" to replacer */
	preg_match_all('~(:|: )#([0-9abcdef]*)"~ius',$text,$matches);
	foreach($matches[0] as $k=>$match){
		if($matches[2][$k]){
			$replace_arr[$replace_num]='#'.$matches[2][$k].'"';
			$text=str_replace($match,':{replacerQarrQ'.$replace_num.'};',$text);
			$replace_num++;
		}
	}

	if($markdown){
		$text=str_replace('<p><center>','<center>',$text);
		$text=str_replace('</center><p>','</center>',$text);
		$text=str_replace("</td>\n",'</td>',$text);
		$text=str_replace("</th>\n",'</th>',$text);
		$text=preg_replace("~</th>([ ]*)<th>~iUs",'</th><th>',$text);
		$text=preg_replace("~</td>([ ]*)<td>~iUs",'</td><td>',$text);
		$test_text=$parsedownextra->text($text);
		if(strlen($test_text)/strlen($text)>0.5){
			$text=$test_text;
		}
		preg_match_all('~\<p\>(.*)\<\/p\>~iUs',$text,$matches);
		foreach($matches[1] as $k=>$v){
			//$text=str_replace($matches[1][$k],str_replace("\n\n","</p><p>",$matches[1][$k]),$text);
			$text=str_replace($matches[1][$k],str_replace("\n","<br>",$matches[1][$k]),$text);
		}
		$text=str_replace("<p>\n",'<p>',$text);

		$text=str_replace("<li>\n",'<li>',$text);
		$text=str_replace("\n</li>",'</li>',$text);
		preg_match_all('~\<li\>\<p\>(.*)\<\/p\>\<\/li\>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($match,'<li>'.$matches[1][$k].'</li>',$text);
		}
		/* additional converts im markdown */
		preg_match_all('~\!\[\]\((.[^\n\[\]]*)\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$buf_html='<img src="'.htmlspecialchars($matches[1][$k]).'" alt="">';
			$replace_arr[$replace_num]=$buf_html;
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\!\[(.[^\n\[\]]*)\]\((.[^\n\[\]]*)\)~is',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$buf_html='<img src="'.htmlspecialchars($matches[2][$k]).'" alt="'.htmlspecialchars($matches[1][$k]).'">';
			$replace_arr[$replace_num]=$buf_html;
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\[(.[^\n\[\]\(\)]*)\]\((.[^\n\[\]\(\)]*)\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$buf_html='<a href="'.htmlspecialchars($matches[2][$k]).'" target="_blank">'.$matches[1][$k].'</a>';
			$replace_arr[$replace_num]=$buf_html;
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\[([.]*)\]\(\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],$matches[1][$k],$text);
		}
		preg_match_all('~\[\]\(([.]*)\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],$matches[1][$k],$text);
		}
		preg_match_all('~\[\]\(\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'',$text);
		}
		preg_match_all('~\!\[\]\(\)~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'',$text);
		}
		/* change auto links from markdown to images */
		preg_match_all('~<a href="(.*)">(.*)</a>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			if($matches[1][$k]==$matches[2][$k]){
				if(preg_match('~\.(jpg|jpeg|gif|png|psd|tiff|webp)$~is',$matches[1][$k],$link_arr)){
					$image_text='<img src="https://i.goldvoice.club/0x0/'.$matches[1][$k].'" class="convert-link-image" alt="">';
					if(preg_match('~^https://~iUs',$matches[1][$k])){
						$image_text='<img src="https://i.goldvoice.club/0x0/'.$matches[1][$k].'" class="convert-link-image" alt="">';
					}
					$replace_arr[$replace_num]=$image_text;
					$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
					$replace_num++;
				}
				else{//просто ссылка, но ее сделал markdown, если там ютуб то мы ее не обработаем, поэтому мы ее оборачиваем обратно в просто текст, ниже она обработается уже по html правилам
					/*
					$link_text='<a href="'.$matches[1][$k].'" class="markdown-convert-link">'.$matches[1][$k].'</a>';
					$replace_arr[$replace_num]=$link_text;
					$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
					$replace_num++;*/
					$text=str_replace($matches[0][$k],$matches[1][$k],$text);
				}
			}
		}
		/* remove steem/golos images gates */
		//https://steemitimages.com/0x0/https://goldvoice.club/uploads/goldvoice/calendar1.png
		$text=preg_replace('~https:\/\/imgp\.golos\.io\/([tx0-9]*)\/~is','',$text);
		$text=preg_replace('~https:\/\/steemitimages\.com\/([tx0-9]*)\/~is','',$text);
		/* convert tags to replacer arr */
		preg_match_all('~<img (.*)>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			preg_match('~src=(\"|\')(.*)(\"|\')~iUs',$matches[1][$k],$img_arr);
			if($img_arr[2]){
				//$new_img='<img src="https://imgp.golos.io/0x0/'.$img_arr[2].'">';
				$new_img_src='src="https://i.goldvoice.club/0x0/'.$img_arr[2].'"';
				if(preg_match('~^https://goldvoice\.club/~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}
				if(preg_match('~^https://i.goldvoice\.club/~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}
				/*
				if(preg_match('~^https://~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}*/
				if(preg_match('~^https://imgp\.golos\.io/~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}
				if(preg_match('~^https://images\.golos\.io/~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}
				$new_img=str_replace($img_arr[0],$new_img_src,$match);
				$replace_arr[$replace_num]=$new_img;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
			}
		}
		preg_match_all('~<a(.*)>(.*)</a>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=$matches[0][$k];
			$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~<iframe (.*)>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=$matches[0][$k];
			$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}

	//~~~ embed:FXvxiCDl7p0 youtube ~~~
	preg_match_all('|\~\~\~ embed\:(.*) \~\~\~|iUs',$text,$matches);
	foreach($matches[1] as $k=>$match){
		if($match){
			if(false!==strpos($match,'youtube')){
				preg_match('~([a-zA-Z0-9_\-]*) youtube~is',$match,$link_arr);
				$youtube_code=$link_arr[1];
				$youtube_image='https://i.ytimg.com/vi/'.$youtube_code.'/sddefault.jpg';
				$youtube_text='<div class="youtube_wrapper" data-youtube-code="'.$youtube_code.'"><iframe src="https://www.youtube.com/embed/'.$youtube_code.'" width="768" height="576" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>';
				$replace_arr[$replace_num]=$youtube_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
				unset($youtube_code);
				unset($youtube_image);
				unset($youtube_text);
			}
		}
	}
	preg_match_all('~<code>(.*)</code>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<a (.*)>(.*)</a>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<iframe (.*)>(.*)</iframe>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	/* convert links to youtube/images */
	preg_match_all('~(https|http)://([0-9a-zA-Z:;_\#\-+,!\@=&\%\.\/\?]*)~is',$text,$matches);
	usort($matches[0],'sort_by_value_length');
	foreach($matches[0] as $k=>$match){
		if(false!==strpos($matches[0][$k],'//coub.com/view/')){
			preg_match('~/view/([a-zA-Z0-9_\-]*)~is',$matches[0][$k],$link_arr);
			$coub_code=$link_arr[1];
			$coub_text='<div class="coub_wrapper" data-coub-code="'.$youtube_code.'"><iframe src="//coub.com/embed/'.$coub_code.'?muted=false&autostart=false&originalSize=false&startWithHD=false" allowfullscreen="true" frameborder="0" width="480" height="270"></iframe></div>';
			$replace_arr[$replace_num]=$coub_text;
			$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
			unset($coub_code);
			unset($coub_text);
		}
		elseif(false!==strpos($matches[0][$k],'youtube.com/embed/')){
			preg_match('~/embed/([a-zA-Z0-9_\-]*)~is',$matches[0][$k],$link_arr);
			$youtube_code=$link_arr[1];
			$youtube_image='https://i.ytimg.com/vi/'.$youtube_code.'/sddefault.jpg';
			$youtube_text='<div class="youtube_wrapper" data-youtube-code="'.$youtube_code.'"><iframe src="https://www.youtube.com/embed/'.$youtube_code.'" width="768" height="576" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>';
			$replace_arr[$replace_num]=$youtube_text;
			$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
			unset($youtube_code);
			unset($youtube_image);
			unset($youtube_text);
		}
		elseif(false!==strpos($matches[0][$k],'youtube.com')){
			preg_match('~v=([a-zA-Z0-9_\-]*)~is',$matches[0][$k],$link_arr);
			$youtube_code=$link_arr[1];
			if(''!=$youtube_code){
				$youtube_image='https://i.ytimg.com/vi/'.$youtube_code.'/sddefault.jpg';
				$youtube_text='<div class="youtube_wrapper" data-youtube-code="'.$youtube_code.'"><iframe src="https://www.youtube.com/embed/'.$youtube_code.'" width="768" height="576" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>';
				$replace_arr[$replace_num]=$youtube_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
				unset($youtube_image);
				unset($youtube_text);
				unset($youtube_code);
			}
			else{
				$link_text='<a href="'.$matches[0][$k].'" class="convert-link youtube">'.$matches[0][$k].'</a>';
				$replace_arr[$replace_num]=$link_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
			}
		}
		elseif(false!==strpos($matches[0][$k],'youtu.be')){//https://youtu.be/NIAjU3Pr7Cg
			preg_match('~\.be\/([a-zA-Z0-9_\-]*)~is',$matches[0][$k],$link_arr);
			$youtube_code=$link_arr[1];
			if(''!=$youtube_code){
				$youtube_image='https://i.ytimg.com/vi/'.$youtube_code.'/sddefault.jpg';
				$youtube_text='<div class="youtube_wrapper" data-youtube-code="'.$youtube_code.'"><iframe src="https://www.youtube.com/embed/'.$youtube_code.'" width="768px" height="576px" frameborder="0" allowfullscreen="allowfullscreen"></iframe></div>';
				$replace_arr[$replace_num]=$youtube_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
				unset($youtube_code);
				unset($youtube_image);
				unset($youtube_text);
			}
		}
		else{
			if(preg_match('~\.(jpg|jpeg|gif|png|psd|tiff|webp)$~is',$matches[0][$k],$link_arr)){
				$image_text='<img src="https://i.goldvoice.club/0x0/'.$matches[0][$k].'" class="convert-link-image" alt="">';
				if(preg_match('~^https://goldvoice\.club/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				if(preg_match('~^https://i.goldvoice\.club/~iUs',$img_arr[2])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				/*
				if(preg_match('~^https://~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}*/
				if(preg_match('~^https://imgp\.golos\.io/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				if(preg_match('~^https://images\.golos\.io/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				$replace_arr[$replace_num]=$image_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
			}
			elseif(preg_match('~\.(jpg|jpeg|gif|png|psd|tiff|webp)\?(.[^\n ]*)$~is',$matches[0][$k],$link_arr)){
				$image_text='<img src="https://i.goldvoice.club/0x0/'.$matches[0][$k].'" class="convert-link-image" alt="">';
				if(preg_match('~^https://goldvoice\.club/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				if(preg_match('~^https://i.goldvoice\.club/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				/*
				if(preg_match('~^https://~iUs',$img_arr[2])){
					$new_img_src='src="'.$img_arr[2].'"';
				}*/
				if(preg_match('~^https://imgp\.golos\.io/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				if(preg_match('~^https://images\.golos\.io/~iUs',$matches[0][$k])){
					$image_text='<img src="'.$matches[0][$k].'" class="convert-link-image" alt="">';
				}
				$replace_arr[$replace_num]=$image_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
			}
			else{
				$link_text='<a href="'.$matches[0][$k].'" class="convert-link">'.$matches[0][$k].'</a>';
				$replace_arr[$replace_num]=$link_text;
				$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
				$replace_num++;
			}
		}
	}

	/* convert #tag to links */
	preg_match_all('~\#([а-яА-ЯёЁa-zA-Z0-9+\.\-\_]*)~ius',$text,$matches);
	usort($matches[0],'sort_by_value_length');
	usort($matches[1],'sort_by_value_length');
	foreach($matches[0] as $k=>$match){
		$match=trim($match,'.');
		$matches[1][$k]=trim($matches[1][$k],'.');
		if($matches[1][$k]){
			$tag_ru=tags_translate(mb_strtolower($matches[1][$k]));
			if($tag_ru!=$matches[1][$k]){
				$tag_ru='ru--'.$tag_ru;
			}
			$replace_arr[$replace_num]='<a href="/tags/'.htmlspecialchars($tag_ru).'/">#'.$matches[1][$k].'</a>';
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}

	if($markdown){
		/* strange markdown golos */
		preg_match_all('~\n###### (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h6>'.$matches[1][$k].'</h6>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\n##### (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h5>'.$matches[1][$k].'</h5>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\n#### (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h4>'.$matches[1][$k].'</h4>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\n### (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h3>'.$matches[1][$k].'</h3>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\n## (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h2>'.$matches[1][$k].'</h2>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~\n# (.*)\n~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$replace_arr[$replace_num]=PHP_EOL.'<h1>'.$matches[1][$k].'</h1>';
			$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
		preg_match_all('~___(.[^\r\n]*)___~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<em><strong>'.$matches[1][$k].'</strong></em>',$text);
		}
		preg_match_all('~\*\*\*(.[^\r\n]*)\*\*\*~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<em><strong>'.$matches[1][$k].'</strong></em>',$text);
		}
		preg_match_all('~__(.[^\r\n]*)__~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<strong>'.$matches[1][$k].'</strong>',$text);
		}
		preg_match_all('~\*\*(.[^\r\n]*)\*\*~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<strong>'.$matches[1][$k].'</strong>',$text);
		}
		preg_match_all('!~~(.[^\r\n]*)~~!iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<del>'.$matches[1][$k].'</del>',$text);
		}
		/*preg_match_all('~_(.[^\r\n]*)_~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<em>'.$matches[1][$k].'</em>',$text);
		}*/
		preg_match_all('~\*(.[^\r\n]*)\*~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<em>'.$matches[1][$k].'</em>',$text);
		}
		preg_match_all('!```(.*)```!iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<pre><code>'.$matches[1][$k].'</code></pre>',$text);
		}
		preg_match_all('!``(.*)``!iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<pre><code>'.$matches[1][$k].'</code></pre>',$text);
		}
		preg_match_all('!`(.[^\r\n]*)`!iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($matches[0][$k],'<code>'.$matches[1][$k].'</code>',$text);
		}

		$text=str_replace(">\n<",'>!NEW_LINE_BR!<',$text);
		$text=str_replace("\n",'<br>',$text);
		$text=str_replace('>!NEW_LINE_BR!<',">\n<",$text);
		$text=str_replace(' <br>','<br>',$text);
		$text=str_replace('<br> ','<br>',$text);
	}
	/* convert mail@domain to links */
	preg_match_all('~([a-z0-9\.\-\_]*)\@([a-z0-9\.\-\_]*)~is',$text,$matches);
	usort($matches[0],'sort_by_value_length');
	usort($matches[1],'sort_by_value_length');
	foreach($matches[0] as $k=>$match){
		if($matches[1][$k]){
			$replace_arr[$replace_num]='<a href="mailto:'.htmlspecialchars($matches[0][$k]).'">'.$matches[0][$k].'</a>';
			$text=str_replace($match,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}
	/* convert @login to links */
	preg_match_all('~\@([a-z0-9\.\-\_]*)~is',$text,$matches);
	usort($matches[0],'sort_by_value_length');
	usort($matches[1],'sort_by_value_length');
	foreach($matches[0] as $k=>$match){
		if($matches[1][$k]){
			$user_login=trim($matches[1][$k]," \r\n\t.");
			$replace_arr[$replace_num]='<a href="/@'.htmlspecialchars($user_login).'/">@'.$user_login.'</a>';
			$text=str_replace('@'.$user_login,'{replacerQarrQ'.$replace_num.'}',$text);
			$replace_num++;
		}
	}

	preg_match_all('~<code>(.*)</code>~iUs',$text,$matches);
	foreach($matches[1] as $k=>$match){
		$match=str_replace('<','&lt;',$match);
		$match=str_replace('>','&gt;',$match);
		$replace_arr[$replace_num]='<code>'.$match.'</code>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<pre(.*)>(.*)</pre>~iUs',$text,$matches);
	foreach($matches[2] as $k=>$match){
		$replace_arr[$replace_num]='<pre'.$matches[1][$k].'>'.$match.'</pre>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<table(.*)>(.*)</table>~iUs',$text,$matches);
	foreach($matches[2] as $k=>$match){
		$match=repair_html_tags($match);
		$replace_arr[$replace_num]='<table'.$matches[1][$k].'>'.$match.'</table>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~<p(.*)>(.*)</p>~iUs',$text,$matches);
	foreach($matches[2] as $k=>$match){
		$match=clear_html_tag($match,'p');
		$match=repair_html_tags($match);
		$text=str_replace($matches[0][$k],'<p'.$matches[1][$k].'>'.$match.'</p>',$text);
	}
	preg_match_all('~<div(.*)>(.*)</div>~iUs',$text,$matches);
	foreach($matches[2] as $k=>$match){
		$match=clear_html_tag($match,'div');
		$match=repair_html_tags($match);
		$replace_arr[$replace_num]='<section'.$matches[1][$k].'>'.$match.'</section>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	$text=str_replace('</div>','</section>',$text);
	$text=str_replace('<div>','<section>',$text);

	foreach($replace_arr as $k=>$v){
		$text=str_replace('{replacerQarrQ'.$k.'}',$v,$text);
	}
	//need next replacement for expand <a><img></a>
	preg_match_all('~\{replacerQarrQ([0-9]*)\}~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace('{replacerQarrQ'.$matches[1][$k].'}',$replace_arr[$matches[1][$k]],$text);
	}
	//need next replacement for expand <a><img></a>
	preg_match_all('~\{replacerQarrQ([0-9]*)\}~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace('{replacerQarrQ'.$matches[1][$k].'}',$replace_arr[$matches[1][$k]],$text);
	}
	preg_match_all('~\{replacerQarrQ([0-9]*)\}~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace('{replacerQarrQ'.$matches[1][$k].'}',$replace_arr[$matches[1][$k]],$text);
	}
	if(false!==strpos($text,'<br><br>')){
		$text_arr=explode('<br><br>',$text);
		$text='<p>'.implode("</p>\n<p>",$text_arr).'</p>';
		//$text=str_replace("</p>\n<p>",'<br>',$text);
	}

	//print '<!-- replaces, after markdown 2: '.$text.' -->';
	//$text=close_html_tags($text);
	/*
	$tagsToClose = getOpennedTags($text);
	$tagsToClose = array_reverse($tagsToClose);
	foreach ($tagsToClose as $tag) {
		$text .= "</$tag>";
	}*/

	/*preg_match_all('~<p(.*)>(.*)</p>~iUs',$text,$matches);
	foreach($matches[2] as $k=>$match){
		$match=clear_html_tag($match,'p');
		$match=repair_html_tags($match);
		$text=str_replace($matches[0][$k],'<p'.$matches[1][$k].'>'.$match.'</p>',$text);
	}*/

	//$text=repair_html_tags($text);
	if($remove_images){
		preg_match_all('~<img (.*)>~iUs',$text,$matches);
		foreach($matches[0] as $k=>$match){
			$text=str_replace($match,htmlspecialchars($match),$text);
		}
	}
	return $text;
}
function comment_to_view($id,$level=false,$hide_post=false){
	global $db,$redis,$l10n,$session_arr,$auth;
	$ret='';
	$comment_q=$db->sql("SELECT `comments`.* FROM `comments` WHERE `comments`.`id`='".(int)$id."' AND `comments`.`status`!=1");
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
		$post_url=$path_array[2];
		$sql="SELECT `posts`.*, `pd`.`title` as `post_title`, `pd`.`body` as `post_body`, `pd`.`json_metadata` as `json_metadata`, `pd`.`image` as `post_image`, `pd`.`image_format` as `image_format`, `pd`.`format` as `post_format` FROM `posts` LEFT JOIN `posts_data` as `pd` ON `posts`.`id`=`pd`.`post` WHERE `posts`.`id`='".$db->prepare($comment['post'])."' AND `posts`.`status`!=1 LIMIT 1";
		$q=$db->sql($sql);
		$post_arr=$db->row($q);
		$post_arr['author_login']=$redis->get('user_login:'.$post_arr['author']);
		$post_arr['author_name']=$redis->hget('users:'.$post_arr['author_login'],'name');
		$post_arr['author_avatar']=$redis->hget('users:'.$post_arr['author_login'],'avatar');

		$ret.='<div class="comment comment-card clearfix" data-id="'.$comment['id'].'" data-author="'.$comment['author_login'].'" data-permlink="'.$comment['permlink'].'" data-parent="'.$comment['parent'].'" data-allow-votes="'.$comment['allow_votes'].'" data-allow-replies="'.$comment['allow_replies'].'" data-payment-decline="'.($payout_decline?'1':'0').'" data-payment-inpower="'.($payout_inpower?'1':'0').'" data-vote="'.($vote?'1':'0').'" data-flag="'.($flag?'1':'0').'" data-vote-weight="'.($vote_weight).'" data-vote-time="'.($vote_time).'" data-level="'.($level?$comment['level']:'0').'" id="'.htmlspecialchars($comment['permlink']).'">';
		$ret.='<div class="comment-anchor"><a href="/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/#'.htmlspecialchars($comment['permlink']).'">#</a></div>';
		$ret.='<div class="comment-avatar"><a href="/@'.$comment['author_login'].'/" class="user-avatar"><img src="https://i.goldvoice.club/32x32a/'.$comment['author_avatar'].'" alt=""></a></div>';
		$ret.='<div class="comment-user"><a href="/@'.$comment['author_login'].'/">'.$comment['author_name'].'</a>';
		if(!$hide_post){
			$ret.='<span class="deep-gray small"> в теме <a href="/@'.$post_arr['author_login'].'/'.$post_arr['permlink'].'/">'.htmlspecialchars($post_arr['post_title']).'</a></span>';
		}
		$ret.='</div>';
		$ret.='<div class="comment-text">';
		$ret.=text_to_view($comment['body']);
		$ret.='</div>';
		$ret.='<div class="comment-info">';
			$ret.='<a class="comment-reply reply-action" data-comment-id="'.$comment['id'].'"><span class="l10n" data-cat="comment_card" data-name="reply"></span> <i class="fa fa-fw fa-commenting-o" aria-hidden="true"></i></a>';
			$ret.='<span class="comment-date" data-timestamp="'.$comment['time'].'">'.date('d.m.Y H:i',$comment['time']).'</span>';
			$ret.='<div class="comment-payments'.((float)$comment['payout']?' payout':'').'" data-comment-payout="'.$comment['payout'].'" data-comment-curator-payout="'.$comment['curator_payout'].'" data-comment-pending-payout="'.$comment['pending_payout'].'"><i class="fa fa-fw fa-diamond" aria-hidden="true"></i> <span>&hellip;</span></div>';
			if($auth){
				$ret.='<div class="comment-flags flag-action"><i class="fa fa-fw fa-flag'.($flag?'':'-o').'" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
			}
			else{
				$ret.='<div class="comment-flags flag-action"><i class="fa fa-fw fa-flag" aria-hidden="true"></i> <span>'.$flags_count.'</span></div>';
			}
			if($auth){
				$ret.='<div class="comment-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-'.($vote?'':'o-').'up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
			}
			else{
				$ret.='<div class="comment-upvotes upvote-action"><i class="fa fa-fw fa-thumbs-up" aria-hidden="true"></i> <span>'.$votes_count.'</span></div>';
			}
		$ret.='</div>';
		$ret.='</div>';
	}
	return $ret;
}
function markdown2html($text){
	global $parsedownextra;
	$text=$parsedownextra->text($text);

	/*
	// https://markdown-it.github.io/
	$replace_arr=array();
	$replace_num=1;

	preg_match_all('~^([-]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~\n([\-]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~^([\*]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~\n([\*]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~^([_]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~\n([_]+)\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<hr>';
		$text=str_replace($matches[0][$k],PHP_EOL.'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~>(.*)\n\n~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]='<blockquote>'.$matches[1][$k].'</blockquote>';
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	$text=str_replace("\r",'',$text);
	$text_arr=explode("\n\n",$text);
	$text='<p>'.implode('</p><p>',$text_arr).'</p>';
	$text=str_replace("\n","<br>\n",$text);

	foreach($replace_arr as $k=>$v){
		$text=str_replace('{replacerQarrQ'.$k.'}',$v,$text);
	}

	//print_r($replace_arr);
	return $text;
	*/
	preg_match_all('~\!\[(.[^\[\]\(\)]*)\]\((.[^\[\]\(\)]*) \"(.[^\"]*)\"\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$buf_html='<img src="'.htmlspecialchars($matches[2][$k]).'" alt="'.htmlspecialchars($matches[1][$k]).'" title="'.htmlspecialchars($matches[2][$k]).'">';
		$text=str_replace($match,$buf_html,$text);
	}
	preg_match_all('~\!\[(.[^\[\]\(\)]*)\]\((.[^\[\]\(\)]*)\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$buf_html='<img src="'.htmlspecialchars($matches[2][$k]).'" alt="'.htmlspecialchars($matches[1][$k]).'">';
		$text=str_replace($match,$buf_html,$text);
	}
	preg_match_all('~\[(.[^\[\]\(\)]*)\]\((.[^\[\]\(\)]*)\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$buf_html='<a href="'.htmlspecialchars($matches[2][$k]).'" target="_blank">'.$matches[1][$k].'</a>';
		$text=str_replace($match,$buf_html,$text);
	}
	preg_match_all('~\[([.]*)\]\(\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace($matches[0][$k],$matches[1][$k],$text);
	}
	preg_match_all('~\[\]\(([.]*)\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace($matches[0][$k],$matches[1][$k],$text);
	}
	preg_match_all('~\[\]\(\)~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$text=str_replace($matches[0][$k],'',$text);
	}
	//https://www.youtube.com/watch?v=xFM7PyqTDUw
	return $text;
}
function highlight_links($text){
	preg_match_all('~<a(.*)>(.*)</a>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~<img (.*)>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<iframe (.*)>(.*)</a>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	foreach($replace_arr as $k=>$v){
		$text=str_replace('{replacerQarrQ'.$k.'}',$v,$text);
	}
	return $text;
}
function sort_by_value_length($a,$b){
    return strlen($b)-strlen($a);
}
$tags_symbols_ru=array(
	'а'=>'a',
	'б'=>'b',
	'в'=>'v',
	'г'=>'g',
	'д'=>'d',
	'е'=>'e',
	'з'=>'z',
	'и'=>'i',
	'к'=>'k',
	'л'=>'l',
	'м'=>'m',
	'н'=>'n',
	'о'=>'o',
	'п'=>'p',
	'р'=>'r',
	'с'=>'s',
	'т'=>'t',
	'у'=>'u',
	'ф'=>'f',
	'ь'=>'x',
	'ые'=>'yie',
	'ы'=>'y',
	'ые'=>'yie',
	'ё'=>'yo',
	'ж'=>'zh',
	'й'=>'ij',
	'х'=>'kh',
	'ц'=>'cz',
	'ч'=>'ch',
	'ш'=>'sh',
	'ъ'=>'xx',
	'э'=>'ye',
	'ю'=>'yu',
	'я'=>'ya',
	'щ'=>'shch',
);
$tags_revert_symbols_ru=array_reverse($tags_symbols_ru,true);
function tags_translate($str,$lang='ru'){
	global $tags_symbols_ru;
	$check_arr='tags_symbols_'.$lang;
	$chars_arr=array();
	if(isset($$check_arr)){
		$chars_arr=$$check_arr;
	}
	$result='';
	$str_len=mb_strlen($str);
	for($i=0;$i<$str_len;$i++){
		$substr=mb_substr($str,$i,1);
		if($chars_arr[$substr]){
			$result.=$chars_arr[$substr];
		}
		else{
			$result.=$substr;
		}
	}
	return $result;
}
function tags_untranslate($str,$lang='ru'){
	global $tags_revert_symbols_ru;
	$check_arr='tags_revert_symbols_'.$lang;
	$chars_arr=array();
	if(isset($$check_arr)){
		$chars_arr=$$check_arr;
	}
	foreach($chars_arr as $k=>$v){
		$str=str_replace($v,$k,$str);
	}
	return $str;
}
function highlight_users($text){
	$replace_arr=array();
	$replace_num=1;

	preg_match_all('~<a(.*)>(.*)</a>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}

	preg_match_all('~<img (.*)>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~<iframe (.*)>~iUs',$text,$matches);
	foreach($matches[0] as $k=>$match){
		$replace_arr[$replace_num]=$matches[0][$k];
		$text=str_replace($matches[0][$k],'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	preg_match_all('~\@([a-z0-9\.\-\_^ \>\<]*)~is',$text,$matches);
	usort($matches[0],'sort_by_value_length');
	usort($matches[1],'sort_by_value_length');
	foreach($matches[0] as $k=>$match){
		$user_login=trim($match,' \r\n\t.');
		$replace_arr[$replace_num]='<a href="/@'.htmlspecialchars($user_login).'">@'.$user_login.'</a>';
		$text=str_replace($user_login,'{replacerQarrQ'.$replace_num.'}',$text);
		$replace_num++;
	}
	foreach($replace_arr as $k=>$v){
		$text=str_replace('{replacerQarrQ'.$k.'}',$v,$text);
	}
	return highlight_links($text);
}

$users_arr=array();
function get_user_id($login){
	global $db,$users_arr,$redis;
	if(!isset($users_arr[$login])){
		//$user_id=$db->select_one('users','id',"WHERE `login`='".$db->prepare($login)."'");
		$key=(int)$redis->hget('users:'.$login,'id');
		if($key){
			$users_arr[$login]=$key;
		}
		else{
			return false;
		}
		/*
		$user_id=$redis->zscore('users_id',$login);
		if($user_id){
			$users_arr[$login]=intval($user_id);
		}
		else{
			return false;
		}*/
	}
	return $users_arr[$login];
}
function get_user_login($id){
	global $db,$users_arr,$redis;
	$key=array_search($id,$users_arr);
	if(false===$key){
		//$key=$db->select_one('users','`login`',"WHERE `id`='".(int)$id."'");
		$key=$redis->get('user_login:'.$id);
		if($key){
			$users_arr[$key]=(int)$id;
		}
		else{
			return false;
		}
	}
	return $key;
}
function get_user_link($user_1,$user_2,$what=1){
	global $db;
	return $db->sql_row("SELECT `id`,`mutually`,`value` FROM `users_links` WHERE `user_1`='".(int)$user_1."' AND `user_2`='".(int)$user_2."' AND `value`='".(int)$what."' LIMIT 1");
}
function comment_parent_author($comment_id){
	global $db,$redis;
	$parent=$db->select_one('comments','parent',"WHERE `id`='".(int)$comment_id."'");
	$comment_arr=$db->sql_row("SELECT `author`,`permlink` FROM `comments` WHERE `id`='".(int)$parent."'");
	//$author_arr=$db->sql_row("SELECT `login` FROM `users` WHERE `id`='".(int)$comment_arr['author']."'");
	$author_login=$redis->get('user_login:'.$comment_arr['author']);
	return $author_login;
}
function post_parent_author($post_id){
	global $db,$redis;
	$parent=$db->select_one('posts','parent',"WHERE `id`='".(int)$post_id."'");
	$post_arr=$db->sql_row("SELECT `author`,`permlink` FROM `posts` WHERE `id`='".(int)$parent."'");
	$author_login=$redis->get('user_login:'.$post_arr['author']);
	return $author_login;
}
function comment_parent_permlink($comment_id){
	global $db;
	$parent=$db->select_one('comments','parent',"WHERE `id`='".(int)$comment_id."'");
	$comment_arr=$db->sql_row("SELECT `permlink` FROM `comments` WHERE `id`='".(int)$parent."'");
	return $comment_arr['permlink'];
}
function post_parent_permlink($post_id){
	global $db;
	$parent=$db->select_one('posts','parent',"WHERE `id`='".(int)$post_id."'");
	$post_arr=$db->sql_row("SELECT `permlink` FROM `posts` WHERE `id`='".(int)$parent."'");
	return $post_arr['permlink'];
}

function comment_author($comment_id){
	global $db,$redis;
	$comment_arr=$db->sql_row("SELECT `author`,`permlink` FROM `comments` WHERE `id`='".(int)$comment_id."'");
	//$author_arr=$db->sql_row("SELECT `login` FROM `users` WHERE `id`='".(int)$comment_arr['author']."'");
	$author_login=$redis->get('user_login:'.$comment_arr['author']);
	return $author_login;
}
function post_author($post_id){
	global $db,$redis;
	$post_arr=$db->sql_row("SELECT `author`,`permlink` FROM `posts` WHERE `id`='".(int)$post_id."'");
	//$author_arr=$db->sql_row("SELECT `login` FROM `users` WHERE `id`='".(int)$post_arr['author']."'");
	$author_login=$redis->get('user_login:'.$post_arr['author']);
	return $author_login;
}
function comment_permlink($comment_id){
	global $db;
	$comment_arr=$db->sql_row("SELECT `permlink` FROM `comments` WHERE `id`='".(int)$comment_id."'");
	return $comment_arr['permlink'];
}
function post_permlink($post_id){
	global $db;
	$post_arr=$db->sql_row("SELECT `permlink` FROM `posts` WHERE `id`='".(int)$post_id."'");
	return $post_arr['permlink'];
}
function show_rtn($text){
	$text=str_replace("\r","R\r",$text);
	$text=str_replace("\t","T\t",$text);
	$text=str_replace("\n","N\n",$text);
	return $text;
}
function post_golos_link($post_id){
	global $db,$redis;
	$post_arr=$db->sql_row("SELECT `author`,`permlink` FROM `posts` WHERE `id`='".(int)$post_id."'");
	//$author_arr=$db->sql_row("SELECT `login` FROM `users` WHERE `id`='".(int)$post_arr['author']."'");
	$author_login=$redis->get('user_login:'.$post_arr['author']);
	return 'https://golos.io/@'.$author_login.'/'.$post_arr['permlink'].'/';
}
function comment_golos_link($comment_id){
	global $db,$redis;
	$comment_arr=$db->sql_row("SELECT `post`,`permlink` FROM `comments` WHERE `id`='".(int)$comment_id."'");
	$post_arr=$db->sql_row("SELECT `author`,`permlink` FROM `posts` WHERE `id`='".(int)$comment_arr['post']."'");
	//$author_arr=$db->sql_row("SELECT `login` FROM `users` WHERE `id`='".(int)$post_arr['author']."'");
	$author_login=$redis->get('user_login:'.$post_arr['author']);
	return 'https://golos.io/@'.$author_login.'/'.$post_arr['permlink'].'/#'.$comment_arr['permlink'];
}
function add_post_history($post_id,$patch='',$new_body='',$tx_time){
	global $db;
	$post_arr=$db->sql_row("SELECT `id`,`post`,`title`,`body`,`json_metadata` FROM `posts_data` WHERE `post`='".(int)$post_id."'");
	if($post_arr['id']){
		$db->sql("INSERT INTO `posts_history` (`post`,`time`,`title`,`body_old`,`patch`,`body_new`,`json_metadata`) VALUES ('".$post_arr['post']."','".$tx_time."','".$db->prepare($post_arr['title'])."','".$db->prepare($post_arr['body'])."','".$db->prepare($patch)."','".$db->prepare($new_body)."','".$db->prepare($post_arr['json_metadata'])."')");
	}
}
function add_comment_history($comment_id,$patch='',$new_body='',$tx_time){
	global $db;
	$comment_arr=$db->sql_row("SELECT `id`,`body`,`json_metadata` FROM `comments` WHERE `id`='".(int)$comment_id."'");
	if($comment_arr['id']){
		$db->sql("INSERT INTO `comments_history` (`comment`,`time`,`body_old`,`patch`,`body_new`,`json_metadata`) VALUES ('".$comment_arr['id']."','".$tx_time."','".$db->prepare($comment_arr['body'])."','".$db->prepare($patch)."','".$db->prepare($new_body)."','".$db->prepare($comment_arr['json_metadata'])."')");
	}
}
function unidiff_patch($str1,$patch,$debug=false,$debug2=false){
	$result=$str1;
	$patch_arr=explode("\n@@",$patch);
	if(!$patch_arr){
		$patch_arr=array($patch);
	}
	$offset=0;
	foreach($patch_arr as $patch_num=>$patch_str){
		if(0!=$patch_num){
			$patch_str='@@'.$patch_str;
		}
		$patch_arr2=explode("\n",$patch_str);
		$diff_str=$patch_arr2[0];
		$diff_arr=explode(' ',$diff_str);
			$diff_from=$diff_arr[1];
			$diff_from=trim($diff_from,'-+');
			$diff_from_arr=explode(',',$diff_from);

			$diff_to=$diff_arr[3];
			$diff_to=trim($diff_to,'-+');
			$diff_to_arr=explode(',',$diff_to);
		$diff_count=count($patch_arr2);

		$substr_before=mb_substr($result,0,$diff_from_arr[0]-1);
		$substr=mb_substr($result,$diff_from_arr[0]-1,$diff_from_arr[1]);
		$substr_after=mb_substr($result,$diff_from_arr[0]+$diff_from_arr[1]-1);
		if($debug){
			print PHP_EOL.'<'.$patch_arr2[1];
			print PHP_EOL.'<<'.$substr_before;
			print PHP_EOL.'=='.$substr;
			print PHP_EOL.'>>'.$substr_after;
			print PHP_EOL.'>'.$patch_arr2[$diff_count-1];
		}

		$find_str_start=mb_substr($patch_arr2[1],1);
		$find_str_middle='';
		if($debug){
			print PHP_EOL.'! diff_str='.$diff_str;
		}
		if($debug2){print '<hr>';}
		for($i=2;$i<$diff_count-1;$i++){
			$micro_str=$patch_arr2[$i];
			$command=mb_substr($micro_str,0,1);
			$ignore=false;
			if($debug2){
				print '<div style="margin:2px 0;padding:2px;background:#544;color:#fff;">command: "'.show_rtn($command).'", micro_str: "'.show_rtn($micro_str ).'"</div>';
			}
			if('+'==$command){
				$command=' ';
			}
			if('-'==$command){
				$ignore=true;
			}
			if(' '==$command){
				$micro_str=mb_substr($micro_str,1);
				if(!$micro_str){
					$micro_str="\n ";
				}
			}
			if(!$micro_str){
				$micro_str="\n";
			}
			if($debug2){
				print '<div style="margin:2px 0;padding:2px;background:#454;color:#fff;">command: "'.show_rtn($command).'", micro_str: "'.show_rtn($micro_str ).'"</div>';
			}
			if($debug){print PHP_EOL.'!! command='.$command.', micro_str='.$micro_str;}
			if(!$ignore){
				$find_str_middle.=$micro_str;
			}
		}
		$find_str_end=mb_substr($patch_arr2[$diff_count-1],1);

		$result=$substr_before.$find_str_start.$find_str_middle.$find_str_end.$substr_after;
		if($debug){
			print PHP_EOL.'==============================================';
			print PHP_EOL.'new result: '.$result;
			print PHP_EOL.'==============================================';
		}
		if($debug2){
			print '<div style="margin:2px 0;padding:2px;background:#333;color:#fff;"><pre>'.show_rtn($result).'</pre></div>';
		}
		$new_text_len=mb_strlen($find_str_start.$find_str_middle.$find_str_end);
		$old_text_len=mb_strlen($substr);
		if($debug){
			print PHP_EOL.'!!! old len: '.$old_text_len.', new len: '.$new_text_len;
		}
	}
	return $result;
}
function rip($ip){
	$quads=explode('.',$ip);
	$rip=$quads[3].'.'.$quads[2].'.'.$quads[1].'.'.$quads[0];
	return $rip;
}
$preset=array();
$preset['time']=array((int)date('Y'),(int)date('m'),(int)date('d'),(int)date('H'),(int)date('i'),(int)date('s'));

$currencies_price=unserialize($cache->get('currencies_price'));

$preset['currencies_price']=&$currencies_price;
$preset['forbidden']=1;
$session_id=$_COOKIE['session_id'];
$check_session_id=$redis->zscore('sessions_cookie',$session_id);
$session_arr=$redis->hgetall('sessions:'.$check_session_id);
if(!$session_arr['user']){
	unset($session_arr);
}

//$session_arr=$db->sql_row("SELECT * FROM `sessions` WHERE `cookie`='".$db->prepare($session_id)."' AND `user`!=0");// AND `ip`='".$db->prepare($ip)."'
if($session_arr['id']){
	$preset['forbidden']=0;
	$auth=true;
	//$session_arr['public_profile']=$db->sql_row('SELECT `id`,`login`,`name`,`avatar`,`reg_time`,`birthday`,`last_post_time`,`action_time`,`timezone`,`balance`,`sbd_balance`,`vesting_shares`,`savings_balance`,`savings_sbd_balance`,`voting_power`,`reputation`,`reputation_short`,`about`,`location`,`birth_location`,`website`,`witnesses_proxy` FROM `users` WHERE `id`=\''.$db->prepare($session_arr['user']).'\' AND `status`!=1');
	$user_login_buf=get_user_login($session_arr['user']);
	$user_arr_buf=$redis->hgetall('users:'.$user_login_buf);
	if(1!=$user_arr_buf['status']){
		$session_arr['public_profile']=array(
			'id'=>$user_arr_buf['id'],
			'login'=>$user_arr_buf['login'],
			'name'=>$user_arr_buf['name'],
			'avatar'=>$user_arr_buf['avatar'],
			'reg_time'=>$user_arr_buf['reg_time'],
			'birthday'=>$user_arr_buf['birthday'],
			'last_post_time'=>$user_arr_buf['last_post_time'],
			'action_time'=>$user_arr_buf['action_time'],
			'timezone'=>$user_arr_buf['timezone'],
			'balance'=>$user_arr_buf['balance'],
			'sbd_balance'=>$user_arr_buf['sbd_balance'],
			'vesting_shares'=>$user_arr_buf['vesting_shares'],
			'savings_balance'=>$user_arr_buf['savings_balance'],
			'savings_sbd_balance'=>$user_arr_buf['savings_sbd_balance'],
			'voting_power'=>$user_arr_buf['voting_power'],
			'reputation'=>$user_arr_buf['reputation'],
			'reputation_short'=>$user_arr_buf['reputation_short'],
			'about'=>$user_arr_buf['about'],
			'location'=>$user_arr_buf['location'],
			'birth_location'=>$user_arr_buf['birth_location'],
			'website'=>$user_arr_buf['website'],
			'witnesses_proxy'=>$user_arr_buf['witnesses_proxy'],
		);
	}
	$session_arr['friends']=array();
	$session_arr['subscribed']=array();
	$session_arr['subscribed_by']=array();
	$session_arr['ignored']=array();
	$session_arr['ignored_by']=array();
	$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$session_arr['user']."' AND `value`=1 AND `mutually`=1");
	while($m=$db->row($q)){
		$session_arr['friends'][]=$m['user_2'];
	}
	$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$session_arr['user']."' AND `value`=1 AND `mutually`=0");
	while($m=$db->row($q)){
		$session_arr['subscribed'][]=$m['user_2'];
	}
	$q=$db->sql("SELECT `user_1` FROM `users_links` WHERE `user_2`='".$session_arr['user']."' AND `value`=1 AND `mutually`=0");
	while($m=$db->row($q)){
		$session_arr['subscribed_by'][]=$m['user_1'];
	}
	$q=$db->sql("SELECT `user_2` FROM `users_links` WHERE `user_1`='".$session_arr['user']."' AND `value`=2");
	while($m=$db->row($q)){
		$session_arr['ignored'][]=$m['user_2'];
	}
	$q=$db->sql("SELECT `user_1` FROM `users_links` WHERE `user_2`='".$session_arr['user']."' AND `value`=2");
	while($m=$db->row($q)){
		$session_arr['ignored_by'][]=$m['user_1'];
	}
	if($user_login_buf){
		$redis->hset('users:'.$user_login_buf,'action_time',time());
		$redis->zadd('users_action_time',time(),$user_login_buf);
		$redis->hset('sessions:'.$session_arr['id'],'action_time',time());
		$redis->zadd('sessions_action_time',time(),$session_arr['id']);
	}
	unset($user_login_buf);
	unset($user_arr_buf);
	//$db->sql("UPDATE `users` SET `action_time`='".time()."' WHERE `id`='".$session_arr['user']."'");
	//$db->sql("UPDATE `sessions` SET `action_time`='".time()."' WHERE `id`='".$session_arr['id']."'");

	if('goldvoice'==$session_arr['public_profile']['login']){
		$admin=true;
	}
	if(''==$session_arr['public_profile']['avatar']){
		$session_arr['public_profile']['avatar']='https://goldvoice.club/images/noava50.png';
	}
	$replace['user_block']='';


	$preset['user_profile']=&$session_arr['public_profile'];
	$user_balance=array();
	$user_balance['voting_power']=(float)($session_arr['public_profile']['voting_power']/100);
	$user_balance['sg']=(float)$session_arr['public_profile']['vesting_shares']*(float)$currencies_price['sg_per_vests'];
	$user_balance['sg']=(float)round($user_balance['sg'],3);
	$user_balance['golos']=(float)$session_arr['public_profile']['balance'];
	$user_balance['gbg']=(float)$session_arr['public_profile']['sbd_balance'];
	$user_balance['savings_golos']=(float)$session_arr['public_profile']['savings_balance'];
	$user_balance['savings_gbg']=(float)$session_arr['public_profile']['savings_sbd_balance'];
	$preset['user_balance']=&$user_balance;

	$replace['user_block'].='<a class="menu-energy right-button" title="'.$l10n['global']['energy'].'">&hellip;</a>';
	$replace['user_block'].='<a class="menu-notifications right-button" title="'.$l10n['notifications']['page_title'].'"><i class="fa fa-bell" aria-hidden="true"></i></a>';
	$replace['user_block'].='<a class="menu-add-post right-button" href="/add-post/"><i class="fa fa-fw fa-pencil-square" aria-hidden="true"></i><div class="caption">'.$l10n['global']['add_post'].'</div></a>';

	$replace['user_block'].='<a class="menu-dropdown right-button"><div class="menu-avatar" title="'.htmlspecialchars($session_arr['public_profile']['name']).'"><img src="https://i.goldvoice.club/32x32a/'.$session_arr['public_profile']['avatar'].'"></div><i class="fa fa-fw fa-caret-right" aria-hidden="true"></i></a>';
}
$replace['head_addon'].='<script>';
$preset=unserialize(str_replace(array('NAN;','INF;'),'0;',serialize($preset)));
$replace['head_addon'].='var preset='.json_encode($preset).';';
$replace['head_addon'].='var l10n='.json_encode($l10n).';';
$replace['head_addon'].='</script>';