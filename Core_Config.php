<?php namespace Model;

class Core_Config {
	public function postUpdate($from, $to){
		$cacheFile = INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Core'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache.php';
		if(file_exists($cacheFile))
			unlink($cacheFile);
		file_put_contents(INCLUDE_PATH.'app'.DIRECTORY_SEPARATOR.'FrontController.php', str_replace('FrontController extends \\Model\\Core', 'FrontController extends \\Model\\Core\\Core', file_get_contents(INCLUDE_PATH.'app'.DIRECTORY_SEPARATOR.'FrontController.php')));
	}

	public function makeCache(){}
}
