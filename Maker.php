<?php namespace Model\Core;

class Maker
{
	/** @var Core */
	private $model;

	/**
	 * @param Core $model
	 */
	function __construct(Core $model)
	{
		$this->model = $model;
	}

	/**
	 * @param string $type
	 * @return array
	 */
	public function getParamsList(string $type): array
	{
		$raw = $this->getFileTemplate($type);
		preg_match_all('/\{([a-zA-Z0-9_-]+)\}/', $raw, $matches);

		$params = [];
		foreach ($matches[1] as $m) {
			if ($m !== 'namespace')
				$params[] = $m;
		}
		if (!in_array('name', $params))
			array_unshift($params, 'name');
		return $params;
	}

	/**
	 * @param string $module
	 * @param string $type
	 * @param array $data
	 * @return bool
	 */
	public function make(string $module, string $type, array $data): bool
	{
		$fileTypeData = $this->getFileTypeData($type);

		$params = $this->getParamsList($type);
		$raw = $this->getFileTemplate($type);

		$params[] = 'namespace';
		$data['namespace'] = 'Model\\' . $module . '\\' . $fileTypeData['folder'];

		if (!isset($data['name']))
			$this->model->error('Parameter is mandatory');
		if (isset($fileTypeData['suffix']))
			$data['name'] .= $fileTypeData['suffix'];

		foreach ($params as $p) {
			if (!isset($data[$p]))
				$this->model->error('Missing parameter ' . $p);

			if ($p === 'name' and !trim($data[$p]))
				$this->model->error('Parameter ' . $p . ' cannot be empty');

			$raw = str_replace('{' . $p . '}', $data[$p], $raw);
		}

		$folder = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $fileTypeData['folder'];
		if (!is_dir($folder))
			mkdir($folder, 0777, true);

		return file_put_contents($folder . DIRECTORY_SEPARATOR . $data['name'] . '.php', $raw) !== false;
	}

	/**
	 * @param string $type
	 * @return string
	 */
	private function getFileTemplate(string $type): string
	{
		$fileTypeData = $this->getFileTypeData($type);

		$fileTemplatePath = INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . $fileTypeData['module'] . DIRECTORY_SEPARATOR . 'file-types-templates' . DIRECTORY_SEPARATOR . $type . '.php';
		if (!file_exists($fileTemplatePath))
			$this->model->error('File template for ' . $type . ' does not exist');

		return file_get_contents($fileTemplatePath);
	}

	/**
	 * @param string $type
	 * @return array
	 */
	private function getFileTypeData(string $type): array
	{
		if (!isset(Autoloader::$fileTypes[$type]))
			$this->model->error('File type ' . $type . ' does not exist');

		return Autoloader::$fileTypes[$type];
	}
}
