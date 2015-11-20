<?php
/**
 * 常用函数
 *
 * @file system.php
 * @author Jianyu Yang <xiaoyjy@gmail.com>
 * @date 2012/12/21 10:31:44
 * @version $Revision: 1.0 $ 
 *  
 */



/**
 * cy_debug_enable  是否将错误信息展现在页面上 
 *
 *
 * @author xiaoyjy@gmail.com
 * @since 1.0
 *
 * @param bool $enable 是否打开错鋘信息
 */
function cy_debug_enable($enable = false)
{
	if($enable)
	{
		ini_set('display_errors' , 'on');
		ini_set('html_errors'    , 'on');
		ini_set('error_reporting', E_ALL);

		ini_set('xdebug.var_display_max_depth'   , -1);
		ini_set('xdebug.var_display_max_children', -1);
		ini_set('xdebug.var_display_max_data'    , -1);
	}
	else
	{
		ini_set('display_errors', 'off');
		ini_set('xdebug.default_enable', '0');
	}

	$_ENV['debug'] = $enable;
}

/**
 * 框架在退出的时间会调用此方法
 *
 * @ignore
 * 
 * @author xiaoyjy@gmail.com
 */
function cy_shutdown_callback()
{
	$errno = isset($_ENV['errno']) ? $_ENV['errno'] : 0;

	cy_stat_flush();
}

/**
 * 如果脚本中出现异常 ，但未被catch，调用此方法出系统繁忙
 *
 * @ignore
 *
 * @param Exception $e 异常堆栈
 */
function cy_exception_handler($e)
{
	cy_exit(CYE_UNKNOWN_EXCEPTION);
}

/**
 * 判断一个IPv4是否为内网IP
 *
 * @param [string|int] $ipv4_addr xxx.xxx.xxx.xxx liked ip address or integer ip.
 * @return bool 是否为内网IP
 */
function cy_reserved_ipv4($ipv4_addr)
{
	$ipn = is_numeric($ipv4_addr) ? (int)$ipv4_addr : ip2long($ipv4_addr);
	$ipb = ($ipn & 0xFFFF0000);
	return (
		(($ipn & 0xFF000000) == 0x0A000000)        || /* 10.0.0.0/8 */
		($ipb <= 0xAC1F0000 && 0xAC100000 <= $ipb) || /* 172.16-32.0.0/16 */
		($ipb == 0xC0A80000)                       || /* 192.168.0.0/16 */
		($ipb == 0x7F000000)                          /* 127.0.0.0/16 */
	);
}

/**
 * Get remote IP addr
 *
 * @return string xxx.xxx.xxx.xxx liked ip address
 */
function cy_client_ip()
{
	(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $ip = $_SERVER['HTTP_X_FORWARDED_FOR']) ||
	(isset($_SERVER['REMOTE_ADDR']         ) && $ip = $_SERVER['REMOTE_ADDR']         ) ||
	(isset($_SERVER['HTTP_CLIENT_IP']      ) && $ip = $_SERVER['HTTP_CLIENT_IP']      ) || $ip = '127.0.0.1';

	return $ip;
}

/**
 * 脚本执行终止
 * cy_exit可以跟据请求的不同，输入json,xml,html等不同格式的错误信息
 *
 * @author xiaoyjy@gmail.com
 *
 * @param int $errno error code int lib/util/errno.php
 * @param string $message user defined error message
 * @param CY_Util_Output $t output template
 */
function cy_exit($errno = 0, $message = NULL, $t = NULL)
{
	/* Many be used in shutdown functions. */
	$_ENV['errno'] = $errno;

	$data = [];

	/* hacked, return data by message. */
	if(is_array($message))
	{
		$tmp   = $message;
		$error = isset($message['error']) ? $message['error'] : NULL;

		unset($tmp['error']);
		$data += $tmp;
	}
	elseif(is_string($message))
	{
		$error = $message;
	}

	/* First field must be 'result'. */
	$data = array
		(
		 'errno' => $errno,
		 'error' => empty($error) ? cy_strerror($errno) : $error,
		 'exitting' => true
		);

	$t == NULL && $t = new CY_Util_Output();
	$t->assign($data);

	$file = ($_ENV['display'] === 'html' || $_ENV['display'] === 'php') ?
		CY_HOME.'/app/html/error.'.$_ENV['display'] : NULL;
	echo $t->render($file);

	/* useful in nginx, useless in apache. */
	if (function_exists('fastcgi_finish_request'))
	{
		/* if use fastcgi, we may finish it first. */
		fastcgi_finish_request();
	}

	exit($errno);
}


