<?php
$count=0;
header('Content-type: text/xml;');
$cbuf=$cache->get('sitemap');
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
	while($m=$sdb->tags("WHERE `status`=0 ORDER BY `posts` DESC LIMIT 250")){
		print '
<url>
<loc>https://goldvoice.club/tags/'.$m['en'].'/</loc>
<changefreq>daily</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>0.8</priority>
</url>
';
	}
	while($m=$sdb->categories("WHERE `status`=0 ORDER BY `posts` DESC LIMIT 100")){
		print '
<url>
<loc>https://goldvoice.club/categories/'.$m['name'].'/</loc>
<changefreq>daily</changefreq>
<lastmod>'.date('c').'</lastmod>
<priority>0.8</priority>
</url>
';
	}
	print'</urlset>';
	$content=ob_get_contents();
	ob_end_clean();
	$cache->set('sitemap',$content,36000);
	print $content;
}
exit;