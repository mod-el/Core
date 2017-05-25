<?php
namespace Model;

class Module_Config{
	/** @var Core */
	protected $model;
	/** @var bool */
	public $configurable = false;

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
	 * The sintax is: [
	 * 		'idx'=>['rule'=>'rule', 'controller'=>'controller'],
	 * ]
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
		return null;
	}

	/**
	 * Executed after the first installation of the module
	 *
	 * @param array $data
	 * @return bool
	 */
	public function install($data=[]){
		return true;
	}

	/**
	 * This is called every time a POST request hits the configuration page
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function saveConfig($type, $data){
		$classname = $this->getClass();

		$configFile = INCLUDE_PATH.'data'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.$classname.DIRECTORY_SEPARATOR.'config.php';

		$w = file_put_contents($configFile, '<?php
$config = '.var_export($data, true).';
');

		return (bool) $w;
	}

	/**
	 * Retrieves the configuration file of this module - if exists - and returns it.
	 *
	 * @return array
	 */
	public function retrieveConfig(){
		$classname = $this->getClass();

		if(file_exists(INCLUDE_PATH.'data'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.$classname.DIRECTORY_SEPARATOR.'config.php')){
			require(INCLUDE_PATH.'data'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.$classname.DIRECTORY_SEPARATOR.'config.php');
			return $config;
		}else{
			return [];
		}
	}

	/**
	 * It has to return the required data for the configuration of the module via CLI, in the form of [ k => ['label'=>label, 'default'=>default], etc... ]
	 *
	 * @return array
	 */
	public function getConfigData(){
		return [];
	}

	/**
	 * Executes eventual postUpdate methods, called after every update
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function postUpdate($from, $to){
		if($from=='0.0.0') // Fresh installation
			return true;

		$methods = $this->getPostUpdateMethods();

		foreach($methods as $idx=>$method){
			$idx = explode('.', $idx);
			$idx = ((int) $idx[0]).'.'.((int) $idx[1]).'.'.((int) $idx[2]);

			if(($from!==null and version_compare($idx, $from)>0) and version_compare($idx, $to)<=0){
				$res = call_user_func(array($this, $method));
				if(!$res){
					if(method_exists($this, $method.'_Backup')){
						call_user_func(array($this, $method.'_Backup'));
					}
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns a list of all postUpdate methods
	 *
	 * @return array
	 */
	private function getPostUpdateMethods(){
		$arr = array();

		$reflection = new \ReflectionClass($this);
		$methods = $reflection->getMethods();
		foreach($methods as $m){
			if(preg_match('/^postUpdate_[0-9]+_[0-9]+_[0-9]+$/', $m->name)){
				$name = explode('_', $m->name);
				$idx = str_pad($name[1], 10, '0', STR_PAD_LEFT)
					.'.'.str_pad($name[2], 10, '0', STR_PAD_LEFT)
					.'.'.str_pad($name[3], 10, '0', STR_PAD_LEFT);
				$arr[$idx] = $m->name;
			}
		}

		ksort($arr);

		return $arr;
	}

	/**
	 * Returns the non-namespaced class name of this module.
	 *
	 * @return string
	 */
	private function getClass(){
		$classname = get_class($this);
		if ($pos = strrpos($classname, '\\')) // Get the non-namespaced class name
			$classname = substr($classname, $pos + 1);
		return substr($classname, 0, -7);
	}
}