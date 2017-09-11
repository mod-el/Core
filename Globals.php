<?php
class Globals{
	public static $data = array(
		'users_db'=>false,
		'nomiGiorniSettimana'=>array('Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'),
		'nomiMesi'=>array('', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'),
	);
	public static $classesPaths = array();
	public static $modulesClasses = array();
	public static $JSON_errors = array(
		JSON_ERROR_NONE=>'JSON_ERROR_NONE',
		JSON_ERROR_DEPTH=>'JSON_ERROR_DEPTH',
		JSON_ERROR_STATE_MISMATCH=>'JSON_ERROR_STATE_MISMATCH',
		JSON_ERROR_CTRL_CHAR=>'JSON_ERROR_CTRL_CHAR',
		JSON_ERROR_SYNTAX=>'JSON_ERROR_SYNTAX',
		JSON_ERROR_UTF8=>'JSON_ERROR_UTF8',
	);

	public static function setUsers($data){
		self::$data['users_db'] = $data;
	}
}

if(version_compare(phpversion(), '5.5.0', '>=')){
	Globals::$JSON_errors[JSON_ERROR_RECURSION] = 'JSON_ERROR_RECURSION';
	Globals::$JSON_errors[JSON_ERROR_INF_OR_NAN] = 'JSON_ERROR_INF_OR_NAN';
	Globals::$JSON_errors[JSON_ERROR_UNSUPPORTED_TYPE] = 'JSON_ERROR_UNSUPPORTED_TYPE';
}
