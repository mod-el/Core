<?php
include_once(realpath(dirname(__FILE__)).'/../Autoloader.php');

spl_autoload_register(function($className){
	return \Model\Autoloader::autoload($className);
});

include_once('func.php');
include_once(realpath(dirname(__FILE__)).'/../../../data/func-extra.php');

// Prevenzione degli attacchi CSRF
if(!isset($_SESSION['csrf']))
	$_SESSION['csrf'] = md5(uniqid(rand(), true));

function checkCsrf(){
	if(isset($_POST['c_id']) and $_POST['c_id']==$_SESSION['csrf']) return true;
	else return false;
}

function csrfInput(){
	echo '<input type="hidden" name="c_id" value="'.$_SESSION['csrf'].'" />';
}