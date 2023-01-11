<?php namespace Model\Core\Controllers;

use Composer\InstalledVersions;
use Model\Core\Controller;
use Model\Core\Exception;
use Model\Core\ReflectionModule;
use Model\Core\Updater;

class ZkController extends Controller
{
	private Updater $updater;
	protected array $options = [];
	private array $injected = [];
	private string $lastBreakingChange = '2.8.1';

	public function init()
	{
		$this->model->viewOptions['cache'] = false;

		$this->model->viewOptions['template-module'] = 'Core';
		$this->updater = new Updater($this->model);
		if ($this->model->isLoaded('Log'))
			$this->model->_Log->disableAutoLog();
	}

	public function get()
	{
		$qry_string = http_build_query($this->model->getInput(null, 'get'));
		if ($qry_string)
			$qry_string = '?' . $qry_string;

		switch ($this->model->getRequest(1)) {
			case 'modules':
				$this->model->viewOptions['template'] = 'modules';

				switch ($this->model->getRequest(2)) {
					case 'install':
						$this->model->viewOptions['showLayout'] = false;
						$this->model->viewOptions['template'] = 'new-module';
						$modules = $this->updater->downloadableModules();
						$this->injected['modules'] = $modules;
						break;

					case 'config':
					case 'init':
						if (!$this->model->getRequest(3))
							die('No module specified');

						if ($this->model->getRequest(2) == 'init') { // Check if already installed
							$checkModule = new ReflectionModule($this->model->getRequest(3), $this->model);
							if ($checkModule->installed)
								$this->model->redirect(PATH . 'zk/modules' . $qry_string);
						}

						$configClass = $this->updater->getConfigClassFor($this->model->getRequest(3));
						if ($configClass) {
							$this->model->viewOptions['template-module'] = $this->model->getRequest(3);
							$this->model->viewOptions['template'] = $configClass->getTemplate($this->model->getRequest(2));

							if ($this->model->viewOptions['template'] === null)
								die('Can\'t find ' . $this->model->getRequest(2) . ' template for the module');

							$config = $configClass->retrieveConfig();
							$this->injected['configClass'] = $configClass;
							$this->injected['config'] = $config;
							/*}*/
						} else {
							die('Can\'t find config class for the module');
						}
						break;

					case 'files-list':
						if (!$this->model->getInput('modules'))
							die('Invalid data');

						$modules = explode(',', $this->model->getInput('modules'));
						$files = $this->updater->getFilesList($modules);
						if (!$files)
							die("File list not found\n");

						$_SESSION['delete-files'] = $files['delete'];
						return $files['update'];

					case null:
						$modules = $this->updater->getModules(true);
						$modules = $this->updater->topSortModules($modules);

						// Check that all dependencies are satisfied, and check if some module still has to be initialized
						$toBeInitialized = [];
						foreach ($modules as $m) {
							$allDependenciesSatisfied = true;

							foreach ($m->requires as $package) {
								if (!InstalledVersions::isInstalled($package)) {
									$this->model->viewOptions['errors'][] = 'Composer library "' . $package . '", dependency of "' . $m->name . '" is not installed!';
									$allDependenciesSatisfied = false;
								}
							}

							foreach ($m->dependencies as $depModule => $depVersion) {
								if (!isset($modules[$depModule])) {
									$this->model->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '" is not installed!';
									$allDependenciesSatisfied = false;
								} else {
									if ($depVersion === '*')
										continue;

									if (str_starts_with($depVersion, '>=') or str_starts_with($depVersion, '<=') or str_starts_with($depVersion, '<>') or str_starts_with($depVersion, '!=') or str_starts_with($depVersion, '==')) {
										$compareOperator = substr($depVersion, 0, 2);
										$compareToVersion = substr($depVersion, 2);
									} elseif (str_starts_with($depVersion, '>') or str_starts_with($depVersion, '<') or str_starts_with($depVersion, '=')) {
										$compareOperator = substr($depVersion, 0, 1);
										$compareToVersion = substr($depVersion, 1);
									} else {
										$compareOperator = '=';
										$compareToVersion = $depVersion;
									}

									if (isset($modules[$depModule]->version) and !version_compare($modules[$depModule]->version, $compareToVersion, $compareOperator)) {
										$this->model->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '", does not match required version of ' . $depVersion;
										$allDependenciesSatisfied = false;
									}

									if (!$modules[$depModule]->installed and !in_array($depModule, array_map(function ($m) {
											return $m->folder_name;
										}, $toBeInitialized))) // Installed but not initalized (and moreover, it's not going to be initialized now)
										$allDependenciesSatisfied = false;
								}
							}

							if (isset($m->version) and $m->version !== '0.0.0' and !$m->installed) {
								if ($allDependenciesSatisfied)
									$toBeInitialized[] = $m;
							} else {
								// Load the config class only if the module is updated in respect of the Core (to avoid non-compatibility between classes)
								$m->configClassCompatible = ($m->folder_name === 'Core');

								if (!$m->configClassCompatible) {
									foreach ($m->dependencies as $dependency => $dependencyVersion) {
										if ($dependency === 'Core') {
											$version = preg_replace('/^.*([0-9]+\.[0-9]+\.[0-9]+)$/', '$1', $dependencyVersion);
											if (version_compare($modules['Core']->version, $version, '>=') and version_compare($this->lastBreakingChange, $version, '<=')) {
												$m->configClassCompatible = true;
												break;
											}
										}
									}
								}

								if ($m->getConfigClass())
									$m->getConfigClass()->checkAssets();
							}
						}

						if (count($toBeInitialized) > 0) {
							foreach ($toBeInitialized as $moduleToInit) {
								$response = $this->updater->initModule($moduleToInit->folder_name);
								if ($response)
									$moduleToInit->installed = true;
								else
									$this->model->redirect(PATH . 'zk/modules/init/' . $moduleToInit->folder_name);
							}

							$coreConfigClass = $this->updater->getConfigClassFor('Core');
							$coreConfigClass->makeCache();
						}

						$priorities = [];
						foreach ($modules as $moduleName => $m)
							$priorities[$moduleName] = count($priorities);

						uasort($modules, function ($a, $b) {
							return $a->folder_name <=> $b->folder_name;
						});

						$this->injected['priorities'] = $priorities;
						$this->injected['modules'] = $modules;
						break;

					default:
						die('Unknown action');
				}
				break;

			case 'local-modules':
				$modules = $this->updater->getModules(false, false, 'app' . DIRECTORY_SEPARATOR . 'modules');

				if ($this->model->getRequest(2) and isset($modules[$this->model->getRequest(2)])) {
					$this->model->viewOptions['template'] = 'local-module';
					$this->injected['module'] = $modules[$this->model->getRequest(2)];

					switch ($this->model->getRequest(3)) {
						case 'make':
							if (!$this->model->getRequest(4))
								die('Missing data');
							$this->model->viewOptions['template'] = 'make-file';
							$this->model->viewOptions['showLayout'] = false;
							break;

						case 'action':
							if (!$this->model->getRequest(4) or !$this->model->getRequest(5) or !$this->model->getRequest(6))
								die('Missing data');

							$type = $this->model->getRequest(4);
							$fileName = $this->model->getRequest(5);
							$actionName = $this->model->getRequest(6);

							$maker = new \Model\Core\Maker($this->model);
							$fileTypeData = $maker->getFileTypeData($type);

							if (!isset($fileTypeData['actions'][$actionName]))
								die('Action does not exist');

							$action = $fileTypeData['actions'][$actionName];

							$this->injected['type'] = $type;
							$this->injected['fileName'] = $fileName;
							$this->injected['actionName'] = $actionName;
							$this->injected['action'] = $action;

							if (count($action['params'] ?? []) > 0 and count($_POST) < count($action['params'])) {
								$this->model->viewOptions['template'] = 'action-on-file-form';
								$this->model->viewOptions['showLayout'] = false;
							} else {
								$params = [];
								foreach (($action['params'] ?? []) as $paramName => $paramOptions) {
									if (!isset($_POST[$paramName]))
										die('Missing data');
									$params[$paramName] = $_POST[$paramName];
								}

								$reflectionModule = new ReflectionModule('Db', $this->model);
								$moduleConfig = $reflectionModule->getConfigClass();
								$obj = $moduleConfig->getFileInstance($type, $fileName);

								$results = $obj->{$action['method']}($params);

								$this->injected['results'] = $results;
								$this->model->viewOptions['template'] = 'action-on-file';
								$this->model->viewOptions['showLayout'] = false;
							}
							break;
					}
				} else {
					$this->model->viewOptions['template'] = 'local-modules';
					$this->injected['modules'] = $modules;
				}
				break;

			case 'make-cache':
				if ($this->model->getRequest(2)) {
					try {
						$this->updater->updateModuleCache($this->model->getRequest(2));
						echo 'ok';
					} catch (Exception $e) {
						echo getErr($e);
					}
					die();
				} else {
					$modules = $this->getSortedModules(true);
					// I update the Core cache twice, because other things could have changed since last update (i.e. router rules)
					$modules[] = 'Core';

					$this->model->viewOptions['showLayout'] = false;
					$this->model->viewOptions['template'] = 'make-cache';
					$this->injected['modules'] = $modules;
				}
				break;

			case 'empty-session':
				$_SESSION = [];
				die("Session cleared.\n");

			case 'inspect-session':
				zkdump($_SESSION);
				die();

			default:
				$this->model->redirect(PATH . 'zk/modules' . $qry_string);
				break;
		}
	}