/**
 * 将错误码转为错误信息
 *
 * @see /util/errno.php
 * @param int $errno 错误码
 * @return string 错误信息 
 */
function cy_strerror($errno)
{
	/* Can be cached in local memory. */
	if (!isset($_ENV['errors']))
	{
		include CY_LIB_PATH . '/util/error.php';
	}

	return isset($_ENV['errors'][$errno]) ? $_ENV['errors'][$errno] : '';
}

/**
 * 记录接口调用的所花的时间
 *
 * $example cy_stat(__CLASS__.':'.__FUNCTION__, 5000);
 *
 * $param string $key key of interface.
 * $param int $elapdwset_elapsed
 * $param array $options options maybe errno, args.. etc.
 */
function cy_stat($key, $elapsed, $options = array())
{
	if(empty($_ENV['stat'][$key]))
	{
		$_ENV['stat'][$key] = array();
		$_ENV['stat_count'] = 0;
		$_ENV['stat_flush_times'] = 0;
	}

	/* errno === 0 时不写，减少日志大小
	   if(empty($options['errno']))
	   {
	   $options['errno'] = 0;
	   }
	 */

	$_ENV['stat_count']++;
	$_ENV['stat'][$key]['c'][] = (int)$elapsed; 
	empty($options) || $_ENV['stat'][$key]['o'][] = $options;
	if($_ENV['stat_count'] === $_ENV['config']['stat_max_line'])
	{
		cy_stat_flush();
	}
}

/**
 * cy_stat_flush
 *
 * 如果$_ENV['stat'], 太大，可以会造成memory leak, 有时间需要手动刷一下。
 */
function cy_stat_flush()
{
	$endtime = microtime(true);
	$elapsed = (int)(($endtime - $_ENV['stat_time'])*1000000);
	$post_data = empty($_POST) ? '' : 'input='.json_encode(array_keys($_POST)).' ';

	empty($_ENV['stat']) && $_ENV['stat'] = [];
	empty($_ENV['stat_flush_times']) && $_ENV['stat_flush_times'] = 0;
	cy_log(CYE_ACCESS, 'memory:%d %s elapsed={total:%d,detail:%s}', memory_get_usage(), $post_data, $elapsed, json_encode($_ENV['stat']));

	$_ENV['stat'] = array();
	$_ENV['stat_count'] = 0;
	$_ENV['stat_time']  = $endtime;
	$_ENV['stat_flush_times'] += 1;
}

/**
 * cy_guid2bin
 *
 * @param $guid string Microsoft convention (GUID)
 * @return $guid_bin 16bytes binary string.
 */
function cy_guid2bin($guid)
{
	$hex = str_replace('-', '', $guid);
	return pack('H32', $hex);
}

/**
 * cy_bin2guid
 *
 * @param $guid_bin 16bytes binary string.
 */
function cy_bin2guid($guid_bin)
{
	$b1 = substr($guid_bin, 0, 4);
	$b2 = substr($guid_bin, 4, 2);
	$b3 = substr($guid_bin, 6, 2);
	$b4 = substr($guid_bin, 8, 2);
	$b5 = substr($guid_bin, 10);

	return bin2hex($b1).'-'.
		bin2hex($b2).'-'.
		bin2hex($b3).'-'.
		bin2hex($b4).'-'.
		bin2hex($b5);
}

/**
 * @ignore
 *
 * just used by cy_array_trim
 */
function cy_array_filter_r($source, $fn)
{
	$result = array();
	foreach ($source as $key => $value)
	{
		if(is_string($value))
		{
			$value = trim($value);
		}

		if(is_array($value))
		{
			$a = cy_array_filter_r($value, $fn);
			if(!empty($a))
			{
				$result[$key] = $a;
			}

		}
		else if($fn($key, $value))
		{
			$result[$key] = $value; // KEEP
		}
	}

	return $result;
}

/**
 * 去掉数组中的空元素
 * 
 * @filesource 
 	$source = ['a' => NULL, 'b' => '1234', 'c' => array(), 'd' => ['d1' => [], 'd2' => 'get']]
 	print_r(cy_array_trim($source));
 
 *  result is:
 	['b' => '1234', 'd' => ['d2' => 'get']]
 *
 * @param arrays have empty elements.
 */
function cy_array_trim($source)
{
	$cmp = function($k, $v)
	{
		return !empty($v);
	};

	return cy_array_filter_r($source, $cmp);
}

