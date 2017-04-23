<?php
namespace Model;

class Controller{
	/** @var \FrontController */
	protected $model;
	/** @var \Model\Router */
	protected $router;
	/** @var array */
	public $viewOptions = array('errori'=>array(), 'messaggi'=>array());

	function __construct(\FrontController $model){
		$this->model = $model;
		$this->router = $model->_Router;
	}

	/**
	 * Meant to be extended
	 * This will be executed first
	 */
	public function init(){}

	/**
	 * This is executed after init()
	 */
	public function modelInit(){

	}

	/**
	 * Meant to be extended
	 * This should contain the actual business logic of the application
	 */
	public function index(){}

	/**
	 * Optionally, you can specify different behaviours for POST or GET requests
	 */
	public function post(){
		return $this->index();
	}

	/**
	 * Getter for viewoptions
	 *
	 * @return array
	 */
	public function getViewOptions(){
		return $this->viewOptions;
	}

	/**
	 * Shortcut for $this->model->load
	 *
	 * @param string $name
	 * @param array $options
	 * @param mixed $idx
	 * @return mixed
	 */
	private function load($name, $options=array(), $idx=0){
		return $this->model->load($name, $options, $idx);
	}
}