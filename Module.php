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
		if(file_exists(INCLUDE_PATH.'data/config/'.get_class($this).'/config.php')){
			require(INCLUDE_PATH.'data/config/'.get_class($this).'/config.php');
			return $config;
		}else{
			return array();
		}
	}
}