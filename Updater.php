<?php namespace Model\Core;

class Updater
{
	/** @var bool|array */
	private $queue = false;
	/** @var string */
	private $queue_file;
	/** @var Core */
	private $model;
	/** @var array */
	private $updating = [];

	/**
	 * @param Core $model
	 */
	function __construct(Core $model)
	{
		$this->model = $model;
		$this->queue_file = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update-queue.php';
	}

	/**
	 * Get a list of the current installed modules
	 * If $get_updates is true, check on the repository if a new version is available
	 *
	 * @param bool $get_updates
	 * @param string $base_dir
	 * @return ReflectionModule[]
	 */
	public function getModules(bool $get_updates = false, string $base_dir = 'model'): array
	{
		$modules = [];

		$dirs = glob(INCLUDE_PATH . $base_dir . DIRECTORY_SEPARATOR . '*');
		foreach ($dirs as $f) {
			$name = explode(DIRECTORY_SEPARATOR, $f);
			$name = end($name);
			$module = new ReflectionModule($name, $this->model, $base_dir);
			if ($module->exists)
				$modules[$name] = $module;
		}

		if ($get_updates) {
			$config = $this->model->retrieveConfig();

			$modules_arr = [];
			foreach ($modules as $m)
				$modules_arr[] = $m->folder_name . '|' . $m->version;

			$remote_str = file_get_contents($config['repository'] . '?act=get-modules&modules=' . urlencode(implode(',', $modules_arr)) . '&key=' . urlencode($config['license']));
			$remote = json_decode($remote_str, true);
			if ($remote !== null) {
				foreach ($modules as $name => &$m) {
					if (isset($remote[$name]) and $remote[$name]) {
						$m->official = true;

						if ($remote[$name]['old_md5']) {
							$m->expected_md5 = $remote[$name]['old_md5'];
							if ($m->md5 != $m->expected_md5) {
								if ($m->expected_md5 != $m->version_md5) {
									$m->new_version = true;
								} else {
									$m->corrupted = true;
								}
							}
						}
						if ($remote[$name]['current_version'] and version_compare($remote[$name]['current_version'], $m->version, '>'))
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
	 * @param $name
	 * @return bool
	 */
	public function firstInit(string $name): bool
	{
		if (!file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data'))
			mkdir(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data');

		$this->changeModuleInternalVar($name, 'installed', true);

		$module = new ReflectionModule($name, $this->model);
		$this->changeModuleInternalVar($name, 'md5', $module->md5);

		$configClass = $this->getConfigClassFor($name);
		if ($configClass) {
			try {
				$configClass->makeCache();
			} catch (\Exception $e) {

			}
		}

		return true;
	}

	protected function changeModuleInternalVar(string $name, string $k, $v): bool
	{
		$file_path = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vars.php';

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

		$vars[$k] = $v;

		return (bool)file_put_contents($file_path, "<?php\n\$vars = " . var_export($vars, true) . ";\n");
	}

	/**
	 * Retrieves file list for a module from the repository
	 *
	 * @param string $name
	 * @return array|bool
	 */
	public function getModuleFileList(string $name)
	{
		$config = $this->model->retrieveConfig();

		$files = file_get_contents($config['repository'] . '?act=get-files&module=' . urlencode($name) . '&key=' . urlencode($config['license']) . '&md5');
		$files = json_decode($files, true);
		if (!$files)
			return false;

		$filesToUpdate = [];
		$filesToDelete = [];
		$filesArr = [];
		foreach ($files as $f) {
			$filesArr[] = $f['path'];
			if (file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $f['path'])) {
				$md5 = md5(file_get_contents(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $f['path']));
				if ($md5 != $f['md5'])
					$filesToUpdate[] = $f['path'];
			} else {
				$filesToUpdate[] = $f['path'];
			}
		}

		$module = new ReflectionModule($name, $this->model);
		foreach ($module->files as $f) {
			if (!in_array(str_replace(DIRECTORY_SEPARATOR, '/', $f['path']), $filesArr))
				$filesToDelete[] = $f['path'];
		}

		return ['update' => $filesToUpdate, 'delete' => $filesToDelete];
	}

	/**
	 * Retrieves a single file from the repository and writes it in the temp folder
	 *
	 * @param string $name
	 * @param string $file
	 * @return bool
	 * @throws Exception
	 */
	public function updateFile(string $name, string $file): bool
	{
		$config = $this->model->retrieveConfig();

		$content = file_get_contents($config['repository'] . '?act=get-file&module=' . urlencode($name) . '&file=' . urlencode($file) . '&key=' . urlencode($config['license']));
		if ($content == 'File not found')
			$this->model->error('File ' . $file . ' not found');

		$temppath = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $file;
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
	 * @param string $name
	 * @param array $delete
	 * @return bool
	 * @throws \Exception
	 */
	public function finalizeUpdate(string $name, array $delete): bool
	{
		$old_version = null;

		$module = new ReflectionModule($name, $this->model);
		$old_version = $module->version;

		foreach ($delete as $f) {
			unlink(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $f);
		}
		if (!$this->deleteDirectory('model' . DIRECTORY_SEPARATOR . $name, true))
			return false;
		if (!$this->recursiveCopy('model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp', 'model' . DIRECTORY_SEPARATOR . $name))
			return false;
		if (!$this->deleteDirectory('model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'temp'))
			return false;

		$config = $this->model->retrieveConfig();
		$remote_str = file_get_contents($config['repository'] . '?act=get-modules&modules=' . urlencode($name) . '&key=' . urlencode($config['license']));
		$remote = json_decode($remote_str, true);
		if (!isset($remote[$name]))
			return false;
		$this->changeModuleInternalVar($name, 'md5', $remote[$name]['md5']);

		$coreConfig = new Config($this->model);
		$coreConfig->makeCache();

		$module = new ReflectionModule($name, $this->model);
		if ($module->hasConfigClass()) {
			$new_version = $module->version;

			$configClass = $module->getConfigClass();
			if ($configClass) {
				$postUpdate = $configClass->postUpdate($old_version, $new_version);
				if (!$postUpdate)
					return false;
				$configClass->makeCache();
			}
		}

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

		$ff = glob(INCLUDE_PATH . $folder . DIRECTORY_SEPARATOR . '*');
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
	 * Module update via CLI
	 *
	 * @param string $module
	 * @throws \Exception
	 */
	public function cliUpdate(string $module)
	{
		$this->checkUpdateQueue($module);

		echo "--------------------\n";
		echo "Updating " . $module . "\n";
		echo "--------------------\n";

		$files = $this->getModuleFileList($module);

		if (!$files) {
			echo "File list not found\n";
		} elseif (!$files['update'] and !$files['delete']) {
			echo "Nothing to update\n";
		} else {
			$cf = 0;
			$tot_steps = count($files['update']) + 2;

			foreach ($files['update'] as $f) {
				$cf++;

				$this->showCliPercentage('Downloading ' . $f, $cf, $tot_steps);

				if (!$this->updateFile($module, $f)) {
					die("Error in file download, aborting.\n");
				}
			}

			$cf++;
			$this->showCliPercentage('Finalizing update', $cf, $tot_steps);

			if (!$this->finalizeUpdate($module, $files['delete']))
				die("Error in finalizing update, aborting.\n");

			$cf++;
			$this->showCliPercentage('Update successful', $cf, $tot_steps);
		}

		if (count($this->queue) > 0) {
			$this->cliUpdate($this->queue[0]);
		}
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
	 * Checks if the next module is the one in the update queue, and eventually removes it
	 *
	 * @param string $module
	 * @return bool
	 */
	public function checkUpdateQueue(string $module): bool
	{
		if ($this->queue === false)
			$this->getUpdateQueue();

		if (count($this->queue) > 0 and $this->queue[0] == $module) {
			array_shift($this->queue);
			file_put_contents($this->queue_file, "<?php\n\$queue = " . var_export($this->queue, true) . ";");
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns current update queue
	 *
	 * @return array
	 */
	public function getUpdateQueue(): array
	{
		if ($this->queue === false) {
			if (file_exists($this->queue_file)) {
				include($this->queue_file);
				$this->queue = $queue;
			} else {
				$this->queue = [];
			}
		}
		return $this->queue;
	}

	/**
	 * Sets the update queue
	 *
	 * @param array $queue
	 * @return bool
	 */
	public function setUpdateQueue(array $queue): bool
	{
		$this->queue = $queue;
		$w = file_put_contents($this->queue_file, "<?php\n\$queue = " . var_export($queue, true) . ";\n");
		return (bool)$w;
	}

	/**
	 * List of installable modules from repository
	 *
	 * @return array
	 */
	public function downloadableModules(): array
	{
		$config = $this->model->retrieveConfig();

		$modules = $this->getModules();

		$remote_str = file_get_contents($config['repository'] . '?act=get-modules&key=' . urlencode($config['license']));
		$remote = json_decode($remote_str, true);
		if ($remote !== null) {
			$return = array();
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
	public function getConfigClassFor(string $name)
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
	 * @param string $type
	 * @return bool
	 * @throws \Exception
	 */
	public function cliConfig(string $module, string $type): bool
	{
		$configClass = $this->getConfigClassFor($module);
		if (!$configClass)
			return true;

		$configData = $configClass->getConfigData();

		$data = [];
		if ($configData) {
			echo "-------------------\nConfiguration of " . $this->model->getRequest(3) . "...\n";
			if (!$this->model->getInput('skip-input'))
				echo "Leave data empty to keep defaults\n";
			echo "\n";
			if (!$this->model->getInput('skip-input'))
				$handle = fopen("php://stdin", "r");
			foreach ($configData as $k => $d) {
				if ($this->model->getInput('skip-input')) {
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
			if (!$this->model->getInput('skip-input'))
				fclose($handle);
		}

		switch ($type) {
			case 'config':
				if ($configClass->saveConfig('config', $data)) {
					echo "Configuration saved\n";
					return true;
				}
				break;
			case 'init':
				if ($this->install($configClass, $data)) {
					$this->firstInit($module);
					echo "----------------------\n";
					echo "Module " . $module . " initialized\n";
					echo "----------------------\n";
					return true;
				} else {
					echo "Some error occurred while installing.\n";
				}
				break;
		}

		return false;
	}

	/**
	 * Installs a module
	 *
	 * @param Module_Config $configClass
	 * @param array $data
	 * @return bool
	 * @throws \Exception
	 */
	public function install(Module_Config $configClass, array $data = []): bool
	{
		$configClass->checkAssets();
		return $configClass->install($data);
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
		if (in_array($module, $this->updating)) {
			$this->model->error('Cache update loop');
		} else {
			$this->updating[] = $module;
		}
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
					if (!is_array($sub_updated))
						return null;
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

	public function getModulesPriority(array $modules): array
	{
		$priorities = [
			'Core' => 0,
		];
		$c = 0;
		while (count($priorities) < count($modules)) {
			foreach ($modules as $m) {
				if (isset($priorities[$m->folder_name]))
					continue;

				$allSet = true;
				$score = 0;

				foreach ($m->dependencies as $dep => $version) {
					if (isset($priorities[$dep])) {
						$score = max($score, $priorities[$dep]);
					} else {
						$allSet = false;
						break;
					}
				}

				if ($allSet)
					$priorities[$m->folder_name] = $score + 1;
			}

			$c++;
			if ($c === 1000)
				$this->model->error('Infinite loop, maybe one of the modules has broken dependencies.');
		}

		return $priorities;
	}
}
