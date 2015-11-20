<?php
/**
 * 全局初始化函数 
 *
 * 所有文件在调用cy_lib之前，都需要调用init.php
 *
 * @file init.php
 * @author jianyu@carext.com 
 * @date 2012/12/21 10:31:44
 * @version $Revision: 1.0.0 $ 
 *  
 */

if(!defined('CY_LIB_LOAD')) : // 防止重复初始化

define("CY_LIB_LOAD", 1);

if(PHP_VERSION_ID < 50500)
{
	exit("CY_LIB need PHP-5.5.0 or upper.\n");
}

date_default_timezone_set('Asia/Shanghai');

/* request stat start here. */
$_ENV['stat_time'] = $_SERVER['REQUEST_TIME_FLOAT'];
$_SERVER['REQUEST_TIME_START'] = microtime(true);

empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] = '';

defined('CY_LIB_PATH') || define('CY_LIB_PATH', dirname(__FILE__));
defined('CY_HOME'    ) || define('CY_HOME'    , dirname(CY_LIB_PATH));

include CY_LIB_PATH.'/misc/string.php';
include CY_LIB_PATH.'/misc/system.php';
include CY_LIB_PATH.'/misc/log.php';
include CY_LIB_PATH.'/util/loader.php';
include CY_LIB_PATH.'/util/errno.php';
include CY_LIB_PATH.'/util/error.php';

/* Load config */
if(!include(CY_HOME.'/etc/settings.php'))
{
	exit(CY_HOME.'/etc/settings.php is not exists.');
}

if(!include(CY_HOME.'/etc/backends.php'))
{
	exit(CY_HOME.'/etc/backends.php is not exists.');
}

/* Start cy_lib's autoload */
$_g_loader = new CY_Util_Loader();

/* 初始化log库 */
cy_init_log();

register_shutdown_function('cy_shutdown_callback');
//libxml_use_internal_errors(true);

set_exception_handler('cy_exception_handler');

// if(!defined('CY_LIB_LOAD')) end
endif;

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
