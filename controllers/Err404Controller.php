<?php
class Err404Controller extends \Model\Core\Controller {
	function init(){
		header("HTTP/1.0 404 Not Found");
		$this->viewOptions['cacheTemplate'] = false;
	}
}
