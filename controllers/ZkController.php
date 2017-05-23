<?php
class ZkController extends \Model\Controller {
	/** @var \Model\Updater */
	private $updater;

	function init(){
		$this->viewOptions['template-path'] = 'model/Core/templates';
		$this->updater = new \Model\Updater($this->model, 0, []);
	}

	function index(){
		switch($this->model->getRequest(1)){
			case 'modules':
				$this->viewOptions['template'] = 'modules';
				$this->viewOptions['cache'] = false;

				$this->model->addCSS('model/Core/templates/style.css');
				$this->model->addJS('model/Core/templates/js.js');

				switch($this->model->getRequest(2)){
					case 'config':
					case 'init':
						if($this->model->getRequest(2)=='init'){ // Check if already installed
							$checkModule = new \Model\ReflectionModule($this->model->getRequest(3), $this->model);
							if($checkModule->installed){
								if($this->model->isCLI()){
									die("Module already installed\n");
								}else{
									$this->model->redirect(PATH.'zk/modules');
								}
							}
						}
						$configClass = $this->getConfigClassFor($this->model->getRequest(3));
						if($configClass){
							$config = $configClass->retrieveConfig();
							if($this->model->isCLI()){
								$configData = $configClass->getConfigData();

								$data = [];
								if($configData){
									echo "-------------------\nConfiguration of ".$this->model->getRequest(3)."...\nLeave data empty to keep defaults\n\n";
									$handle = fopen ("php://stdin","r");
									foreach($configData as $k=>$d){
										echo $d['label'].($d['default']!==null ? ' (default '.$d['default'].')' : '').': ';
										$line = trim(fgets($handle));
										if($line)
											$data[$k] = $line;
										else
											$data[$k] = $d['default'];
									}
									fclose($handle);
								}

								switch ($this->model->getRequest(2)) {
									case 'config':
										if($configClass->saveConfig('config', $data))
											echo "Configuration saved\n";
										break;
									case 'init':
										if($configClass->install($data)){
											$this->updater->markAsInstalled($this->model->getRequest(3));
											echo "Module ".$this->model->getRequest(3)." initialized\n";
											$this->model->redirect(PATH . 'zk/modules');
										}else{
											echo "Some error occurred while installing.\n";
										}
										break;
								}

								die();
							}else {
								$this->viewOptions['template'] = $configClass->getTemplate($this->model->getRequest());
								if ($this->viewOptions['template'] === null) {
									if ($this->model->getRequest(2) == 'init') {
										$installation = $configClass->install();
										if ($installation) {
											$this->updater->markAsInstalled($this->model->getRequest(3));
											$this->model->redirect(PATH . 'zk/modules');
										} else {
											$this->viewOptions['errors'][] = 'Something is wrong, can\'t initialize module ' . $this->model->getRequest(3);
										}
									}
								}
								$this->viewOptions['config'] = $config;
							}
						}else{
							if($this->model->getRequest(2)=='init'){
								$this->updater->markAsInstalled($this->model->getRequest(3));
								$this->model->redirect(PATH.'zk/modules');
							}
						}
						break;
					case 'update':
						if($this->model->isCLI()){
							$this->updater->cliUpdate($this->model->getInput('module'));
						}else{
							$this->updater->checkUpdateQueue($this->model->getInput('module'));

							$files = $this->updater->getModuleFileList($this->model->getInput('module'));
							if(!$files){
								die("File list not found\n");
							}elseif(!$files['update'] and !$files['delete']){
								die("Nothing to update\n");
							}

							$_SESSION[SESSION_ID]['delete-from-module-'.$this->model->getInput('module')] = $files['delete'];
							$this->model->sendJSON($files['update'], false);
						}

						die();
						break;
					case 'update-file':
						if($this->model->isCLI()){
							die('Unsupported action in CLI');
						}else{
							if(!isset($_GET['file']))
								die('Missing data');
							if ($this->updater->updateFile($_GET['module'], $_GET['file']))
								echo 'ok';
							else
								echo 'Error while updating file.';
							die();
						}
						break;
					case 'finalize-update':
						if($this->model->isCLI()){
							die('Unsupported action in CLI');
						}else{
							if ($this->updater->finalizeUpdate($_GET['module'], $_SESSION[SESSION_ID]['delete-from-module-'.$_GET['module']]))
								echo 'ok';
							else
								echo 'Error while finalizing the update, you might need to update manually.';
							die();
						}
						break;
					case 'refresh':
						$modules = $this->updater->getModules(true);
						$this->viewOptions['showLayout'] = false;
						$this->viewOptions['template'] = 'module';
						$this->viewOptions['module'] = $modules[$_GET['module']];
						break;
					case 'new':
						$this->viewOptions['showLayout'] = false;
						$this->viewOptions['template'] = 'new-module';
						$modules = $this->updater->downloadableModules();
						$this->viewOptions['modules'] = $modules;
						break;
					default:
						$modules = $this->updater->getModules(true);

						// Check that all dependencies are satisfied, and check if some module still has to be installed
						$toBeInstalled = [];
						foreach($modules as $m){
							if(!$m->installed)
								$toBeInstalled[] = $m;

							foreach($m->dependencies as $depModule=>$depVersion){
								if(!isset($modules[$depModule])){
									$this->viewOptions['errors'][] = 'Module "'.$depModule.'", dependency of "'.$m->name.'" is not installed!';
								}else{
									if($depVersion=='*')
										continue;

									if(strpos($depVersion, '>=')===0 or strpos($depVersion, '<=')===0 or strpos($depVersion, '<>')===0 or strpos($depVersion, '!=')===0 or strpos($depVersion, '==')===0){
										$compareOperator = substr($depVersion, 0, 2);
										$compareToVersion = substr($depVersion, 2);
									}elseif(strpos($depVersion, '>')===0 or strpos($depVersion, '<')===0 or strpos($depVersion, '=')===0){
										$compareOperator = substr($depVersion, 0, 1);
										$compareToVersion = substr($depVersion, 1);
									}else{
										$compareOperator = '=';
										$compareToVersion = $depVersion;
									}

									if(!version_compare($modules[$depModule]->version, $compareToVersion, $compareOperator))
										$this->viewOptions['errors'][] = 'Module "'.$depModule.'", dependency of "'.$m->name.'", does not match required version of '.$depVersion;
								}
							}
						}

						if(count($toBeInstalled)>0){
							// I sort the modules so that I can install the modules without dependencies first
							usort($toBeInstalled, function($a, $b){
								if(count($a->dependencies)==count($b->dependencies)){
									return 0;
								}else{
									return count($a->dependencies)>count($b->dependencies) ? 1 : -1;
								}
							});

							$first = reset($toBeInstalled);
							$this->model->redirect(PATH.'zk/modules/init/'.$first->folder_name);
						}

						$this->viewOptions['update-queue'] = $this->updater->getUpdateQueue();

						if($this->model->isCLI() and count($this->viewOptions['update-queue'])>0){
							$this->updater->cliUpdate($this->viewOptions['update-queue'][0]);
							$modules = $this->updater->getModules(true);
						}

						$this->viewOptions['modules'] = $modules;
						break;
				}
				break;
			case 'make-cache':
				try {
					$modules = $this->updater->getModules();

					$Core_Config = new \Model\Core_Config($this->model);
					if (!$Core_Config->makeCache())
						$this->model->error('Error: can\'t make cache for the Core.');

					$modulesConfigs = [];
					foreach ($modules as $mIdx => $m) {
						if ($mIdx == 'Core')
							continue;
						if ($m->hasConfigClass) {
							$modulesConfigs['\\Model\\' . $mIdx . '_Config'] = false;
						}
					}

					$cacheErrors = [];
					for ($c = 1; $c <= 2; $c++) { // Since some modules require others to have cache updated, I run through each one twice, instead of manually forcing the order
						foreach ($modulesConfigs as $className => $mStatus) {
							try {
								$moduleConfig = new $className($this->model);
								$modulesConfigs[$className] = $moduleConfig->makeCache();
							} catch (Exception $e) {
								$modulesConfigs[$className] = false;
								$cacheErrors[$className] = $e->getMessage();
							}
						}
					}

					foreach ($modulesConfigs as $className => $mStatus) {
						if (!$mStatus)
							$this->model->error('Error in making cache for module ' . $className.(isset($cacheErrors[$className]) ? "\n".$cacheErrors[$className] : ''));
					}
				} catch (Exception $e) {
					die(getErr($e));
				}

				die('Cache succesfully updated.');
				break;
			case 'empty-session':
				$_SESSION[SESSION_ID] = array();
				die('Session cleared.');
				break;
			case 'inspect-session':
				if($this->model->isCLI()){
					$this->model->sendJSON($_SESSION[SESSION_ID]);
				}else{
					zkdump($_SESSION[SESSION_ID]);
				}
				die();
				break;
			default:
				$this->model->redirect(PATH.'zk/modules');
				break;
		}
	}

