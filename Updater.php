<?php namespace Model\Core;

use MJS\TopSort\Implementations\StringSort;

class Updater
{
	private Core $model;
	private array $updating = [];
	private array $moduleVarsCache = [];

	/**
	 * @param Core $model
	 */
	function __construct(Core $model)
	{
		$this->model = $model;
	}

	/**
	 * Get a list of the current installed modules
	 * If $get_updates is true, check on the repository if a new version is available
	 *
	 * @param bool $get_updates
	 * @param bool $load_md5
	 * @param string|null $base_dir
	 * @return ReflectionModule[]
	 */
	public function getModules(bool $get_updates = false, bool $load_md5 = true, ?string $base_dir = null): array
	{
		$modules = [];

		if ($base_dir === null) {
			$cacheModules = $this->model->listModules();

			foreach ($cacheModules as $m) {
				if ($m['custom'])
					continue;
				if (!is_dir(INCLUDE_PATH . $m['path']))
					mkdir(INCLUDE_PATH . $m['path'], 0777, true);

				$modules[$m['name']] = new ReflectionModule($m['name'], $this->model, $load_md5);
			}
		} else { // For Repository module
			$dirs = glob(INCLUDE_PATH . $base_dir . DIRECTORY_SEPARATOR . '*');
			foreach ($dirs as $f) {
				if (!is_dir($f))
					continue;
				$name = explode(DIRECTORY_SEPARATOR, $f);
				$name = end($name);
				$module = new ReflectionModule($name, $this->model, $load_md5, $base_dir);
				if ($module->exists)
					$modules[$name] = $module;
			}
		}

		if ($get_updates) {
			$config = $this->model->retrieveConfig();

			$modules_arr = [];
			foreach ($modules as $m)
				$modules_arr[] = $m->folder_name . '|' . ($m->version ?? '0.0.0');

			$remote_str = file_get_contents($config['repository'] . '?act=get-modules&modules=' . urlencode(implode(',', $modules_arr)) . '&key=' . urlencode($config['license']));
			$remote = json_decode($remote_str, true);
			if ($remote !== null) {
				foreach ($modules as $name => &$m) {
					if (isset($remote[$name]) and $remote[$name]) {
						$m->official = true;

						if ($remote[$name]['old_md5']) {
							$m->expected_md5 = $remote[$name]['old_md5'];
							if ($m->md5 === $m->expected_md5) {
								if ($m->md5 !== $m->version_md5)
									$this->changeModuleInternalVar($name, 'md5', $m->md5);
							} else {
								if ($m->expected_md5 !== $m->version_md5)
									$m->new_version = $m->version;
								else
									$m->corrupted = true;
							}
						}
						if ($remote[$name]['current_version'] and version_compare($remote[$name]['current_version'], $m->version ?? '0.0.0', '>'))
							$m->new_version = $remote[$name]['current_version'];
					} else {
						$m->official = false;
					}
				}
				unset($m);
			}
		}

		return $modules;
	}

