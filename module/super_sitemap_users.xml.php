<?php
$count=0;
header('Content-type: text/xml;');
$char_sitemap=false;
$char=$db->prepare(mb_substr($_GET['char'],0,1,'UTF-8'));
if(preg_match('~[a-z]~iUs',$char)){
	$char_sitemap=true;
}
$cache_name='sitemap-users';
if($char_sitemap){
	$cache_name.='-'.$char;
}
$cbuf=$cache->get($cache_name);
if($cbuf){
	print $cbuf;
}
else{
	ob_start();
	print '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url>
<loc>https://goldvoice.club/</loc>
<changefreq>always</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>1.0</priority>
</url>
';
	if($char_sitemap){
		while($m=$sdb->users("WHERE `status`=0 AND `login` LIKE '".$char."%' AND `last_post_time`!=0")){
			print '
<url>
<loc>https://goldvoice.club/@'.$m['login'].'/</loc>
<changefreq>daily</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>0.9</priority>
</url>
';
			while($m2=$sdb->posts("WHERE `author`='".$m['id']."' AND `status`=0 AND `parent_post`=0 ORDER BY `id` DESC LIMIT 1000")){
				print '
<url>
<loc>https://goldvoice.club/@'.$m['login'].'/'.$m2['permlink'].'/</loc>
<changefreq>daily</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>0.5</priority>
</url>
';
			}
		}
	}
	else{
		while($m=$sdb->users("WHERE `status`=0 AND `last_post_time`!=0")){
			print '
<url>
<loc>https://goldvoice.club/@'.$m['login'].'/</loc>
<changefreq>daily</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>0.9</priority>
</url>
';
		}
	}
	print'</urlset>';
	$content=ob_get_contents();
	ob_end_clean();
	$cache->set($cache_name,$content,36000);
	print $content;
}
exit;