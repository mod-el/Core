<?php namespace Model\Core;

class Exception extends \Exception
{
	public string $_mex = '';
	public array $_details = [];

	public function show()
	{
		$message = parent::getMessage();
		if (DEBUG_MODE) return ($this->getCode() ? $this->getCode() . ' - ' : '') . $message . ($this->_mex ? '<br />' . $this->_mex : '');
		else return $message;
	}
}