	/**
	 * Sets the internal status of a module as installed
	 *
	 * @param string $name
	 * @param Module_Config $configClass
	 * @return bool
	 */
	public function markAsInitialized(string $name, ?Module_Config $configClass = null): bool
	{
		if (!file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data'))
			mkdir(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data');

		$this->changeModuleInternalVar($name, 'installed', true);

		$module = new ReflectionModule($name, $this->model);
		$this->changeModuleInternalVar($name, 'md5', $module->md5);

		if ($configClass) {
			try {
				$configClass->makeCache();
			} catch (\Exception $e) {
			}
		}

		$coreConfigClass = $this->getConfigClassFor('Core');
		$coreConfigClass->makeCache();

		return true;
	}

	/**
	 * @param string $name
	 * @param array|null $data
	 * @return bool
	 */
	public function initModule(string $name, ?array $data = null): bool
	{
		$configClass = $this->getConfigClassFor($name);
		if ($configClass) {
			$configClass->checkAssets();
			if ($configClass->init($data))
				return $this->markAsInitialized($name, $configClass);
			elseif ($data === null)
				return false;
			else
				$this->model->error('Something is wrong, can\'t initialize module ' . $name);
		} else {
			return $this->markAsInitialized($name);
		}
	}

	/**
	 * Retrieves module internal vars and caches them
	 *
	 * @param string $name
	 * @return array
	 */
	protected function getModuleInternalVars(string $name): array
	{
		if (!isset($this->moduleVarsCache[$name])) {
			$folder_path = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data';
			if (!is_dir($folder_path))
				mkdir($folder_path, 0755, true);

			$file_path = $folder_path . DIRECTORY_SEPARATOR . 'vars.php';
			if (!file_exists($file_path)) {
				file_put_contents($file_path, "<?php\n");
				@chmod($file_path, 0755);
			}

			require($file_path);

			if (!isset($vars)) {
				$vars = [
					'installed' => false,
					'md5' => null,
				];
			}

			$this->moduleVarsCache[$name] = $vars;
		}

		return $this->moduleVarsCache[$name];
	}

	/**
	 * @param string $name
	 * @param string $k
	 * @param $v
	 * @return bool
	 */
	protected function changeModuleInternalVar(string $name, string $k, $v): bool
	{
		$vars = $this->getModuleInternalVars($name);

		$vars[$k] = $v;
		$this->moduleVarsCache[$name][$k] = $v;

		$file_path = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vars.php';
		return (bool)file_put_contents($file_path, "<?php\n\$vars = " . var_export($vars, true) . ";\n");
	}

	/**
	 * Retrieves file list for a module from the repository
	 *
	 * @param array $modules
	 * @return array|null
	 */
	public function getFilesList(array $modules): ?array
	{
		$config = $this->model->retrieveConfig();

		$files = file_get_contents($config['repository'] . '?act=get-files&modules=' . urlencode(implode(',', $modules)) . '&key=' . urlencode($config['license']) . '&md5');
		$files = json_decode($files, true);
		if (!$files)
			return null;

		$filesToUpdate = [];
		$filesToDelete = [];
		$filesArr = [];
		foreach ($files as $f) {
			$filesArr[] = $f['module'] . '/' . $f['path'];
			if (file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $f['module'] . DIRECTORY_SEPARATOR . $f['path'])) {
				$md5 = md5(file_get_contents(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $f['module'] . DIRECTORY_SEPARATOR . $f['path']));
				if ($md5 != $f['md5'])
					$filesToUpdate[] = $f['module'] . DIRECTORY_SEPARATOR . $f['path'];
			} else {
				$filesToUpdate[] = $f['module'] . DIRECTORY_SEPARATOR . $f['path'];
			}
		}

		foreach ($modules as $name) {
			$module = new ReflectionModule($name, $this->model);
			foreach ($module->files as $f) {
				if (!in_array($name . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $f['path']), $filesArr))
					$filesToDelete[] = $name . DIRECTORY_SEPARATOR . $f['path'];
			}
		}

		return [
			'update' => $filesToUpdate,
			'delete' => $filesToDelete,
		];
	}

