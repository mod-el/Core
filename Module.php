<?php namespace Model\Core;

class Module implements ModuleInterface
{
	/** @var Core */
	public Core $model;
	/** @var mixed */
	public $module_id;
	/** @var array|null */
	public ?array $configCache = null;

	/**
	 * Module constructor.
	 * @param Core $front
	 * @param mixed $idx
	 */
	function __construct(Core $front, $idx = 0)
	{
		$this->model = $front;
		$this->module_id = $idx;
	}

	/**
	 * Meant to be expanded in the specific module.
	 *
	 * @param array $options
	 */
	public function init(array $options)
	{
	}

	/**
	 * This method is called by the "terminate" method of the Core, at the end of each execution.
	 */
	public function terminate()
	{
	}

	/**
	 * Utility method, returns the path of this module.
	 *
	 * @return string
	 */
	public function getPath(): string
	{
		$rc = new \ReflectionClass(get_class($this));
		return substr(dirname($rc->getFileName()), strlen(INCLUDE_PATH)) . '/';
	}

	/**
	 * Retrieves the configuration file of this module - if exists - and returns it.
	 *
	 * @return array
	 */
	public function retrieveConfig(): array
	{
		if ($this->configCache === null) {
			$classname = $this->getClass();

			if (file_exists(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php')) {
				require(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php');
				$this->configCache = $config;
			} else {
				$this->configCache = [];
			}
		}

		return $this->configCache;
	}

	/**
	 * Clears stored config data
	 *
	 * @param array $options
	 * @return bool
	 */
	public function reloadConfig(array $options = []): bool
	{
		$this->configCache = null;
		return true;
	}

	/**
	 * Returns the non-namespaced class name of this module.
	 *
	 * @return string
	 */
	protected function getClass(): string
	{
		$classname = get_class($this);
		if ($pos = strrpos($classname, '\\')) // Get the non-namespaced class name
			$classname = substr($classname, $pos + 1);
		return $classname === 'DbOld' ? 'Db' : $classname; // Trick per coesistenza con nuova libreria model/db
	}

	/**
	 * A loaded module can, optionally, put content in the "head" section of the page
	 */
	public function headings()
	{
	}

	/**
	 * Meant to be extended if needed
	 *
	 * @param array $request
	 * @param string $rule
	 * @return array|null
	 */
	public function getController(array $request, string $rule): ?array
	{
		return null;
	}

	/**
	 * Meant to be extended if needed
	 *
	 * @param string $controller
	 * @param string|null $id
	 * @param array $tags
	 * @param array $opt
	 * @return string|null
	 */
	public function getUrl(?string $controller = null, ?string $id = null, array $tags = [], array $opt = []): ?string
	{
		return null;
	}
}
