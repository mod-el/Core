<?php
/***
ModEl Framework
Autoloader
 ***/

namespace Model;

class Autoloader{
	/** @var string[] */
	public static $classes = [];

	/**
	 * Autoloader for classes of ModEl Framework.
	 *
	 * @param string $className
	 * @param bool $errors
	 * @return bool
	 * @throws \Exception
	 */
	static function autoload($className, $errors = true){
		if($className=='FrontController'){
			require_once(realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'FrontController.php');
			return true;
		}

		if($className=='Model\Core'){
			require_once(realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'Core.php');
			return true;
		}

		$path = explode('\\', $className);
		if($path[0]==='Model')
			array_shift($path);
		$path = implode('\\', $path);

		if(isset(self::$classes[$path])){
			if(file_exists(self::$classes[$path])){
				require_once(self::$classes[$path]);
			}else{
				return false;
			}
		}else{
			return false;
		}

		$realClassName = '\\'.$className;

		if(!class_exists($realClassName, false) and !interface_exists($realClassName, false)){
			if($errors)
				throw new \Exception('The file for "'.$className.'" exists, but the class/interface does not. Check the definition spelling inside that file!!');
			return false;
		}

		return true;
	}
}
