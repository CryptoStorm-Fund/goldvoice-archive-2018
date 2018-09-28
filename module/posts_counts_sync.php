<?php
set_time_limit(0);
if(!$config['blockchain_parse']){
	exit;
}
$offset=0;
$perpage=50;
$offset=$_GET['offset'];
$q=$db->sql("SELECT `id`,`parent_post` FROM `posts` ORDER BY `id` ASC LIMIT ".$perpage." OFFSET ".$offset);
while($m=$db->row($q)){
	if(0==$m['parent_post']){
		$upvotes_count=$db->table_count('posts_votes',"WHERE `post`='".$m['id']."' AND `weight`>0");
		$downvotes_count=$db->table_count('posts_votes',"WHERE `post`='".$m['id']."' AND `weight`<0");
		$db->sql("UPDATE `posts` SET `upvotes`='".$upvotes_count."', `downvotes`='".$downvotes_count."' WHERE `id`='".$m['id']."'");
	}
}
print '<meta http-equiv="refresh" content="1; url=https://goldvoice.club/posts_counts_sync/?offset='.($offset+$perpage).'">';
exit;