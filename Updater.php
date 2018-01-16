<?php namespace Model\Core;

class Updater
{
	/** @var bool|array */
	private $queue = false;
	/** @var string */
	private $queue_file;
	/** @var Core */
	private $model;

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
	public function getModules($get_updates = false, $base_dir = '')
	{
		$modules = [];

		$dirs = glob(INCLUDE_PATH . $base_dir . 'model' . DIRECTORY_SEPARATOR . '*');
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
							if ($m->md5 != $m->expected_md5)
								$m->corrupted = true;
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
	public function firstInit($name)
	{
		if (!file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data'))
			mkdir(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data');
		$file_path = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vars.php';

		if (!file_exists($file_path)) {
			file_put_contents($file_path, "<?php\n");
			@chmod($file_path, 0755);
		}

		$text = file_get_contents($file_path);

		if (stripos($text, '$installed') !== false) {
			$return = (bool)file_put_contents($file_path, preg_replace('/\$installed ?=.+;/i', '$installed = true;', $text));
		} else {
			$return = (bool)file_put_contents($file_path, $text . "\n" . '$installed = true;' . "\n");
		}

		$configClass = $this->getConfigClassFor($name);
		if ($configClass) {
			try {
				$configClass->makeCache();
			} catch (\Exception $e) {

			}
		}

		return true;
	}

	/**
	 * Retrieves file list for a module from the repository
	 *
	 * @param string $name
	 * @return array|bool
	 */
	public function getModuleFileList($name)
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
	 */
	public function updateFile($name, $file)
	{
		$config = $this->model->retrieveConfig();

		$content = file_get_contents($config['repository'] . '?act=get-file&module=' . urlencode($name) . '&file=' . urlencode($file) . '&key=' . urlencode($config['license']));
		if ($content == 'File not found')
			$this->model->error('File ' . $file . ' not found');

		$arr_path = explode(DIRECTORY_SEPARATOR, $file);
		$buildingPath = '';
		foreach ($arr_path as $f) {
			if (stripos($f, '.') !== false) break; // File
			if (!is_dir(INCLUDE_PATH . $buildingPath . $f))
				mkdir(INCLUDE_PATH . $buildingPath . $f);
			$buildingPath .= $f . DIRECTORY_SEPARATOR;
		}

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
	 */
	public function finalizeUpdate($name, array $delete)
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
	private function deleteDirectory($folder, $onlyEmpties = false)
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
	private function recursiveCopy($source, $dest)
	{
		$folder = INCLUDE_PATH . $source . DIRECTORY_SEPARATOR;
		$ff = glob($folder . '*');
		foreach ($ff as $f) {
			$name = substr($f, strlen($folder));
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
	 */
	public function cliUpdate($module)
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
	public function showCliPercentage($string, $c, $tot_steps)
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
	public function checkUpdateQueue($module)
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
	public function getUpdateQueue()
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
	public function setUpdateQueue(array $queue)
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
	public function downloadableModules()
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
	 * @return \Model\Core\Module_Config|bool
	 */
	public function getConfigClassFor($name)
	{
		if (file_exists(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'Config.php')) {
			require_once(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'Config.php');
			$configClass = '\\Model\\' . $name . '\\Config';
			if (!class_exists($configClass, false))
				return false;
			$configClass = new $configClass($this->model);
			return $configClass;
		} else {
			return false;
		}
	}

	/**
	 * Module configuration via CLI
	 *
	 * @param string $module
	 * @param string $type
	 * @return bool
	 */
	public function cliConfig($module, $type)
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
				if ($configClass->install($data)) {
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
}
