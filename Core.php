<?php namespace Model\Core;

class Core implements \JsonSerializable, ModuleInterface
{
	/** @var array[] */
	public $modules = [];
	/** @var array[] */
	protected $boundMethods = [];
	/** @var array[] */
	protected $boundProperties = [];
	/** @var array[] */
	protected $availableModules = [];
	/** @var array[] */
	protected $rules = [];
	/** @var array[] */
	protected $controllers = [];
	/** @var string */
	public $leadingModule;
	/** @var string */
	public $controllerName;
	/** @var array|bool */
	private $request = false;
	/** @var string */
	private $requestPrefix = '';
	/** @var array */
	private $prefixMakers = [];
	/** @var Controller */
	public $controller;
	/** @var array */
	public $viewOptions = [
		'header' => [
			'layoutHeader',
		],
		'footer' => [
			'layoutFooter',
		],
		'template-folder' => [],
		'errors' => [],
		'messages' => [],
	];
	/** @var bool|array */
	private $inputVarsCache = false;
	/** @var array */
	private $registeredListeners = [];
	/** @var array */
	private $eventsHistory = [];
	/** @var bool */
	private $eventsOn = true;
	/** @var array */
	private $modulesWithCleanUp = [];

	/**
	 * Sets all the basic operations for the ModEl framework to operate, and loads the cache file.
	 */
	public function preInit()
	{
		if (version_compare(phpversion(), '7.1.0', '<'))
			die('PHP version (' . phpversion() . ') is not enough for ModEl framework to run.');

		$this->trigger('Core', 'start');

		$this->defineConstants();

		error_reporting(E_ALL);
		ini_set('display_errors', DEBUG_MODE);

		mb_internal_encoding('utf-8');
		if (!$this->isCLI()) {
			header('Content-type: text/html; charset=utf-8');

			if (!isset($_COOKIE['ZKID'])) {
				$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 0;
				$zkid = sha1($ip . time());
				setcookie('ZKID', $zkid, time() + 60 * 60 * 24 * 30, PATH);
			}

			setcookie('ZK', PATH, time() + (60 * 60 * 24 * 365), PATH);
		}

		if (DEBUG_MODE and function_exists('opcache_reset'))
			opcache_reset();

		if (!isset($_SESSION))
			$_SESSION = [];

		$model = $this;
		register_shutdown_function(function () use ($model) {
			$model->terminate();
		});

		set_error_handler(array($this, 'errorHandler'));

		$this->reloadCacheFile();

		$this->modules['Core'][0] = $this;

		$this->checkCleanUp();
	}

