<?php namespace Model\Core;

class Exception extends \Exception
{
	public $_mex = '';
	public $_details = [];

	function show()
	{
		$message = parent::getMessage();
		if (DEBUG_MODE) return ($this->getCode() ? $this->getCode() . ' - ' : '') . $message . ($this->_mex ? '<br />' . $this->_mex : '');
		else return $message;
	}
}
