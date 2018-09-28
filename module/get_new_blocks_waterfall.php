<?php
if('cron_parse_password'!=$path_array[2]){
	exit;
}
if(!$config['blockchain_parse']){
	exit;
}
set_time_limit(75);
ob_end_clean();
ignore_user_abort(true);
header("Connection: close");
fastcgi_finish_request();

$web=new golos_jsonrpc_web($config['blockchain_jsonrpc'],true);

$adult_tags_arr=array(37706,26400,17591,7517,878,741,1476,2343,3525,4349,12463);
$start_time=time();
$end_time=$start_time+59;
$parse_times=0;
$block_id=$db->select_one('blocks','id',"ORDER BY `id` DESC");
$block_id++;
$top_witnesses_arr=array();
$q=$db->sql('SELECT `user` FROM `witnesses` ORDER BY `votes` DESC LIMIT 19');
while($m=$db->row($q)){
	$top_witnesses_arr[$m['user']]=true;
}
$error_count=0;
$debug_info='';
$debug_operations_amount=array();
$debug_history='';
while(time()<$end_time){
	$block_info=$web->execute_method('get_block',array($block_id),false);

	$wait=false;
	if(is_array($block_info)){
		$date=date_parse_from_format('Y-m-d\TH:i:s',$block_info['timestamp']);
		$block_time=mktime($date['hour'],$date['minute'],$date['second'],$date['month'],$date['day'],$date['year']);
		$block_witness=$block_info['witness'];
		$block_witness_user=get_user_id($block_info['witness']);
		$db->sql("INSERT INTO `blocks` (`id`,`time`,`witness`,`witness_user`,`witness_top`) VALUES ('".$block_id."','".$block_time."','".$db->prepare($block_witness)."','".$db->prepare($block_witness_user)."',CONV('".(true===$top_witnesses_arr[$block_witness_user]?'1':'0')."', 2, 10)+0)");
		$debug_history.=serialize($block_info['transactions']);
		$transaction_arr=$block_info['transactions'];
		$tx_id=1;
		foreach($transaction_arr as $transaction){
			$operations=$transaction['operations'];
			$error=false;
			//<-----------------------------------------------------
			$op_id=1;
			foreach($operations as $operation){
				$operation_name=$operation[0];
				$ignore=false;
				$save_raw=false;
				if('comment'==$operation_name){
					$parent_author=$operation[1]['parent_author'];
					$parent_permlink=$operation[1]['parent_permlink'];
					$author=$operation[1]['author'];
					$redis->zadd('users_action_time',$block_time,$operation[1]['author']);
					$permlink=$operation[1]['permlink'];
					$title=$operation[1]['title'];
					$body=$operation[1]['body'];
					$json_metadata=$operation[1]['json_metadata'];
					$author_id=get_user_id($author);
					if(''==$parent_author){//пост
						if($author_id){
							//$author_status=$db->select_one('users','status',"WHERE `id`='".$author_id."'");
							$author_status=$redis->hget('users:'.$author,'status');
							$find_post=$db->select_one('posts','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
							if($find_post){//пост нашли, значит надо внести изменения
								$json_metadata_encoded=json_decode($json_metadata,true);
								$first_image='';
								if($json_metadata_encoded['image'][0]){//нам не нужны все картинки - только первая
									$first_image=$json_metadata_encoded['image'][0];
								}
								if($json_metadata_encoded['post_image'][0]){//или указанная вручную
									$first_image=$json_metadata_encoded['post_image'][0];
								}
								$image_format=1;
								if($json_metadata_encoded['$image_format']){
									$image_format=(int)$json_metadata_encoded['image_format'];
								}
								$app='';
								if($json_metadata_encoded['app']){
									$app=$json_metadata_encoded['app'];
								}
								$format='';
								if($json_metadata_encoded['format']){
									$format=$json_metadata_encoded['format'];
								}
								if('@@ '==mb_substr($body,0,3)){
									$old_body=$db->select_one('posts_data','body',"WHERE `post`='".$find_post."'");
									$new_body=unidiff_patch($old_body,rawurldecode($body));
									add_post_history($find_post,rawurldecode($body),$new_body,$block_time);
									$db->sql("UPDATE `posts_data` SET `title`='".$db->prepare($title)."', `body`='".$db->prepare($new_body)."', `json_metadata`='".$db->prepare($json_metadata)."', `image`='".$db->prepare($first_image)."', `image_format`='".$db->prepare($image_format)."', `app`='".$db->prepare($app)."', `format`='".$db->prepare($format)."' WHERE `post`='".$find_post."'");
									redis_add_ulist('update_posts',$find_post);
									//redis_add_queue('update_posts',array($find_post));
									//$db->sql("UPDATE `posts` SET `payout_parse_priority`='1' WHERE `id`='".$find_post."'");
								}
								else{
									add_post_history($find_post,'','',$block_time);
									$db->sql("UPDATE `posts_data` SET `title`='".$db->prepare($title)."', `body`='".$db->prepare($body)."', `json_metadata`='".$db->prepare($json_metadata)."', `image`='".$db->prepare($first_image)."', `image_format`='".$db->prepare($image_format)."', `app`='".$db->prepare($app)."', `format`='".$db->prepare($format)."' WHERE `post`='".$find_post."'");
								}
								if(1==$db->select_one('posts','status',"WHERE `id`='".$find_post."'")){
									$db->sql("UPDATE `posts` SET `status`='".$author_status."', `delete_time`='0' WHERE `id`='".$find_post."' AND `status`=1");
									$q2=$db->sql("SELECT `user_1` FROM `users_links` WHERE `user_2`='".$author_id."' AND `value`=1");
									while($m2=$db->row($q2)){
										//redis_add_queue('feed',array($m2['user_1'],$find_post));
										redis_add_feed($m2['user_1'],$find_post);
										//$db->sql("INSERT INTO `feed` (`user`,`post`) VALUES ('".$m2['user_1']."','".$find_post."')");
									}
								}
								$db->sql("DELETE FROM  `posts_users` WHERE `post`='".$find_post."'");
								$users_arr=$json_metadata_encoded['users'];
								foreach($users_arr as $user){//разбираем юзеров, которых упомянули в посте
									$look_user_id=get_user_id($user);
									if($look_user_id){
										$db->sql("INSERT INTO `posts_users` (`post`,`user`) VALUES ('".$post_id."','".$look_user_id."')");
									}
								}
								$db->sql("DELETE FROM  `posts_tags` WHERE `post`='".$find_post."'");
								$adult=false;
								$tags_arr=$json_metadata_encoded['tags'];
								$weight=100;
								foreach($tags_arr as $tag){//разбираем тэги + выставляем вес
									if(10>$weight){
										$weight=10;
									}
									$tag_id=$db->select_one('tags','id',"WHERE `en`='".$db->prepare($tag)."'");
									if(!$tag_id){
										$ru='';
										if('ru--'==substr($tag,0,4)){
											$ru=tags_untranslate(substr($tag,4));
										}
										$db->sql("INSERT INTO `tags` (`en`,`ru`) VALUES ('".$db->prepare($tag)."','".$db->prepare($ru)."')");
										$tag_id=$db->last_id();
									}
									if(in_array($tag_id,$adult_tags_arr)){
										$adult=true;
									}
									$db->sql("INSERT INTO `posts_tags` (`post`,`tag`,`weight`) VALUES ('".$find_post."','".$tag_id."','".$weight."')");
									$weight-=20;
								}
								if($adult){
									$db->sql("UPDATE `posts` SET `adult`=1 WHERE `id`='".$find_post."'");
								}
							}
							else{//такого поста нет, значит он новый
								$redis->hincrby('users:'.$author,'pc',1);
								$sql="INSERT INTO `posts` (`time`,`parent_permlink`,`author`,`permlink`,`status`) VALUES ('".$block_time."','".$db->prepare($parent_permlink)."','".$author_id."','".$db->prepare($permlink)."','".$author_status."')";
								$db->sql($sql);
								$post_id=$db->last_id();
								if($post_id){
									$json_metadata_encoded=json_decode($json_metadata,true);
									$first_image='';
									if($json_metadata_encoded['image'][0]){//нам не нужны все картинки - только первая
										$first_image=$json_metadata_encoded['image'][0];
									}
									if($json_metadata_encoded['post_image'][0]){//или указанная вручную
										$first_image=$json_metadata_encoded['post_image'][0];
									}
									$image_format=1;
									if($json_metadata_encoded['$image_format']){
										$image_format=(int)$json_metadata_encoded['image_format'];
									}
									$app='';
									if($json_metadata_encoded['app']){//приложение для постинга, можно использовать для фильтрации чужих приложений и добавлять посту status=2
										$app=$json_metadata_encoded['app'];
									}
									$format='';
									if($json_metadata_encoded['format']){//markdown или html
										$format=$json_metadata_encoded['format'];
									}
									$db->sql("INSERT `posts_data` (`post`,`title`,`body`,`json_metadata`,`image`,`image_format`,`app`,`format`) VALUES ('".$post_id."','".$db->prepare($title)."','".$db->prepare($body)."','".$db->prepare($json_metadata)."','".$db->prepare($first_image)."','".$db->prepare($image_format)."','".$db->prepare($app)."','".$db->prepare($format)."')");
									$q2=$db->sql("SELECT `user_1` FROM `users_links` WHERE `user_2`='".$author_id."' AND `value`=1");
									while($m2=$db->row($q2)){
										//redis_add_queue('feed',array($m2['user_1'],$post_id));
										redis_add_feed($m2['user_1'],$post_id);
										//$db->sql("INSERT INTO `feed` (`user`,`post`) VALUES ('".$m2['user_1']."','".$post_id."')");
									}
									$q2=$db->sql("SELECT `group` FROM `group_members` WHERE `member`='".$author_id."' AND `status`=0");
									while($m2=$db->row($q2)){
										$db->sql("INSERT INTO `group_feed` (`group`,`post`) VALUES ('".$m2['group']."','".$post_id."')");
									}
									$users_arr=$json_metadata_encoded['users'];
									foreach($users_arr as $user){//разбираем юзеров, которых упомянули в посте
										$look_user_id=get_user_id($user);
										if($look_user_id){
											if($author_id!=$look_user_id){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".$look_user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$look_user_id,10,(int)$post_id));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$look_user_id."',10,'".(int)$post_id."')");
												}
											}
											$db->sql("INSERT INTO `posts_users` (`post`,`user`) VALUES ('".$post_id."','".$look_user_id."')");
										}
									}
									$adult=false;
									$tags_arr=$json_metadata_encoded['tags'];
									$weight=100;
									foreach($tags_arr as $tag){//разбираем тэги + выставляем вес
										if(10>$weight){
											$weight=10;
										}
										$tag_id=$db->select_one('tags','id',"WHERE `en`='".$db->prepare($tag)."'");
										if(!$tag_id){
											$ru='';
											if('ru--'==substr($tag,0,4)){
												$ru=tags_untranslate(substr($tag,4));
											}
											$db->sql("INSERT INTO `tags` (`en`,`ru`) VALUES ('".$db->prepare($tag)."','".$db->prepare($ru)."')");
											$tag_id=$db->last_id();
										}
										if(in_array($tag_id,$adult_tags_arr)){
											$adult=true;
										}
										$db->sql("INSERT INTO `posts_tags` (`post`,`tag`,`weight`) VALUES ('".$post_id."','".$tag_id."','".$weight."')");
										$weight-=20;
										$db->sql("UPDATE `tags` SET `posts`=1+`posts` WHERE `id`='".$tag_id."'");
									}
									if($adult){
										$db->sql("UPDATE `posts` SET `adult`=1 WHERE `id`='".$post_id."'");
									}
									$cat=$parent_permlink;
									$find_cat=$db->select_one('categories','id',"WHERE `name`='".$db->prepare($cat)."'");
									if(!$find_cat){
										$db->sql("INSERT INTO `categories` (`name`) VALUES ('".$db->prepare($cat)."')");
										$find_cat=$db->last_id();
									}
									if($find_cat){
										$db->sql("UPDATE `categories` SET `posts`=`posts`+1 WHERE `id`='".$find_cat."'");
										$db->sql("INSERT INTO `posts_categories` (`post`,`category`) VALUES ('".$post_id."','".$find_cat."')");
									}
									unset($post_id);
								}
								else{
									$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Пост не вставляется','".$operation_name."','".$db->prepare(serialize($operation[1]))."','".$db->prepare($sql)."','0')");
									$error=true;
								}
							}
						}
						else{
							$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден автор поста','".$operation_name."','".$db->prepare(serialize($operation[1]))."','0')");
							$error=true;
						}
					}
					else{//comment
						$parent_author_id=get_user_id($parent_author);
						if($parent_author_id){
							$author_id=get_user_id($author);
							if($author_id){
								//$author_status=$db->select_one('users','status',"WHERE `id`='".$author_id."'");
								$author_status=$redis->hget('users:'.$author,'status');
								$find_post=$db->select_one('comments','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
								if($find_post){//коммент нашли, значит надо внести изменения
									if('@@ '==mb_substr($body,0,3)){
										$old_body=$db->select_one('comments','body',"WHERE `id`='".$find_post."'");
										$new_body=unidiff_patch($old_body,rawurldecode($body));
										add_comment_history($find_post,rawurldecode($body),$new_body,$block_time);
										//$db->sql("UPDATE `comments` SET `body`='".$new_body."', `json_metadata`='".$json_metadata."' WHERE `id`='".$find_post."'");
										redis_add_ulist('update_comments',$find_post);
										//$db->sql("UPDATE `comments` SET `payout_parse_priority`='1', `json_metadata`='".$json_metadata."' WHERE `id`='".$find_post."'");
									}
									else{
										add_comment_history($find_post,'','',$block_time);
										$db->sql("UPDATE `comments` SET `body`='".$body."', `json_metadata`='".$json_metadata."' WHERE `id`='".$find_post."'");
									}
									$db->sql("UPDATE `comments` SET `status`='".$author_status."', `delete_time`='0' WHERE `id`='".$find_post."' AND `status`=1");
									$json_metadata_encoded=json_decode($json_metadata,true);
									$db->sql("DELETE FROM  `comments_users` WHERE `comment`='".$find_post."'");
									$users_arr=$json_metadata_encoded['users'];
									foreach($users_arr as $user){//разбираем юзеров, которых упомянули в посте
										$look_user_id=get_user_id($user);
										if($look_user_id){
											$db->sql("INSERT INTO `comments_users` (`comment`,`user`) VALUES ('".$comment_id."','".$look_user_id."')");
										}
									}
									/*//on1x optimize
									$db->sql("DELETE FROM  `comments_tags` WHERE `comment`='".$find_post."'");
									$tags_arr=$json_metadata_encoded['tags'];
									foreach($tags_arr as $tag){//разбираем тэги + выставляем вес
										$tag_id=$db->select_one('tags','id',"WHERE `en`='".$db->prepare($tag)."'");
										if(!$tag_id){
											$db->sql("INSERT INTO `tags` (`en`) VALUES ('".$db->prepare($tag)."')");
											$tag_id=$db->last_id();
										}
										$db->sql("INSERT INTO `comments_tags` (`comment`,`tag`) VALUES ('".$find_post."','".$tag_id."')");
										$db->sql("UPDATE `tags` SET `comments`=1+`comments` WHERE `id`='".$tag_id."'");
									}
									*/
								}
								else{//коммента не было, добавляем
									$parent_permlink_id=$db->select_one('posts','id',"WHERE `author`='".$parent_author_id."' AND `permlink`='".$db->prepare($parent_permlink)."'");
									$post_id=0;
									$parent_comment_id=0;
									$level=0;
									$sort=0;
									if(!$parent_permlink_id){//пост не найден, ищем коммент
										$redis->hincrby('users:'.$author,'cc',1);
										$parent_permlink_id=$db->select_one('comments','id',"WHERE `author`='".$parent_author_id."' AND `permlink`='".$db->prepare($parent_permlink)."'");
										if($parent_permlink_id){//коммент к комменту!
											$parent_comment=$db->sql_row("SELECT * FROM `comments` WHERE `id`='".$parent_permlink_id."'");
											$post_id=$parent_comment['post'];
											$post_author_id=$db->select_one('posts','author',"WHERE `id`='".$post_id."'");
											$parent_comment_id=$parent_comment['id'];
											$level=1+$parent_comment['level'];

											$find_parent_next=$db->select_one('comments','`sort`',"WHERE `post`='".$post_id."' AND `level`<='".$parent_comment['level']."' AND `sort`>'".$parent_comment['sort']."' ORDER BY `sort` ASC");
											if($find_parent_next){
												$sort=$find_parent_next;
											}
											else{
												$sort=$db->select_one('comments','sort',"WHERE `post`='".$post_id."' ORDER BY `sort` DESC");
												$sort++;
											}
											if(0==$db->table_count('users_links',"WHERE `user_1`='".$post_author_id."' AND `user_2`='".$author_id."' AND `value`=2")){
												$db->sql("UPDATE `posts` SET `comments`=1+`comments` WHERE `id`='".$post_id."'");
											}
											else{
												$author_status=1;
											}
											$db->sql("UPDATE `comments` SET `sort`=1+`sort` WHERE `post`='".$post_id."' AND `sort`>='".$sort."'");
											$db->sql("INSERT INTO `comments` (`post`,`parent`,`author`,`permlink`,`time`,`level`,`sort`,`body`,`json_metadata`,`status`) VALUES ('".$post_id."','".$parent_comment_id."','".$author_id."','".$db->prepare($permlink)."','".$block_time."','".$level."','".$sort."','".$db->prepare($body)."','".$db->prepare($json_metadata)."','".$author_status."')");
											$comment_id=$db->last_id();
											if($author_id!=(int)$parent_comment['author']){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$parent_comment['author']."' AND `user_2`='".$author_id."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$parent_comment['author'],5,(int)$comment_id));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$parent_comment['author']."',5,'".(int)$comment_id."')");
												}
											}
											$json_metadata_encoded=json_decode($json_metadata,true);
											$users_arr=$json_metadata_encoded['users'];
											foreach($users_arr as $user){//разбираем юзеров, которых упомянули в посте
												$look_user_id=get_user_id($user);
												if($look_user_id){
													if($author_id!=(int)$look_user_id){
														if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$look_user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
															redis_add_queue('notifications',array($block_time,(int)$look_user_id,11,(int)$comment_id));
															//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$look_user_id."',11,'".(int)$comment_id."')");
														}
													}
													$db->sql("INSERT INTO `comments_users` (`comment`,`user`) VALUES ('".$comment_id."','".$look_user_id."')");
												}
											}
											/*//on1x optimize
											$tags_arr=$json_metadata_encoded['tags'];
											foreach($tags_arr as $tag){//разбираем тэги + выставляем вес
												$tag_id=$db->select_one('tags','id',"WHERE `en`='".$db->prepare($tag)."'");
												if(!$tag_id){
													$db->sql("INSERT INTO `tags` (`en`) VALUES ('".$db->prepare($tag)."')");
													$tag_id=$db->last_id();
												}
												$db->sql("INSERT INTO `comments_tags` (`comment`,`tag`) VALUES ('".$comment_id."','".$tag_id."')");
												$db->sql("UPDATE `tags` SET `comments`=1+`comments` WHERE `id`='".$tag_id."'");
											}
											*/
										}
										else{
											$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пост или коммент для комментария','".$operation_name."','".$db->prepare(serialize($operation[1]))."','0')");
											$error=true;
										}
									}
									else{//пост найден
										$post_id=$parent_permlink_id;
										$post_arr=$db->sql_row("SELECT * FROM `posts` WHERE `id`='".$post_id."'");
										$sort=$db->select_one('comments','sort',"WHERE `post`='".$post_id."' ORDER BY `sort` DESC");
										$sort++;
										if(0==$db->table_count('users_links',"WHERE `user_1`='".$post_arr['author']."' AND `user_2`='".$author_id."' AND `value`=2")){
											$db->sql("UPDATE `posts` SET `comments`=1+`comments` WHERE `id`='".$post_id."'");
										}
										else{
											$author_status=1;
										}
										$db->sql("INSERT INTO `comments` (`post`,`parent`,`author`,`permlink`,`time`,`level`,`sort`,`body`,`json_metadata`,`status`) VALUES ('".$post_id."','".$parent_comment_id."','".$author_id."','".$db->prepare($permlink)."','".$block_time."','".$level."','".$sort."','".$db->prepare($body)."','".$db->prepare($json_metadata)."','".$author_status."')");
										$comment_id=$db->last_id();
										if($author_id!=(int)$post_arr['author']){
											if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$post_arr['author']."' AND `user_2`='".$author_id."' AND `value`=2")){
												redis_add_queue('notifications',array($block_time,(int)$post_arr['author'],4,(int)$comment_id));
												//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$post_arr['author']."',4,'".(int)$comment_id."')");
											}
										}
										$json_metadata_encoded=json_decode($json_metadata,true);
										$users_arr=$json_metadata_encoded['users'];
										foreach($users_arr as $user){//разбираем юзеров, которых упомянули в посте
											$look_user_id=get_user_id($user);
											if($look_user_id){
												if($author_id!=(int)$look_user_id){
													if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$look_user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
														redis_add_queue('notifications',array($block_time,(int)$look_user_id,11,(int)$comment_id));
														//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$look_user_id."',11,'".(int)$comment_id."')");
													}
												}
												$db->sql("INSERT INTO `comments_users` (`comment`,`user`) VALUES ('".$comment_id."','".$look_user_id."')");
											}
										}
										/*on1x optimize
										$tags_arr=$json_metadata_encoded['tags'];
										foreach($tags_arr as $tag){//разбираем тэги + выставляем вес
											$tag_id=$db->select_one('tags','id',"WHERE `en`='".$db->prepare($tag)."'");
											if(!$tag_id){
												$db->sql("INSERT INTO `tags` (`en`) VALUES ('".$db->prepare($tag)."')");
												$tag_id=$db->last_id();
											}
											$db->sql("INSERT INTO `comments_tags` (`comment`,`tag`) VALUES ('".$comment_id."','".$tag_id."')");
											$db->sql("UPDATE `tags` SET `comments`=1+`comments` WHERE `id`='".$tag_id."'");
										}
										*/
									}
								}
							}
							else{
								$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден автор комментария','".$operation_name."','".$db->prepare(serialize($operation[1]))."','0')");
								$error=true;
							}
						}
						else{
							$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден автор поста для комментария','".$operation_name."','".$db->prepare(serialize($operation[1]))."','0')");
							$error=true;
						}
					}
					$ignore=true;
					unset($parent_author);
					unset($parent_permlink);
					unset($author);
					unset($permlink);
					unset($title);
					unset($body);
					unset($json_metadata);
					//usleep(300000);
				}
				elseif('vote'==$operation_name){
					$author=$operation[1]['author'];
					$permlink=$operation[1]['permlink'];
					$weight=$operation[1]['weight'];
					$author_id=get_user_id($author);
					if($author_id){
						$user_login=$operation[1]['voter'];
						$user_id=get_user_id($user_login);
						if($user_id){
							redis_add_ulist('update_users2',$user_login);
							$redis->zadd('users_action_time',$block_time,$user_login);
							if($weight>0){
								$redis->hincrby('users:'.$user_login,'uc',1);
							}
							if($weight<0){
								$redis->hincrby('users:'.$user_login,'dc',1);
							}
							$find_post=$db->select_one('posts','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
							if($find_post){//пост
								$find_post_vote=$db->select_one('posts_votes','id',"WHERE `post`='".$find_post."' AND `user`='".$user_id."'");
								if($find_post_vote){
									$db->sql("UPDATE `posts_votes` SET `weight`='".(int)$weight."', `time`='".$block_time."' WHERE `id`='".$find_post_vote."'");
								}
								else{
									redis_add_queue('posts_votes',array($find_post,$author_id,$user_id,$block_time,(int)$weight));
									//$db->sql("INSERT INTO `posts_votes` (`post`,`post_author`,`user`,`time`,`weight`) VALUES ('".$find_post."','".$author_id."','".$user_id."','".$block_time."','".(int)$weight."')");
									/*//on1x !!! +
									$find_post_vote=$db->last_id();
									if($weight>0){
										if($author_id!=$user_id){
											if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
												redis_add_queue('notifications',array($block_time,(int)$author_id,6,(int)$find_post_vote));
												//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$author_id."',6,'".(int)$find_post_vote."')");
											}
										}
									}
									else{
										if($author_id!=$user_id){
											if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
												redis_add_queue('notifications',array($block_time,(int)$author_id,8,(int)$find_post_vote));
												//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$author_id."',8,'".(int)$find_post_vote."')");
											}
										}
									}
									*/
								}
								redis_add_ulist('update_posts',$find_post);
								unset($find_post);
								//redis_add_queue('update_posts',array($find_post));
								//$db->sql("UPDATE `posts` SET `payout_parse_priority`='1' WHERE `id`='".$find_post."'");
							}
							else{
								$find_post=$db->select_one('comments','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
								if($find_post){//комментарий
									$find_comment_vote=$db->select_one('comments_votes','id',"WHERE `comment`='".$find_post."' AND `user`='".$user_id."'");
									if($find_comment_vote){
										$db->sql("UPDATE `comments_votes` SET `weight`='".(int)$weight."', `time`='".$block_time."' WHERE `id`='".$find_comment_vote."'");
									}
									else{
										$db->sql("INSERT INTO `comments_votes` (`comment`,`comment_author`,`user`,`time`,`weight`) VALUES ('".$find_post."','".$author_id."','".$user_id."','".$block_time."','".(int)$weight."')");
										$find_comment_vote=$db->last_id();
										if($weight>0){
											if($author_id!=$user_id){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$author_id,7,(int)$find_comment_vote));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$author_id."',7,'".(int)$find_comment_vote."')");
												}
											}
										}
										else{
											if($author_id!=$user_id){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_id."' AND `user_2`='".$author_id."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$author_id,9,(int)$find_comment_vote));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$author_id."',9,'".(int)$find_comment_vote."')");
												}
											}
										}
									}
								}
								redis_add_ulist('update_comments',$find_post);
								unset($find_post);
								//$db->sql("UPDATE `comments` SET `payout_parse_priority`='1' WHERE `id`='".$find_post."'");
							}
						}
						else{
							$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь (голосующий)','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
							$error=true;
						}
					}
					else{
						$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь (автор поста)','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
						$error=true;
					}
					$ignore=true;
				}
				elseif('pow2'==$operation_name){
					$ignore=true;
				}
				elseif('feed_publish'==$operation_name){$save_raw=false;}
				elseif('comment_options'==$operation_name){
					$author=$operation[1]['author'];
					$author_id=get_user_id($author);
					if($author_id){
						$permlink=$operation[1]['permlink'];
						$max_accepted_payout=$operation[1]['max_accepted_payout'];
						$max_accepted_payout=str_replace(' GBG','',$max_accepted_payout);
						$max_accepted_payout=intval($max_accepted_payout);
						if($max_accepted_payout>0){
							$max_accepted_payout=1;
						}
						else{
							$max_accepted_payout=0;
						}
						$percent_steem_dollars=$operation[1]['percent_steem_dollars'];
						$find_post=$db->select_one('posts','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
						if($find_post){
							$db->sql("UPDATE `posts` SET `max_accepted_payout`='".$max_accepted_payout."', `percent_steem_dollars`='".(int)$percent_steem_dollars."' WHERE `id`='".$find_post."'");
						}
						else{
							$find_post=$db->select_one('comments','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
							if($find_post){
								$db->sql("UPDATE `comments` SET `max_accepted_payout`='".$max_accepted_payout."', `percent_steem_dollars`='".(int)$percent_steem_dollars."' WHERE `id`='".$find_post."'");
							}
							else{
								$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пост или комментарий','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
								$error=true;
							}
						}
					}
					else{
						$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
						$error=true;
					}
					$ignore=true;
				}
				elseif('custom_json'==$operation_name){
					//$save_raw=true;
					$custom_json_name=$operation[1]['id'];
					$required_posting_auths=$operation[1]['required_posting_auths'];
					$required_auths=$operation[1]['required_auths'];
					$json=$operation[1]['json'];
					$json=json_decode($json,true);
					if('witness_poll_vote'==$custom_json_name){
						$poll_url=$db->prepare($json['url']);
						$poll_id=$db->select_one('witnesses_polls','id',"WHERE `url`='".$poll_url."' AND `end_time`>'".$block_time."'");
						if($poll_id){
							$poll_option=(int)$json['option'];
							$user_login=$required_posting_auths[0];
							$user_id=get_user_id($user_login);
							if(0!=$db->table_count('witnesses_polls',"WHERE `url`='".$poll_url."'")){
								$user_vote=$db->select_one('witnesses','votes',"WHERE `user`='".$user_id."'");
								if($user_vote){
									if(0!=$db->table_count('witnesses_votes',"WHERE `poll`='".$poll_id."' AND `user`='".$user_id."'")){
										$db->sql("UPDATE `witnesses_votes` SET `option`='".$db->prepare($poll_option)."',`time`='".$db->prepare($block_time)."',`block`='".$db->prepare($block_id)."' WHERE `poll`='".$poll_id."' AND `user`='".$user_id."'");
									}
									else{
										$db->sql("INSERT INTO `witnesses_votes` (`poll`,`user`,`start_votes`,`option`,`block`,`time`) VALUES ('".$db->prepare($poll_id)."','".$db->prepare($user_id)."','".$db->prepare($user_vote)."','".$db->prepare($poll_option)."','".$db->prepare($block_id)."','".$db->prepare($block_time)."')");
									}
								}
							}
						}
					}
					else
					if('witness_poll'==$custom_json_name){
						$poll_url=$json['url'];
						$poll_name=$json['name'];
						$poll_days=(int)$json['days'];
						$poll_descr=$json['descr'];
						$poll_options=implode('|',$json['options']);
						$user_login=$required_posting_auths[0];
						$user_id=get_user_id($user_login);
						if($poll_days<14){
							$poll_days=14;
						}
						if(poll_url){
							$end_time=$block_time+$poll_days*(3600*24);
							$db->sql("INSERT INTO `witnesses_polls` (`url`,`name`,`descr`,`options`,`user`,`start_time`,`end_time`,`start_block`) VALUES ('".$db->prepare($poll_url)."','".$db->prepare($poll_name)."','".$db->prepare($poll_descr)."','".$db->prepare($poll_options)."','".$db->prepare($user_id)."','".$db->prepare($block_time)."','".$db->prepare($end_time)."','".$db->prepare($block_id)."')");
						}
					}
					else
					if('goldvoice'==$custom_json_name){
						$custom_json_action=$json[0];
						if('auth'==$custom_json_action){
							$user_login=$required_posting_auths[0];
							$user_id=get_user_id($user_login);
							$session_key=$json[1]['key'];
							if($user_id){
								$check_session_id=$redis->zscore('sessions_key',$session_key);
								if($check_session_id){
									$redis->hset('sessions:'.$check_session_id,'user',$user_id);
								}
								//$db->sql("UPDATE `sessions` SET `user`='".$user_id."' WHERE `key`='".$db->prepare($session_key)."'");
							}
						}
					}
					else
					if('follow'==$custom_json_name){
						$custom_json_action=$json[0];
						if('follow'==$custom_json_action){
							$user_1=get_user_id($json[1]['follower']);
							if(in_array($json[1]['follower'],$required_posting_auths)){
								$user_2=get_user_id($json[1]['following']);
								$what=$json[1]['what'];
								if(0==count($what)){
									if($user_2!=$user_1){
										if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_2."' AND `user_2`='".$user_1."' AND `value`=2")){
											redis_add_queue('notifications',array($block_time,(int)$user_2,1,(int)$user_1));
											//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$user_2."',1,'".(int)$user_1."')");
										}
									}
									$db->sql("DELETE FROM `users_links` WHERE `user_1`='".(int)$user_1."' AND `user_2`='".(int)$user_2."'");
									$db->sql("UPDATE `users_links` SET `mutually`='0' WHERE `user_1`='".(int)$user_2."' AND `user_2`='".(int)$user_1."'");
									$db->sql("UPDATE `reg_subscribes` SET `status`=2, `unsubscribe_time`='".$block_time."' WHERE `user1`='".(int)$user_1."' AND `user2`='".(int)$user_2."'");
								}
								else{
									$what=$what[0];
									if('blog'==$what){
										$what=1;
										$db->sql("DELETE FROM `users_links` WHERE `user_1`='".(int)$user_1."' AND `user_2`='".(int)$user_2."'");
										$mutually=0;
										if(0!=$db->table_count('users_links',"WHERE `user_1`='".(int)$user_2."' AND `user_2`='".(int)$user_1."' AND `value`='".$what."'")){
											$mutually=1;
											$db->sql("UPDATE `users_links` SET `mutually`='1' WHERE `user_1`='".(int)$user_2."' AND `user_2`='".(int)$user_1."' AND `value`='".$what."'");
											if($user_2!=$user_1){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_2."' AND `user_2`='".$user_1."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$user_2,3,(int)$user_1));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$user_2."',3,'".(int)$user_1."')");
												}
											}
										}
										else{
											if($user_2!=$user_1){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$user_2."' AND `user_2`='".$user_1."' AND `value`=2")){
													redis_add_queue('notifications',array($block_time,(int)$user_2,2,(int)$user_1));
													//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$user_2."',2,'".(int)$user_1."')");
												}
											}
										}
										$db->sql("INSERT INTO `users_links` (`user_1`,`user_2`,`value`,`mutually`,`time`) VALUES ('".(int)$user_1."','".(int)$user_2."','".$what."','".$mutually."','".$block_time."')");
										$db->sql("UPDATE `reg_subscribes` SET `status`=1, `subscribe_time`='".$block_time."' WHERE `user1`='".(int)$user_1."' AND `user2`='".(int)$user_2."'");
									}
									elseif('ignore'==$what){
										$what=2;
										$db->sql("DELETE FROM `users_links` WHERE `user_1`='".(int)$user_1."' AND `user_2`='".(int)$user_2."'");
										$mutually=0;
										if(0!=$db->table_count('users_links',"WHERE `user_1`='".(int)$user_2."' AND `user_2`='".(int)$user_1."' AND `value`='".$what."'")){
											$mutually=1;
											$db->sql("UPDATE `users_links` SET `mutually`='1' WHERE `user_1`='".(int)$user_2."' AND `user_2`='".(int)$user_1."' AND `value`='".$what."'");
										}
										$db->sql("INSERT INTO `users_links` (`user_1`,`user_2`,`value`,`mutually`,`time`) VALUES ('".(int)$user_1."','".(int)$user_2."','".$what."','".$mutually."','".$block_time."')");
										$db->sql("UPDATE `reg_subscribes` SET `status`=2, `unsubscribe_time`='".$block_time."' WHERE `user1`='".(int)$user_1."' AND `user2`='".(int)$user_2."'");
									}
								}
							}
							else{
								$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не соблюдены права доступа','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
								$error=true;
							}
						}
						if('reblog'==$custom_json_action){
							$author=$json[1]['author'];
							$author_id=get_user_id($author);
							if($author_id){
								$user_login=$json[1]['account'];
								if(in_array($user_login,$required_posting_auths)){
									$user_id=get_user_id($user_login);
									if($user_id){
										$permlink=$json[1]['permlink'];
										$find_post=$db->select_one('posts','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
										if($find_post){//пост
											$redis->hincrby('users:'.$user_login,'rc',1);
											$db->sql("INSERT INTO `posts` (`time`,`parent_post`,`author`,`permlink`) VALUES ('".$block_time."','".$find_post."','".$user_id."','')");
											$reblog_post=$db->last_id();
											$comment_str=$json[1]['comment'];
											if($comment_str){
												$db->sql("INSERT INTO `posts_reblog_comment` (`post`,`comment`) VALUES ('".$reblog_post."','".$db->prepare($comment_str)."')");
											}
											if(0==$db->table_count('users_links',"WHERE `user_1`='".(int)$author_id."' AND `user_2`='".$user_id."' AND `value`=2")){
												redis_add_queue('notifications',array($block_time,(int)$author_id,13,(int)$reblog_post));
												//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$author_id."',13,'".(int)$reblog_post."')");
											}
											$q2=$db->sql("SELECT `user_1` FROM `users_links` WHERE `user_2`='".$user_id."' AND `value`=1");
											while($m2=$db->row($q2)){
												if(0==$db->table_count('users_links',"WHERE `user_1`='".$m2['user_1']."' AND `user_2`='".$author_id."' AND `value`=2")){
													//redis_add_queue('feed',array($m2['user_1'],$reblog_post));
													redis_add_feed($m2['user_1'],$reblog_post);
													//$db->sql("INSERT INTO `feed` (`user`,`post`) VALUES ('".$m2['user_1']."','".$reblog_post."')");
												}
											}
											$q2=$db->sql("SELECT `group` FROM `group_members` WHERE `member`='".$user_id."' AND `status`=0");
											while($m2=$db->row($q2)){
												$db->sql("INSERT INTO `group_feed` (`group`,`post`) VALUES ('".$m2['group']."','".$reblog_post."')");
											}
										}
										else{
											$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пост при реблоге','".$operation_name."','".$db->prepare(serialize($operation[1]))."','0')");
											$error=true;
										}
									}
									else{
										$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь при реблоге','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
										$error=true;
									}
								}
								else{
									$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не соблюдены права доступа','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
									$error=true;
								}
							}
							else{
								$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь (автор поста) при реблоге','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
								$error=true;
							}
						}
					}
				}
				elseif('delete_comment'==$operation_name){
					$author=$operation[1]['author'];
					$author_id=get_user_id($author);
					if($author_id){
						$permlink=$operation[1]['permlink'];
						$find_post=$db->select_one('posts','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
						if($find_post){
							$redis->hincrby('users:'.$user_login,'pc',-1);
							$db->sql("UPDATE `posts` SET `status`='1', `delete_time`='".$block_time."' WHERE `id`='".$find_post."'");
							$db->sql("DELETE FROM `feed` WHERE `post`='".$find_post."'");
						}
						else{
							$find_post=$db->select_one('comments','id',"WHERE `author`='".$author_id."' AND `permlink`='".$db->prepare($permlink)."'");
							if($find_post){
								$redis->hincrby('users:'.$user_login,'cc',-1);
								$db->sql("UPDATE `comments` SET `status`='1', `delete_time`='".$block_time."' WHERE `id`='".$find_post."'");
							}
							else{
								$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пост или комментарий','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
								$error=true;
							}
						}
					}
					else{
						$db->sql("INSERT INTO `errors` (`block`,`time`,`value`,`data_name`,`data`,`sql`,`status`) VALUES ('".$block_id."','".$block_time."','Не найден пользователь','".$operation_name."','".$db->prepare(serialize($operation[1]))."','','0')");
						$error=true;
					}
					$ignore=true;
				}
				elseif('account_create'==$operation_name){
					$account_create_login=$operation[1]['new_account_name'];
					$db->sql("INSERT INTO `users` (`login`,`creator`,`creator_fee`,`reg_time`) VALUES ('".$db->prepare($account_create_login)."','".$db->prepare($operation[1]['creator'])."','".$db->prepare($operation[1]['fee'])."','".$db->prepare($block_time)."')");
					/*
					$client->send(ws_method("get_accounts",array($account_create_login),true));
					$result2=$client->receive();
					$result_arr2=json_decode($result2,true);
					$block_info2=$result_arr2['result'];
					*/
					$block_info2=$web->execute_method('get_accounts',array($account_create_login),true);

					if($block_info2[0]['name']==$account_create_login){
						$user_arr=$block_info2[0];
						$json_metadata2=json_decode($user_arr['json_metadata'],true);
						$date2=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['created']);
						$reg_time=mktime($date2['hour'],$date2['minute'],$date2['second'],$date2['month'],$date2['day'],$date2['year']);
						$date2=date_parse_from_format('Y-m-d\TH:i:s',$user_arr['last_post']);
						$last_post=mktime($date2['hour'],$date2['minute'],$date2['second'],$date2['month'],$date2['day'],$date2['year']);
						$new_user_arr=array(
							'id'=>$user_arr['id'],
							'reg_time'=>$reg_time,
							'balance'=>substr($user_arr['balance'],0,strpos($user_arr['balance'],' ')),
							'sbd_balance'=>substr($user_arr['sbd_balance'],0,strpos($user_arr['sbd_balance'],' ')),
							'vesting_shares'=>substr($user_arr['vesting_shares'],0,strpos($user_arr['vesting_shares'],' ')),
							'voting_power'=>$user_arr['voting_power'],
							'avatar'=>$json_metadata2['profile']['profile_image'],
							'name'=>$json_metadata2['profile']['name'],
							'about'=>$json_metadata2['profile']['about'],
							'location'=>$json_metadata2['profile']['location'],
							'website'=>$json_metadata2['profile']['website'],
							'last_post_time'=>$last_post,
							'creator'=>$user_arr['recovery_account'],
							'reputation'=>$user_arr['reputation'],
							'reputation_short'=>floor(max(log10((int)$user_arr['reputation'])-9,0)*(($user_arr['reputation']<0?-1:1)*9)+25),
							'parse_time'=>time(),
						);

						$update_arr=array();
						foreach($new_user_arr as $k=>$v) {
							$update_arr[]='`'.$k.'`=\''.$db->prepare($v).'\'';
						}
						$update_str=implode(', ',$update_arr);
						$db->sql("UPDATE `users` SET ".$update_str." WHERE `login`='".$db->prepare($account_create_login)."'");
						/* redis users */
						/*
						$user_id=$redis->zscore('users_id',$account_create_login);
						if(!$user_id){
							$redis->zadd('users_id',$user_arr['id'],$account_create_login);
							foreach($new_user_arr as $k=>$v){
								if($v){
									$redis->hset('users:'.$user_arr['id'],$k,$v);
								}
							}
							$redis->hset('users:'.$user_arr['id'],'creator',$operation[1]['creator']);
							$redis->hset('users:'.$user_arr['id'],'creator_fee',$operation[1]['fee']);
							$redis->hset('users:'.$user_arr['id'],'reg_time',$block_time);
						}
						$redis->zadd('users_action_time',$block_time,(int)$user_login);
						*/

						$redis->set('user_login:'.$user_arr['id'],$account_create_login);
						$redis->hset('users:'.$account_create_login,'login',$account_create_login);
						$redis->hset('users:'.$account_create_login,'creator',$operation[1]['creator']);
						$redis->hset('users:'.$account_create_login,'creator_fee',$operation[1]['fee']);
						$redis->hset('users:'.$account_create_login,'reg_time',$block_time);
						foreach($new_user_arr as $k=>$v){
							if($v){
								$redis->hset('users:'.$account_create_login,$k,$v);
							}
						}
						$reg_arr=$db->sql_row("SELECT * FROM `reg_history` WHERE `login`='".$db->prepare($account_create_login)."'");
						if($reg_arr['id']){
							$public=true;
							$invite_struct=array();
							if($reg_arr['invite']){
								$invite_struct=$db->sql_row("SELECT * FROM `invite_struct` WHERE `id`='".$db->prepare($reg_arr['invite'])."'");
								$public=false;
								if(1==$invite_struct['public']){
									$public=true;
								}
							}
							$subscribe_arr=array();
							if($public){
								$q=$db->sql("SELECT * FROM `invite_struct` WHERE `public`=1 AND `status`=1");
								while($m=$db->row($q)){
									$reg_balance=$redis->hget('users:'.get_user_login($m['user']),'reg_balance');
									if(0<floatval($reg_balance)){
										if(false!==strpos($m['subscribes'],',')){
											$arr=explode(',',$m['subscribes']);
											foreach($arr as $subscribe){
												$subscribe_user_id=get_user_id($subscribe);
												$subscribe_arr[$subscribe_user_id]=$m['id'];
											}
										}
										else{
											$subscribe_user_id=get_user_id($m['subscribes']);
											$subscribe_arr[$subscribe_user_id]=$m['id'];
										}
									}
									else{
										$db->sql("UPDATE `invite_struct` SET `status`=0 WHERE `id`='".$m['id']."'");
									}
								}
							}
							else{
								if(false!==strpos($invite_struct['subscribes'],',')){
									$arr=explode(',',$invite_struct['subscribes']);
									foreach($arr as $subscribe){
										$subscribe_user_id=get_user_id($subscribe);
										$subscribe_arr[$subscribe_user_id]=$invite_struct['id'];
									}
								}
								else{
									$subscribe_user_id=get_user_id($invite_struct['subscribes']);
									$subscribe_arr[$subscribe_user_id]=$invite_struct['id'];
								}
							}
							$feed_arr=array();
							//$subscribe_arr=array_unique($subscribe_arr);
							foreach($subscribe_arr as $subscribe=>$invite_id){
								$db->sql("INSERT INTO `reg_subscribes` (`user1`,`invite`,`user2`,`status`) VALUES ('".$user_arr['id']."','".$invite_id."','".$subscribe."',0)");
								$q=$db->sql("SELECT `id` FROM `posts` WHERE `author`='".$subscribe."' `status`!=1 LIMIT 10");
								while($m=$db->row($q)){
									$feed_arr[]=$m['id'];
								}
							}
							sort($feed_arr);
							foreach($feed_arr as $feed_post){
								//redis_add_queue('feed',array($user_arr['id'],$feed_post));
								redis_add_feed($user_arr['id'],$feed_post);
								//$db->sql("INSERT INTO `feed` (`user`,`post`) VALUES ('".$user_arr['id']."','".$feed_post."')");
							}
						}
						unset($json_metadata2);
						unset($block_info2);
						unset($result2);
						unset($account_create_login);
						unset($update_arr);
						unset($date2);
						unset($new_user_arr);
					}
					$ignore=true;
				}
				elseif('account_metadata'==$operation_name){
					$user_id=get_user_id($operation[1]['account']);
					//redis_add_ulist('update_users',$user_id);
					redis_add_ulist('update_users2',$operation[1]['account']);
					//redis_add_queue('update_users',array($user_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `login`='".$db->prepare($operation[1]['account'])."'");
					$ignore=true;
				}
				elseif('account_update'==$operation_name){
					$user_id=get_user_id($operation[1]['account']);
					//redis_add_ulist('update_users',$user_id);
					redis_add_ulist('update_users2',$operation[1]['account']);
					//redis_add_queue('update_users',array($user_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `login`='".$db->prepare($operation[1]['account'])."'");
					$ignore=true;
				}
				elseif('change_recovery_account'==$operation_name){
					$user_id=get_user_id($operation[1]['account_to_recover']);
					//redis_add_ulist('update_users',$user_id);
					redis_add_ulist('update_users2',$operation[1]['account_to_recover']);
					//redis_add_queue('update_users',array($user_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `login`='".$db->prepare($operation[1]['account_to_recover'])."'");
					$ignore=true;
				}
				elseif('transfer'==$operation_name){
					$arr=$operation[1];
					$from_id=get_user_id($arr['from']);
					$to_id=get_user_id($arr['to']);
					$amount_arr=explode(' ',$arr['amount']);
					$amount=(float)$amount_arr[0];
					$currency_str=$amount_arr[1];
					$currency=-1;
					if($currencies_arr[$currency_str]){
						$currency=$currencies_arr[$currency_str];
					}
					$memo=$arr['memo'];

					//$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$block_time."','".$from_id."','".$to_id."','".$amount."','".$currency."','".$db->prepare($memo)."')");
					//$transfer_id=$db->last_id();

					$transfer_id=$redis->incr('id:transfers');
					//$debug_info.='try transfer operation, incr id:transfers='.$transfer_id.PHP_EOL;
					$redis->hmset('transfers:'.$transfer_id,
						array(
							'id'=>$transfer_id,
							'from'=>$from_id,
							'to'=>$to_id,
							'amount'=>$amount,
							'currency'=>$currency,
							'memo'=>$memo,
							'time'=>$block_time
						)
					);

					$redis->zadd('transfers_from:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to:'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_way:'.$from_id.':'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to_currency:'.$to_id.':'.$currency,$block_time,$transfer_id);

					if($to_id!=$from_id){
						if(0==$db->table_count('users_links',"WHERE `user_1`='".$to_id."' AND `user_2`='".$from_id."' AND `value`=2")){
							redis_add_queue('notifications',array($block_time,(int)$to_id,12,(int)$transfer_id));
							//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$to_id."',12,'".(int)$transfer_id."')");
						}
					}
					if(55479==$to_id){//goldvoice
						if(1==$currency){
							if('reg'==$memo){
								$db->sql("INSERT INTO `reg_transfers` (`transfer`,`time`,`user`,`amount`,`status`) VALUES ('".$transfer_id."','".$block_time."','".$from_id."','".$amount."','0')");
							}
						}
					}
					//redis_add_ulist('update_users',$from_id);
					//redis_add_ulist('update_users',$to_id);
					redis_add_ulist('update_users2',$arr['from']);
					redis_add_ulist('update_users2',$arr['to']);
					$redis->zadd('users_action_time',$block_time,$arr['from']);
					$redis->zadd('users_action_time',$block_time,$arr['to']);
					//redis_add_queue('update_users',array($from_id));
					//redis_add_queue('update_users',array($to_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$from_id."'");
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$to_id."'");
					$ignore=true;
				}
				elseif('account_witness_proxy'==$operation_name){
					//$save_raw=true;
					$arr=$operation[1];
					$account=get_user_id($arr['account']);
					//redis_add_ulist('update_users',$account);
					redis_add_ulist('update_users2',$arr['account']);
					//redis_add_queue('update_users',array($account));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$account."'");
					$ignore=true;
				}
				elseif('account_witness_vote'==$operation_name){
					$save_raw=true;
					$arr=$operation[1];
					$witness_user_id=get_user_id($arr['witness']);
					$user_id=get_user_id($arr['account']);
					$approve=$arr['approve'];
					if($user_id!=$witness_user_id){
						if($approve){
							if($witness_user_id!=$user_id){
								if(0==$db->table_count('users_links',"WHERE `user_1`='".$witness_user_id."' AND `user_2`='".$user_id."' AND `value`=2")){
									redis_add_queue('notifications',array($block_time,(int)$witness_user_id,14,(int)$user_id));
									//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$witness_user_id."',14,'".(int)$user_id."')");
								}
							}
						}
						else{
							if($witness_user_id!=$user_id){
								if(0==$db->table_count('users_links',"WHERE `user_1`='".$witness_user_id."' AND `user_2`='".$user_id."' AND `value`=2")){
									redis_add_queue('notifications',array($block_time,(int)$witness_user_id,15,(int)$user_id));
									//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$witness_user_id."',15,'".(int)$user_id."')");
								}
							}
						}
					}
				}
				elseif('convert'==$operation_name){$save_raw=true;}
				elseif('custom'==$operation_name){$save_raw=true;}
				elseif('escrow_approve'==$operation_name){$save_raw=true;}
				elseif('escrow_dispute'==$operation_name){$save_raw=true;}
				elseif('escrow_release'==$operation_name){$save_raw=true;}
				elseif('escrow_transfer'==$operation_name){$save_raw=true;}
				elseif('recover_account'==$operation_name){$save_raw=true;}
				elseif('request_account_recovery'==$operation_name){$save_raw=true;}
				elseif('limit_order_cancel'==$operation_name){$save_raw=true;}
				elseif('limit_order_create'==$operation_name){$save_raw=true;}
				elseif('limit_order_create2'==$operation_name){$save_raw=true;}
				elseif('witness_update'==$operation_name){
					$arr=$operation[1];
					$user_id=get_user_id($arr['owner']);
					if(0==$db->table_count('witnesses',"WHERE `user`='".$user_id."'")){
						$db->sql("INSERT INTO `witnesses` (`user`,`parse_priority`) VALUES ('".$user_id."',1)");
					}
					else{
						$db->sql("UPDATE `witnesses` SET `parse_priority`=1 WHERE `id`='".$user_id."'");
					}
				}
				elseif('cancel_transfer_from_savings'==$operation_name){
					$save_raw=true;
					$arr=$operation[1];
					$from_id=get_user_id($arr['from']);
					$request_id=(int)$arr['request_id'];
					$memo='CANCEL WITHDRAW SAVINGS: '.$request_id;
					//$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$block_time."','".$from_id."','".$from_id."','0','0','".$db->prepare($memo)."')");
					//$transfer_id=$db->last_id();

					$transfer_id=$redis->incr('id:transfers');

					$redis->hmset('transfers:'.$transfer_id,
						array(
							'id'=>$transfer_id,
							'from'=>$from_id,
							'to'=>$from_id,
							'memo'=>$memo,
							'time'=>$block_time
						)
					);

					$redis->zadd('transfers_from:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_way:'.$from_id.':'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to_currency:'.$from_id.':0',$block_time,$transfer_id);
					//redis_add_ulist('update_users',$from_id);
					redis_add_ulist('update_users2',$arr['from']);
					//redis_add_queue('update_users',array($from_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$from_id."'");
					$ignore=true;
				}
				elseif('transfer_from_savings'==$operation_name){
					//$save_raw=true;
					$arr=$operation[1];
					$from_id=get_user_id($arr['from']);
					$to_id=get_user_id($arr['to']);
					$request_id=(int)$arr['request_id'];
					$amount_arr=explode(' ',$arr['amount']);
					$amount=(float)$amount_arr[0];
					$currency_str=$amount_arr[1];
					$currency=-1;
					if($currencies_arr[$currency_str]){
						$currency=$currencies_arr[$currency_str];
					}
					$memo='WITHDRAW SAVINGS: '.$request_id;
					//$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$block_time."','".$from_id."','".$to_id."','".$amount."','".$currency."','".$db->prepare($memo)."')");
					//$transfer_id=$db->last_id();

					$transfer_id=$redis->incr('id:transfers');

					$redis->hmset('transfers:'.$transfer_id,
						array(
							'id'=>$transfer_id,
							'from'=>$from_id,
							'to'=>$to_id,
							'amount'=>$amount,
							'currency'=>$currency,
							'memo'=>$memo,
							'time'=>$block_time
						)
					);

					$redis->zadd('transfers_from:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to:'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_way:'.$from_id.':'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to_currency:'.$to_id.':'.$currency,$block_time,$transfer_id);

					if($from_id!=$to_id){
						if(0==$db->table_count('users_links',"WHERE `user_1`='".$to_id."' AND `user_2`='".$from_id."' AND `value`=2")){
							redis_add_queue('notifications',array($block_time,(int)$to_id,12,(int)$transfer_id));
							//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$to_id."',12,'".(int)$transfer_id."')");
						}
					}
					//redis_add_ulist('update_users',$from_id);
					//redis_add_ulist('update_users',$to_id);
					redis_add_ulist('update_users2',$arr['from']);
					redis_add_ulist('update_users2',$arr['to']);
					//redis_add_queue('update_users',array($from_id));
					//redis_add_queue('update_users',array($to_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$from_id."'");
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$to_id."'");
					$ignore=true;
				}
				elseif('transfer_to_savings'==$operation_name){
					$save_raw=true;
					$arr=$operation[1];
					$from_id=get_user_id($arr['from']);
					$to_id=get_user_id($arr['to']);
					$amount_arr=explode(' ',$arr['amount']);
					$amount=(float)$amount_arr[0];
					$currency_str=$amount_arr[1];
					$currency=-1;
					if($currencies_arr[$currency_str]){
						$currency=$currencies_arr[$currency_str];
					}
					$memo='DEPOSIT SAVINGS: '.$arr['memo'];
					//$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$block_time."','".$from_id."','".$to_id."','".$amount."','".$currency."','".$db->prepare($memo)."')");
					//$transfer_id=$db->last_id();

					$transfer_id=$redis->incr('id:transfers');

					$redis->hmset('transfers:'.$transfer_id,
						array(
							'id'=>$transfer_id,
							'from'=>$from_id,
							'to'=>$to_id,
							'amount'=>$amount,
							'currency'=>$currency,
							'memo'=>$memo,
							'time'=>$block_time
						)
					);

					$redis->zadd('transfers_from:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to:'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_way:'.$from_id.':'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to_currency:'.$to_id.':'.$currency,$block_time,$transfer_id);

					if($from_id!=$to_id){
						if(0==$db->table_count('users_links',"WHERE `user_1`='".$to_id."' AND `user_2`='".$from_id."' AND `value`=2")){
							redis_add_queue('notifications',array($block_time,(int)$to_id,12,(int)$transfer_id));
							//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$to_id."',12,'".(int)$transfer_id."')");
						}
					}
					//redis_add_ulist('update_users',$from_id);
					//redis_add_ulist('update_users',$to_id);
					redis_add_ulist('update_users2',$arr['from']);
					redis_add_ulist('update_users2',$arr['to']);
					//redis_add_queue('update_users',array($from_id));
					//redis_add_queue('update_users',array($to_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$from_id."'");
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$to_id."'");
					$ignore=true;
				}
				elseif('transfer_to_vesting'==$operation_name){
					$arr=$operation[1];
					$from_id=get_user_id($arr['from']);
					$to_id=get_user_id($arr['to']);
					$amount_arr=explode(' ',$arr['amount']);
					$amount=(float)$amount_arr[0];
					$currency_str=$amount_arr[1];
					$currency=-1;
					if($currencies_arr[$currency_str]){
						$currency=$currencies_arr[$currency_str];
					}
					$memo=$arr['memo'];
					$memo='GOLOS POWER';

					//$db->sql("INSERT INTO `transfers` (`time`,`from`,`to`,`amount`,`currency`,`memo`) VALUES ('".$block_time."','".$from_id."','".$to_id."','".$amount."','".$currency."','GOLOS POWER')");
					//$transfer_id=$db->last_id();

					$transfer_id=$redis->incr('id:transfers');

					$redis->hmset('transfers:'.$transfer_id,
						array(
							'id'=>$transfer_id,
							'from'=>$from_id,
							'to'=>$to_id,
							'amount'=>$amount,
							'currency'=>$currency,
							'memo'=>$memo,
							'time'=>$block_time
						)
					);

					$redis->zadd('transfers_from:'.$from_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to:'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_way:'.$from_id.':'.$to_id,$block_time,$transfer_id);
					$redis->zadd('transfers_to_currency:'.$to_id.':'.$currency,$block_time,$transfer_id);

					if($from_id!=$to_id){
						if(0==$db->table_count('users_links',"WHERE `user_1`='".$to_id."' AND `user_2`='".$from_id."' AND `value`=2")){
							redis_add_queue('notifications',array($block_time,(int)$to_id,12,(int)$transfer_id));
							//$db->sql("INSERT INTO `notifications` (`time`,`user`,`type`,`target`) VALUES ('".$block_time."','".(int)$to_id."',12,'".(int)$transfer_id."')");
						}
					}
					//redis_add_ulist('update_users',$from_id);
					//redis_add_ulist('update_users',$to_id);
					redis_add_ulist('update_users2',$arr['from']);
					redis_add_ulist('update_users2',$arr['to']);
					//redis_add_queue('update_users',array($from_id));
					//redis_add_queue('update_users',array($to_id));
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$from_id."'");
					//$db->sql("UPDATE `users` SET `parse_priority`=1 WHERE `id`='".$to_id."'");
					$ignore=true;
				}
				elseif('withdraw_vesting'==$operation_name){$ignore=true;}
				if(!$ignore){
					if($save_raw){
						//$encoded_data=gzencode(serialize($operation[1]),9);
						//$db->sql("INSERT INTO `raw_operations` (`block`,`time`,`data`,`name`) VALUES ('".$block_id."','".$block_time."','".$db->prepare($encoded_data)."','".$db->prepare($operation_name)."')");
						//unset($encoded_data);
					}
				}
				$op_id++;
				$debug_operations_amount[$operation_name]++;
			}
			if($error_count>5){
				$error=false;
				$error_count=0;
			}
			if($error){
				//usleep(700000);
				$error_count++;
			}
			else{
				//on1x
				//$transaction_operations=serialize($operations);
				//$db->sql("INSERT INTO `transactions` (`block`,`time`,`operations`,`parsed_time`) VALUES ('".$block_id."','".$block_time."','".$db->prepare($transaction_operations)."','".time()."')");
				//unset($transaction_operations);
			}
			unset($operations);
			unset($encode_data);
			unset($operation_name);
			$error=false;
			//<-----------------------------------------------------
			$tx_id++;
		}
		$block_id++;
		unset($block_info);
	}
	else{
		$wait=true;
	}
	$parse_times++;
	if($wait){
		sleep(1);
		$wait=false;
	}
	else{
		usleep(300);
	}
}
$cache->set('waterfall_history',$debug_info.PHP_EOL.serialize($debug_operations_amount).PHP_EOL.$debug_history.PHP_EOL.serialize($db->history()),60);
exit;