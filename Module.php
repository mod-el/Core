<?php
namespace Model;

class Module{
	/** @var Core */
	public $model;
	/** @var mixed */
	public $module_id;
	/** @var array */
	public $methods = array();
	/** @var array */
	public $properties = array();

	/**
	 * Module constructor.
	 * @param Core $front
	 * @param mixed $idx
	 * @param mixed $options
	 */
	function __construct($front, $idx, $options){
		$this->model = $front;
		$this->module_id = $idx;
		$this->init($options);
	}

	/**
	 * Meant to be expanded in the specific module.
	 *
	 * @param mixed $options
	 */
	public function init($options){}

	/**
	 * This method is called by the "terminate" method of the Core, at the end of each execution.
	 */
	public function terminate(){}

	/**
	 * Utility method, returns the path of this module.
	 *
	 * @return string
	 */
	public function getPath(){
		$rc = new \ReflectionClass(get_class($this));
		return substr(dirname($rc->getFileName()), strlen(INCLUDE_PATH)).'/';
	}

	/**
	 * Retrieves the configuration file of this module - if exists - and returns it.
	 *
	 * @return array
	 */
	public function retrieveConfig(){
		$classname = $this->getClass();

		if(file_exists(INCLUDE_PATH.'data/config/'.$classname.'/config.php')){
			require(INCLUDE_PATH.'data/config/'.$classname.'/config.php');
			return $config;
		}else{
			return [];
		}
	}

	/**
	 * This will trigger a new event in the Core, coming from this module.
	 * Each event has its own name (param $event) and eventually carries a bunch of data in the form of an array (param $data)
	 *
	 * @param $event
	 * @param array $data
	 * @return bool
	 */
	protected function trigger($event, $data = []){
		return $this->model->trigger($this->getClass(), $event, $data);
	}

	/**
	 * Returns the non-namespaced class name of this module.
	 *
	 * @return string
	 */
	private function getClass(){
		$classname = get_class($this);
		if ($pos = strrpos($classname, '\\')) // Get the non-namespaced class name
			$classname = substr($classname, $pos + 1);
		return $classname;
	}
}