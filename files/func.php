<?php
/**
 * Shortcut for extended htmlentities handling
 *
 * @param string $text
 * @param bool $br
 * @return string
 */
function entities($text, $br = false){
	if(is_object($text) and DEBUG_MODE){
		echo 'Oggetto passato dentro entities!';
		zkBacktrace();
	}
	$text = htmlentities($text, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
	if($br) $text = nl2br($text);
	return $text;
}

/**
 * Cut a given text at a certain length, aware of word breaks.
 *
 * @param string $text
 * @param int $limit
 * @param array $options
 * @param bool $safe
 * @return string
 */
function textCutOff($text, $limit, $options=array(), $safe = true){
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

/**
 * Returns a correct price representation of a given number
 *
 * @param float $p
 * @param array $options
 * @return string
 */
function makePrice($p, $options=array()){
	$options = array_merge(array(
		'show_currency'=>true,
		'decimal_separator'=>',',
		'thousands_separator'=>'.'
	), $options);

	$return = number_format($p, 2, $options['decimal_separator'], $options['thousands_separator']);
	if($options['show_currency']) $return .= '&euro;';
	return $return;
}

/**
 * Conversion of several number formats from string to number
 *
 * @param $n
 * @return bool|int|string
 */
function textToNumber($n){
	$n = str_replace(',', '.', $n);
	$n = preg_replace('/\.(?=.*\.)/', '', $n);
	return is_numeric($n) ? $n : false;
}

/**
 * Rewrite words in a correct url format
 *
 * @param array $names
 * @param bool $lower
 * @return string
 */
function rewriteUrlWords($names, $lower=true){
	if(empty($names))
		return '';
	if(!is_array($names))
		$names = array($names);

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

/**
 * Dumping for a visual representation
 *
 * @param mixed $v
 * @param bool $use_json
 * @param bool $return
 * @return bool|string
 */
function zkdump($v, $use_json=false, $return=false){
	if(!DEBUG_MODE and !$return)
		return false;
	if($return) ob_start();
	echo '<pre>';
	if($use_json)
		$v = json_decode(json_encode($v), true);
	var_dump($v);
	echo '</pre>';
	if($return)
		return ob_get_clean();
}

/**
 * @param bool $return
 * @return array
 */
function zkBacktrace($return = false){
	$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	if($return)
		return $backtrace;
	else
		zkdump($backtrace);
}

/**
 * Is a ModEl Exception?
 *
 * @param $el
 * @return bool
 */
function isErr($el){
	if(is_object($el) and get_class($el)=='Model\\Core\\Exception') return true;
	else return false;
}

/**
 * Gets the string from an Exception, depending if it's a simple Exception or a ModEl Exception
 *
 * @param Exception $e
 * @return string
 */
function getErr(\Exception $e){
	return isErr($e) ? $e->show() : $e->getMessage();
}

/**
 * Given an array, it returns true if is associative, false otherwise
 *
 * @param array $arr
 * @return bool
 */
function isAssoc($arr){
	return array_keys($arr) !== range(0, count($arr) - 1);
}

// Functions for preventing CSRF attacks

if(!isset($_SESSION['csrf']))
	$_SESSION['csrf'] = md5(uniqid(rand(), true));

/**
 * @return bool
 */
function checkCsrf(){
	if(isset($_POST['c_id']) and $_POST['c_id']==$_SESSION['csrf']) return true;
	else return false;
}

/**
 *
 */
function csrfInput(){
	echo '<input type="hidden" name="c_id" value="'.$_SESSION['csrf'].'" />';
}

/**
* @param array $array1
* @param array $array2
* @return array
* @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
* @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
*/
function array_merge_recursive_distinct(array &$array1, array &$array2) {
	$merged = $array1;

	foreach( $array2 as $key => &$value ) {
		if( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) ) {
			$merged [$key] = array_merge_recursive_distinct ( $merged [$key], $value );
		}else{
			$merged [$key] = $value;
		}
	}

	return $merged;
}
