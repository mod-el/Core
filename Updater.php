<?php
namespace Model;

class Updater extends Module{
	/**
	 * Get a list of the current installed modules
	 * If $get_updates is true, check on the repository if a new version is available
	 *
	 * @param bool $get_updates
	 * @param string $base_dir
	 * @return ReflectionModule[]
	 */
	public function getModules($get_updates=false, $base_dir=''){
		$modules = array();

		$dirs = glob(INCLUDE_PATH.$base_dir.'model/*');
		foreach($dirs as $f){
			if(is_dir($f) and file_exists($f.'/model.php')){
				$name = explode('/', $f); $name = end($name);
				$modules[$name] = new ReflectionModule($name, $this->model, $base_dir);
			}
		}

		if($get_updates){
			$config = $this->model->_Core->retrieveConfig();

			$modules_arr = array();
			foreach($modules as $m)
				$modules_arr[] = $m->folder_name.'|'.$m->version;

			$remote_str = file_get_contents($config['repository'].'?act=get-modules&modules='.urlencode(implode(',', $modules_arr)).'&key='.urlencode($config['license']));
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

	/**
	 * Sets the internal status of a module as installed
	 *
	 * @param $name
	 * @return bool
	 */
	public function markAsInstalled($name){
		if(!file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'))
			mkdir(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data');
		$file_path = INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'vars.php';

		if(!file_exists($file_path)){
			file_put_contents($file_path, "<?php\n");
			@chmod($file_path, 0755);
		}

		$text = file_get_contents($file_path);

		if(stripos($text, '$installed')!==false){
			return (bool) file_put_contents($file_path, preg_replace('/\$installed ?=.+;/i', '$installed = true;', $text));
		}else{
			return (bool) file_put_contents($file_path, $text."\n".'$installed = true;'."\n");
		}
	}

	/**
	 * Retrieves file list for a module from the repository
	 *
	 * @param string $name
	 * @return array|bool
	 */
	public function getModuleFileList($name){
		$config = $this->model->_Core->retrieveConfig();

		$files = file_get_contents($config['repository'].'?act=get-files&module='.urlencode($name).'&key='.urlencode($config['license']).'&md5');
		$files = json_decode($files, true);
		if(!$files)
			return false;

		$filesToUdate = []; $filesToDelete = []; $filesArr = [];
		foreach($files as $f){
			$filesArr[] = $f['path'];
			if(file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$f['path'])){
				$md5 = md5(file_get_contents(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$f['path']));
				if($md5!=$f['md5'])
					$filesToUdate[] = $f['path'];
			}else{
				$filesToUdate[] = $f['path'];
			}
		}

		$module = new ReflectionModule($name, $this->model);
		foreach($module->files as $f){
			if(!in_array($f['path'], $filesArr))
				$filesToDelete[] = $f['path'];
		}

		return ['update'=>$filesToUdate, 'delete'=>$filesToDelete];
	}

	/**
	 * Retrieves a single file from the repository and writes it in the temp folder
	 *
	 * @param string $name
	 * @param string $file
	 * @return bool
	 */
	public function updateFile($name, $file){
		$config = $this->model->_Core->retrieveConfig();

		$content = file_get_contents($config['repository'].'?act=get-file&module='.urlencode($name).'&file='.urlencode($file).'&key='.urlencode($config['license']));
		if($content=='File not found')
			$this->model->error('File '.$file.' not found');

		$arr_path = explode('/', $file);
		$buildingPath = '';
		foreach($arr_path as $f){
			if(stripos($f, '.')!==false) break; // File
			if(!is_dir(INCLUDE_PATH.$buildingPath.$f))
				mkdir(INCLUDE_PATH.$buildingPath.$f);
			$buildingPath .= $f.'/';
		}

		$temppath = INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR.$file;
		$path = pathinfo($temppath, PATHINFO_DIRNAME);

		if(!is_dir($path))
			mkdir($path, 0755, true);
		$saving = file_put_contents($temppath, $content);
		if($saving!==false){
			@chmod($temppath, 0755);
			return true;
		}else{
			return false;
		}
	}

	/**
	 * After all files have been downloaded, this method copies them all at once in the correct location, and executes post updates
	 *
	 * @param string $name
	 * @param array $delete
	 * @return bool
	 */
	public function finalizeUpdate($name, $delete){
		$old_version = null;
		if(file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'model.php')){
			include(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'model.php');
			$old_version = $moduleData['version'];
		}

		foreach($delete as $f){
			unlink(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$f);
		}
		if(!$this->deleteDirectory('model'.DIRECTORY_SEPARATOR.$name, true))
			return false;
		if(!$this->recursiveCopy('model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'temp', 'model'.DIRECTORY_SEPARATOR.$name))
			return false;
		if(!$this->deleteDirectory('model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'temp'))
			return false;

		$configClassFileName = $name.'_Config';
		$configClassName = '\\Model\\'.$configClassFileName;
		if(file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$configClassFileName.'.php')){
			$module = new ReflectionModule($name, $this->model);
			$new_version = $module->version;

			require_once(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$configClassFileName.'.php');
			$configClass = new $configClassName($this->model);
			$postUpdate = $configClass->postUpdate($old_version, $new_version);
			if(!$postUpdate)
				return false;
			$configClass->makeCache();
		}

		return true;
	}

	/**
	 * Delete a directory (if onlyEmpties is true, it just cleans the empty directories)
	 *
	 * @param string $folder
	 * @param bool $onlyEmpties
	 * @return bool
	 */
	function deleteDirectory($folder, $onlyEmpties=false){
		if(!file_exists(INCLUDE_PATH.$folder))
			return true;

		$ff = glob(INCLUDE_PATH.$folder.DIRECTORY_SEPARATOR.'*');
		foreach($ff as $f){
			$f_name = substr($f, strlen(INCLUDE_PATH.$folder.DIRECTORY_SEPARATOR));
			if(is_dir($f)){
				if(!$this->deleteDirectory($folder.DIRECTORY_SEPARATOR.$f_name, $onlyEmpties))
					return false;
			}elseif(!$onlyEmpties){
				unlink($f);
			}
		}
		if(!$onlyEmpties or count($ff)==0)
			return rmdir(INCLUDE_PATH.$folder);

		return true;
	}

	/**
	 * Recursive copy of a folder into a destination
	 *
	 * @param string $source
	 * @param string $dest
	 * @return bool
	 */
	function recursiveCopy($source, $dest){
		$folder = INCLUDE_PATH.$source.'/';
		$ff = glob($folder.'*');
		foreach($ff as $f){
			$name = substr($f, strlen($folder));
			if(is_dir($f)){
				if(!file_exists(INCLUDE_PATH.$dest.'/'.$name))
					mkdir(INCLUDE_PATH.$dest.'/'.$name);
				$this->recursiveCopy($source.'/'.$name, $dest.'/'.$name);
			}else{
				copy($f, INCLUDE_PATH.$dest.'/'.$name);
			}
		}

		return true;
	}
}