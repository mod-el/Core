<?php
namespace Model;

class Module_Config{
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
	 * If this module needs rules to register, this is the method who should return them.
	 *
	 * @return array
	 */
	public function getRules(){
		return [];
	}
}