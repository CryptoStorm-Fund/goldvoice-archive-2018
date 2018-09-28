<?php
class DataManagerCache {
	//private $db;
	private $redis;
	public function __construct(){
		global $db,$redis;
		//$this->db=&$db;
		$this->redis=&$redis;
	}
	public function get($name){
		return $this->redis->get('cache:'.$name);
		return false;
		/*
		$c_q=$this->db->sql("SELECT `value` FROM `cache` WHERE `name`='".$this->db->prepare($name)."' AND `expire`>'".time()."'");
		$c_m=$this->db->row($c_q);
		if($c_m['value']){
			return $c_m['value'];
		}
		else{
			return false;
		}
		*/
	}
	public function set($name,$value,$expire=1800){
		$this->redis->set('cache:'.$name,$value);
		$this->redis->expire('cache:'.$name,$expire);
		/*
		$this->db->sql("DELETE FROM `cache` WHERE `name`='".$this->db->prepare($name)."'");
		$this->db->sql("INSERT INTO `cache` (`name`,`value`,`expire`) VALUES ('".$this->db->prepare($name)."','".$this->db->prepare($value)."','".(time()+$expire)."')");
		*/
	}
	public function stats(){
		//$c_q=$this->db->sql("SHOW TABLE STATUS FROM `".$this->db->basename."` WHERE `Name`='cache'");
		//$c_m=$this->db->row($c_q);
		//return "cache: table ".$c_m[0].", rows ".$c_m[4].", size ".round($c_m[6]/1024,2)."Kb;";
	}
}