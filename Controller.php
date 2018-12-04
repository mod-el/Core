<?php namespace Model\Core;

class Controller
{
	/** @var \FrontController */
	protected $model;
	/** @var array */
	public $viewOptions = array(
		'header' => [], // Deprecated, use model->viewOptions
		'footer' => [], // Deprecated, use model->viewOptions
		'template-folder' => [], // Deprecated, use model->viewOptions
		'errors' => [], // Deprecated, use model->viewOptions
		'messages' => [], // Deprecated, use model->viewOptions
	);

	function __construct(\FrontController $model)
	{
		$this->model = $model;
	}

	/**
	 * Meant to be extended
	 * This will be executed first
	 */
	public function init()
	{
	}

	/**
	 * This is executed after init()
	 */
	public function modelInit()
	{

	}

	/**
	 * Business logic of the application
	 * You can use either this for generic response, or the various methods below
	 *
	 * @return mixed
	 */
	public function index()
	{
		return;
	}

	/**
	 * Called for GET requests
	 */
	public function get()
	{
		return $this->index();
	}

	/**
	 * Called for POST requests
	 */
	public function post()
	{
		return $this->index();
	}

	/**
	 * Called for PUT requests
	 */
	public function put()
	{
		return $this->index();
	}

	/**
	 * Called for DELETE requests
	 */
	public function delete()
	{
		return $this->index();
	}

	/**
	 * Called for PATCH requests
	 */
	public function patch()
	{
		return $this->index();
	}

	/**
	 * Called when used in a CLI environment
	 */
	public function cli()
	{
		return $this->index();
	}

	/**
	 * Outputs the content
	 * It uses Output model, by default, but this behaviour can be customized by extending the method
	 * If Output module is not installed, it tries to execute outputCLI (only works if "outputCLI" was customized)
	 *
	 * @param array $options
	 */
	public function output(array $options = [])
	{
		$options = array_merge_recursive_distinct($options, $this->viewOptions);

		/* Backward compatibility */
		if (isset($options['errori']))
			$options['errors'] = $options['errori'];
		if (isset($options['messaggi']))
			$options['messages'] = $options['messaggi'];

		if (!array_key_exists('template', $options)) { // By default, load the template with the same name as the current controller
			$classShortName = (new \ReflectionClass($this))->getShortName();
			$options['template'] = strtolower(preg_replace('/(?<!^)([A-Z])/', '-\\1', substr($classShortName, 0, -10)));
		}

		if ($this->model->moduleExists('Output')) {
			$this->model->_Output->render($options);
		} else {
			echo '<pre>';
			$this->outputCLI($options, true);
			echo '</pre>';
		}
	}

	/**
	 * Optionally, this method can be expanded by the controller to override the default output method, if model is executed in a CLI environment
	 * If it's called as a fallback for a missing Output module, it will not output anything
	 *
	 * @param array $options
	 * @param bool $asFallback
	 */
	public function outputCLI(array $options = [], bool $asFallback = false)
	{
		if (!$asFallback)
			$this->output();
	}

	/**
	 * Shortcut for $this->model->load
	 *
	 * @param string $name
	 * @param array $options
	 * @param mixed $idx
	 * @return mixed
	 * @throws Exception
	 */
	private function load(string $name, array $options = array(), $idx = 0)
	{
		return $this->model->load($name, $options, $idx);
	}
}
