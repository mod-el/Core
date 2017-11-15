<?php
session_start();

// Setup the Autoloader
include_once(realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Autoloader.php');

spl_autoload_register(function($className){
	return \Model\Autoloader::autoload($className);
});

// Utility functions
include_once('func.php');
// Custom functions
include_once(realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'app/func-extra.php');