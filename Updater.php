<?php
namespace Model;

class Updater extends Module{
	/** @var array */
	public $model_files = array(
		'index.php',
		'img/loading.gif',
	);

	/**
	 * Get a list of the current installed modules
	 * If $get_updates is true, check on the repository if a new version is available
	 *
	 * @param bool $get_updates
	 * @param string $base_dir
	 * @return ReflectionModule[]
	 */
	function getModules($get_updates=false, $base_dir=''){
		$modules = array();

		$dirs = glob(INCLUDE_PATH.$base_dir.'model/*');
		foreach($dirs as $f){
			if(is_dir($f) and file_exists($f.'/model.php')){
				$name = explode('/', $f); $name = end($name);
				$modules[$name] = new ReflectionModule($name, $this->model, $base_dir, $name=='Core' ? $this->model_files : []);
			}
		}

		if($get_updates){
			$repository = $this->model->_Core->retrieveConfig()['repository'];

			$modules_arr = array();
			foreach($modules as $m)
				$modules_arr[] = $m->folder_name.'|'.$m->version;

			$remote_str = file_get_contents($repository.'/get-modules?modules='.urlencode(implode(',', $modules_arr)).'&key='.urlencode(ZK_KEY));
			$remote = json_decode($remote_str, true);
			if($remote!==null){
				foreach($modules as $name=>&$m){
					if(isset($remote[$name]) and $remote[$name]){
						$m->official = true;

						if($remote[$name]['old_md5']){
							$m->expected_md5 = $remote[$name]['old_md5'];
							if($m->md5!=$m->expected_md5)
								$m->corrupted = true;
						}
						if($remote[$name]['current_version'] and version_compare($remote[$name]['current_version'], $m->version, '>'))
							$m->new_version = $remote[$name]['current_version'];
					}else{
						$m->official = false;
					}
				}
				unset($m);
			}
		}

		return $modules;
	}
}