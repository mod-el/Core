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
		$fileTypeData = $this->getFileTypeData($type);

		$raw = $this->getFileTemplate($type);
		preg_match_all('/\{([a-zA-Z0-9_-]+)\}/', $raw, $matches);

		$params = $matches[1];
		if (!in_array('name', $params))
			array_unshift($params, 'name');

		$fullParamsData = [];
		foreach ($params as $param) {
			$fullParamsData[$param] = [
				'label' => ucwords(str_replace(['-', '_'], ' ', $param)),
				'notes' => '',
			];

			if (isset($fileTypeData['params'][$param]['label']))
				$fullParamsData[$param]['label'] = $fileTypeData['params'][$param]['label'];
			if (isset($fileTypeData['params'][$param]['notes']))
				$fullParamsData[$param]['notes'] = $fileTypeData['params'][$param]['notes'];
		}

		return $fullParamsData;
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

		$data['namespace'] = 'Model\\' . $module . '\\' . $fileTypeData['folder'];

		if (!isset($data['name']))
			$this->model->error('"name" parameter is mandatory');

		// Params can be customized in runtime while creating them (with suffix and/or prefix), those settings are defined in manifest.json of the appropriate module
		foreach (($fileTypeData['params'] ?? []) as $param => $paramOptions) {
			if (!isset($data[$param]))
				continue;

			if (isset($paramOptions['prefix']))
				$data[$param] = $this->parsePattern($paramOptions['prefix']) . $data[$param];
			if (isset($paramOptions['suffix']))
				$data[$param] .= $this->parsePattern($paramOptions['suffix']);
		}

		foreach ($params as $p => $pOptions) {
			if (!isset($data[$p]))
				$this->model->error('Missing parameter ' . $p);

			if ($p === 'name' and !trim($data[$p]))
				$this->model->error('Parameter ' . $p . ' cannot be empty');

			$raw = str_replace('{' . $p . '}', $data[$p], $raw);
		}

		$folder = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $fileTypeData['folder'];
		if (!is_dir($folder))
			mkdir($folder, 0777, true);

		$fullPath = $folder . DIRECTORY_SEPARATOR . $data['name'] . '.php';
		if (file_exists($fullPath))
			$this->model->error('File ' . $fullPath . ' already exists');

		return file_put_contents($fullPath, $raw) !== false;
	}

	/**
	 * @param string $pattern
	 * @return string
	 */
	private function parsePattern(string $pattern): string
	{
		preg_match_all('/(\{[a-zA-Z0-9_-]+\})/', $pattern, $matches);

		foreach ($matches[1] as $m) {
			switch ($m) {
				case '{datetime}':
					$pattern = str_replace($m, date('YmdHis'), $pattern);
					break;
			}
		}

		return $pattern;
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
	public function getFileTypeData(string $type): array
	{
		if (!isset(Autoloader::$fileTypes[$type]))
			$this->model->error('File type ' . $type . ' does not exist');

		return Autoloader::$fileTypes[$type];
	}
}
