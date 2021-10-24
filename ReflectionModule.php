<?php namespace Model\Core;

class ReflectionModule
{
	protected string $base_dir = '';
	protected string $path;
	public bool $exists = false;
	protected Core $model;
	public string $name;
	public string $folder_name;
	public string $description;
	public array $dependencies = [];
	public bool $installed = false;
	public string $version;
	public array $files = [];
	public ?string $md5 = null;
	public ?string $version_md5 = null;
	public ?bool $official = null;
	private ?Module_Config $configClass = null;
	public bool $configClassCompatible = true;

	public string $expected_md5;
	public bool $new_version = false;
	public bool $corrupted = false;

	/**
	 * @param string $name
	 * @param Core $model
	 * @param string $base_dir
	 */
	function __construct(string $name, Core $model, string $base_dir = 'model')
	{
		$this->folder_name = $name;
		$this->model = $model;
		$this->base_dir = $base_dir;
		$this->path = INCLUDE_PATH . $this->base_dir . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;

		$manifestExists = $this->loadManifest();

		if ($manifestExists) {
			$this->exists = true;
		} else {
			$this->name = $this->folder_name;

			if ($base_dir === 'model') {
				$this->exists = false;
				return;
			} else {
				$this->exists = true;
				$this->name = $name;
			}
		}

		$this->files = $this->getFiles($this->path);

		$vars_file = INCLUDE_PATH . $this->base_dir . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'vars.php';

		$status_vars = [
			'installed' => false,
			'md5' => null,
		];

		if (file_exists($vars_file)) {
			require($vars_file);

			if (isset($vars)) {
				$status_vars = array_merge($status_vars, $vars);
			}

			if (isset($installed))
				$status_vars['installed'] = $installed;
		}

		$this->installed = $status_vars['installed'];
		$this->version_md5 = $status_vars['md5'];

		$md5 = [];
		foreach ($this->files as $f)
			$md5[] = $f['md5'];
		sort($md5);
		$this->md5 = md5(implode('', $md5));
	}

	/**
	 * Loads the module manifest file
	 *
	 * @return bool
	 */
	private function loadManifest(): bool
	{
		if (file_exists($this->path . 'manifest.json')) {
			$moduleData = json_decode(file_get_contents($this->path . 'manifest.json'), true);
			if ($moduleData === null)
				return false;

			$this->name = $moduleData['name'];
			$this->description = $moduleData['description'];
			$this->version = $moduleData['version'];
			$this->dependencies = $moduleData['dependencies'];

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns an array with all the files of the module and their MD5
	 *
	 * @param string $folder
	 * @return array
	 */
	private function getFiles(string $folder): array
	{
		$files = [];
		$ff = glob($folder . '*');
		foreach ($ff as $f) {
			if (is_dir($f)) {
				$f_name = substr($f, strlen($folder));
				if (in_array($f_name, ['data', '.git', '.gitignore', 'README.md', 'tests'])) continue;
				$sub_files = $this->getFiles($f . DIRECTORY_SEPARATOR);
				foreach ($sub_files as $sf) {
					$sf['path'] = $f_name . DIRECTORY_SEPARATOR . $sf['path'];
					$files[] = $sf;
				}
			} else {
				$f_name = substr($f, strlen($folder));
				$files[] = [
					'path' => $f_name,
					'md5' => md5(file_get_contents($f)),
				];
			}
		}

		return $files;
	}

	/**
	 * Returns true or false, whether the module is configurable or not
	 *
	 * @return bool
	 */
	public function isConfigurable(): bool
	{
		if ($this->configClass === null)
			$this->loadConfigClass();

		if ($this->configClass) {
			return $this->configClass->configurable;
		} else {
			return false;
		}
	}

	/**
	 * @return bool
	 */
	private function loadConfigClass(): bool
	{
		if (!$this->configClassCompatible)
			return false;

		$configClassPath = $this->getConfigClassPath();
		if (file_exists($configClassPath)) {
			require_once($configClassPath);

			$configClass = '\\Model\\' . $this->folder_name . '\\Config';
			if (class_exists($configClass, false)) {
				$this->configClass = new $configClass($this->model);
				return true;
			} else {
				$this->configClass = null;
				return false;
			}
		} else {
			$this->configClass = null;
			return false;
		}
	}

	/**
	 * @return Module_Config
	 */
	public function getConfigClass()
	{
		$this->loadConfigClass();
		return $this->configClass ?: null;
	}

	/**
	 * Does a config class file exist?
	 */
	public function hasConfigClass()
	{
		$configClassPath = $this->getConfigClassPath();
		return file_exists($configClassPath);
	}

	/**
	 * Returns path for the eventual config class
	 *
	 * @return string
	 */
	private function getConfigClassPath(): string
	{
		return INCLUDE_PATH . $this->base_dir . DIRECTORY_SEPARATOR . $this->folder_name . DIRECTORY_SEPARATOR . 'Config.php';
	}

	/**
	 *
	 */
	public function getFilesByType()
	{
		$arr_files = [];
		foreach (Autoloader::$fileTypes as $type => $data) {
			if (!isset($arr_files[$type]))
				$arr_files[$type] = [];
			foreach ($data['files'] as $module => $files) {
				if ($module !== $this->folder_name)
					continue;
				foreach ($files as $f => $path)
					$arr_files[$type][] = $f;
			}
		}
		return $arr_files;
	}
}
