<?php
namespace Model;

class Core implements \JsonSerializable{
	/** @var array[] */
	public $modules = array();
	/** @var string[] */
	protected $modulesMethods = array();
	/** @var array[] */
	protected $availableModules = [];
	/** @var string[] */
	protected $rules = [];

	/**
	 * Core constructor.
	 *
	 * Sets all the basic operations for the ModEl framework to operate, and loads the cache file.
	 */
	function __construct(){
		session_start();

		DEFINE('START_TIME', microtime(true));

		if(version_compare(phpversion(), '5.4.0', '<'))
			die('PHP version ('.phpversion().') is not enough for ModEl framework to run.');

		include_once(realpath(dirname(__FILE__)).'/../../data/config/config.php');

		if(isset($_COOKIE['ZKADMIN']) and $_COOKIE['ZKADMIN']=='69')
			define('DEBUG_MODE', 1);
		else
			define('DEBUG_MODE', MAIN_DEBUG_MODE);

		error_reporting(E_ALL);
		ini_set('display_errors', DEBUG_MODE);

		header('Content-type: text/html; charset=utf-8');
		mb_internal_encoding('utf-8');

		if(DEBUG_MODE and version_compare(phpversion(), '5.5.0', '>=') and function_exists('opcache_reset'))
			opcache_reset();

		define('SESSION_ID', md5(PATH));
		if(!isset($_SESSION[SESSION_ID]))
			$_SESSION[SESSION_ID] = [];

		$model = $this;
		register_shutdown_function(function() use($model){ // Poco prima del termine dell'esecuzione dello script - non importa se fine naturale, o tramite end o fatal error - , chiamo la funzione terminate, che serve a chiudere tutte le eventuali transazioni aperte
			$model->terminate();
		});

		if(!isset($_COOKIE['ZKID'])){
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 0;
			$zkid = sha1($ip.time());
			setcookie('ZKID', $zkid, time()+60*60*24*30, PATH);
		}

		setcookie('ZK', PATH, time()+(60*60*24*365), PATH);

		define('ZK_LOADING_ID', substr(md5(microtime()), 0, 16));

		set_error_handler(array($this, 'errorHandler'));

		$cacheFile = INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Core'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache.php';
		if(file_exists($cacheFile)){
			include_once($cacheFile);
			$this->availableModules = $modules;
			$this->rules = $rules;
			Autoloader::$classes = $classes;
		}else{
			$this->availableModules = [
				'Core'=>[
					'path'=>'model/Core',
				],
			];
			$this->rules = ['zk'];
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
	public function moduleExists($name){
		if(isset($this->availableModules[$name]))
			return $this->availableModules[$name];
		else
			return false;
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
	 * @return mixed
	 */
	public function load($name, $options=array(), $idx=0){
		if(isset($this->modules[$name][$idx])){
			return $this->modules[$name][$idx];
		}

		$module = $this->moduleExists($name);
		if(!$module){
			$this->error('Module "'.entities($name).'" not found.');
		}

		if($module['load']){
			$className = '\\Model\\'.$name;
			$this->modules[$name][$idx] = new $className($this, $idx, $options);
			return $this->modules[$name][$idx];
		}else{
			$this->modules[$name][$idx] = true;
		}

		return true;
	}

	/**
	 * Check if a given module is already loaded.
	 * Returns boolean true or false.
	 *
	 * @param string $name
	 * @param mixed $idx
	 * @return bool
	 */
	public function isLoaded($name, $idx=false){
		if($idx===false){
			return isset($this->modules[$name]);
		}else{
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
	 * @return mixed
	 */
	public function getModule($name, $idx=false, $autoload=true){
		if($idx===false){
			if(isset($this->modules[$name])){
				reset($this->modules[$name]);
				$idx = key($this->modules[$name]);
			}else{
				$idx = 0;
			}
		}

		if(isset($this->modules[$name][$idx])){
			return $this->modules[$name][$idx];
		}else{
			if($autoload){
				if($this->load($name, array(), $idx))
					return $this->modules[$name][$idx];
				else
					return null;
			}else{
				return null;
			}
		}
	}

	/**
	 * Returns all loaded modules, or all loaded modules of a specific type, if given.
	 *
	 * @param mixed $name
	 * @return array
	 */
	public function allModules($name=false){
		if($name===false){
			$return = array();
			foreach($this->modules as $name=>$arr){
				if(count($arr)==1 and key($arr)===0){
					$return[$name] = $arr[0];
				}else{
					foreach($arr as $idx=>$m)
						$return[$name.'_'.$idx] = $m;
				}
			}
			return $return;
		}else{
			return isset($this->modules[$name]) ? $this->modules[$name] : array();
		}
	}

	/**
	 * Magic getter, shortcut for getModule.
	 * The modules can be retrieved (and this is the official way) with ->_ModuleName or ->_ModuleName_Idx
	 * It raises an exception if the module is not found.
	 *
	 * @param $i
	 * @return Module
	 */
	function __get($i){
		if(preg_match('/^_[a-z0-9]+(_[a-z0-9]+)?$/i', $i)){
			$name = explode('_', substr($i, 1));
			if(count($name)==1){
				return $this->getModule($name[0]);
			}elseif(count($name)==2){
				return $this->getModule($name[0], $name[1]);
			}else{
				$this->error('Unknown module '.entities($i).'.');
			}
		}else{
			$this->error('Wrong module name format.');
		}
	}

	/**
	 * Call a registered method off one of the modules.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	function __call($name, $arguments){
		if(isset($this->modulesMethods[$name])){
			$module = $this->getModule($this->modulesMethods[$name]);
			return call_user_func_array(array($module, $name), $arguments);
		}else{
			return null;
		}
	}

	/* MAIN EXECUTION */

	/**
	 * It may be expanded in the FrontController.
	 * It is called before the actual execution of the page begins, and here will be loaded all the main generic modules (such as Db, ORM, etc)
	 */
	protected function init(){}

	/**
	 * Main execution of the ModEl framework
	 */
	private function exec(){
		// Get the request
		$request = $this->getRequest();

		/*
		 * I look in the registered rules for the ones matching with current request.
		 * If there is no rule matching, I redirect to a 404 Not Found error.
		 * If more than one rule is matching, I assign a score to each one (the more specific is the rule, the higher is the score) and pick the highest one.
		 * So I'll found the module in charge to handle the current request.
		 * */
		$matchedRules = [];
		foreach($this->rules as $r=>$module){
			$rArr = explode('/', $r);
			$score = 0;
			foreach($rArr as $i=>$sr){
				if(!isset($request[$i]))
					continue 2;
				if(!preg_match('/'.$sr.'/i', $request[$i]))
					continue 2;

				$score = $i*2;
				if(strpos($sr, '[')===false)
					$score += 1;
			}
			$matchedRules[$r] = $score;
		}

		if(count($matchedRules)==0){
			$ruleFound = '404';
			$module = 'Core';
		}else{
			if(count($matchedRules)>1)
				krsort($matchedRules);

			$ruleFound = key($matchedRules);
			$module = $this->rules[$ruleFound];
		}

		/*
		 *
		 * */

		zkdump($ruleFound);
		zkdump($module);
	}

	/**
	 * It may be expanded in the FrontController.
	 * It is called at the end of each correct (with no exception raised) execution.
	 */
	protected function end(){}

	/**
	 * Wrapper to execute the main methods in the correct order and catch potential uncaught exceptions.
	 * This is the one to call from the outside.
	 */
	public function run(){
		try{
			$this->init();
			$this->exec();
			$this->end();
		}catch(\Exception $e){
			echo getErr($e);
			if(DEBUG_MODE)
				zkdump($e->getTrace(), true);
		}
	}

	/**
	 * It is executed at the end of each execution (both correct ones and with errors) and it calls the "terminate" method of each loaded module, to do possible clean ups.
	 */
	public function terminate(){
		foreach($this->modules as $name=>$modules){
			foreach($modules as $m){
				$m->terminate();
			}
		}
	}

	/* INPUT MANAGEMENT */

	/**
	 * Returns the current request.
	 * ModEl framwork can be called either via HTTP request (eg. via browser) or via CLI.
	 * If $i is given, it returns that index of the request.
	 * Otherwise, it returns the full array.
	 *
	 * @param bool|int $i
	 * @return array|string|null
	 */
	public function getRequest($i=false){
		if (php_sapi_name() == "cli") {
			global $argv;
			if(!is_array($argv))
				return $i===false ? [] : null;

			if(array_key_exists(1, $argv)){
				$req = explode('/', $argv[1]);
			}else{
				$req = [];
			}
		}else{
			$req = isset($_GET['url']) ? explode('/', $_GET['url']) : array();
		}

		if($i===false){
			return $req;
		}else{
			return array_key_exists($i, $req) ? $req[$i] : null;
		}
	}

	/* ERRORS MANAGEMENT */

	/**
	 * This will raise a ZkException and it attempts to log it (via errorHandler method).
	 *
	 * @param string $gen
	 * @param string|array $options
	 * @throws ZkException
	 */
	public function error($gen, $options=''){
		if(!is_array($options))
			$options = array('mex'=>$options);
		$options = array_merge(array(
			'code'=>'ModEl',
			'mex'=>'',
			'details'=>array(),
		), $options);

		$b = debug_backtrace();

		$this->errorHandler('ModEl', '('.$options['code'].')'.$gen.' - '.$options['mex'], $b[0]['file'], $b[0]['line']); // Log

		$e = new ZkException($gen);
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
	 * @param bool $errcontext
	 * @return bool
	 */
	public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext=false){
		$db = $this->getModule('Db', false, false);
		if($db){
			try{
				if(!defined('MYSQL_MAX_ALLOWED_PACKET')){
					$max_allowed_packet_query = $db->query('SHOW VARIABLES LIKE \'max_allowed_packet\'')->fetch();
					if($max_allowed_packet_query)
						define('MYSQL_MAX_ALLOWED_PACKET', $max_allowed_packet_query['Value']);
					else
						define('MYSQL_MAX_ALLOWED_PACKET', 1000000);
				}

				$backtrace = zkBacktrace(true);
				array_shift($backtrace);
				$prepared_session = $db->quote(serializeForLog($_SESSION[SESSION_ID]));
				$prepared_backtrace = $db->quote(serializeForLog($backtrace));

				if(strlen($prepared_session)>MYSQL_MAX_ALLOWED_PACKET-400)
					$prepared_session = '\'TOO LARGE\'';
				if(strlen($prepared_backtrace)>MYSQL_MAX_ALLOWED_PACKET-400)
					$prepared_backtrace = '\'TOO LARGE\'';
				if(strlen($prepared_session)+strlen($prepared_backtrace)>MYSQL_MAX_ALLOWED_PACKET-400)
					$prepared_session = '\'TOO LARGE\'';

				$get = $_GET; if(isset($get['url'])) unset($get['url']);
				$user = isset($_COOKIE['ZKID']) ? $db->quote($_COOKIE['ZKID']) : 'NULL';

				$errors = array('ZK'=>'ZK', E_ERROR=>'E_ERROR', E_WARNING=>'E_WARNING', E_PARSE=>'E_PARSE', E_NOTICE=>'E_NOTICE', E_CORE_ERROR=>'E_CORE_ERROR', E_CORE_WARNING=>'E_CORE_WARNING', E_COMPILE_ERROR=>'E_COMPILE_ERROR', E_COMPILE_WARNING=>'E_COMPILE_WARNING', E_USER_ERROR=>'E_USER_ERROR', E_USER_WARNING=>'E_USER_WARNING', E_USER_NOTICE=>'E_USER_NOTICE', E_STRICT=>'E_STRICT', E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR', E_DEPRECATED=>'E_DEPRECATED', E_USER_DEPRECATED=>'E_USER_DEPRECATED', E_ALL=>'E_ALL');
				$db->query('INSERT INTO zk_error_log(type,str,file,line,backtrace,session,date,user,url) VALUES(
				'.$db->quote(isset($errors[$errno]) ? $errors[$errno] : 'UNKWNOWN ('.$errno.')').',
				'.$db->quote($errstr).',
				'.$db->quote($errfile).',
				'.$db->quote($errline).',
				'.$prepared_backtrace.',
				'.$prepared_session.',
				'.$db->quote(date('Y-m-d H:i:s')).',
				'.$user.',
				'.$db->quote(implode('/', $this->getRequest()).'?'.http_build_query($get)).'
			)');
			}catch(Exception $e){
				if(DEBUG_MODE){
					echo '<b>ERRORE DURANTE IL LOG:</b> '.$e->getMessage();
				}
			}
		}
		return false;
	}

	/* VARIOUS UTILITIES */
	/**
	 * Implementation of JsonSerializable, just to avoid huge useless prints while debugging with zkdump.
	 *
	 * @return string
	 */
	public function jsonSerialize(){
		return 'MODEL/FRONT CONTROLLER';
	}

	/* BACKWARD COMPATIBILITY */

	public function loaded($name, $idx=false){ return $this->isLoaded($name, $idx); }
	public function loadOnce($name, $options=array(), $idx=null, $error=null){ return $this->load($name, $options, $idx); }
	public function loadIfNot($name, $options=array(), $idx=null, $error=null){ return $this->load($name, $options, $idx); }
}