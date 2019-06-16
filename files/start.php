<?php
session_start();

$baseDirectory = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR;

// Setup the Autoloader
include_once($baseDirectory . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php');

\Model\Core\Autoloader::setNamespace('Model\\', $baseDirectory . 'model');
\Model\Core\Autoloader::setNamespace('Model\\', $baseDirectory . 'app');

spl_autoload_register(function ($className) {
	return \Model\Core\Autoloader::autoload($className);
});

// Utility functions
include_once('func.php');
// Custom functions
include_once($baseDirectory . 'app/func-extra.php');