function cy_empty($val)
{
	return empty($val);
}

function cy_not($val)
{
	return !empty($val);
}

/**
 * 从数组中根据key获取一个值，如果key不存在，就返回默认值
 *
 */
function cy_val($arr, $key, $default = NULL)
{
	return isset($arr[$key]) ? $arr[$key] : $default;
}

/*
function cy_gi($key)
{
	return cy_hs_i_get($key, CY_HS_TYPE_CFG_I);
}

function cy_gs($key)
{
	return cy_hs_s_get($key, CY_HS_TYPE_CFG_S);
}

function cy_si($key, $val)
{
	return cy_hs_i_set($key, CY_HS_TYPE_CFG_I, $val);
}

function cy_ss($key, $val)
{
	return cy_hs_s_set($key, CY_HS_TYPE_CFG_S, $val);
}
*/

function cy_cp(&$dest, $src, $key)
{
	empty($src[$key]) || $dest[$key] = $src[$key];
}

function cy_rt($r, $key)
{
	if($r['errno'] !== 0 || empty($r['data'][$key]['data']))
	{
		return [];
	}

	return reset($r['data'][$key]['data']);
}

function cy_rt_all($r, $key)
{
	if($r['errno'] !== 0 || empty($r['data'][$key]['data']))
	{
		return [];
	}

	return $r['data'][$key]['data'];
}

function cy_rt_current($r)
{
	if($r['errno'] !== 0 || empty($r['data']))
	{
		return [];
	}

	return current($r['data']);
}


function cy_rt_data($r)
{
	if($r['errno'] !== 0 || empty($r['data']))
	{
		return [];
	}

	return $r['data'];
}

function cy_dt($errno, $data = array(), $options = array())
{       
	if($errno === 0)
	{
		$data = ['errno' => $errno, 'data' => $data];

		isset($options['menu']) && $data['menu'] = $options['menu'];
		return $data;
	}       

	$error = empty($data) ? cy_strerror($errno) : $data;
	if(isset($options['trace']))
	{
		$stack = debug_backtrace();
		unset($stack[0]);
		return ['errno' => $errno, 'error' => $error, 'backtrace' => $stack];
	}

	return ['errno' => $errno, 'error' => $error];
} 

function cy_r_current($r)
{
	if($r['errno'] === 0)
	{
		$r['data'] = current($r['data']);
		return $r;
	}

	return $r;
}

function cy_r_m($dt)
{
	if($dt['errno'] !== 0)
	{
		return $dt;
	}

	$data = array();
	foreach($dt['data'] as $sub)
	{
		if($sub['errno'] !== 0)
		{
			continue;
		}

		$data += $sub['data'];
	}

	return cy_dt(0, $data); 
}

/**
 * calculates intersection of two arrays like array_intersect_key but recursive
 *
 * @param  array/mixed  master array
 * @param  array        array that has the keys which should be kept in the master array
 * @return array/mixed  cleand master array
 */
function cy_intersect_key($master, $mask)
{
	if (!is_array($master))
	{
		return $master;
	}

	foreach ($master as $k=>$v)
	{
		if (!isset($mask[$k]))
		{
			// remove value from $master if the key is not present in $mask
			unset ($master[$k]);
			continue;
		}

		if (is_array($mask[$k]))
		{
			// recurse when mask is an array
			$master[$k] = cy_intersect_key($master[$k], $mask[$k]);
		}

		// else simply keep value
	}

	return $master;
}

/**
 * 将xml转成array，方法1
 *
 * $param $string XML字符串
 */
function cy_xml2array($string)
{
	$obj = (array)simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NOCDATA|LIBXML_NOBLANKS); 
	return simplexml2array($obj);
}

/**
 * 将xml转成array，方法1
 *
 * $param object simplexml object.
 */
function cy_simplexml2array($obj)
{
	if(empty($obj)) 
	{
		return '';
	}

	$data = array();
	foreach( $obj as $key => $val )
	{
		if((is_array($val) || is_object($val)))
		{
			$data[$key] = simplexml2array((array)$val );
		}
		else
		{
			$data[$key] = (string)$val;
		}
	}

	return $data;
}


/**
 * 用于显式指定入口程序调用的html模版 
 *
 * @param string $module 模版的模块名
 * @param string $method 模版的文件名
 */
function cy_set_view($module, $method)
{
	global $_g_module, $_g_method;
	$_ENV['module'] = $_g_module = $module;
	//$_ENV['action'] = $_g_action = $action;
	$_ENV['method'] = $_g_method = $method;
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
