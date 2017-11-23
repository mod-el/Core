<?php namespace Model\Core;

class Autoloader{
	/** @var string[] */
	public static $classes = [];
	/** @var string[] */
	public static $aliases = [];
	/** @var string[] */
	public static $namespaces = [];

	/**
	 * @param string $ns
	 * @param string $path
	 */
	public static function setNamespace($ns, $path){
		if(!isset(self::$namespaces[$ns]))
			self::$namespaces[$ns] = [];
		self::$namespaces[$ns][] = $path;
	}

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

		$realClassName = self::getRealClassName($className);

		if($realClassName and isset(self::$classes[$realClassName])){
			if(file_exists(self::$classes[$realClassName])){
				require_once(self::$classes[$realClassName]);
				$className = $realClassName;
			}else{
				return false;
			}
		}else{
			$found = false;
			foreach(self::$namespaces as $ns => $paths){
				if(strpos($className, $ns)===0){
					$found = true;
					break;
				}
			}

			if($found){
				$fileFound = false;

				if(substr($ns, -1)!=='\\')
					$ns .= '\\';

				$path = substr($className, strlen($ns));
				$path = explode('\\', $path);
				$path = implode(DIRECTORY_SEPARATOR, $path);

				foreach($paths as $base){
					if(file_exists($base.DIRECTORY_SEPARATOR.$path.'.php')){
						require_once($base.DIRECTORY_SEPARATOR.$path.'.php');
						$fileFound = true;
						break;
					}
				}

				if(!$fileFound)
					return false;
			}else{
				return false;
			}
		}

		$fullClassName = '\\'.$className;

		if(!class_exists($fullClassName, false) and !interface_exists($fullClassName, false)){
			if($errors)
				throw new \Exception('The file for "'.$className.'" exists, but the class/interface does not. Check the definition spelling inside that file!!');
			return false;
		}

		return true;
	}

	/**
	 * Classes can be loaded through aliases (usually is the class identifier without namespace)
	 *
	 * @param string $className
	 * @return string
	 */
	public static function getRealClassName($className){
		if(isset(self::$classes[$className])){
			return $className;
		}elseif(isset(self::$aliases[$className])){
			return self::$aliases[$className];
		}else{
			return null;
		}
	}
}