	private function getSortedModules(bool $onlyWithConfig = false): array
	{
		$modules = $this->updater->getModules(false, false);
		$modules = $this->updater->topSortModules($modules);
		if ($onlyWithConfig) {
			foreach ($modules as $mName => $m) {
				if (!$m->hasConfigClass())
					unset($modules[$mName]);
			}
		}

		return array_keys($modules);
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
						case 'install':
							try {
								$modules = (string)$this->model->getInput('modules');
								$modules = array_filter(explode(',', $modules));
								if (count($modules) === 0)
									throw new Exception('No module selected');

								foreach ($modules as $module) {
									if ($this->model->moduleExists($module))
										throw new Exception('Module ' . $module . ' already exists.');
								}

								foreach ($modules as $module) {
									$this->updater->addModuleToCache($module);
									if (!isset($_SESSION['update-queue']))
										$_SESSION['update-queue'] = [];
									$_SESSION['update-queue'][] = $module;
								}

								echo 'ok';
							} catch (\Exception $e) {
								echo getErr($e);
							}
							die();

						case 'config':
						case 'init':
							$this->get();

							$configClass = $this->injected['configClass'];
							if ($configClass) {
								switch ($this->model->getRequest(2)) {
									case 'config':
										if ($configClass->saveConfig($this->model->getRequest(2), $_POST))
											$this->model->viewOptions['messages'][] = 'Configuration saved.';
										break;
									case 'init':
										try {
											if ($this->updater->initModule($this->model->getRequest(3), $_POST)) {
												$coreConfigClass = $this->updater->getConfigClassFor('Core');
												$coreConfigClass->makeCache();

												$this->model->redirect(PATH . 'zk/modules' . $qry_string);
											} else {
												$this->model->error('Some error occurred while installing.');
											}
										} catch (\Exception $e) {
											$this->model->viewOptions['errors'][] = getErr($e);
										}
										break;
								}
							}

							$this->get();
							break;

						case 'update-file':
							if (!isset($_POST['file']))
								die('Missing data');

							if ($this->updater->updateFile($_POST['file']))
								echo 'ok';
							else
								echo 'Error while updating file.';
							die();

						case 'finalize-update':
							try {
								$modules = $this->model->getInput('modules');
								if (!$modules)
									die('Missing data');
								$modules = explode(',', $modules);
								if ($this->updater->finalizeUpdate($modules, $_SESSION['delete-files']))
									echo 'ok';
								else
									echo 'Generic error while finalizing';
							} catch (\Exception $e) {
								echo "Error while finalizing the update, you might need to update manually.\n" . getErr($e);
							}
							die();

						case 'delete':
							$modules = $this->model->getInput('modules');
							if (!$modules)
								die('Missing data');

							$modules = explode(',', $modules);

							try {
								foreach ($modules as $m) {
									$m = trim($m);
									if (!$m or strpos($m, '/'))
										throw new Exception('Invalid module name');
									if ($m === 'Core')
										throw new Exception('You cannot delete ModEl Core');
									if (!$this->updater->deleteModule($m))
										throw new Exception('Error while deleting module ' . $m);
								}

								echo 'ok';
							} catch (\Exception $e) {
								echo getErr($e);
							}
							die();
					}

