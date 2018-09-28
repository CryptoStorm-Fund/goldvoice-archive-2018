<?php
class digital_web{
	public $cookies;
	public $result_arr=array();
	public $request_arr=array();
	public $last_url;
	public $headers_arr=array();
	function digital_web(){
		$this->cookies=array();
		$this->result_arr=array();
		$this->request_arr=array();

		$this->headers_arr['Accept']='text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
		$this->headers_arr['Accept-Encoding']='gzip, deflate, sdch';
		$this->headers_arr['Accept-Language']='ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4';
		$this->headers_arr['Upgrade-Insecure-Requests']='1';
		$this->headers_arr['User-Agent']='Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36';
		//$this->headers_arr['Accept-Charset']='cp-1251';
	}
	function refresh_headers(){
		$this->headers_arr['Accept']='text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
		$this->headers_arr['Accept-Encoding']='gzip, deflate, sdch';
		$this->headers_arr['Accept-Language']='ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4';
		$this->headers_arr['Upgrade-Insecure-Requests']='1';
		$this->headers_arr['User-Agent']='Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36';
		//$this->headers_arr['Accept-Charset']='cp-1251';
	}
	function get_url($url,$ref=false,$post=array()){
		$this->last_url=$url;
		$method='GET';
		if($post){
			$method='POST';
		}
		preg_match('#://(.*)/#iUs',$url,$stock);
		preg_match('#://'.$stock[1].'/(.*)$#iUs',$url,$stock2);
		$host=$stock[1];
		$path=$stock2[1];
		$request=$method." /".$path." HTTP/1.1\r\n";
		$request.="Host: ".$host."\r\n";
		if($ref){
			$request.="Referer: ".$ref."\r\n";
		}
		foreach($this->headers_arr as $k=>$v){
			if($v){
				$request.=$k.': '.$v."\r\n";
			}
		}
		$cookie_str_arr=array();
		foreach($this->cookies as $k=>$v){
			$v=urlencode($v);
			$v=str_replace('%2B','+',$v);
			$v=str_replace('%2F','/',$v);
			$v=str_replace('%7B','{',$v);
			$v=str_replace('%22','"',$v);
			$v=str_replace('%3A',':',$v);
			$v=str_replace('%2C',',',$v);
			$v=str_replace('%5B','[',$v);
			$v=str_replace('%5D',']',$v);
			$v=str_replace('%7D','}',$v);

			$cookie_str_arr[]=urlencode($k).'='.$v;
		}
		$cookie_str=implode('; ',$cookie_str_arr);
		if($cookie_str){
			$request.="Cookie: ".$cookie_str."\r\n";
		}
		$request.="Connection: close\r\n";
		if(is_array($post)){
			$post_str_arr=array();
			foreach($post as $k=>$v){
				if('sr'!=$k){
					$v=urlencode($v);
					$v=str_replace('%2B','+',$v);
					$v=str_replace('%2F','/',$v);
					$v=str_replace('%7B','{',$v);
					$v=str_replace('%22','"',$v);
					$v=str_replace('%3A',':',$v);
					$v=str_replace('%2C',',',$v);
					$v=str_replace('%5B','[',$v);
					$v=str_replace('%5D',']',$v);
					$v=str_replace('%7D','}',$v);
				}
				$post_str_arr[]=urlencode($k).'='.$v;
			}
			$post_str=implode('&',$post_str_arr);
			$post_str=str_replace('--hide--','',$post_str);
			if($post_str){
				if($this->headers_arr['Content-Type']){
					$request.=$this->headers_arr['Content-Type']."\r\n";
				}
				else{
					$request.="Content-Type: application/x-www-form-urlencoded\r\n";
				}
				$request.="Content-Length: ".strlen($post_str)."\r\n\r\n";
				$request.=$post_str;
				$request.="\r\n";
			}
		}
		else{
			$request.="Content-Type: application/x-www-form-urlencoded\r\n";
			$request.="Content-Length: ".strlen($post)."\r\n\r\n";
			$request.=$post;
			$request.="\r\n";
		}
		$request.="\r\n";
		$this->request_arr[]=$request;
		$result='';
		$port=80;

		if(false!==strpos($url,'https://')){
			$port=443;
			$host='ssl://'.$host;
		}
		if ($sock=fsockopen($host, $port, $errno, $errstr, 4)){
			fwrite($sock,$request,strlen($request));
			while(!feof($sock)){
				$result.=fread($sock,1);
			}
			fclose($sock);
		}
		//print $request;
		$this->result_arr[]=$result;
		preg_match_all('~Set-Cookie: (.*)=(.*);~iUs',$result,$cookie_arr);
		foreach($cookie_arr[2] as $k=>$v){
			if(false!==strpos($v,'; ')){
				$v=substr($v,0,strpos($v,'; '));
			}
			if('deleted'==$v){
				$this->cookies[$cookie_arr[1][$k]]=$v;
			}
			else{
				$this->cookies[$cookie_arr[1][$k]]=$v;
			}
		}
		if(false!==strpos($result,'Location:')){
			preg_match('~Location: (.*)\n~iUs',$result,$stack);
			$next_url=trim($stack[1]," \r\n\t");
			if('/'==substr($next_url,0,1)){
				$next_url=substr($url,0,strpos($url,'/',7)).$next_url;
			}
			sleep(1);
			return $result.$this->get_url($next_url,$url,array());
		}
		return $result;
	}
}
function clear_chunked($str) {
	$arr=explode("\r\n",$str);
	$i=0;
	$count=count($arr);
	while($i<=$count){
		if(strlen($arr[$i])<=5){
			unset($arr[$i]);
			$i+=2;
		}
		else{
			$arr[$i]="\r\n".$arr[$i];
			$i+=1;
		}
	}
	$res=implode('',$arr);
	return $res;
}
function parse_web_result($fp){
	$headers=mb_substr($fp,0,mb_strpos($fp,"\r\n\r\n"));
	$clear_r=mb_substr($fp,mb_strpos($fp,"\r\n\r\n")+4);
	if(false!==strpos($headers,'Transfer-Encoding: chunked')){$clear_r=clear_chunked($clear_r);}
	if(false!==strpos($headers,'Content-Encoding: gzip')){$clear_r=gzdecode($clear_r);}
	return array($headers,$clear_r);
}