	function outputCLI(){
		switch($this->model->getRequest(1)){
			case 'modules':
				switch($this->model->getRequest(2)){
					case 'new':
						echo "Downloadable modules:\n";
						break;
				}

				if(count($this->viewOptions['modules'])>0){
					foreach($this->viewOptions['modules'] as $m){
						if(is_array($m)){
							echo '* '.$m['name']." (v".$m['current_version'].")\n";
						}else{
							echo '* '.$m->name." (v".$m->version.")\n";
						}
					}
				}else{
					echo "No module found\n";
				}
				break;
			default:
				die('CLI not supported for the request.');
				break;
		}
	}

	function post(){
		switch($this->model->getRequest(1)) {
			case 'modules':
				switch ($this->model->getRequest(2)) {
					case 'config':
					case 'init':
						$configClass = $this->getConfigClassFor($this->model->getRequest(3));
						if($configClass){
							switch ($this->model->getRequest(2)) {
								case 'config':
									if($configClass->saveConfig($this->model->getRequest(2), $_POST))
										$this->viewOptions['messages'][] = 'Configuration saved.';
									break;
								case 'init':
									if($configClass->install($_POST)){
										$this->updater->markAsInstalled($this->model->getRequest(3));
										$this->model->redirect(PATH.'zk/modules');
									}else{
										$this->viewOptions['errors'][] = 'Some error occurred while installing.';
									}
									break;
							}
						}
						break;
				}
				break;
		}

		$this->index();
	}

	/**
	 * @param string $name
	 * @return \Model\Module_Config|bool
	 */
	public function getConfigClassFor($name){
		if(file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$name.'_Config.php')) {
			require_once(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $name . '_Config.php');
			$configClass = '\\Model\\' . $name . '_Config';
			$configClass = new $configClass($this->model);
			return $configClass;
		}else{
			return false;
		}
	}
}