				case 'local-modules':
					$this->get();

					if (isset($_POST['makeNewFile'])) {
						try {
							$maker = new \Model\Core\Maker($this->model);
							$data = $_POST;
							unset($data['makeNewFile']);
							$maker->make($this->model->getRequest(2), $_POST['makeNewFile'], $data);
							$this->updater->updateModuleCache('Core');
						} catch (\Exception $e) {
							$this->model->viewOptions['errors'][] = getErr($e);
						}
					}
					break;
			}
		} catch (\Exception $e) {
			$this->model->viewOptions['errors'][] = $e->getMessage();
		}
	}

	public function cli()
	{
		switch ($this->model->getRequest(1)) {
			case 'modules':
				switch ($this->model->getRequest(2)) {
					case 'install':
						$this->get();

						if ($this->model->getRequest(3) and isset($this->injected['modules'][$this->model->getRequest(3)])) {
							$name = $this->model->getRequest(3);
							if (!$this->model->moduleExists($name)) {
								echo "Adding module to cache...\n";
								$this->updater->addModuleToCache($name);
								echo "Starting the update...\n";
								$allModules = $this->updater->getModules(true);
								if (!isset($allModules[$name]))
									die("Something's wrong, can\'t find new module.\n");

								$modules = [
									$name => $allModules[$name],
								];
								$this->updater->cliUpdate($modules);
							} else {
								die("Module already exists.\n");
							}
						} else {
							echo "Downloadable modules:\n";

							if (count($this->injected['modules'] ?? []) > 0) {
								foreach ($this->injected['modules'] as $m)
									echo '* ' . $m['name'] . " (v" . $m['current_version'] . ")\n";
							} else {
								echo "No module found\n";
							}
						}
						break;

					case 'update':
						$modules = null;
						if ($this->model->getInput('modules')) {
							$modulesNames = explode(',', $this->model->getInput('modules'));
							$modules = [];
							foreach ($modulesNames as $name) {
								if (!$name)
									continue;
								$modules[] = new ReflectionModule($name, $this->model);
							}
						}

						$this->updater->cliUpdate($modules);
						break;

					case 'init':
					case 'config':
						if ($this->model->getRequest(3)) {
							if ($this->updater->cliConfig($this->model->getRequest(3), $this->model->getRequest(2), (bool)$this->model->getInput('skip-input'), true))
								echo "Module " . $this->model->getRequest(3) . " successful configured\n";
							else
								echo "Error while configuring module " . $this->model->getRequest(3) . "\n";
						} else {
							echo "Usage: zk/modules/config/<module>\n";
						}
						break;

					case null:
						$this->get();

						echo "Installed modules:\n";

						if (count($this->injected['modules'] ?? []) > 0) {
							foreach ($this->injected['modules'] as $m) {
								echo '* ' . $m->name . " (v" . $m->version . ")";
								if ($m->new_version)
									echo ' - New version available';
								elseif ($m->corrupted)
									echo ' - Edited';
								echo "\n";
							}
						} else {
							echo "No module found\n";
						}
						break;

					default:
						echo $this->model->getRequest(2) . " is not a recognized command\n";
						break;
				}
				break;

			case 'init':
				$modules = $this->getSortedModules();
				echo "Modules init...\n";
				foreach ($modules as $module) {
					echo $module . "... ";
					if ($this->updater->cliConfig($module, 'init', true)) {
						echo "OK\n";
					} else {
						echo "ERROR\n";
						exit(1);
					}
				}

				echo "Refreshing core cache...\n";
				$coreConfigClass = $this->updater->getConfigClassFor('Core');
				$coreConfigClass->makeCache();
				echo "Done\n";
				break;

			case 'make-cache':
				$modules = $this->getSortedModules(true);
				// I update the Core cache twice, because other things could have changed since last update (i.e. router rules)
				$modules[] = 'Core';

				// Si può richiedere di aggiornare il core una volta sola alla fine
				if ($this->model->getInput('core_once'))
					array_shift($modules);

				echo "Elaboro cache moduli...\n";
				foreach ($modules as $module) {
					echo $module . "... ";
					try {
						$this->updater->updateModuleCache($module);
						if ($module === 'Core')
							$this->model->reloadCacheFile();

						echo "OK\n";
					} catch (Exception $e) {
						echo getErr($e);
						exit(1);
					}
				}
				break;

			case 'inspect-session':
				echo json_encode($_SESSION) . "\n";
				break;

			case null:
				echo "Usage: zk/<command> [parameter1=value1] [parameter2=value2] ...\n\n"
					. "Available commands:\n\n"
					. "init                         Init modules\n"
					. "make-cache                   Re-make the cache of all modules\n"
					. "modules                      List all installed modules\n"
					. "modules/install              List all installable modules\n"
					. "modules/install/<module>     Downloads and installs specified module\n"
					. "modules/update               Updates all modules\n"
					. "                             You can optionally specify \"modules\" parameter,\n"
					. "                             with a comma-separated list of modules to update:\n"
					. "                                 modules/update modules=Output,Router,Ecc\n"
					. "modules/config/<module>      Configure options for a module\n"
					. "\n";
				break;

			default:
				echo $this->model->getRequest(1) . " is not a recognized command\n";
				break;
		}
	}

	public function output(array $options = [])
	{
		foreach ($this->injected as $injName => $injObj)
			${$injName} = $injObj;

		$this->options = array_merge_recursive_distinct($options, $this->model->viewOptions);

		if (!isset($this->model->viewOptions['showLayout']) or $this->model->viewOptions['showLayout'])
			require(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layoutHeader.php');

		if (!empty($this->model->viewOptions['errors']))
			echo '<div class="red-message">' . implode('<br />', $this->model->viewOptions['errors']) . '</div>';
		if (!empty($this->model->viewOptions['messages']))
			echo '<div class="green-message">' . implode('<br />', $this->model->viewOptions['messages']) . '</div>';

		if ($this->model->viewOptions['template'])
			require(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $this->model->viewOptions['template-module'] . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $this->model->viewOptions['template'] . '.php');
		if (!isset($this->model->viewOptions['showLayout']) or $this->model->viewOptions['showLayout'])
			require(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layoutFooter.php');
	}
}
