<?php
/***
ModEl Framework
Functions
 ***/

function serializeForLog($var){
	try {
		$string = serialize( $var );
		return $string;
	} catch( Exception $e ) {
		return 'unserializable';
	}
}

function entities($text, $br = false){
	if(is_object($text) and DEBUG_MODE){
		echo 'Oggetto passato dentro entities!';
		zkBacktrace();
	}
	$text = htmlentities($text, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
	if($br) $text = nl2br($text);
	return $text;
}

function textCutOff($text, $limit, $options=array(), $safe = true){
	if(!is_array($options)) // Retrocompatibilità
		$options = array('other'=>$options, 'safe'=>$safe);

	$options = array_merge(array(
		'other'=>false,
		'safe'=>true,
		'puntini'=>true
	), $options);

	$text = trim($text);
	$len = strlen($text);
	if($limit>=$len){
		if($options['other']) return '';
		else return $text;
	}
	$breaks = array('.', ':', "\n", '!', '?', ' ');
	$lastBreak = false;
	if($options['safe']) for($p=0;$p<$limit;$p++){
		$c = $text{$p};
		if(in_array($c, $breaks)) $lastBreak = $p;
	}
	if($options['safe'] and !$lastBreak){
		$p = $limit; $c = true;
		while(!$lastBreak and $c){
			if(strlen($text)>$p) $c = $text{$p};
			else $c = false;
			if(in_array($c, $breaks)) $lastBreak = $p;
			$p++;
		}
	}
	if($lastBreak){
		$lastBreak++;
		if($options['other']) $tor = trim(mb_substr($text, $lastBreak));
		else $tor = trim(mb_substr($text, 0, $lastBreak));
	}else{
		if($options['other']) $tor = trim(mb_substr($text, $limit));
		else $tor = trim(mb_substr($text, 0, $limit));
	}
	if($options['puntini'] and strlen($tor)<strlen($text)){
		if($lastBreak) return $tor.' [...]';
		else return $tor.'[...]';
	}else return $tor;
}

function makePrice($p, $options=array()){
	if(is_bool($options)) // Retrocompatibilità
		$options = array('show_currency'=>true);

	$options = array_merge(array(
		'show_currency'=>true,
		'decimal_separator'=>',',
		'thousands_separator'=>'.'
	), $options);

	$return = number_format($p, 2, $options['decimal_separator'], $options['thousands_separator']);
	if($options['show_currency']) $return .= '&euro;';
	return $return;
}

function textToNumber($n){
	$n = str_replace(',', '.', $n);
	$n = preg_replace('/\.(?=.*\.)/', '', $n);
	return is_numeric($n) ? $n : false;
}

function rewriteUrlWords($names, $lower=true){
	if(empty($names)) return false;
	if(!is_array($names)) $names = array($names);

	foreach($names as $n => $name){
		if($lower)
			$name = mb_strtolower($name);
		$name = str_replace('à', 'a', $name);
		$name = str_replace(array('è', 'é'), 'e', $name);
		$name = str_replace('ì', 'i', $name);
		$name = str_replace('ò', 'o', $name);
		$name = str_replace('ù', 'u', $name);
		$name = str_replace(array("'", '.', ','), ' ', $name);
		$name = trim($name);
		$name = preg_replace('/  */u', '-', $name);
		$name = preg_replace('/[^a-zа-я0-9\p{Han}-]+/iu', '-', $name);
		while(strpos($name, '--')!==false) $name = str_replace('--', '-', $name);
		$name = preg_replace('/^-(.+)$/', '\\1', $name);
		$name = preg_replace('/^(.+)-$/', '\\1', $name);
		$names[$n] = $name;
	}
	$url = implode('/', $names);
	return $url;
}

function zkdump($v, $use_json=false, $return=false){
	if(!DEBUG_MODE and !$return)
		return false;
	if($return) ob_start();
	echo '<pre>';
	if($use_json)
		$v = json_decode(json_encode($v), true);
	var_dump($v);
	echo '</pre>';
	if($return) return ob_get_clean();
}

function zkBacktrace($return = false){
	$backtrace = version_compare(phpversion(), '5.3.6', '<') ? debug_backtrace(false) : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	if(version_compare(phpversion(), '5.3.6', '<')){
		foreach($backtrace as &$bt){
			if(isset($bt['args']))
				unset($bt['args']);
		}
		unset($bt);
	}
	if($return) return $backtrace;
	else zkdump($backtrace);
}

function isErr($el){
	if(is_object($el) and get_class($el)=='ZkException') return true;
	else return false;
}

function getErr($e){
	return isErr($e) ? $e->show() : $e->getMessage();
}

function isAssoc($arr){
	return array_keys($arr) !== range(0, count($arr) - 1);
}