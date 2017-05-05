<?php
namespace Model;

class Module_Config{
	/** @var Core */
	protected $model;

	public function __construct(Core $model){
		$this->model = $model;
	}

	/**
	 * Utility method, returns the path of this module.
	 *
	 * @return string
	 */
	public function getPath(){
		$rc = new \ReflectionClass(get_class($this));
		return substr(dirname($rc->getFileName()), strlen(INCLUDE_PATH)).DIRECTORY_SEPARATOR;
	}

	/**
	 * Meant to be expanded by the specific module config class.
	 * It has to build the (possible) cache needed by the specific module to work.
	 *
	 * @return bool
	 */
	public function makeCache(){
		return true;
	}

	/**
	 * If this module needs to register rules, this is the method that should return them.
	 *
	 * @return array
	 */
	public function getRules(){
		return [];
	}

	/**
	 * If this module has classes to register, this is the method that should return them.
	 *
	 * @return array
	 */
	public function getClasses(){
		return [];
	}

	/**
	 * If the module has a configuration/installation page in the control panel, it's handled by this method
	 *
	 * @param array $request
	 * @return string
	 */
	public function getTemplate($request){
		return false;
	}

	/**
	 * This is called every time a POST request hits the configuration page
	 *
	 * @param array $request
	 * @return string
	 */
	public function execConfig($request, $data){
		return true;
	}
}