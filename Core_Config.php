<?php
namespace Model;

class Core_Config extends Module_Config {
	public $configurable = true;

	/**
	 * Caches the following:
	 * - All the available modules
	 * - All the rules registered by the modules
	 * - All the classes for the Autoloader to get to know them
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function makeCache(){
		$classes = [];
		$rules = [];
		$modules = [];

		$dirs = glob(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'*');
		foreach($dirs as $d){
			if(file_exists($d.DIRECTORY_SEPARATOR.'model.php')){
				$d_info = pathinfo($d);
				$modules[$d_info['filename']] = [
					'path'=>substr($d, strlen(INCLUDE_PATH)),
					'load'=>file_exists($d.DIRECTORY_SEPARATOR.$d_info['filename'].'.php'),
				];

				require($d.DIRECTORY_SEPARATOR.'model.php');
				if(isset($moduleData['autoload']) and !$moduleData['autoload'])
					$modules[$d_info['filename']]['autoload'] = false;

				$files = glob($d.DIRECTORY_SEPARATOR.'*');
				foreach($files as $f){
					if(is_dir($f))
						continue;
					$file = pathinfo($f);
					if($file['extension']!='php' or $file['basename']=='model.php' or $file['basename']=='README.md')
						continue;

					$classes[$file['filename']] = $f;
				}

				if(is_dir($d.DIRECTORY_SEPARATOR.'controllers')){
					$files = glob($d.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'*');
					foreach($files as $f){
						if(is_dir($f))
							continue;
						$file = pathinfo($f);
						if($file['extension']!='php')
							continue;

						$classes[$file['filename']] = $f;
					}
				}

				if(file_exists($d.DIRECTORY_SEPARATOR.$d_info['filename'].'_Config.php')){
					include_once($d.DIRECTORY_SEPARATOR.$d_info['filename'].'_Config.php');
					$configClassName = '\\Model\\'.$d_info['filename'].'_Config';
					$configClass = new $configClassName($this->model);

					$moduleRules = $configClass->getRules();
					if(!is_array($moduleRules))
						throw new \Exception('The module '.$d_info['filename'].' returned a non-array as rules.');

					foreach($moduleRules as $rIdx=>$r){
						if(isset($rules[$r]))
							continue;
						$rules[$r] = [
							'module'=>$d_info['filename'],
							'idx'=>$rIdx,
						];
					}

					$moduleClasses = $configClass->getClasses();
					if(!is_array($moduleClasses))
						throw new \Exception('The module '.$d_info['filename'].' returned a non-array as classes.');

					$classes = array_merge($classes, $moduleClasses);
				}
			}
		}

		$files = glob(INCLUDE_PATH.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'*');
		foreach($files as $f){
			if(is_dir($f))
				continue;
			$file = pathinfo($f);
			if($file['extension']!='php')
				continue;

			$classes[$file['filename']] = $f;
		}

		$cacheFile = INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Core'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'cache.php';
		$scrittura = file_put_contents($cacheFile, '<?php
$classes = '.var_export($classes, true).';
$rules = '.var_export($rules, true).';
$modules = '.var_export($modules, true).';
');
		if(!$scrittura)
			return false;

		return true;
	}

	/**
	 * The Core module needs a "zk" rule to manage the basics of the framework
	 *
	 * @return array
	 */
	public function getRules(){
		return [
			'zk'=>'zk',
		];
	}

	/**
	 * Returns the config template
	 *
	 * @param array $request
	 * @return string
	 */
	public function getTemplate($request){
		return $request[2]=='config' ? INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.'Core'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'config' : null;
	}

	/**
	 * Save the configuration
	 *
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function saveConfig($type, $data){
		$config = $this->retrieveConfig();

		$configFile = INCLUDE_PATH.'data'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'Core'.DIRECTORY_SEPARATOR.'config.php';

		$data = ['repository', 'license'];
		foreach($data as $d){
			if(isset($_POST[$d]))
				$config[$d] = $_POST[$d];
		}

		$w = file_put_contents($configFile, '<?php
$config = '.var_export($config, true).';
');
		return (bool) $w;
	}
}