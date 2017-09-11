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
	static function autoload($className, $errors=true){
		if($className=='FrontController'){
			require_once(realpath(dirname(__FILE__)).'/../../data/FrontController.php');
			return true;
		}

		if($className=='Model\Core'){
			require_once(realpath(dirname(__FILE__)).'/Core.php');
			return true;
		}

		$path = explode('\\', $className);

		if(count($path)==2 and $path[0]=='Model')
			array_shift($path);

		if(isset(self::$classes[$path[0]])){
			if(file_exists(self::$classes[$path[0]])){
				require_once(self::$classes[$path[0]]);
			}else{
				return false;
			}
		}else{
			return false;
		}

		$realClassName = '\\'.$className;

		if(!class_exists($realClassName, false)){
			if($errors){
				if(DEBUG_MODE)
					throw new \Exception('Esiste il file, ma non la classe "'.$className.'". Controllare i nomi!');
				else
					throw new \Exception('Errore tecnico durante il caricamento di una parte del sito ('.$className.'), si prega di riprovare fra un po\'. Se il problema persiste, contattare gentilmente il supporto.');
			}
			return false;
		}

		return true;
	}
}
