<?php
class ZkController extends \Model\Controller {
	function init(){
		$this->viewOptions['template-path'] = 'model/Core/templates';
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
					case 'install':
						if(file_exists(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$this->model->getRequest(3).DIRECTORY_SEPARATOR.$this->model->getRequest(3).'_Config.php')){
							require(INCLUDE_PATH.'model'.DIRECTORY_SEPARATOR.$this->model->getRequest(3).DIRECTORY_SEPARATOR.$this->model->getRequest(3).'_Config.php');
							$configClass = '\\Model\\'.$this->model->getRequest(3).'_Config';
							$configClass = new $configClass($this->model);
							$this->viewOptions['template'] = $configClass->getTemplate($this->model->getRequest());
						}
						break;
					default:
						$updater = new \Model\Updater($this->model, 0, []);
						$this->viewOptions['modules'] = $updater->getModules(true);
						break;
				}
				break;
			case 'make-cache':
				$updater = new \Model\Updater($this->model, 0, []);
				$modules = $updater->getModules();

				$Core_Config = new \Model\Core_Config($this->model);
				if(!$Core_Config->makeCache())
					die('Error: can\'t make cache for the Core.');

				$modulesConfigs = [];
				foreach($modules as $mIdx=>$m){
					if($mIdx=='Core')
						continue;
					if($m->hasConfigClass){
						$modulesConfigs['\\Model\\'.$mIdx.'_Config'] = false;
					}
				}

				for($c=1;$c<=2;$c++){ // Since some modules require others to have cache updated, I run through each one twice, instead of manually forcing the order
					foreach($modulesConfigs as $className=>$mStatus){
						$moduleConfig = new $className($this->model);
						$modulesConfigs[$className] = $moduleConfig->makeCache();
					}
				}

				foreach($modulesConfigs as $className=>$mStatus){
					if(!$mStatus)
						die('Error in making cache for module '.$className);
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
				if($this->model->isCLI())
					die('Invalid request.'.PHP_EOL);
				else
					$this->model->redirect(PATH.'zk/modules');
				break;
		}
	}

	function outputCLI(){
		switch($this->model->getRequest(2)){
			case 'config':
				die('Module configuration still supported only via browser.');
				break;
			case 'install':
				die('Module installation still supported only via browser.');
				break;
			default:
				$this->model->sendJSON($this->viewOptions['modules']);
				break;
		}
	}
}