<?php
class DataManagerUser extends DataManagerEssence {
	public $info;
	public $group;
	public $perm;
	public $status=false;
	public function form(){
		return '<form class="login" action="" method="POST">
		<input type="text" name="email"> &mdash; Электронная почта<br>
		<input type="password" name="password"> &mdash; Пароль<br>
		<input type="submit" value="Выполнить вход">
		</form>';
	}
	public function set_cookies(){
		@setcookie('email',$this->info['email'],0,'/');
		@setcookie('password',$this->info['password'],0,'/');
		if($_SERVER['HTTP_REFERER']){
			header('location:'.$_SERVER['HTTP_REFERER']);
		}
		elseif($_SERVER['REQUEST_URI']){
			header('location:'.$_SERVER['REQUEST_URI']);
		}
	}
	public function logout(){
		@setcookie('email','',0,'/');
		@setcookie('password','',0,'/');
		if($_SERVER['HTTP_REFERER']){
			header('location:'.$_SERVER['HTTP_REFERER']);
		}
		else{
			header('location:/');
		}
	}
	public function login(){
		if(!isset($_POST['email'])){
			return FALSE;
		}
		if(!isset($_POST['password'])){
			return FALSE;
		}
		$where=array(
			'email'=>$_POST['email'],
			'password'=>md5($_POST['password'])
		);
		$this->info=$this->sdb->users($where);
		if($this->info['id']>0){
			$this->status=true;
			$this->group=$this->info['group'];
			$this->info=array_merge($this->info,$this->sdb->users_info(array('id'=>$this->info['id'])));
			return TRUE;
		}
		else{
			return FALSE;
		}
	}
	public function auth(){
		if(!isset($_COOKIE['email'])){
			return FALSE;
		}
		if(!isset($_COOKIE['password'])){
			return FALSE;
		}
		$where=array(
			'email'=>$_COOKIE['email'],
			'password'=>$_COOKIE['password']
		);
		$this->info=$this->sdb->users($where);
		if($this->info['id']>0){
			$this->status=true;
			$this->group=$this->info['group'];
			$this->info=array_merge($this->info,$this->sdb->users_info(array('id'=>$this->info['id'])));
			$this->db->sql("UPDATE `users_info` SET `action_time`='".time()."' WHERE `id`='".$this->info['id']."'");
			return TRUE;
		}
		else{
			return FALSE;
		}
	}
	public function load_perms(){
		$where=array(
			'gid'=>($this->group)
		);
		while($perm=$this->sdb->perms($where)){
			$this->perm[$perm['name']]=$perm['value'];
		}
		$where=array(
			'uid'=>($this->info['id'])
		);
		while($perm=$this->sdb->perms($where)){
			$this->perm[$perm['name']]=$perm['value'];
		}
	}
	public function check_perm($name){
		if($this->perm[$name]){
			return $this->perm[$name];
		}
		else{
			return false;
		}
	}
}