<?php
class DataManagerEssence{
	public $db;
	public $t;
	public $sdb;
	public $cache;
	public function __construct(){
		global $db,$t,$sdb,$cache;
		$this->db=&$db;
		$this->t=&$t;
		$this->sdb=&$sdb;
		$this->cache=&$cache;
	}
}