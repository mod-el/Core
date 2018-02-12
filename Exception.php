<?php namespace Model\Core;

class Exception extends \Exception
{
	public $_mex = '';
	public $_code = 'ModEl';
	public $_details = array();

	function show()
	{
		$message = parent::getMessage();
		if (DEBUG_MODE) return $this->_code . ' - ' . $message . ($this->_mex ? '<br />' . $this->_mex : '');
		else return $message;
	}
}