	/**
	 * Retrieves a single file from the repository and writes it in the temp folder
	 *
	 * @param string $file
	 * @return bool
	 * @throws Exception
	 */
	public function updateFile(string $file): bool
	{
		$config = $this->model->retrieveConfig();

		$content = file_get_contents($config['repository'] . '?act=get-file&file=' . urlencode($file) . '&key=' . urlencode($config['license']));
		if ($content == 'File not found')
			$this->model->error('File ' . $file . ' not found');

		$temppath = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $file;
		$path = pathinfo($temppath, PATHINFO_DIRNAME);

		if (!is_dir($path))
			mkdir($path, 0755, true);
		$saving = file_put_contents($temppath, $content);
		if ($saving !== false) {
			@chmod($temppath, 0755);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * After all files have been downloaded, this method copies them all at once in the correct location, and executes post updates
	 *
	 * @param string[] $modules
	 * @param string[] $delete
	 * @return bool
	 */
	public function finalizeUpdate(array $modules, array $delete): bool
	{
		$config = $this->model->retrieveConfig();
		$remote_str = file_get_contents($config['repository'] . '?act=get-modules&modules=' . urlencode(implode(',', $modules)) . '&key=' . urlencode($config['license']));
		$remote = json_decode($remote_str, true);

		foreach ($delete as $f)
			unlink(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $f);

		foreach ($modules as $name) {
			$module = new ReflectionModule($name, $this->model);
			$old_version = $module->version ?? '0.0.0';

			if (!$this->deleteDirectory('model' . DIRECTORY_SEPARATOR . $name, true))
				return false;
			if (!$this->recursiveCopy('model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $name, 'model' . DIRECTORY_SEPARATOR . $name))
				return false;

			if (isset($remote[$name]))
				$this->changeModuleInternalVar($name, 'md5', $remote[$name]['md5']);

			$coreConfig = new Config($this->model);
			$coreConfig->makeCache();

			$module = new ReflectionModule($name, $this->model);
			if ($module->hasConfigClass()) {
				$new_version = $module->version;

				$configClass = $module->getConfigClass();
				if ($configClass) {
					if ($old_version and $old_version !== '0.0.0') {
						$postUpdate = $configClass->postUpdate($old_version, $new_version);
						if (!$postUpdate)
							return false;
					}
					$configClass->makeCache();
				}
			}
		}

		if (!$this->deleteDirectory('model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp'))
			return false;

		if (isset($_SESSION['update-queue']))
			unset($_SESSION['update-queue']);

		return true;
	}

	/**
	 * Delete a directory (if onlyEmpties is true, it just cleans the empty directories)
	 *
	 * @param string $folder
	 * @param bool $onlyEmpties
	 * @return bool
	 */
	private function deleteDirectory(string $folder, bool $onlyEmpties = false): bool
	{
		if (!file_exists(INCLUDE_PATH . $folder))
			return true;

		$ff = glob(INCLUDE_PATH . $folder . DIRECTORY_SEPARATOR . '{*,.[!.]*,..?*}', GLOB_BRACE);
		foreach ($ff as $f) {
			$f_name = substr($f, strlen(INCLUDE_PATH . $folder . DIRECTORY_SEPARATOR));
			if (is_dir($f)) {
				if (!$this->deleteDirectory($folder . DIRECTORY_SEPARATOR . $f_name, $onlyEmpties))
					return false;
			} elseif (!$onlyEmpties) {
				unlink($f);
			}
		}
		if (!$onlyEmpties or count($ff) == 0)
			return rmdir(INCLUDE_PATH . $folder);

		return true;
	}

	/**
	 * Recursive copy of a folder into a destination
	 *
	 * @param string $source
	 * @param string $dest
	 * @return bool
	 */
	private function recursiveCopy(string $source, string $dest): bool
	{
		$folder = INCLUDE_PATH . $source . DIRECTORY_SEPARATOR;
		$ff = glob($folder . '*');
		foreach ($ff as $f) {
			$name = substr($f, strlen($folder));

			$destFile = $dest . DIRECTORY_SEPARATOR . $name;
			$destDir = dirname(INCLUDE_PATH . $destFile);
			if (!is_dir($destDir))
				mkdir($destDir, 0755, true);

			if (is_dir($f)) {
				if (!file_exists(INCLUDE_PATH . $dest . DIRECTORY_SEPARATOR . $name))
					mkdir(INCLUDE_PATH . $dest . DIRECTORY_SEPARATOR . $name);
				$this->recursiveCopy($source . DIRECTORY_SEPARATOR . $name, $dest . DIRECTORY_SEPARATOR . $name);
			} else {
				copy($f, INCLUDE_PATH . $dest . DIRECTORY_SEPARATOR . $name);
			}
		}

		return true;
	}

	/**
	 * Modules update via CLI
	 *
	 * @param ReflectionModule[]|null $modules
	 */
	public function cliUpdate(?array $modules = null)
	{
		if ($modules === null) {
			echo "Updating all modules\n";

			$modules = $this->getModules(true);
		} else {
			echo "Updating " . count($modules) . " modules\n";
		}

		$realModules = [];
		foreach ($modules as $module) {
			if ($module->new_version or $module->corrupted)
				$realModules[] = $module;
		}
		$modules = $realModules;

		echo count($modules) . " modules to update\n";
		if (count($modules) === 0)
			return;

		$modules = $this->topSortModules($modules);

		$modulesNames = array_map(function ($module) {
			return $module->folder_name;
		}, $modules);

		echo "(" . implode(', ', $modulesNames) . ")\n";

		$files = $this->getFilesList($modulesNames);
		if (!$files)
			die("File list not found\n");

		if (!$files['update'] and !$files['delete'])
			die("No file to update\n");

		echo count($files['update']) . " files to update\n";
		echo count($files['delete']) . " files to delete\n";

		$cf = 0;
		$tot_steps = count($files['update']) + 2;

		foreach ($files['update'] as $f) {
			$cf++;

			$this->showCliPercentage('Downloading ' . $f, $cf, $tot_steps);

			if (!$this->updateFile($f))
				die("Error in file download, aborting.\n");
		}

		$cf++;
		$this->showCliPercentage('Finalizing update', $cf, $tot_steps);

		if (!$this->finalizeUpdate($modulesNames, $files['delete']))
			die("Error in finalizing update, aborting.\n");

		$cf++;
		$this->showCliPercentage('Update successful', $cf, $tot_steps);
	}

	/**
	 * Calculates and nicely prints current percentage
	 *
	 * @param string $string
	 * @param int $c
	 * @param int $tot_steps
	 */
	public function showCliPercentage(string $string, int $c, int $tot_steps)
	{
		$perc = round($c / $tot_steps * 100);

		echo str_pad($string, 60, ' ', STR_PAD_RIGHT);
		echo str_pad(implode('', array_fill(0, $perc, '*')), 100, ' ', STR_PAD_RIGHT) . '  ';
		echo $perc . "%\n";
	}

	/**
	 * List of installable modules from repository
	 *
	 * @return array
	 */
	public function downloadableModules(): array
	{
		$config = $this->model->retrieveConfig();

		$modules = $this->getModules(false, false);

		$remote_str = file_get_contents($config['repository'] . '?act=get-modules&key=' . urlencode($config['license']));
		$remote = json_decode($remote_str, true);
		if ($remote !== null) {
			$return = [];
			foreach ($remote as $m => $mod) {
				if (array_key_exists($m, $modules))
					continue;
				$return[$m] = $mod;
			}
			return $return;
		} else {
			return [];
		}
	}

	/**
	 * @param string $name
	 * @return \Model\Core\Module_Config|null
	 */
	public function getConfigClassFor(string $name): ?Module_Config
	{
		if (file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'Config.php')) {
			require_once(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'Config.php');
			$configClass = '\\Model\\' . $name . '\\Config';
			if (!class_exists($configClass, false))
				return null;
			$configClass = new $configClass($this->model);
			return $configClass;
		} else {
			return null;
		}
	}

	/**
	 * Module configuration via CLI
	 *
	 * @param string $module
	 * @param string $type ("init" or "config")
	 * @param bool $skipInput
	 * @param bool $verbose
	 * @return bool
	 * @throws \Exception
	 */
	public function cliConfig(string $module, string $type, bool $skipInput = false, bool $verbose = false): bool
	{
		$configClass = $this->getConfigClassFor($module);
		if ($configClass) {
			$configData = $configClass->getConfigData();
			if ($configData === null) {
				echo "Module " . $module . " cannot be configured via CLI\n";
				return false;
			}
		} else {
			if ($type === 'config')
				return true;

			$configData = [];
		}

		$data = [];

		if ($verbose) {
			echo "-------------------\nConfiguration of " . $module . "...\n";
			if (!$skipInput)
				echo "Leave data empty to keep defaults\n";
			echo "\n";
		}

		if (!$skipInput)
			$handle = fopen("php://stdin", "r");
		foreach ($configData as $k => $d) {
			if ($skipInput) {
				$data[$k] = $d['default'];
			} else {
				echo $d['label'] . ($d['default'] !== null ? ' (default ' . $d['default'] . ')' : '') . ': ';
				$line = trim(fgets($handle));
				if ($line)
					$data[$k] = $line;
				else
					$data[$k] = $d['default'];
			}
		}
		if (!$skipInput)
			fclose($handle);

		switch ($type) {
			case 'config':
				if ($configClass->saveConfig('config', $data)) {
					if ($verbose)
						echo "Configuration saved\n";
					return true;
				}
				break;
			case 'init':
				if ($this->initModule($module, $data)) {
					if ($verbose) {
						echo "----------------------\n";
						echo "Module " . $module . " initialized\n";
						echo "----------------------\n";
					}
					return true;
				} else {
					if ($verbose)
						echo "Some error occurred while installing.\n";
				}
				break;
		}

		return false;
	}

	/**
	 * Updates a module (and its dependencies) cache
	 * Returns a list of updated modules
	 *
	 * @param string $module
	 * @return array|null
	 * @throws Exception
	 */
	public function updateModuleCache(string $module): array
	{
		$this->updating = [];
		return $this->internalUpdateCache($module);
	}

	/**
	 * Recursive, called by the previous method
	 *
	 * @param string $module
	 * @return array
	 * @throws Exception
	 */
	private function internalUpdateCache(string $module): array
	{
		if (in_array($module, $this->updating))
			$this->model->error('Cache update loop');

		$this->updating[] = $module;

		$updated = [];
		$configClass = $this->getConfigClassFor($module);
		if ($configClass) {
			$dependencies = $configClass->cacheDependencies();
			foreach ($dependencies as $dep) {
				// Updates all the dependencies
				// Eventual recursive loops between dependency modules (e.g. between Core, Router and ORM) will be ignored thanks to the try-catch block
				// This is because the modules will be updated by the next function call in the controller anyway, so there's no need to stop the whole process
				try {
					$sub_updated = $this->internalUpdateCache($dep);
					$updated = array_merge($updated, $sub_updated);
				} catch (Exception $e) {
				}
			}
			if (!$configClass->makeCache())
				$this->model->error('Error in making cache for module ' . $module);
			$updated[] = $module;
		}
		return $updated;
	}

	/**
	 * @param ReflectionModule[] $modules
	 * @return ReflectionModule[]
	 */
	public function topSortModules(array $modules): array
	{
		$sorter = new StringSort;

		foreach ($modules as $module) {
			$dependencies = array_keys($module->dependencies);
			if ($module->folder_name !== 'Core' and !in_array('Core', $dependencies)) // Every module depends upon the Core
				$dependencies[] = 'Core';

			$dependencies = array_filter($dependencies, function ($module) {
				return $this->model->moduleExists($module);
			});

			$sorter->add($module->folder_name, $dependencies);
		}

		$sorted = $sorter->sort();

		$sortedModules = [];
		foreach ($sorted as $moduleName)
			$sortedModules[$moduleName] = $modules[$moduleName];

		return $sortedModules;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function addModuleToCache(string $name): bool
	{
		$cache = $this->model->retrieveCacheFile();
		$cache['modules'][$name] = [
			'path' => 'model' . DIRECTORY_SEPARATOR . $name,
			'load' => false,
			'custom' => false,
			'js' => [],
			'css' => [],
			'dependencies' => [],
			'assets-position' => 'head',
			'defer-js' => false,
			'async-js' => false,
			'defer-css' => false,
			'version' => '0.0.0',
			'initialized' => false,
		];

		$cacheFile = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache.php';
		$scrittura = file_put_contents($cacheFile, '<?php
$cache = ' . var_export($cache, true) . ';
');
		$this->model->reloadCacheFile();
		return (bool)$scrittura;
	}

	/**
	 * @param string $module
	 * @return bool
	 */
	public function deleteModule(string $module): bool
	{
		if (strpos($module, '/') !== false)
			throw new Exception('Invalid module name');

		if (is_dir(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $module)) {
			if (!$this->deleteDirectory('model' . DIRECTORY_SEPARATOR . $module))
				return false;
		}

		if (is_dir(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $module)) {
			if (!$this->deleteDirectory('app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $module))
				return false;
		}

		$cache = $this->model->retrieveCacheFile();
		if (array_key_exists($module, $cache['modules']))
			unset($cache['modules'][$module]);

		$cacheFile = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache.php';
		file_put_contents($cacheFile, '<?php
$cache = ' . var_export($cache, true) . ';
');

		return true;
	}
}
