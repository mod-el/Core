<?php namespace Model\Core;

class Module_Config
{
	/** @var Core */
	protected $model;
	/** @var bool */
	public $configurable = false;
	/** @var bool */
	public $hasCleanUp = false;

	/**
	 * Module_Config constructor.
	 * @param Core $model
	 */
	public function __construct(Core $model)
	{
		$this->model = $model;
	}

	/**
	 * Utility method, returns the path of this module.
	 *
	 * @return string
	 */
	public function getPath()
	{
		$rc = new \ReflectionClass(get_class($this));
		return substr(dirname($rc->getFileName()), strlen(INCLUDE_PATH)) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Meant to be expanded by the specific module config class.
	 * It has to build the (possible) cache needed by the specific module to work.
	 *
	 * @return bool
	 */
	public function makeCache(): bool
	{
		return true;
	}

	/**
	 * If this module needs to register rules, this is the method that should return them.
	 * The sintax is: [
	 *        'rules'=>[
	 *            'idx'=>'rule',
	 *        ],
	 *        'controllers'=>[
	 *            'Controller1',
	 *            'Controller2',
	 *        ],
	 * ]
	 *
	 * @return array
	 */
	public function getRules(): array
	{
		return ['rules' => [], 'controllers' => []];
	}

	/**
	 * If the module has a configuration/installation page in the control panel, it's handled by this method
	 *
	 * @param array $request
	 * @return string
	 */
	public function getTemplate(array $request)
	{
		return null;
	}

	/**
	 * Executed after the first installation of the module
	 *
	 * @param array $data
	 * @return bool
	 */
	public function install(array $data = []): bool
	{
		return true;
	}

	/**
	 * This is called every time a POST request hits the configuration page
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function saveConfig(string $type, array $data): bool
	{
		$classname = $this->getModuleName();

		$configFile = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php';

		$w = file_put_contents($configFile, '<?php
$config = ' . var_export($data, true) . ';
');

		return (bool)$w;
	}

	/**
	 * Retrieves the configuration file of this module - if exists - and returns it.
	 *
	 * @return array
	 */
	public function retrieveConfig(): array
	{
		$classname = $this->getModuleName();

		if (file_exists(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php')) {
			require(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php');
			return $config;
		} else {
			return [];
		}
	}

	/**
	 * It has to return the required data for the configuration of the module via CLI, in the form of [ k => ['label'=>label, 'default'=>default], etc... ]
	 *
	 * @return array
	 */
	public function getConfigData(): array
	{
		return [];
	}

	/**
	 * Executes eventual postUpdate methods, called after every update
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function postUpdate(string $from, string $to): bool
	{
		if ($from == '0.0.0') // Fresh installation
			return true;

		$methods = $this->getPostUpdateMethods();

		foreach ($methods as $idx => $method) {
			$idx = explode('.', $idx);
			$idx = ((int)$idx[0]) . '.' . ((int)$idx[1]) . '.' . ((int)$idx[2]);

			if (($from !== null and version_compare($idx, $from) > 0) and version_compare($idx, $to) <= 0) {
				$res = call_user_func(array($this, $method));
				if (!$res) {
					if (method_exists($this, $method . '_Backup')) {
						call_user_func(array($this, $method . '_Backup'));
					}
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns a list of all postUpdate methods
	 *
	 * @return array
	 */
	private function getPostUpdateMethods(): array
	{
		$arr = [];

		$reflection = new \ReflectionClass($this);
		$methods = $reflection->getMethods();
		foreach ($methods as $m) {
			if (preg_match('/^postUpdate_[0-9]+_[0-9]+_[0-9]+$/', $m->name)) {
				$name = explode('_', $m->name);
				$idx = str_pad($name[1], 10, '0', STR_PAD_LEFT)
					. '.' . str_pad($name[2], 10, '0', STR_PAD_LEFT)
					. '.' . str_pad($name[3], 10, '0', STR_PAD_LEFT);
				$arr[$idx] = $m->name;
			}
		}

		ksort($arr);

		return $arr;
	}

	/**
	 * Returns the module name
	 *
	 * @return string
	 */
	private function getModuleName(): string
	{
		$reflector = new \ReflectionClass(get_class($this));
		return pathinfo(dirname($reflector->getFileName()), PATHINFO_FILENAME);
	}

	/**
	 * Meant to be extended by the moduels, it can be used to perform periodic cleanups (deletes old logs, etc)
	 */
	public function cleanUp()
	{
	}
}
