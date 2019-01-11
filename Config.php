<?php namespace Model\Core;

class Config extends Module_Config
{
	public $configurable = true;

	protected function assetsList()
	{
		$this->addAsset('data', 'cache.php', function () {
			$arr = [
				'classes' => [],
				'rules' => [],
				'controllers' => [],
				'modules' => [],
				'file-types' => [],
				'cleanups' => [],
			];
			return "<?php\n\$cache = " . var_export($arr, true) . ";\n";
		});
	}

	/**
	 * Caches the following:
	 * - All the available modules
	 * - All the rules registered by the modules
	 * - All the classes for the Autoloader to get to know them
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function makeCache(): bool
	{
		$classes = [];
		$fileTypes = [];
		$rules = [];
		$controllers = [];
		$modules = [];
		$cleanups = [];
		$zkpages = [];
		$methods = [];
		$properties = [];

		if (!is_dir(INCLUDE_PATH . 'app-data'))
			mkdir(INCLUDE_PATH . 'app-data');

		$dirs = [];

		$customDirs = glob(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . '*');
		foreach ($customDirs as $d)
			$dirs[] = $d;

		$modelDirs = glob(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . '*');
		foreach ($modelDirs as $d)
			$dirs[] = $d;

		// In the first loop, I look for all the different possible file types
		foreach ($dirs as $d) {
			if (!file_exists($d . DIRECTORY_SEPARATOR . 'manifest.json'))
				continue;

			$moduleData = json_decode(file_get_contents($d . DIRECTORY_SEPARATOR . 'manifest.json'), true);
			if ($moduleData === null)
				continue;

			if (isset($moduleData['file-types'])) {
				$d_info = pathinfo($d);

				foreach ($moduleData['file-types'] as $type => $typeData) {
					if (isset($fileTypes[$type]))
						$this->model->error('File type ' . $type . ' registered by two different modules; can\'t proceed.');
					$typeData['module'] = $d_info['filename'];
					$typeData['files'] = [];
					$fileTypes[$type] = $typeData;
				}
			}
		}

		// In the second loop, I look for everything else (I can now search through the folders for the custom file types, since I know them)
		foreach ($dirs as $d) {
			$d_info = pathinfo($d);
			$modules[$d_info['filename']] = [
				'path' => substr($d, strlen(INCLUDE_PATH)),
				'load' => file_exists($d . DIRECTORY_SEPARATOR . $d_info['filename'] . '.php'),
				'custom' => in_array($d, $customDirs),
				'js' => [],
				'css' => [],
				'dependencies' => [],
				'assets-position' => 'head',
				'version' => '0.0.0',
				'initialized' => in_array($d, $customDirs) ? true : false,
			];

			if (file_exists($d . DIRECTORY_SEPARATOR . 'manifest.json')) {
				$moduleData = json_decode(file_get_contents($d . DIRECTORY_SEPARATOR . 'manifest.json'), true);
			} else {
				$moduleData = null;
			}

			if ($moduleData !== null) {
				if (isset($moduleData['load']) and !$moduleData['load'])
					$modules[$d_info['filename']]['load'] = false;
				if (isset($moduleData['version']))
					$modules[$d_info['filename']]['version'] = $moduleData['version'];
				if (isset($moduleData['js']))
					$modules[$d_info['filename']]['js'] = $moduleData['js'];
				if (isset($moduleData['css']))
					$modules[$d_info['filename']]['css'] = $moduleData['css'];
				if (isset($moduleData['assets-position']))
					$modules[$d_info['filename']]['assets-position'] = $moduleData['assets-position'];
				if (isset($moduleData['dependencies']) and is_array($moduleData['dependencies']))
					$modules[$d_info['filename']]['dependencies'] = array_keys($moduleData['dependencies']);

				if (isset($moduleData['bind-methods']) and is_array($moduleData['bind-methods'])) {
					foreach ($moduleData['bind-methods'] as $coreMethod => $moduleMethod) {
						if (isset($methods[$coreMethod]))
							$this->model->error('Cannot bind method ' . $coreMethod . ' to ' . $d_info['filename'] . '; name already taken');
						$methods[$coreMethod] = [
							'module' => $d_info['filename'],
							'method' => $moduleMethod,
						];
					}
				}

				if (isset($moduleData['bind-properties']) and is_array($moduleData['bind-properties'])) {
					foreach ($moduleData['bind-properties'] as $coreProperty => $moduleProperty) {
						if (isset($properties[$coreProperty]))
							$this->model->error('Cannot bind property ' . $coreProperty . ' to ' . $d_info['filename'] . '; name already taken');
						$properties[$coreProperty] = [
							'module' => $d_info['filename'],
							'property' => $moduleProperty,
						];
					}
				}
			}

			$vars_file = $d . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vars.php';
			if (file_exists($vars_file)) {
				require($vars_file);
				if (isset($vars['installed']) and $vars['installed'])
					$modules[$d_info['filename']]['initialized'] = true;
			}

			$files = glob($d . DIRECTORY_SEPARATOR . '*');
			foreach ($files as $f) {
				if (is_dir($f))
					continue;
				$file = pathinfo($f);
				if ($file['extension'] != 'php')
					continue;

				$fullClassName = 'Model\\' . $d_info['filename'] . '\\' . $file['filename'];
				$classes[$fullClassName] = $f;
			}

			if (file_exists($d . DIRECTORY_SEPARATOR . 'Config.php')) {
				require_once($d . DIRECTORY_SEPARATOR . 'Config.php');
				$configClassName = '\\Model\\' . $d_info['filename'] . '\\Config';
				$configClass = new $configClassName($this->model);

				if ($configClass->hasCleanUp)
					$cleanups[] = $d_info['filename'];

				$moduleRules = $configClass->getRules();
				if (!is_array($moduleRules) or !isset($moduleRules['rules'], $moduleRules['controllers']))
					throw new \Exception('The module ' . $d_info['filename'] . ' returned an invalid format for rules.');

				foreach ($moduleRules['rules'] as $rIdx => $r) {
					$rules[] = [
						'rule' => $r,
						'module' => $d_info['filename'],
						'idx' => $rIdx,
					];
				}

				foreach ($moduleRules['controllers'] as $c) {
					$controllers[] = [
						'controller' => $c,
						'module' => $d_info['filename'],
					];
				}
			}

			if (isset($moduleData['zk-pages'])) {
				foreach ($moduleData['zk-pages'] as $p)
					$zkpages[] = $p;
			}

			foreach ($fileTypes as $type => $typeData) {
				if (is_dir($d . DIRECTORY_SEPARATOR . $typeData['folder'])) {
					$files = $this->getModuleFiles($d . DIRECTORY_SEPARATOR . $typeData['folder'], $typeData['class']);
					foreach ($files as $f => $fPath) {
						if ($typeData['class']) {
							$fullName = 'Model\\' . $d_info['filename'] . '\\' . $typeData['folder'] . '\\' . $f;
							$classes[$fullName] = $fPath;
						} else {
							$fullName = $fPath;
						}
						if (!isset($fileTypes[$type]['files'][$d_info['filename']]))
							$fileTypes[$type]['files'][$d_info['filename']] = [];
						$fileTypes[$type]['files'][$d_info['filename']][$f] = $fullName;
					}
				}
			}
		}

		uksort($rules, function ($a, $b) {
			if ($a === '')
				return 1;
			if ($b === '')
				return -1;
			return 0;
		});

		sort($cleanups);

		$cache = [
			'classes' => $classes,
			'rules' => $rules,
			'controllers' => $controllers,
			'modules' => $modules,
			'file-types' => $fileTypes,
			'cleanups' => $cleanups,
			'zk-pages' => $zkpages,
			'methods' => $methods,
			'properties' => $properties,
		];

		$cacheDir = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data';
		if (!is_dir($cacheDir))
			mkdir($cacheDir, 0777, true);

		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'cache.php';
		$scrittura = file_put_contents($cacheFile, '<?php
$cache = ' . var_export($cache, true) . ';
');
		if (!$scrittura)
			return false;

		$this->model->reloadCacheFile();

		return true;
	}

	/**
	 * Router must be updated in order for it to pass the correct rules
	 *
	 * @return array
	 */
	public function cacheDependencies(): array
	{
		return ['Router'];
	}

