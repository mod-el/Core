<?php
namespace Model;

class ReflectionModule{
	/** @var string */
	protected $base_dir = '';
	/** @var string */
	protected $path;
	/** @var Core */
	protected $model;
	/** @var string|bool */
	public $name = false;
	/** @var string|bool */
	public $folder_name = false;
	/** @var string|bool */
	public $description = false;
	/** @var array */
	public $dependencies = array();
	/** @var bool */
	public $installed = false;
	/** @var string|bool */
	public $version = false;
	/** @var array */
	public $files = array();
	/** @var string|bool */
	public $md5 = false;
	/** @var bool */
	public $official = null;
	/** @var bool */
	public $hasConfigClass = false;
	/** @var bool */
	public $configurable = false;

	/** @var bool */
	public $new_version = false;
	/** @var bool */
	public $expected_md5 = false;
	/** @var bool */
	public $corrupted = false;

	function __construct($name, Core $model, $base_dir=''){
		$this->folder_name = $name;
		$this->model = $model;
		$this->base_dir = $base_dir;

		$this->path = INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR;
		$vars_file = INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'vars.php';
		$this->files = $this->getFiles($this->path);

		require($this->path.'model.php');
		if(file_exists(INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$name.'_Config.php')){
			require_once(INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$name.'_Config.php');
			$this->hasConfigClass = true;
			$configClass = '\\Model\\'.$name.'_Config';
			$configClass = new $configClass($this->model);
			$this->configurable = $configClass->configurable;
		}

		$this->name = $moduleData['name'];
		$this->description = $moduleData['description'];
		$this->version = $moduleData['version'];
		$this->dependencies = $moduleData['dependencies'];

		if(file_exists($vars_file)){
			require($vars_file);
			if(isset($installed))
				$this->installed = $installed;
		}

		$md5 = array();
		foreach($this->files as $f)
			$md5[] = $f['md5'];
		sort($md5);
		$this->md5 = md5(implode('', $md5));
	}

	/**
	 * Returns an array with all the files of the module and their MD5
	 *
	 * @param $folder
	 * @return array
	 */
	function getFiles($folder){
		$files = array();
		$ff = glob($folder.'*');
		foreach($ff as $f){
			if(is_dir($f)){
				$f_name = substr($f, strlen($folder));
				if(in_array($f_name, ['data', '.git', '.gitignore'])) continue;
				$sub_files = $this->getFiles($f.DIRECTORY_SEPARATOR);
				foreach($sub_files as $sf){
					$sf['path'] = $f_name.DIRECTORY_SEPARATOR.$sf['path'];
					$files[] = $sf;
				}
			}else{
				$f_name = substr($f, strlen($folder));
				$files[] = array(
					'path'=>$f_name,
					'md5'=>md5(file_get_contents($f)),
				);
			}
		}

		return $files;
	}
}
