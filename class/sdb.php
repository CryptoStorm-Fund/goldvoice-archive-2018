<?php
class DataManagerSuperDatabaseNode {
	private $db;
	private $name;
	private $q;
	private $q_status;
	public function __construct($name){
		global $db;
		$this->db=&$db;
		$this->name=$name;
	}
	public function __call($name,$attr){		$q_hash=md5($this->name.serialize($name).serialize($attr));
		if($this->q_status[$q_hash]){
			--$this->q_status[$q_hash];
			$result=$this->db->row($this->q[$q_hash]);
			return (isset($result[$name])?$result[$name]:false);
		}		$where=$attr[0];
		if(is_array($where)){
			$addon='WHERE';
			$num=0;
			foreach($where as $k=>$v){
				if($num!=0){
					$addon.=' AND ';
				}
				$addon.=' `'.$this->db->prepare($k).'`=\''.$this->db->prepare($v).'\' ';
				$num++;
			}
			$sql='SELECT `'.$name.'` FROM `'.$this->name.'` '.$addon;
		}
		else{
			if(is_string($where)){
				$sql='SELECT `'.$name.'` FROM `'.$this->name.'` '.$where;
			}
			else{
				$sql='SELECT `'.$name.'` FROM `'.$this->name.'`';
			}
		}
		if(isset($attr[1])){			$sql.=$attr[1];
		}
		$this->q[$q_hash]=$this->db->sql($sql);
		$this->q_status[$q_hash]=mysqli_num_rows($this->q[$q_hash]);
		$result=$this->db->row($this->q[$q_hash]);

		return ($result[$name]?$result[$name]:false);
	}
}
class DataManagerSuperDatabase {	private $db;
	private $q;
	private $q_status;
	private $nodes;
	public function __construct(){
		global $db;
		$this->db=&$db;
		$this->q=array();
		$this->q_status=array();
		$this->nodes=array();
	}
	public function __call($name,$attr){
		$q_hash=md5(serialize($name).serialize($attr));
		if(0==count($attr)){			if(!isset($this->nodes[$q_hash])){				$this->nodes[$q_hash]=new DataManagerSuperDatabaseNode($name);
			}			return $this->nodes[$q_hash];
		}
		if($this->q_status[$q_hash]){
			--$this->q_status[$q_hash];
			return $result=$this->db->row($this->q[$q_hash]);
		}
		$where=$attr[0];
		if(is_array($where)){			$addon='WHERE';
			$num=0;			foreach($where as $k=>$v){				if($num!=0){					$addon.=' AND ';
				}
				$addon.=' `'.$this->db->prepare($k).'`=\''.$this->db->prepare($v).'\' ';
				$num++;
			}
			$sql='SELECT * FROM `'.$name.'` '.$addon;
		}
		else{			if(is_string($where)){
				$sql='SELECT * FROM `'.$name.'` '.$where;
			}
			else{				$sql='SELECT * FROM `'.$name.'`';
			}
		}
		if(isset($attr[1])){
			$sql.=$attr[1];
		}
		$this->q[$q_hash]=$this->db->sql($sql);
		$this->q_status[$q_hash]=mysqli_num_rows($this->q[$q_hash]);
		$result=$this->db->row($this->q[$q_hash]);

		return ($result?$result:false);
	}
}