	/**
	 * @param string $path
	 * @param bool $isClass
	 * @return array
	 */
	private function getModuleFiles(string $path, bool $isClass): array
	{
		$return = [];

		$files = glob($path . DIRECTORY_SEPARATOR . '*');
		foreach ($files as $f) {
			$f_info = pathinfo($f);
			if (is_dir($f)) {
				$subFiles = $this->getModuleFiles($f, $isClass);
				foreach ($subFiles as $sf => $sfPath) {
					$separator = $isClass ? '\\' : DIRECTORY_SEPARATOR;
					$return[$f_info['filename'] . $separator . $sf] = $sfPath;
				}
			} else {
				$return[$f_info['filename']] = $f;
			}
		}

		return $return;
	}

	/**
	 * The Core module needs a "zk" rule to manage the basics of the framework
	 *
	 * @return array
	 */
	public function getRules(): array
	{
		return [
			'rules' => [
				'zk' => 'zk',
			],
			'controllers' => [
				'Zk',
			],
		];
	}

	/**
	 * Returns the config template
	 *
	 * @param string $type
	 * @return string|null
	 */
	public function getTemplate(string $type): ?string
	{
		return $type === 'config' ? 'config' : null;
	}

	/**
	 * Saves the configuration
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 * @throws \Exception
	 */
	public function saveConfig(string $type, array $data): bool
	{
		$config = $this->retrieveConfig();

		$configFile = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'config.php';

		$dataKeys = $this->getConfigData();
		foreach ($dataKeys as $k => $d) {
			if (isset($data[$k]))
				$config[$k] = $data[$k];
		}

		$w = file_put_contents($configFile, '<?php
$config = ' . var_export($config, true) . ';
');
		return (bool)$w;
	}

	/**
	 * @return array
	 */
	public function getConfigData(): ?array
	{
		$config = $this->retrieveConfig();

		return [
			'repository' => [
				'label' => 'Repository',
				'default' => isset($config['repository']) ? $config['repository'] : 'https://www.netrails.net/repository',
			],
			'license' => [
				'label' => 'License Key',
				'default' => isset($config['license']) ? $config['license'] : '',
			],
		];
	}
}
