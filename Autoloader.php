<?php namespace Model\Core;

class Autoloader
{
	/** @var string[] */
	public static array $classes = [];
	/** @var string[] */
	public static array $aliases = [];
	/** @var array */
	public static array $fileTypes = [];
	/** @var string[] */
	public static array $namespaces = [];

	/**
	 * @param string $ns
	 * @param string $path
	 */
	public static function setNamespace(string $ns, string $path)
	{
		if (!isset(self::$namespaces[$ns]))
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
	static function autoload(string $className, bool $errors = true): bool
	{
		if ($className == 'FrontController') {
			require_once(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'FrontController.php');
			return true;
		}

		if (isset(self::$classes[$className])) {
			if (file_exists(self::$classes[$className])) {
				require_once(self::$classes[$className]);
			} else {
				return false;
			}
		} else {
			$found = false;
			foreach (self::$namespaces as $ns => $paths) {
				if (strpos($className, $ns) === 0) {
					$found = true;
					break;
				}
			}

			if ($found) {
				$fileFound = false;

				if (substr($ns, -1) !== '\\')
					$ns .= '\\';

				$path = substr($className, strlen($ns));
				$path = explode('\\', $path);
				$path = implode(DIRECTORY_SEPARATOR, $path);

				foreach ($paths as $base) {
					if (file_exists($base . DIRECTORY_SEPARATOR . $path . '.php')) {
						require_once($base . DIRECTORY_SEPARATOR . $path . '.php');
						$fileFound = true;
						break;
					}
				}

				if (!$fileFound)
					return false;
			} else {
				return false;
			}
		}

		$fullClassName = '\\' . $className;

		if (!class_exists($fullClassName, false) and !interface_exists($fullClassName, false) and !trait_exists($fullClassName, false)) {
			if ($errors)
				throw new \Exception('The file for "' . $className . '" exists, but the class/interface does not. Check the definition spelling inside that file!!');
			return false;
		}

		return true;
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param string|null $module
	 * @return string|null
	 */
	public static function searchFile(string $type, string $name, ?string $module = null): ?string
	{
		if (isset(self::$fileTypes[$type])) {
			if ($module !== null) {
				if (isset(self::$fileTypes[$type]['files'][$module][$name]))
					return self::$fileTypes[$type]['files'][$module][$name];
			} else {
				foreach (self::$fileTypes[$type]['files'] as $module => $files) {
					if (isset($files[$name]))
						return $files[$name];
				}
			}
		}
		return null;
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @return string|null
	 */
	public static function getModuleForFile(string $type, string $name): ?string
	{
		if (isset(self::$fileTypes[$type])) {
			foreach (self::$fileTypes[$type]['files'] as $module => $files) {
				if (isset($files[$name]))
					return $module;
			}
		}
		return null;
	}

	/**
	 * @param string $type
	 * @return array
	 */
	public static function getFilesByType(string $type): array
	{
		if (isset(self::$fileTypes[$type])) {
			return self::$fileTypes[$type]['files'];
		} else {
			return [];
		}
	}
}
