<?php
namespace Model;

class Controller{
	/** @var \FrontController */
	protected $model;
	/** @var \Model\Router */
	protected $router;
	/** @var array */
	public $viewOptions = array(
		'header' => [
			'layoutHeader',
		],
		'footer' => [
			'layoutFooter',
		],
		'template-folder' => [],
		'template' => false,
		'errors' => [],
		'messagges' => [],
	);

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
	 * Outputs the content
	 * It uses Output model, by default, but this behaviour can be customized by extending the method
	 *
	 */
	public function output(){
		/* Backward compatibility */
		if(isset($this->viewOptions['errori']))
			$this->viewOptions['errors'] = $this->viewOptions['errori'];
		if(isset($this->viewOptions['messaggi']))
			$this->viewOptions['messages'] = $this->viewOptions['messaggi'];

		if($this->viewOptions['template']===false){ // By default, load the template with the same name as the current controller
			$this->viewOptions['template'] = strtolower(preg_replace('/(?<!^)([A-Z])/', '-\\1', substr(get_class($this), 0, -10)));
		}

		$this->model->_Output->render($this->viewOptions);
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