	private function defineConstants()
	{
		if (defined('START_TIME'))
			return;

		DEFINE('START_TIME', microtime(true));

		include(realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

		define('INCLUDE_PATH', realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR);
		define('PATHBASE', substr(INCLUDE_PATH, 0, -strlen(PATH)));

		if (isset($_COOKIE['ZKADMIN']) and $_COOKIE['ZKADMIN'] == '69')
			define('DEBUG_MODE', 1);
		else
			define('DEBUG_MODE', MAIN_DEBUG_MODE);

		define('SESSION_ID', md5(PATH)); // TODO: here for backward compatibility, to be removed

		define('ZK_LOADING_ID', substr(md5(microtime()), 0, 16));
	}

	/**
	 * Reloads the internal cache file
	 */
	public function reloadCacheFile()
	{
		$cacheFile = $this->retrieveCacheFile();
		Autoloader::$classes = $cacheFile['classes'];
		Autoloader::$fileTypes = $cacheFile['file-types'];
		$this->rules = $cacheFile['rules'];
		$this->controllers = $cacheFile['controllers'];
		$this->availableModules = $cacheFile['modules'];
		$this->modulesWithCleanUp = $cacheFile['cleanups'];
		$this->boundMethods = $cacheFile['methods'] ?? [];
		$this->boundProperties = $cacheFile['properties'] ?? [];
	}

	/**
	 * Looks for the internal cache file, and attempts to generate it if not found (e.g. first runs, or accidental cache wipes)
	 *
	 * @return array
	 */
	public function retrieveCacheFile(): array
	{
		$cacheFile = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache.php';
		if (!file_exists($cacheFile)) {
			require_once(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Module_Config.php');
			require_once(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Config.php');
			$configClass = new Config($this);
			$configClass->makeCache();
		}

		if (file_exists($cacheFile)) {
			require($cacheFile);
			return $cache;
		} else {
			$this->error('Cannot generate Core cache file.');
		}
	}

	/**
	 * Cleanups take a probabilistic approach:
	 * Every user that open the app for the first time, rolls a dice: it has one probability over a certain number to starts a cleanup
	 * If the probability is matched, it runs a cleanup on one of the supported modules
	 * Every time the dice is rolled, it won't be rolled again for an hour
	 * This ensures a nice load distribution over the users and over time
	 */
	private function checkCleanUp()
	{
		if (count($this->modulesWithCleanUp) === 0)
			return;

		$dice = mt_rand(1, 100);
		if ($dice === 1) {
			try {
				$lastModule = $this->getSetting('cleanup-last-module');
				$lastModuleK = array_search($lastModule, $this->modulesWithCleanUp);
				if ($lastModuleK === false or $lastModuleK === (count($this->modulesWithCleanUp) - 1))
					$nextModule = $this->modulesWithCleanUp[0];
				else
					$nextModule = $this->modulesWithCleanUp[$lastModuleK + 1];

				$lastCleanup = $this->getSetting('last-cleanup');
				$lastCleanup = $lastCleanup ? date_create($lastCleanup) : false;
				$today = date_create();
				if ($lastCleanup) {
					$interval = $today->getTimestamp() - $lastCleanup->getTimestamp();

					$totalInterval = (int)$this->getSetting('cleanup-total-interval');
					$numberOfIntervals = (int)$this->getSetting('cleanup-intervals');

					$totalInterval += $interval;
					$numberOfIntervals++;

					$this->setSetting('cleanup-total-interval', $totalInterval);
					$this->setSetting('cleanup-intervals', $numberOfIntervals);
					$this->setSetting('cleanups-average', round($totalInterval / $numberOfIntervals));
				}

				$this->setSetting('cleanup-last-module', $nextModule);
				$this->setSetting('last-cleanup', date('Y-m-d H:i:s'));

				$configClassName = '\\Model\\' . $nextModule . '\\Config';
				$configClass = new $configClassName($this);
				$configClass->cleanUp();
			} catch (Exception $e) {
			}
		}
	}

	/* MODULES MANAGEMENT */

	/**
	 * Check if a module exists.
	 * Returns the module data (as an array) if it is found, false otherwise.
	 *
	 * @param string $name
	 * @return array|bool
	 */
	public function moduleExists(string $name)
	{
		if (isset($this->availableModules[$name]) and $this->availableModules[$name]['initialized'])
			return $this->availableModules[$name];
		else
			return false;
	}

	/**
	 * List all modules in cache
	 *
	 * @param bool|null $custom
	 * @return array
	 */
	public function listModules(bool $custom = null): array
	{
		$modules = [];
		foreach ($this->availableModules as $name => $module) {
			if ($custom !== null and $module['custom'] !== $custom)
				continue;
			$modules[] = [
				'name' => $name,
				'custom' => $module['custom'],
				'path' => $module['path'],
				'version' => $module['version'],
			];
		}
		usort($modules, function ($a, $b) {
			return $a['name'] <=> $b['name'];
		});
		return $modules;
	}

	/**
	 * Attempts to load a specific module.
	 * Raise an exception if the module does not exist.
	 * Otherwise it returns the loaded module.
	 * If the module is already loaded, doesn't load it a second time and it just returns it.
	 *
	 * @param string $name
	 * @param array $options
	 * @param mixed $idx
	 * @return Module|null
	 */
	public function load(string $name, array $options = [], $idx = 0): ?ModuleInterface
	{
		if (isset($this->modules[$name][$idx])) {
			return $this->modules[$name][$idx];
		}

		$this->trigger('Core', 'loadModule', [
			'module' => $name,
			'idx' => $idx,
		]);

		$module = $this->moduleExists($name);
		if (!$module)
			$this->error('Module "' . entities($name) . '" not found.');

		if (isset($module['dependencies'])) {
			foreach ($module['dependencies'] as $dep) {
				if (!$this->isLoaded($dep)) {
					$depModule = $this->moduleExists($dep);
					if (!$depModule)
						$this->error('Module "' . entities($dep) . '", dependency of "' . entities($name) . '", not found.');
					if ($depModule['css'] or $depModule['js'])
						$this->load($dep);
				}
			}
		}

		if ($module['load']) {
			$className = '\\Model\\' . $name . '\\' . $name;
			$this->modules[$name][$idx] = new $className($this, $idx);
			$this->modules[$name][$idx]->init($options);
		} else {
			$this->modules[$name][$idx] = null;
		}

		$head = $module['assets-position'] === 'head' ? true : false;

		foreach ($module['js'] as $js) {
			$this->_Output->addJS(strtolower(substr($js, 0, 4)) == 'http' ? $js : 'model/' . $name . '/' . $js . '?v=' . $module['version'], [
				'custom' => false,
				'head' => $head,
			]);
		}
		foreach ($module['css'] as $css) {
			$this->_Output->addCSS(strtolower(substr($css, 0, 4)) == 'http' ? $css : 'model/' . $name . '/' . $css . '?v=' . $module['version'], [
				'custom' => false,
				'head' => $head,
			]);
		}

		return $this->modules[$name][$idx];
	}

	/**
	 * Check if a given module is already loaded.
	 * Returns boolean true or false.
	 *
	 * @param string $name
	 * @param string|null $idx
	 * @return bool
	 */
	public function isLoaded(string $name, ?string $idx = null): bool
	{
		if ($idx === null) {
			return isset($this->modules[$name]);
		} else {
			return isset($this->modules[$name][$idx]);
		}
	}

	/**
	 * Returns a loaded module.
	 * If the module isn't loaded yet, if $autoload is set to true, it will atempt to load it.
	 * If the module cannot be found, it returns null.
	 *
	 * @param string $name
	 * @param mixed $idx
	 * @param bool $autoload
	 * @return ModuleInterface|null
	 */
	public function getModule(string $name, $idx = null, bool $autoload = true): ?ModuleInterface
	{
		if ($idx === null) {
			if (isset($this->modules[$name])) {
				reset($this->modules[$name]);
				$idx = key($this->modules[$name]);
			} else {
				$idx = 0;
			}
		}

		if (isset($this->modules[$name][$idx])) {
			return $this->modules[$name][$idx];
		} else {
			if ($autoload) {
				if ($this->load($name, [], $idx))
					return $this->modules[$name][$idx];
				else
					return null;
			} else {
				return null;
			}
		}
	}

	/**
	 * Returns all loaded modules, or all loaded modules of a specific type, if given.
	 *
	 * @param string|null $name
	 * @return array
	 */
	public function allModules(?string $name = null): array
	{
		if ($name === null) {
			$return = array();
			foreach ($this->modules as $name => $arr) {
				if (count($arr) == 1 and key($arr) === 0) {
					$return[$name] = $arr[0];
				} else {
					foreach ($arr as $idx => $m)
						$return[$name . '_' . $idx] = $m;
				}
			}
			return $return;
		} else {
			return isset($this->modules[$name]) ? $this->modules[$name] : array();
		}
	}

	/**
	 * Getter.
	 * If called with an underscore, like _Db, it attempts to retrieve a module (recomended way of retrieving modules).
	 * The modules can be retrieved (and this is the official way) with ->_ModuleName or ->_ModuleName_Idx
	 * It raises an exception if the module is not found.
	 *
	 * Otherwise it will attempt to return a bound property, if any exists.
	 *
	 * @param $i
	 * @return mixed
	 */
	function __get(string $i)
	{
		if (preg_match('/^_[a-z0-9]+(_[a-z0-9]+)?$/i', $i)) {
			$name = explode('_', substr($i, 1));
			if (count($name) == 1) {
				return $this->getModule($name[0]);
			} elseif (count($name) == 2) {
				return $this->getModule($name[0], $name[1]);
			} else {
				$this->error('Unknown module ' . entities($i) . '.');
				return false;
			}
		} elseif (isset($this->boundProperties[$i])) {
			$module = $this->getModule($this->boundProperties[$i]['module']);
			return $module->{$this->boundProperties[$i]['property']};
		} else {
			return null;
		}
	}

	/**
	 * Call a method bound by one of the modules.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	function __call(string $name, array $arguments)
	{
		if (isset($this->boundMethods[$name])) {
			$module = $this->getModule($this->boundMethods[$name]['module']);
			return call_user_func_array([$module, $this->boundMethods[$name]['method']], $arguments);
		} else {
			return null;
		}
	}

	/* MAIN EXECUTION */

	/**
	 * It may be expanded in the FrontController.
	 * It is called before the actual execution of the page begins, and here will be loaded all the main generic modules (such as Db, ORM, etc)
	 */
	protected function init()
	{
	}

	/**
	 * Main execution of the ModEl framework
	 */
	private function exec()
	{
		// Get the request
		$request = $this->getRequest();

		/*
		 * I look in the registered rules for the ones matching with current request.
		 * If there is no rule matching, I redirect to a 404 Not Found error.
		 * So I'll found the module in charge to handle the current request.
		 * Next step, I ask the module in charge which controller should I load
		 * The module can also return a prefix to keep in mind for building future requests (defaults to PATH)
		 * A "redirect" to another request can also be returned, and this will repeat the process for the new request
		 * Then, I check if it really exists and I load it. I pass the default View Options to it as well
		 * */

		$this->requestPrefix = PATH;

		$preventInfiniteLoop = 0;
		while (true) {
			$preventInfiniteLoop++;

			$match = $this->matchRule($request);

			if ($match === null) {
				$module = 'Core';
				$controllerName = 'Err404';
				$this->viewOptions['404-reason'] = 'No rule matched the request.';
				break;
			} else {
				$module = $match['module'];
				$ruleFound = $match['idx'];
			}

			$moduleData = $this->moduleExists($module);
			if (!$moduleData)
				$this->model->error('Module ' . $module . ' does not exist');

			$this->leadingModule = $module;
			$this->trigger('Core', 'leadingModuleFound', ['module' => $module]);

			if ($moduleData['load']) {
				$controllerData = $this->getModule($module)->getController($request, $ruleFound);
			} else {
				// If this is not an auto-loading module, I just return the first controller I find
				$controllerData = null;
				foreach ($this->controllers as $c) {
					if ($c['module'] === $module) {
						$controllerData = [
							'controller' => $c['controller'],
						];
						break;
					}
				}
			}

			if (!is_array($controllerData)) {
				$this->viewOptions['404-reason'] = 'Module ' . $module . ' can\'t return a controller name.';
				$module = 'Core';
				$controllerName = 'Err404';
				break;
			}

			if (isset($controllerData['prefix']) and $controllerData['prefix'])
				$this->requestPrefix .= $controllerData['prefix'] . '/';

			if (isset($controllerData['redirect'])) {
				if ($controllerData['redirect'] === $request)
					$this->error('Recursion error: module ' . $module . ' is trying to redirect to the same request.');

				$request = $controllerData['redirect'];
				$this->request = $controllerData['redirect'];
				continue;
			}

			if (isset($controllerData['controller']) and $controllerData['controller']) {
				$controllerName = $controllerData['controller'];
			} else {
				$this->viewOptions['404-reason'] = 'Module ' . $module . ' has not returned a controller name.';
				$module = 'Core';
				$controllerName = 'Err404';
				break;
			}

			if ($controllerName) {
				$this->trigger('Core', 'controllerFound', ['controller' => $controllerName]);
				break;
			}

			if ($preventInfiniteLoop) {
				$this->error('Infinite loop while trying to find the controller.');
				break;
			}
		}

		$this->leadingModule = $module; // Let me set the leading module again, as it may have changed in the previous lines

		$controllerClassName = Autoloader::searchFile('Controller', $controllerName . 'Controller');

		if (!$controllerClassName or !class_exists($controllerClassName)) {
			$controllerName = 'Err404';
			$controllerClassName = 'Model\\Core\\Controllers\\Err404Controller';
			$this->viewOptions['404-reason'] = 'Controller class not found.';
		}

		$this->controllerName = $controllerName;
		$this->controller = new $controllerClassName($this);

		/*
		 * The init method is expanded in the specific controller and is executed beforehand
		 * Then I execute the modelInit which is standard for all controllers
		 * And at last, the index (or post) method which should contain the actual execution of the actions
		 * */

		$this->trigger('Core', 'controllerInit');

		$this->controller->init();
		$this->controller->modelInit();

		if ($this->isCLI()) {
			$this->trigger('Core', 'controllerExecution', ['method' => 'cli']);
			$controllerReturn = $this->controller->cli();
		} else {
			$httpMethod = strtolower($_SERVER['REQUEST_METHOD'] ?? 'GET');
			if (!in_array($httpMethod, ['get', 'post', 'put', 'delete', 'patch']))
				$httpMethod = 'get';

			$this->trigger('Core', 'controllerExecution', ['method' => $httpMethod]);
			$controllerReturn = $this->controller->{$httpMethod}();
		}

		if ($controllerReturn !== null) {
			/*
			 * If I have a returning value from the controller, I send it to the output stream as a json string
			 * */
			$this->trigger('Core', 'jsonResponse');
			echo json_encode($controllerReturn);
		} elseif (!$this->isCLI()) {
			/*
		 * Otherwise, I render the standard output content (default method in the controller use the Output module to handle this, but this behaviour can be customized).
		 * */
			$this->trigger('Core', 'outputStart');
			$this->controller->output($this->viewOptions);
		}
	}

	/**
	 * It may be expanded in the FrontController.
	 * It is called at the end of each correct execution.
	 */
	protected function end()
	{
	}

	/**
	 * Wrapper to execute the main methods in the correct order and catch potential uncaught exceptions.
	 * This is the one to call from the outside.
	 */
	public function run()
	{
		try {
			$this->preInit();
			$this->init();
			$this->exec();
			$this->end();
		} catch (\Exception $e) {
			if ($e->getMessage() != 'cli-redirect') { // Special case, throws an Exception to get out of the main cycle and end this froncontroller, to handle fake-redirects in CLI
				echo getErr($e);
				if (DEBUG_MODE)
					zkdump($e->getTrace(), true);
			}
		}
	}

	/**
	 * It is executed at the end of each execution (both correct ones and with errors) and it calls the "terminate" method of each loaded module, to do possible clean ups.
	 */
	public function terminate()
	{
		$this->trigger('Core', 'end');

		foreach ($this->modules as $name => $modules) {
			if ($name == 'Core')
				continue;
			foreach ($modules as $m) {
				if (is_object($m))
					$m->terminate();
			}
		}
	}

	/* REQUEST AND INPUT MANAGEMENT */

	/**
	 * Checks if a request matches one (or more) of the rules
	 * If more than one rule is matching, I assign a score to each one (the more specific is the rule, the higher is the score) and pick the highest one.
	 *
	 * @param array $request
	 * @return array|null
	 */
	private function matchRule(array $request): ?array
	{
		$matchedRules = [];

		foreach ($this->rules as $rIdx => $r) {
			if ($r['rule'] === '') {
				if ($request === [])
					$matchedRules[$rIdx] = 0;
				else
					continue;
			}

			if ($r['rule'] === null) {
				$matchedRules[$rIdx] = 0.5;
			} else {
				$rArr = explode('/', $r['rule']);
				$score = 0;
				foreach ($rArr as $i => $sr) {
					if (!isset($request[$i]))
						continue 2;
					if (!preg_match('/^' . $sr . '$/iu', $request[$i]))
						continue 2;

					$score = $i * 2;
					if (strpos($sr, '[') === false)
						$score += 1;
				}

				$matchedRules[$rIdx] = $score;
			}
		}

		if (count($matchedRules) == 0) {
			return null;
		} else {
			if (count($matchedRules) > 1)
				arsort($matchedRules);

			return $this->rules[key($matchedRules)];
		}
	}

	/**
	 * Returns the controller needed for the rules for which the Core module is in charge (currently only "zk" for the management panel)
	 *
	 * @param array $request
	 * @param string $rule
	 * @return array
	 */
	public function getController(array $request, string $rule): ?array
	{
		switch ($rule) {
			case 'zk':
				return [
					'controller' => 'Zk',
				];
				break;
			default:
				return [
					'controller' => 'Err404',
				];
				break;
		}

		return null;
	}

	/**
	 * Returns the current request.
	 * ModEl framework can be called either via HTTP request (eg. via browser) or via CLI.
	 * If $i is given, it returns that index of the request.
	 * Otherwise, it returns the full array.
	 *
	 * @param int $i
	 * @return array|string|null
	 */
	public function getRequest(?int $i = null)
	{
		if ($this->request === false) {
			if ($this->isCLI()) {
				global $argv;
				if (!is_array($argv))
					return $i === null ? [] : null;

				if (array_key_exists(1, $argv)) {
					$this->request = explode('/', $argv[1]);
				} else {
					$this->request = [];
				}
			} else {
				$this->request = isset($_GET['url']) ? explode('/', trim($_GET['url'], '/')) : array();
			}
		}

		if ($i === null) {
			return $this->request;
		} else {
			return array_key_exists($i, $this->request) ? $this->request[$i] : null;
		}
	}

	/**
	 * Returns one or all the input variables.
	 * ModEl framework can be called either via HTTP request (eg. via browser) or via CLI.
	 * If $i is given, it returns that variable.
	 * Otherwise, it returns the full array.
	 *
	 * @param string $i
	 * @param string $type
	 * @return array|string|null
	 */
	public function getInput(string $i = null, string $type = 'request')
	{
		if ($this->isCLI()) {
			if ($this->inputVarsCache === false) {
				$this->inputVarsCache = [];

				global $argv;

				if (is_array($argv) and count($argv) > 2) {
					$arr = $argv;
					unset($arr[0]); // Script name
					unset($arr[1]); // Main request (accesible via getRequest method)

					foreach ($arr as $input) {
						$input = explode('=', $input);
						if (count($input) == 2) {
							$this->inputVarsCache[$input[0]] = $input[1];
						}
					}
				}
			}

			$arr = $this->inputVarsCache;
		} else {
			switch ($type) {
				case 'get':
					$arr = $_GET;
					break;
				case 'post':
					$arr = $_POST;
					break;
				case 'request':
					$arr = $_REQUEST;
					break;
			}

			if ($type !== 'post' and isset($arr['url']))
				unset($arr['url']);
		}

		if ($i === null) {
			return $arr;
		} else {
			return array_key_exists($i, $arr) ? $arr[$i] : null;
		}
	}

	/**
	 * Returns the requests prefix
	 *
	 * @param array $tags
	 * @param array $opt
	 * @return string
	 */
	public function prefix(array $tags = [], array $opt = []): string
	{
		$opt = array_merge([
			'path' => true,
		], $opt);

		if ($tags) { // If no tag is given, returns the current prefix, otherwise it generates a new prefix dynamically
			$prefix = $opt['path'] ? PATH : '';
			foreach ($this->prefixMakers as $m) {
				$partial = $this->getModule($m)->getPrefix($tags, $opt);
				if ($partial)
					$prefix .= $partial . '/';
			}
		} else {
			$prefix = $this->requestPrefix;
			if (!$opt['path']) {
				$prefix = substr($prefix, strlen(PATH));
			}
		}
		return $prefix;
	}

	/**
	 * Some modules can prepend something to generated urls/requests, if that's the case, they need to call this method to let the Core know
	 *
	 * @param string $module
	 * @return bool
	 */
	public function addPrefixMaker(string $module): bool
	{
		if (!in_array($module, $this->prefixMakers))
			$this->prefixMakers[] = $module;
		return true;
	}

	/**
	 * Builds a request url based on the given parameters
	 *
	 * @param string|null $controller
	 * @param string|null $id
	 * @param array $tags
	 * @param array $opt
	 * @return null|string
	 */
	public function getUrl(?string $controller = null, ?string $id = null, array $tags = [], array $opt = []): ?string
	{
		if ($controller === null)
			$controller = $this->controllerName;

		$opt = array_merge([
			'prefix' => true,
			'path' => true,
		], $opt);

		if (!is_array($tags))
			$tags = ['lang' => $tags];

		if ($opt['prefix']) {
			$prefix = $this->prefix($tags, $opt);
		} else {
			if ($opt['path'])
				$prefix = PATH;
			else
				$prefix = '';
		}

		if ($controller == 'Zk')
			return $prefix . 'zk';

		$modules = [];
		foreach ($this->controllers as $controllerModulePair) {
			if ($controllerModulePair['controller'] == $controller)
				$modules[] = $controllerModulePair['module'];
		}

		if (count($modules) > 0) {
			if (in_array($this->leadingModule, $modules))
				$module = $this->leadingModule;
			else
				$module = reset($modules);
		} else {
			return null;
		}

		$url = $this->getModule($module)->getUrl($controller, $id, $tags, $opt);
		return $url !== false ? $prefix . $url : null;
	}

	/* ERRORS MANAGEMENT */

	/**
	 * This will raise a Exception and it attempts to log it (via errorHandler method).
	 *
	 * @param string $gen
	 * @param string|array $options
	 */
	public function error(string $gen, $options = '')
	{
		if (!is_array($options))
			$options = array('mex' => $options);
		$options = array_merge(array(
			'code' => 'ModEl',
			'mex' => '',
			'details' => array(),
		), $options);

		$b = debug_backtrace();

		$this->errorHandler('ModEl', $gen . ' - ' . $options['mex'], $b[0]['file'], $b[0]['line']); // Log

		$e = new Exception($gen);
		$e->_code = $options['code'];
		$e->_mex = $options['mex'];
		$e->_details = $options['details'];

		throw $e;
	}

	/**
	 * This will attempt to log an error in the error log table in the database.
	 *
	 * @param $errno
	 * @param $errstr
	 * @param $errfile
	 * @param $errline
	 * @param $errcontext
	 * @return bool
	 */
	public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext = false)
	{
		if (error_reporting() === 0)
			return true;

		$backtrace = zkBacktrace(true);
		array_shift($backtrace);

		$errors = array(E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE', E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED', E_ALL => 'E_ALL');
		$this->trigger('Core', 'error', [
			'no' => $errno,
			'code' => isset($errors[$errno]) ? $errors[$errno] : $errno,
			'str' => $errstr,
			'file' => $errfile,
			'line' => $errline,
			'backtrace' => $backtrace,
		]);

		return false;
	}

	/* EVENTS */
	/**
	 * Registers a closure to be called when a particular event is triggered.
	 * The event signature should be provided in the form of Module_Event or just _Event (if it can come from any module)
	 * The callback should accept a $data parameter, which will contain the data of the event
	 * If $retroactive is true and the event has already happened, it will be immediately triggered
	 *
	 * @param string $event
	 * @param \Closure $callback
	 * @param bool $retroactive
	 */
	public function on(string $event, \Closure $callback, bool $retroactive = false)
	{
		if (!isset($this->registeredListeners[$event]))
			$this->registeredListeners[$event] = [];
		$this->registeredListeners[$event][] = $callback;

		if ($retroactive) {
			foreach ($this->eventsHistory as $e) {
				if ($e['module'] . '_' . $e['event'] === $event or $e['event'] === $event) {
					call_user_func($callback, $e['data']);
				}
			}
		}
	}

	/**
	 * Triggers a particular event. If any callback is registered, it gets executed.
	 *
	 * @param string $module
	 * @param string $event
	 * @param array $data
	 * @return bool
	 */
	public function trigger(string $module, string $event, array $data = []): bool
	{
		if (isset($this->registeredListeners[$event])) {
			foreach ($this->registeredListeners[$event] as $callback) {
				call_user_func($callback, $data);
			}
		}

		if (isset($this->registeredListeners[$module . '_' . $event])) {
			foreach ($this->registeredListeners[$module . '_' . $event] as $callback) {
				call_user_func($callback, $data);
			}
		}

		if (!$this->eventsOn)
			return true;

		$this->eventsHistory[] = [
			'module' => $module,
			'event' => $event,
			'data' => $data,
			'time' => microtime(true),
		];

		return true;
	}

	/**
	 * Getter for events history
	 *
	 * @return array
	 */
	public function getEventsHistory(): array
	{
		return $this->eventsHistory;
	}

	/**
	 * Turns off or on events logging
	 *
	 * @param bool $set
	 */
	public function switchEvents(bool $set)
	{
		$this->eventsOn = $set;
	}

	/**
	 * Is events logging on?
	 *
	 * @return bool
	 */
	public function getEventsFlag(): bool
	{
		return $this->eventsOn;
	}

	/* VARIOUS UTILITIES */
	/**
	 * Implementation of JsonSerializable, just to avoid huge useless prints while debugging with zkdump.
	 *
	 * @return string
	 */
	public function jsonSerialize()
	{
		return 'MODEL/FRONT CONTROLLER';
	}

	/**
	 * Returns true if Model is executed via CLI, false otherwise
	 *
	 * @return bool
	 */
	public function isCLI(): bool
	{
		return (php_sapi_name() == "cli");
	}

	/**
	 * Shortcut for redirecting
	 *
	 * @param string $path
	 */
	function redirect(string $path)
	{
		if ($this->isCLI()) {
			if (stripos($path, PATH) !== 0)
				die('Can\t redirect to a non-local url in CLI.');

			$this->end();
			$this->terminate();

			$real_path = substr($path, strlen(PATH));
			global $argv;
			if (strpos($real_path, '?')) {
				$real_path = explode('?', $real_path);
				$argv = [
					0 => $argv[0],
					1 => $real_path[0],
				];

				$arguments = explode('&', $real_path[1]);
				foreach ($arguments as $a)
					$argv[] = $a;
			} else {
				$argv = [
					0 => $argv[0],
					1 => $real_path,
				];
			}

			$frontController = new \FrontController();
			$frontController->run();

			throw new \Exception('cli-redirect');
		} else {
			header('Location: ' . $path);
			die();
		}
	}

	/**
	 * Returns debug data
	 *
	 * @return array
	 */
	public function getDebugData(): array
	{
		$debug = array(
			'prefix' => $this->prefix([], ['path' => false]),
			'request' => implode('/', $this->getRequest()),
			'execution_time' => microtime(true) - START_TIME,
			'module' => $this->leadingModule,
			'controller' => $this->controllerName,
			'modules' => array_keys($this->allModules()),
			'zk_loading_id' => ZK_LOADING_ID,
		);

		if ($this->isLoaded('Db')) {
			$debug['n_query'] = $this->_Db->n_query;
			$debug['n_prepared'] = $this->_Db->n_prepared;
			$debug['query_per_table'] = $this->_Db->n_tables;
		}

		if ($this->isLoaded('Router')) {
			$pageId = $this->_Router->pageId;
			if ($pageId)
				$debug['pageId'] = $pageId;
		}

		if (is_object($this->element)) {
			$debug['elementType'] = get_class($this->element);
			$debug['elementId'] = $this->element[$this->element->settings['primary']];
		}

		return $debug;
	}

	/**
	 * Retrieves the Core config (as for any other module)
	 */
	public function retrieveConfig(): array
	{
		if (file_exists(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'config.php')) {
			require(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'config.php');
			return $config;
		} else {
			return [];
		}
	}

	/**
	 * @param string $k
	 * @return mixed
	 */
	public function getSetting(string $k)
	{
		if (!$this->moduleExists('Db'))
			return null;
		return $this->_Db->select('main_settings', ['k' => $k], 'v');
	}

	/**
	 * @param string $k
	 * @param mixed $v
	 * @return bool
	 */
	public function setSetting(string $k, $v): bool
	{
		if (!$this->moduleExists('Db'))
			return false;

		$current = $this->getSetting($k);
		if ($current === false) {
			return (bool)$this->_Db->insert('main_settings', ['k' => $k, 'v' => $v]);
		} else {
			return (bool)$this->_Db->update('main_settings', ['k' => $k], ['v' => $v]);
		}
	}
}
