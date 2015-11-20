<?php
/* By: xiaoyjy@gmail.com
 *
 * URL rule: /[module]/[resouce id]/[method].[display]
 * 
 * Default method: get.
 * Most method should be GET/PUT/DELETE/MODIFY
 */

define('CY_HOME'           , dirname(__DIR__));
define('CY_LIB_PATH'       , CY_HOME.'/src');
if(empty($_SERVER['REQUEST_URI']))
{
	exit("cli mode is not supported.");
}

/* parse request uri start
 * -----------------------------------
 */
$request_uri = $_SERVER['REQUEST_URI'];
$p           = strpos($request_uri, '?');
$request_uri = $p !== false ? substr($request_uri, 0, $p) : $request_uri;

$p           = strpos($request_uri, 'index.php/');
if($p !== false)
{
	$_ENV["url_base"] = substr($request_uri, 0, $p-1);
	$request_uri      = substr($request_uri,    $p);
	$_ENV["url_path"] = $_ENV["url_base"].'/index.php';
}
else
{
	$_ENV["url_base"] = $_ENV["url_path"] = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));
}


/* security request uri filter. */
if(preg_match('/(\.\.|\"|\'|<|>)/', $request_uri))
{
	exit("Permission denied."); 
}

global $_g_module, $_g_id, $_g_method, $_g_display;

/* get display format. */
if(($p = strrpos($request_uri, '.')) !== false)
{
	$tail = substr($request_uri, $p + 1);
	if(preg_match('/^[a-zA-Z0-9]+$/', $tail))
	{
		$_g_display  = $tail; //'json'
		$request_uri = substr($request_uri, 0, $p);
	}
}

/* get module, id, method. */
$requests = array_pad(explode('/', $request_uri, 5), 5, NULL);
list(, $_g_module, $_g_id, $_g_method) = $requests;

/* default format: json */
empty($_g_display) && $_g_display = 'html';
empty($_g_method ) && $_g_method  = 'get' ;
empty($_g_module ) && $_g_module  = 'index';

if(isset($_GET['id']))
{
	$_g_id = $_GET['id'];
}

if(isset($_GET['op']))
{
	$_g_method = $_GET['op'];
}

$_ENV['module'] = $_g_module;
$_ENV['id']     = $_g_id;
$_ENV['method'] = $_g_method;
$_ENV['display']= $_g_display;
/*-------------------------------------
 * parse request uri end
 */

include CY_LIB_PATH.'/init.php';


/* custom init. */
include CY_HOME.'/app/init.php';

if(empty($_g_module))
{
	header("HTTP/1.1 404 Not Found");
	$dt = array('errno' => CYE_PARAM_ERROR, 'data' => 'Not found.');
	goto end;
}

//
$classname = 'CA_Entry_'.$_g_module;
if(!method_exists($classname, $_g_method) && !method_exists($classname, '__call'))
{
	$classname .= '_'.$_g_method;
	$run        = isset($_GET['a']) ? $_GET['a'] : 'run'; 
	if(!method_exists($classname, $run))
	{
		if(!isset($_ENV['config']['default_moudle']))
		{
			$errno = CYE_PARAM_ERROR;
			$error = "method is not exists $classname:$_g_method";
			$dt = array('errno' => $errno, 'data' => 'unkown request.', 'error' => $error);
			goto end;
		}
		else
		{
			$classname = 'CA_Entry_'.$_ENV['config']['default_moudle'];
			$eny = new $classname;
			$dt  = $eny->$_g_method($_g_id, $_REQUEST, $_ENV);
		}
	}

	$eny = new $classname;
	$dt  = $eny->$_g_method($_g_id, $_REQUEST, $_ENV);
}
else
{
	$eny = new $classname;
	$dt  = $eny->$_g_method($_g_id, $_REQUEST, $_ENV);
}

if(!isset($dt['errno']))
{
	exit("Bad entry return value.");
}

end:
if($_g_display === 'html' || $_g_display === 'php')
{
	if($dt['errno'] == 0)
	{
		$files = array();
		$files[] = CY_HOME.'/app/html/'.$_g_module.'/'.$_g_method.'.'.$_g_display;
		$files[] = CY_HOME.'/app/html/'.$_g_module.'.'.$_g_display;
		$files[] = CY_HOME.'/app/html/default.'.$_g_display;
		foreach($files as $i => $file)
		{
			if(is_file($file))
			{
				break;
			}

			if($i === 1 && $_g_display === 'html')
			{
				$_ENV['display'] = $_g_display = 'php';
				goto end;
			}
		}
	}
	else
	{
		$file = CY_HOME.'/app/html/error.'.$_g_display;
	}
}
else
{
	$file = NULL;
}

$t = new CY_Util_Output();
$t->assign($dt  );
$t->render($file);

?>
