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
					case 'install':

						break;
					default:
						$updater = new \Model\Updater($this->model, 0, []);
						$this->viewOptions['modules'] = $updater->getModules(true);
						break;
				}
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