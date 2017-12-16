<?php namespace Model\Core;

class Controller{
	/** @var \FrontController */
	protected $model;
	/** @var array */
	public $viewOptions = array(
		'header' => [
			'layoutHeader',
		],
		'footer' => [
			'layoutFooter',
		],
		'template-module' => null,
		'template-module-layout' => null,
		'template-folder' => [],
		'template' => false,
		'errors' => [],
		'messages' => [],
	);

	function __construct(\FrontController $model){
		$this->model = $model;
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
	 * If Output module is not installed, it tries to execute outputCLI (only works if "outputCLI" was customized)
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

		if($this->model->moduleExists('Output')){
			$this->model->_Output->render($this->viewOptions);
		}else{
			echo '<pre>';
			$this->outputCLI(true);
			echo '</pre>';
		}
	}

	/**
	 * Optionally, this method can be expanded by the controller to override the default output method, if model is executed in a CLI environment
	 * If it's called as a fallback for a missing Output module, it will not output anything
	 *
	 * @param bool $asFallback
	 */
	public function outputCLI($asFallback=false){
		if(!$asFallback)
			$this->output();
	}

	/**
	 * Shortcut for $this->model->load
	 *
	 * @param string $name
	 * @param array $options
	 * @param mixed $idx
	 * @return mixed
	 */
	private function load($name, array $options=array(), $idx=0){
		return $this->model->load($name, $options, $idx);
	}
}
