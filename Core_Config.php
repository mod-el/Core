<?php
namespace Model;

class Core_Config extends Module_Config {
	/**
	 * Caches the following:
	 * - All the available modules
	 * - All the rules registered by the modules
	 * - All the classes for the Autoloader to get to know them
	 *
	 * @return bool
	 * @throws \Exception
	 */
	function makeCache(){
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

				if(file_exists($d.DIRECTORY_SEPARATOR.$d_info['filename'].'_Config.php')){
					include_once($d.DIRECTORY_SEPARATOR.$d_info['filename'].'_Config.php');
					$configClassName = '\\Model\\'.$d_info['filename'].'_Config';
					$configClass = new $configClassName();

					$moduleRules = $configClass->getRules();
					if(!is_array($moduleRules))
						throw new \Exception('The module '.$d_info['filename'].' returned a non-array as rules.');

					foreach($moduleRules as $r){
						if(isset($rules[$r]))
							continue;
						$rules[$r] = $d_info['filename'];
					}
				}
			}
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
	function getRules(){
		return ['zk'];
	}
}