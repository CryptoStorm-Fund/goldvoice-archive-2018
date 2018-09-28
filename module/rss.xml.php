<?php
function fix_rss($str){
	return str_replace(']]>',']]\>',$str);
}
$count=0;
header('Content-type: text/xml;');
$char_sitemap=false;
$char=$db->prepare(mb_substr($_GET['char'],0,1,'UTF-8'));
if(preg_match('~[a-z]~iUs',$char)){
	$char_sitemap=true;
}
$cache_name='rss';
$user_login=$db->prepare($_GET['user']);
$tag_name=$db->prepare($_GET['tag']);
if($user_login){
	$cache_name.='-users-'.$user_login;
	$user_arr=$db->sql_row("SELECT * FROM `users` WHERE `login`='".$user_login."' AND `status`=0");
}
if($tag_name){
	$cache_name.='-tags-'.$tag_name;
	$tag_arr=$db->sql_row("SELECT * FROM `tags` WHERE `en`='".$tag_name."' AND `status`=0");
}
$cbuf=$cache->get($cache_name);//false;//
if($cbuf){
	print $cbuf;
}
else{
	ob_start();
	print '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title>'.($user_login?'@'.$user_login.' - ':'').($tag_name?'Tag: #'.$tag_name.' - ':'').'RSS GoldVoice.club</title>
	<description><![CDATA['.($user_login?fix_rss($user_arr['name'].': '.$user_arr['about'].' - '):'').($tag_name?fix_rss('Tag: #'.$tag_name.' - '):'').'RSS feed GoldVoice.club]]></description>
	<link>https://goldvoice.club/rss.xml'.($user_login?'?user='.$user_login:'').($tag_name?'?tag='.$tag_name:'').'</link>
	<lastBuildDate>'.date(DATE_RFC822).'</lastBuildDate>
	<pubDate>'.date(DATE_RFC822).'</pubDate>
	<ttl>1800</ttl>
	<atom:link href="https://goldvoice.club/rss.xml'.($user_login?'?user='.$user_login:'').($tag_name?'?tag='.$tag_name:'').'" rel="self" type="application/rss+xml" />
';
	if($user_login){
		if($user_arr['id']){
			while($m2=$sdb->posts("WHERE `author`='".$user_arr['id']."' AND `status`=0 AND `parent_post`=0 ORDER BY `id` DESC LIMIT 25")){
				if($m2['id']){
					$pd=$db->sql_row("SELECT * FROM `posts_data` WHERE `post`='".$m2['id']."'");
					print '
<item>
	<title><![CDATA['.fix_rss($pd['title']).']]></title>
	<description><![CDATA['.fix_rss(text_to_view($pd['body'])).']]></description>
	<link><![CDATA[https://goldvoice.club/@'.$user_arr['login'].'/'.$m2['permlink'].'/]]></link>
	<guid isPermaLink="false">goldvoice-club-'.$m2['id'].'</guid>
	<pubDate>'.date(DATE_RFC822,$m2['time']).'</pubDate>
</item>
';
				}
			}
		}
	}
	elseif($tag_name){
		if($tag_arr['id']){
			while($m=$sdb->posts_tags("WHERE `tag`='".$tag_arr['id']."' ORDER BY `id` DESC LIMIT 25")){
				while($m2=$sdb->posts("WHERE `id`='".$m['post']."' AND `status`=0 AND `parent_post`=0 ORDER BY `id` DESC LIMIT 25")){
					if($m2['id']){
						$pd=$db->sql_row("SELECT * FROM `posts_data` WHERE `post`='".$m2['id']."'");
						print '
<item>
	<title><![CDATA['.fix_rss($pd['title']).']]></title>
	<description><![CDATA['.fix_rss(text_to_view($pd['body'])).']]></description>
	<link><![CDATA[https://goldvoice.club/@'.get_user_login($m2['author']).'/'.$m2['permlink'].'/]]></link>
	<guid isPermaLink="false">goldvoice-club-'.$m2['id'].'</guid>
	<pubDate>'.date(DATE_RFC822,$m2['time']).'</pubDate>
</item>
';
					}
				}
			}
		}
	}
	else{
		while($m2=$sdb->posts("WHERE `status`=0 AND `parent_post`=0 ORDER BY `id` DESC LIMIT 100")){
			if($m2['id']){
				$pd=$db->sql_row("SELECT * FROM `posts_data` WHERE `post`='".$m2['id']."'");
				print '
<item>
<title><![CDATA['.fix_rss($pd['title']).']]></title>
<description><![CDATA['.fix_rss(text_to_view($pd['body'])).']]></description>
<link><![CDATA[https://goldvoice.club/@'.get_user_login($m2['author']).'/'.$m2['permlink'].'/]]></link>
<guid isPermaLink="false">goldvoice-club-'.$m2['id'].'</guid>
<pubDate>'.date(DATE_RFC822,$m2['time']).'</pubDate>
</item>
';
			}
		}
	}
	print'
</channel>
</rss>';
	$content=ob_get_contents();
	ob_end_clean();
	$cache->set($cache_name,$content,120);
	print $content;
}
exit;