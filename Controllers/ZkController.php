<?php namespace Model\Core\Controllers;

use Model\Core\Controller;
use Model\Core\Exception;
use Model\Core\Updater;

class ZkController extends Controller
{
	/** @var Updater */
	private $updater;
	/** @var array */
	protected $options = [];
	/** @var array */
	private $injected = [];

	public function init()
	{
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

		$this->model->viewOptions['cache'] = false;

		switch ($this->model->getRequest(1)) {
			case 'modules':
				$this->model->viewOptions['template'] = 'modules';

				switch ($this->model->getRequest(2)) {
					case 'install':
						$this->model->viewOptions['showLayout'] = false;
						$this->model->viewOptions['template'] = 'new-module';
						$modules = $this->updater->downloadableModules();
						$this->injected['modules'] = $modules;

						if ($this->model->getRequest(3) and isset($modules[$this->model->getRequest(3)])) {
							if (!$this->model->moduleExists($this->model->getRequest(3))) {
								$cache = $this->model->retrieveCacheFile();
								$name = $this->model->getRequest(3);
								$cache['modules'][$name] = [
									'path' => 'model' . DIRECTORY_SEPARATOR . $name,
									'load' => false,
									'custom' => false,
									'js' => [],
									'css' => [],
									'dependencies' => [],
									'assets-position' => 'head',
									'version' => '0.0.0',
								];

								$cacheFile = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache.php';
								file_put_contents($cacheFile, '<?php
$cache = ' . var_export($cache, true) . ';
');
								if (!isset($_SESSION['update-queue']))
									$_SESSION['update-queue'] = [];
								$_SESSION['update-queue'][] = $name;

								die('ok');
							} else {
								$this->model->viewOptions['errors'][] = 'Module already exists.';
							}

							/*if ($this->model->isCLI()) {
								if (empty($this->model->viewOptions['errors'])) {
									$this->updater->cliUpdate($this->model->getRequest(3));
									$this->updater->cliConfig($this->model->getRequest(3), 'init');
								} else {
									echo implode("\n", $this->model->viewOptions['errors']) . "\n";
								}

								die();
							}*/
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

								$this->model->viewOptions['template-module'] = $this->model->getRequest(3);
								$this->model->viewOptions['template'] = $configClass->getTemplate($this->model->getRequest());
								if ($this->model->viewOptions['template'] === null) {
									if ($this->model->getRequest(2) == 'init') {
										$installation = $this->updater->install($configClass);
										if ($installation) {
											$this->updater->firstInit($this->model->getRequest(3));
											$this->model->redirect(PATH . 'zk/modules' . $qry_string);
										} else {
											$this->model->viewOptions['errors'][] = 'Something is wrong, can\'t initialize module ' . $this->model->getRequest(3);
										}
									}
								}
								$this->injected['config_class'] = $configClass;
								$this->injected['config'] = $config;
							}
						} else {
							if ($this->model->getRequest(2) == 'init') {
								$this->updater->firstInit($this->model->getRequest(3));
								$this->model->redirect(PATH . 'zk/modules' . $qry_string);
							}
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
						break;
					default:
						$modules = $this->updater->getModules(true);

						// Check that all dependencies are satisfied, and check if some module still has to be installed
						$toBeInstalled = [];
						foreach ($modules as $m) {
							if ($m->version and $m->version !== '0.0.0' and !$m->installed) {
								$toBeInstalled[] = $m;
							} else {
								// Load the config class only if the module is updated in respect of the Core (to avoid non-compatibility between classes)
								if ($m->folder_name === 'Core')
									$m->configClassCompatible = true;
								if (!$m->configClassCompatible) {
									foreach ($m->dependencies as $dependency => $dependencyVersion) {
										if ($dependency === 'Core') {
											$version = preg_replace('/^.*([0-9]+\.[0-9]+\.[0-9]+)$/', '$1', $dependencyVersion);
											if (version_compare($modules['Core']->version, $version, '>=')) {
												$m->configClassCompatible = true;
												break;
											}
										}
									}
								}

								if ($m->getConfigClass())
									$m->getConfigClass()->checkAssets();
							}

							foreach ($m->dependencies as $depModule => $depVersion) {
								if (!isset($modules[$depModule])) {
									$this->model->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '" is not installed!';
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
										$this->model->viewOptions['errors'][] = 'Module "' . $depModule . '", dependency of "' . $m->name . '", does not match required version of ' . $depVersion;
								}
							}
						}

						$nextToInstall = false;
						if (count($toBeInstalled) > 0) {
							// I sort the modules so that I can install the modules without dependencies first
							$priorities = $this->updater->getModulesPriority($modules);

							usort($toBeInstalled, function ($a, $b) use ($priorities) {
								return $priorities[$a->folder_name] <=> $priorities[$b->folder_name];
							});

							$nextToInstall = reset($toBeInstalled);
						}

						if ($nextToInstall)
							$this->model->redirect(PATH . 'zk/modules/init/' . $nextToInstall->folder_name . $qry_string);

						// I sort the modules so that I can update the modules without dependencies first
						$priorities = $this->updater->getModulesPriority($modules);

						$this->injected['priorities'] = $priorities;
						$this->injected['modules'] = $modules;
						break;
				}
				break;
			case 'local-modules':
				$modules = $this->updater->getModules(false, 'app' . DIRECTORY_SEPARATOR . 'modules');
				if ($this->model->getRequest(2) and isset($modules[$this->model->getRequest(2)])) {
					$this->model->viewOptions['template'] = 'local-module';
					$this->injected['module'] = $modules[$this->model->getRequest(2)];

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
					} elseif ($this->model->getRequest(3) === 'make' and $this->model->getRequest(4)) {
						$this->model->viewOptions['template'] = 'make-file';
						$this->model->viewOptions['showLayout'] = false;
					}
				} else {
					$this->model->viewOptions['template'] = 'local-modules';
					$this->injected['modules'] = $modules;
				}
				break;
			case 'make-cache':
				try {
					$modules = $this->updater->getModules();

					$priorities = $this->updater->getModulesPriority($modules);

					uasort($modules, function ($a, $b) use ($priorities) {
						return $priorities[$a->folder_name] <=> $priorities[$b->folder_name];
					});

					$this->updater->updateModuleCache('Core');

					foreach ($modules as $mName => $m) {
						if ($mName === 'Core')
							continue;
						if ($m->hasConfigClass()) {
							$this->updater->updateModuleCache($mName);
						}
					}
				} catch (Exception $e) {
					die(getErr($e));
				}

				die("Cache succesfully updated.\n");
				break;
			case 'empty-session':
				$_SESSION = [];
				die("Session cleared.\n");
				break;
			case 'inspect-session':
				zkdump($_SESSION);
				die();
				break;
			default:
				$this->model->redirect(PATH . 'zk/modules' . $qry_string);
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
											$this->model->viewOptions['messages'][] = 'Configuration saved.';
										break;
									case 'init':
										if ($this->updater->install($configClass, $_POST)) {
											$this->updater->firstInit($this->model->getRequest(3));
											$this->model->redirect(PATH . 'zk/modules' . $qry_string);
										} else {
											$this->model->viewOptions['errors'][] = 'Some error occurred while installing.';
										}
										break;
								}
							}
							break;
						case 'update-file':
							if (!isset($_POST['file']))
								die('Missing data');

