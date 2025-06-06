<?php namespace Model\Core;

class Module_Config
{
	/** @var Core */
	protected Core $model;
	/** @var bool */
	public bool $configurable = false;
	/** @var bool */
	public bool $hasCleanUp = false;
	/** @var array */
	private array $assets = [];

	/**
	 * Module_Config constructor.
	 * @param Core $model
	 */
	public function __construct(Core $model)
	{
		$this->model = $model;
		$this->assetsList();
	}

	/**
	 * Utility method, returns the name of this module.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getName(): string
	{
		$rc = new \ReflectionClass(get_class($this));
		return pathinfo(dirname($rc->getFileName()), PATHINFO_FILENAME);
	}

	/**
	 * Utility method, returns the path of this module.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getPath(): string
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
	 * Must return a list of modules on which this module depends on, in order to build a correct internal cache
	 *
	 * @return array
	 */
	public function cacheDependencies(): array
	{
		return [];
	}

	/**
	 * If this module needs to register rules, this is the method that should return them.
	 * The syntax is: [
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
	 * @param string $type ("init" or "config")
	 * @return string|null
	 */
	public function getTemplate(string $type): ?string
	{
		return $type;
	}

	/**
	 * First initialization of the module
	 * Has to return true in case of success, or false if more data are needed (the user will be redirected to the init page)
	 * Has to throw an exception in case of failure
	 *
	 * @param array|null $data
	 * @return bool
	 */
	public function init(?array $data = null): bool
	{
		return true;
	}

	/**
	 * This is called every time a POST request hits the configuration page
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 * @throws \Exception
	 */
	public function saveConfig(string $type, array $data): bool
	{
		$classname = $this->getModuleName();

		$configFile = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php';
		$dirName = dirname($configFile);
		if (!is_dir($dirName))
			mkdir($dirName, 0755, true);

		$w = file_put_contents($configFile, '<?php
$config = ' . var_export($data, true) . ';
');

		return (bool)$w;
	}

	/**
	 * Retrieves the configuration file of this module - if exists - and returns it.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function retrieveConfig(): array
	{
		$classname = $this->getModuleName();

		if (file_exists(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php')) {
			require(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $classname . DIRECTORY_SEPARATOR . 'config.php');
			return $config ?? [];
		} else {
			return [];
		}
	}

	/**
	 * It has to return the required data for the configuration of the module via CLI, in the form of [ k => ['label'=>label, 'default'=>default], etc... ]
	 *
	 * @return array|null
	 */
	public function getConfigData(): ?array
	{
		return null;
	}

	/**
	 * Executes eventual postUpdate methods, called after every update
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 * @throws \Exception
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
	 * @throws \Exception
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
	 * @throws \Exception
	 */
	private function getModuleName(): string
	{
		$reflector = new \ReflectionClass(get_class($this));
		return pathinfo(dirname($reflector->getFileName()), PATHINFO_FILENAME);
	}

	/**
	 * Meant to be extended by the modules, it can be used to perform periodic cleanups (deletes old logs, etc)
	 */
	public function cleanUp(): void
	{
	}

	/**
	 * Meant to be extended by the modules, it will consist in a series of "addAsset" calls, telling the framework what files this module needs
	 */
	protected function assetsList(): void
	{
	}

	/**
	 * Add an asset, namely a file or a folder that this module needs
	 * If the file is null, only the main folder will be created (useful for the main config dir)
	 *
	 * @param string $type
	 * @param string|null $file
	 * @param callable|null $defaultContent
	 * @return bool
	 * @throws Exception
	 */
	protected function addAsset(string $type, ?string $file = null, ?callable $defaultContent = null): bool
	{
		if (!in_array($type, ['data', 'config', 'app-data']))
			$this->model->error('Unknown asset type in module definition');

		$this->assets[] = [
			'type' => $type,
			'file' => $file,
			'default' => $defaultContent,
		];

		return true;
	}

	/**
	 * Checks if all assets are created - it's called whenever the user visits the control panel
	 *
	 * @throws \Exception
	 */
	public function checkAssets(): void
	{
		foreach ($this->assets as $asset) {
			switch ($asset['type']) {
				case 'data':
					$base_dir = INCLUDE_PATH . $this->getPath() . 'data';
					break;
				case 'config':
					$base_dir = INCLUDE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $this->getName();
					break;
				case 'app-data':
					$base_dir = INCLUDE_PATH . DIRECTORY_SEPARATOR . 'app-data' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $this->getName();
					break;
				default:
					$this->model->error('Unknown asset type encountered while checking assets');
					break;
			}

			$file = $base_dir . ($asset['file'] ? (DIRECTORY_SEPARATOR . $asset['file']) : '');
			if (!file_exists($file)) {
				if ($asset['default'] !== null) { // If there is default content, then it's a file
					$dir = pathinfo($file, PATHINFO_DIRNAME);
					if (!is_dir($dir))
						mkdir($dir, 0777, true);

					$content = call_user_func($asset['default']);
					if ($content !== null and $content !== false)
						file_put_contents($file, $content);
				} else { // Otherwise, it's a directory
					mkdir($file, 0777, true);
				}
			}
		}
	}

	/**
	 * @param string $file
	 * @param string $default
	 */
	protected function checkFile(string $file, string $default): void
	{
		if (file_exists(INCLUDE_PATH . $file))
			return;

		$dir = pathinfo(INCLUDE_PATH . $file, PATHINFO_DIRNAME);
		if (!is_dir($dir))
			mkdir($dir, 0777, true);

		file_put_contents(INCLUDE_PATH . $file, $default);
	}

	/**
	 * @param string $type
	 * @param string $file
	 * @return object|null
	 */
	public function getFileInstance(string $type, string $file): ?object
	{
		return null;
	}
}
