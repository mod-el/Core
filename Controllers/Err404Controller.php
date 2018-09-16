<?php namespace Model\Core\Controllers;

use Model\Core\Controller;

class Err404Controller extends Controller
{
	function init()
	{
		header("HTTP/1.0 404 Not Found");
		$this->model->viewOptions['cacheTemplate'] = false;
	}
}