							if ($this->updater->updateFile($_POST['file']))
								echo 'ok';
							else
								echo 'Error while updating file.';
							die();
							break;
						case 'finalize-update':
							$modules = $this->model->getInput('modules');
							if (!$modules)
								die('Missing data');

							$modules = explode(',', $modules);
							if ($this->updater->finalizeUpdate($modules, $_SESSION['delete-files']))
								echo 'ok';
							else
								echo 'Error while finalizing the update, you might need to update manually.';
							die();
							break;
					}
					break;
			}
		} catch (\Exception $e) {
			$this->model->viewOptions['errors'][] = $e->getMessage();
		}

		$this->index();
	}

	public function cli()
	{
		switch ($this->model->getRequest(1)) {
			case 'modules':
				switch ($this->model->getRequest(2)) {
					case 'install':
						$this->get();

						echo "Downloadable modules:\n";

						if (count($this->injected['modules'] ?? []) > 0) {
							foreach ($this->injected['modules'] as $m)
								echo '* ' . $m['name'] . " (v" . $m['current_version'] . ")\n";
						} else {
							echo "No module found\n";
						}
						break;
					default:
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
				}
				break;
			case 'make-cache':
			case 'empty-session':
				$this->get();
				break;
			case 'inspect-session':
				echo json_encode($_SESSION) . "\n";
				die();
				break;
			default:
				die("CLI not supported for the request.\n");
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
