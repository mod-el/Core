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
	 * Business logic of the application
	 * You can use either this for generic response, or the various methods below
	 */
	public function index()
	{
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

		if ($this->model->moduleExists('Output'))
			$this->model->_Output->render($options);
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
