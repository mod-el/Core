<?php namespace Model\Core;

class Module
{
	/** @var Core */
	public $model;
	/** @var mixed */
	public $module_id;
	/** @var array */
	public $methods = array();
	/** @var array */
	public $properties = array();
	/** @var mixed */
	private $configCache = null;

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

			if (file_exists(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '' . $classname . DIRECTORY_SEPARATOR . 'config.php')) {
				require(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '' . $classname . DIRECTORY_SEPARATOR . 'config.php');
				$this->configCache = $config;
			} else {
				$this->configCache = [];
			}
		}

		return $this->configCache;
	}

	/**
	 * This will trigger a new event in the Core, coming from this module.
	 * Each event has its own name (param $event) and eventually carries a bunch of data in the form of an array (param $data)
	 *
	 * @param string $event
	 * @param array $data
	 * @return bool
	 */
	protected function trigger(string $event, array $data = [])
	{
		return $this->model->trigger($this->getClass(), $event, $data);
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
		return $classname;
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
	 * @return array|bool
	 */
	public function getController(array $request, string $rule)
	{
		return false;
	}

	/**
	 * Meant to be extended if needed
	 *
	 * @param string $controller
	 * @param int|bool $id
	 * @param array $tags
	 * @param array $opt
	 * @return bool|string
	 */
	public function getUrl(string $controller = null, $id = false, array $tags = [], array $opt = [])
	{
		return false;
	}
}
