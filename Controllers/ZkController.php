<?php namespace Model\Core\Controllers;

use Model\Core\Controller;
use Model\Core\Exception;
use Model\Core\Updater;

class ZkController extends Controller
{
	/** @var Updater */
	private $updater;

	public function init()
	{
		$this->viewOptions['template-module'] = 'Core';
		$this->viewOptions['template-module-layout'] = 'Core';

		if ($this->model->moduleExists('CSRF'))
			$this->model->load('CSRF');

		$this->updater = new Updater($this->model);

		if ($this->model->isLoaded('Output')) {
			$this->model->_Output->wipeCSS();
			$this->model->_Output->wipeJS();
		}
	}

	public function index()
	{
		$qry_string = http_build_query($this->model->getInput(null, 'get'));
		if ($qry_string)
			$qry_string = '?' . $qry_string;

		$this->viewOptions['cache'] = false;

		switch ($this->model->getRequest(1)) {
			case 'modules':
				$this->viewOptions['template'] = 'modules';

				$this->model->addCSS('model/Core/files/style.css');
				$this->model->addJS('model/Core/files/js.js');

				switch ($this->model->getRequest(2)) {
					case 'install':
						$this->viewOptions['showLayout'] = false;
						$this->viewOptions['template'] = 'new-module';
						$modules = $this->updater->downloadableModules();
						$this->viewOptions['modules'] = $modules;

						if ($this->model->getRequest(3) and isset($modules[$this->model->getRequest(3)])) {
							if (!$this->model->moduleExists($this->model->getRequest(3))) {
								$dir = INCLUDE_PATH . 'model/' . $this->model->getRequest(3);
								if (!is_dir($dir)) {
									if (!mkdir($dir))
										$this->viewOptions['errors'][] = 'Can\'t write to folder.';
									@chmod($dir, 0755);
								}
								$tempModuleData = [
									'name' => $this->model->getRequest(3),
									'description' => '',
									'version' => '0.0.0',
									'dependencies' => [],
								];
								file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($tempModuleData, JSON_PRETTY_PRINT));
								@chmod(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $_GET['new'] . DIRECTORY_SEPARATOR . 'manifest.json', 0755);
							} else {
								$this->viewOptions['errors'][] = 'Module already exists.';
							}

							if ($this->model->isCLI()) {
								if (empty($this->viewOptions['errors'])) {
									$this->updater->cliUpdate($this->model->getRequest(3));
									$this->updater->cliConfig($this->model->getRequest(3), 'init');
								} else {
									echo implode("\n", $this->viewOptions['errors']) . "\n";
								}

								die();
							} else {
								$queue = $this->updater->getUpdateQueue();
								$queue[] = $this->model->getRequest(3);
								$this->updater->setUpdateQueue($queue);

								if (empty($this->viewOptions['errors']))
									die('ok');
							}
						}
						break;
					case 'config':
					case 'init':
						if ($this->model->getRequest(2) == 'init') { // Check if already installed
							$checkModule = new \Model\Core\ReflectionModule($this->model->getRequest(3), $this->model);
							if ($checkModule->installed) {
								if ($this->model->isCLI()) {
									die("Module already installed\n");
								} else {
									$this->model->redirect(PATH . 'zk/modules' . $qry_string);
								}
							}
						}
						$configClass = $this->updater->getConfigClassFor($this->model->getRequest(3));
						if ($configClass) {
							if ($this->model->isCLI()) {
								if ($this->updater->cliConfig($this->model->getRequest(3), $this->model->getRequest(2)))
									$this->model->redirect(PATH . 'zk/modules' . $qry_string);
								die();
							} else {
								$config = $configClass->retrieveConfig();

								$this->viewOptions['template-module'] = $this->model->getRequest(3);
								$this->viewOptions['template'] = $configClass->getTemplate($this->model->getRequest());
								if ($this->viewOptions['template'] === null) {
									if ($this->model->getRequest(2) == 'init') {
										$installation = $this->updater->install($configClass);
										if ($installation) {
											$this->updater->firstInit($this->model->getRequest(3));
											$this->model->redirect(PATH . 'zk/modules' . $qry_string);
										} else {
											$this->viewOptions['errors'][] = 'Something is wrong, can\'t initialize module ' . $this->model->getRequest(3);
										}
									}
								}
								$this->viewOptions['config-class'] = $configClass;
								$this->viewOptions['config'] = $config;
							}
						} else {
							if ($this->model->getRequest(2) == 'init') {
								$this->updater->firstInit($this->model->getRequest(3));
								$this->model->redirect(PATH . 'zk/modules' . $qry_string);
							}
						}
						break;
					case 'update':
						if ($this->model->isCLI()) {
							$this->updater->cliUpdate($this->model->getInput('module'));
						} else {
							$this->updater->checkUpdateQueue($this->model->getInput('module'));

							$files = $this->updater->getModuleFileList($this->model->getInput('module'));
							if (!$files) {
								die("File list not found\n");
							} elseif (!$files['update'] and !$files['delete']) {
								die("Nothing to update\n");
							}

							$_SESSION[SESSION_ID]['delete-from-module-' . $this->model->getInput('module')] = $files['delete'];
							$this->model->sendJSON($files['update'], false);
						}

						die();
						break;
					case 'update-file':
						if ($this->model->isCLI()) {
							die('Unsupported action in CLI');
						} else {
							if (!isset($_GET['file']))
								die('Missing data');
							if ($this->updater->updateFile($_GET['module'], $_GET['file']))
								echo 'ok';
							else
								echo 'Error while updating file.';
							die();
						}
						break;
					case 'finalize-update':
						if ($this->model->isCLI()) {
							die('Unsupported action in CLI');
						} else {
							if ($this->updater->finalizeUpdate($_GET['module'], $_SESSION[SESSION_ID]['delete-from-module-' . $_GET['module']]))
								echo 'ok';
							else
								echo 'Error while finalizing the update, you might need to update manually.';
							die();
						}
						break;
					default:
						$modules = $this->updater->getModules(true);

						// Check that all dependencies are satisfied, and check if some module still has to be installed
						$toBeInstalled = [];
						foreach ($modules as $m) {
							if ($m->version != '0.0.0' and !$m->installed) {
								$toBeInstalled[] = $m;
							} else {
								if ($m->getConfigClass())
									$m->getConfigClass()->checkAssets();
							}

							foreach ($m->dependencies as $depModule => $depVersion) {
								if (!isset($modules[$depModule])) {
									$this->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '" is not installed!';
								} else {
									if ($depVersion == '*')
										continue;

									if (strpos($depVersion, '>=') === 0 or strpos($depVersion, '<=') === 0 or strpos($depVersion, '<>') === 0 or strpos($depVersion, '!=') === 0 or strpos($depVersion, '==') === 0) {
										$compareOperator = substr($depVersion, 0, 2);
										$compareToVersion = substr($depVersion, 2);
									} elseif (strpos($depVersion, '>') === 0 or strpos($depVersion, '<') === 0 or strpos($depVersion, '=') === 0) {
										$compareOperator = substr($depVersion, 0, 1);
										$compareToVersion = substr($depVersion, 1);
									} else {
										$compareOperator = '=';
										$compareToVersion = $depVersion;
									}

									if (!version_compare($modules[$depModule]->version, $compareToVersion, $compareOperator))
										$this->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '", does not match required version of ' . $depVersion;
								}
							}
						}

						$nextToInstall = false;
						if (count($toBeInstalled) > 0) {
							// I sort the modules so that I can install the modules without dependencies first
							usort($toBeInstalled, function ($a, $b) {
								if (count($a->dependencies) == count($b->dependencies)) {
									return 0;
								} else {
									return count($a->dependencies) > count($b->dependencies) ? 1 : -1;
								}
							});

							$nextToInstall = reset($toBeInstalled);
						}

						if ($this->model->getRequest(2) == 'refresh') {
							if ($nextToInstall) {
								die(json_encode(['action' => 'init', 'module' => $nextToInstall->folder_name]));
							}

							$this->viewOptions['showLayout'] = false;
							$this->viewOptions['template'] = 'module';
							$this->viewOptions['module'] = $modules[$_GET['module']];
						} else {
							if ($nextToInstall)
								$this->model->redirect(PATH . 'zk/modules/init/' . $nextToInstall->folder_name . $qry_string);

							$this->viewOptions['update-queue'] = $this->updater->getUpdateQueue();

							if ($this->model->isCLI() and count($this->viewOptions['update-queue']) > 0) {
								$this->updater->cliUpdate($this->viewOptions['update-queue'][0]);
								$modules = $this->updater->getModules(true);
							}

							$this->viewOptions['modules'] = $modules;
						}
						break;
				}
				break;
			case 'local-modules':
				$this->model->addCSS('model/Core/files/style.css');
				$this->model->addJS('model/Core/files/js.js');

				$modules = $this->updater->getModules(false, 'app' . DIRECTORY_SEPARATOR . 'modules');
				if ($this->model->getRequest(2) and isset($modules[$this->model->getRequest(2)])) {
					$this->viewOptions['template'] = 'local-module';
					$this->viewOptions['module'] = $modules[$this->model->getRequest(2)];
				} else {
					$this->viewOptions['template'] = 'local-modules';
					$this->viewOptions['modules'] = $modules;
				}
				break;
			case 'make-cache':
				try {
					$modules = $this->updater->getModules();

					$this->updater->updateModuleCache('Core');

					foreach ($modules as $mName => $m) {
						if ($mName == 'Core')
							continue;
						if ($m->hasConfigClass()) {
							$this->updater->updateModuleCache($mName);
						}
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
				if ($this->model->isCLI()) {
					$this->model->sendJSON($_SESSION[SESSION_ID]);
				} else {
					zkdump($_SESSION[SESSION_ID]);
				}
				die();
				break;
			default:
				$this->model->redirect(PATH . 'zk/modules' . $qry_string);
				break;
		}
	}

	public function outputCLI(bool $asFallback = false)
	{
		switch ($this->model->getRequest(1)) {
			case 'modules':
				switch ($this->model->getRequest(2)) {
					case 'install':
						echo "Downloadable modules:\n";
						break;
					default:
						echo "Installed modules:\n";
						break;
				}

				if (count($this->viewOptions['modules']) > 0) {
					foreach ($this->viewOptions['modules'] as $m) {
						if (is_array($m)) {
							echo '* ' . $m['name'] . " (v" . $m['current_version'] . ")\n";
						} else {
							echo '* ' . $m->name . " (v" . $m->version . ")\n";
						}
					}
				} else {
					echo "No module found\n";
				}
				break;
			default:
				die('CLI not supported for the request.');
				break;
		}
	}

	public function post()
	{
		$qry_string = http_build_query($this->model->getInput(null, 'get'));
		if ($qry_string)
			$qry_string = '?' . $qry_string;

		try {
			switch ($this->model->getRequest(1)) {
				case 'modules':
					switch ($this->model->getRequest(2)) {
						case 'config':
						case 'init':
							$configClass = $this->updater->getConfigClassFor($this->model->getRequest(3));
							if ($configClass) {
								switch ($this->model->getRequest(2)) {
									case 'config':
										if ($configClass->saveConfig($this->model->getRequest(2), $_POST))
											$this->viewOptions['messages'][] = 'Configuration saved.';
										break;
									case 'init':
										if ($this->updater->install($configClass, $_POST)) {
											$this->updater->firstInit($this->model->getRequest(3));
											$this->model->redirect(PATH . 'zk/modules' . $qry_string);
										} else {
											$this->viewOptions['errors'][] = 'Some error occurred while installing.';
										}
										break;
								}
							}
							break;
					}
					break;
			}
		} catch (\Exception $e) {
			$this->viewOptions['errors'][] = $e->getMessage();
		}

		$this->index();
	}
}
