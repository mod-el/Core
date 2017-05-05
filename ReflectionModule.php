<?php
namespace Model;

class ReflectionModule{
	protected $base_dir = '';
	protected $path;
	protected $model;
	public $name = false;
	public $folder_name = false;
	public $description = false;
	public $dependencies = array();
	public $installed = false;
	public $version = false;
	public $files = array();
	public $md5 = false;
	public $official = null;
	public $configurable = false;

	public $new_version = false;
	public $expected_md5 = false;
	public $corrupted = false;

	function __construct($name, $model, $base_dir='', $forza_files=array()){
		$this->folder_name = $name;
		$this->model = $model;
		$this->base_dir = $base_dir;

		$this->path = INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR;
		$vars_file = INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'vars.php';
		$this->files = $this->getFiles($this->path);
		foreach($forza_files as $f){
			$this->files[] = array(
				'path'=>$f,
				'md5'=>md5(file_get_contents(INCLUDE_PATH.$this->base_dir.$f)),
			);
		}

		require($this->path.'model.php');
		if(file_exists(INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$name.'_Config.php')){
			require(INCLUDE_PATH.$this->base_dir.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$name.'_Config.php');
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

	function getFiles($folder){
		$files = array();
		$ff = glob($folder.'*');
		foreach($ff as $f){
			if(is_dir($f)){
				$f_name = substr($f, strlen($folder));
				if($f_name=='data') continue;
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

	/*function hasToBeInstalled(){
		if($this->installed)
			return false;

		if(file_exists($this->path.'install') and file_exists($this->path.'install/'.$this->folder_name.'_Config.php')){
			return true;
		}else{
			$this->model->_ZkPanel->markInstalled($this->folder_name);
			return false;
		}
	}*/
}