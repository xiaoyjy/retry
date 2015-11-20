<?php
/***************************************************************************
 * 
 * Copyright (c) 2008 Baidu.com, Inc. All Rights Reserved
 * $Id: log.php,v 1.2 2009/08/28 03:20:19 wanghao2 Exp $ 
 * 
 **************************************************************************/
/**
 * @file log.php
 * 
 * @ignore
 *
 * @author sheny(shenyi@baidu.com)
 * @date 2008/04/19 19:25:03
 * @version $Revision: 1.2 $ 
 * @brief 简单的php日志库,使用上同ub_log
 * 实现及使用说明
 * 1 不做日志回滚,因此需要脚本配合使用
 * 2 如果日志内容没有达到4K,日志会在destruct里打出,原因是为了减少IO次数
 * 3 每条日志可以有一些默认打出的字段,可以参考__mc_log::$BASIC_FIELD,这些参数可以在初始化时传入
 * 4 每个php进程只能使用一个log对象,不然内容可能会乱
 * 
 * @history
 *  2008.5.7 添加多进程支持 sheny
 *  2013.3.13 change by jianyu. 
 **/


/**
 * __mc_log 内部使用的log实现类,使用class的一个考虑是想在析构时打出日志
 * 
 * @ignore
 */
final class __mc_log
{
	const LOG_FATAL   = 1;
	const LOG_WARNING = 2;
	const LOG_MONITOR = 4;
	const LOG_ACCESS  = 8;
	const LOG_NOTICE  = 16;
	const LOG_DEBUG   = 32;

	const PAGE_SIZE   = 4096;
	const MONTIR_STR  = ' ---LOG_MONITOR--- ';

	static $LOG_NAME = array
		(
			self::LOG_FATAL   => 'FATAL',
			self::LOG_WARNING => 'WARNING',
			self::LOG_MONITOR => 'MONITOR',
			self::LOG_ACCESS  => 'ACCESS',
			self::LOG_NOTICE  => 'NOTICE',
			self::LOG_DEBUG   => 'DEBUG'
		);

	static $BASIC_FIELD = array
		(
			'logid',
			'client_ip',
			'local_ip',
			'uip',
			'cuid',
			'imei',
			'uid',
			'un',
			'baiduid',
			'method',
			'url'
		);

	/**
	 * log_name 日志名
	 * 
	 * @var string
	 * @access private
	 */
	private $log_name  = '';
	/**
	 * afd_path 正常日志全路径 
	 * 
	 * @var string
	 * @access private
	 */
	private $afd_path  = '';
	/**
	 * efd_path wf日志全路径 
	 * 
	 * @var string
	 * @access private
	 */
	private $efd_path   = '';
	private $dfd_path   = '';

	private $afd_str    = '';
	private $efd_str    = '';
	private $dfd_str    = '';

	private $basic_info = '';
	private $notice_str = '';

	private $log_level  = 16;
	private $arr_basic  = null;

	/**
	 * force_flush 是否强制写出
	 * 
	 * @var mixed
	 * @access private
	 */
	private $force_flush = false;

	protected $afd = NULL;
	protected $efd = NULL;
	protected $dfd = NULL;
	protected $dir;
	protected $name;

	/**
	 * @ignore
	 *
	 */
	function __destruct()
	{
		$this->force_flush = true;

		/* 只在打出当前进程的日志 */
		$this->check_flush_log();

		if($this->afd) fclose($this->afd);
		if($this->efd) fclose($this->efd);
		if($this->dfd) fclose($this->dfd);
	}

	/**
	 * @ignore
	 *
	 */
	function rotatelog($time)
	{
		$this->time = $time;
		$date = date('Ymd', $time);
		$dir  = rtrim($this->dir , ".");
		$name = rtrim($this->log_name, "/");

		$this->afd_path  = $dir."/".$name.".log.".$date;
		$this->efd_path  = $dir."/".$name.".error.".$date;	
		$this->dfd_path  = $dir."/".$name.".debug.".$date;	
		$alink = $dir."/".$name.".log";
		$elink = $dir."/".$name.".error";
		$dlink = $dir."/".$name.".debug";

		$this->afd = fopen($this->afd_path, 'a+'); 
		if(flock($this->afd, LOCK_EX|LOCK_NB))
		{
			if(is_link($alink)) {  unlink($alink); }

			symlink($this->afd_path, $alink);
			flock($this->afd, LOCK_UN);
		}		

		$this->efd = fopen($this->efd_path, 'a+'); 
		if(flock($this->efd, LOCK_EX|LOCK_NB))
		{
			if(is_link($elink)) { unlink($elink); }

			symlink($this->efd_path, $elink);

			flock($this->efd, LOCK_UN);
		}

		if($this->log_level > self::LOG_ACCESS)
		{
			$this->dfd = fopen($this->dfd_path, 'a+'); 
			if(flock($this->dfd, LOCK_EX|LOCK_NB))
			{
				if(is_link($dlink)) { unlink($dlink); symlink($this->dfd_path, $dlink); } 
				flock($this->dfd, LOCK_UN);
			}
		}
	} 

	/**
	 * @ignore
	 *
	 */
	function init($dir, $name, $level, $arr_basic_info, $flush=false)
	{
		if (empty($dir) || empty($name))
		{
			return false;
		}

		/* 使用的为绝对路径 */
		if ('/'!= $dir{0})
		{
			$dir = realpath($dir);
		}

		$this->dir       = $dir;
		$this->log_name  = $name;
		$this->log_level = $level;
		$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		$this->rotatelog($time);

		/* set basic info */
		$this->arr_basic = $arr_basic_info;

		/* 生成basic info的字符串 */
		$this->gen_basicinfo();

		/* 记录初使化进程的id */
		$this->force_flush = $flush;
		return true;
	}

	/**
	 * @ignore
	 *
	 */
	private function gen_basicinfo()
	{
		$this->basic_info = '';
		foreach (self::$BASIC_FIELD as $key)
		{
			if (!empty($this->arr_basic[$key]))
			{
				$this->basic_info .= " ".$key.'='.$this->arr_basic[$key];
			}
		}
	}

	/**
	 * @ignore
	 *
	 */
	private function check_flush_log()
	{
		if ($this->force_flush || strlen($this->afd_str)>self::PAGE_SIZE)
		{
			$this->write_file($this->afd, $this->afd_str);
			$this->afd_str = '';
		}

		if ($this->force_flush || strlen($this->dfd_str)>self::PAGE_SIZE)
		{
			$this->write_file($this->dfd, $this->dfd_str);
			$this->dfd_str = '';
		}

		if ($this->force_flush || strlen($this->efd_str)>self::PAGE_SIZE)
		{
			$this->write_file($this->efd, $this->efd_str);
			$this->efd_str = '';
		}

	}

	/**
	 * @ignore
	 *
	 */
	private function write_file($fd, $str)
	{
		if (is_resource($fd))
		{
			fputs($fd, $str);
			if($this->force_flush)
			{
				fflush($fd);
			}
		}
	}

	/**
	 * @ignore
	 *
	 */
	public function add_basicinfo($arr_basic_info)
	{
		$this->arr_basic = array_merge($this->arr_basic, $arr_basic_info);
		$this->gen_basicinfo();
	}

	/**
	 * @ignore
	 *
	 */
	public function write_log($type, $format, $arr_data)
	{
		if ($this->log_level < $type)
			return;

		if(strpos($_SERVER['PHP_SELF'], 'bpsd') !== false)
		{
			$now = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		}
		else
		{
			$now = time();
		}

		//if ($now - $this->time > 3600/* more than one hour. */)
		if ((int)($now/3600) - (int)($this->time/3600) > 0/* more than one hour. */)
		{
			$this->rotatelog($now);
		}

		/* log heading */
		$header = [self::$LOG_NAME[$type], date("Y-m-d H:i:s", $now), $this->log_name, '*', posix_getpid()];
		$header[] = 'logid='.cy_log_id();

		if(cy_client_ip() !== '127.0.0.1')
		{
			$header[] = 'addr='.cy_client_ip();
		}

		if($_SERVER['REQUEST_URI'] !== '')
		{
			$header[] = 'url='.$_SERVER['REQUEST_URI'];
		}

		$str = implode(' ', $header);

		/* add monitor tag?	*/	
		if ($type==self::LOG_MONITOR || $type==self::LOG_FATAL)
		{
			$str .= self::MONTIR_STR;
		}

		/* add basic log */
		$str .= $this->basic_info;

		/* add detail log */
		$str .= ' '.vsprintf($format, $arr_data);

		switch ($type)
		{
			case self::LOG_MONITOR :
			case self::LOG_WARNING :
			case self::LOG_FATAL :
				$this->efd_str .= $str . "\n";
				break;

			case self::LOG_ACCESS :
				$this->afd_str .= $str . "\n";
				break;

			case self::LOG_DEBUG :
			case self::LOG_NOTICE :
				$this->dfd_str .= $str . "\n";
				break;

			default : 
				break;	
		}

		$this->check_flush_log(); 
	}
}


$__log = null;
/**
 * @ignore
 *
 */
function __ub_log($type, $arr)
{
	global $__log;
	$format = $arr[0];
	array_shift($arr);
	if (!empty($__log)) {
		$__log->write_log($type, $format, $arr);
	} else {
		/* print out to stderr */
		$s =  __mc_log::$LOG_NAME[$type] . ' ' . vsprintf($format, $arr) . "\n";
		echo $s;
/*
if (!defined('STDERR')) {
$stderr = fopen('php://stderr', 'w');
fprintf($stderr, $s);
echo $s;
} else {
fprintf(STDERR, $s);
}
 */
	} /* if $__log */
}


/**
 * ub_log_init Log初始化 
 * 
 * @ignore
 *
 * @param string $dir      目录名
 * @param string $file     日志名
 * @param interger $level  日志级别 
 * @param array $info      日志基本信息,可以参考__mc_log::$BASIC_FIELD  
 * @param bool  $flush     是否日志直接flush到硬盘,默认会有4K的缓冲
 * @return boolean          true成功;false失败
 */
function ub_log_init($dir, $file, $level, $info, $flush=false)
{
	global $__log;

	//$pid = posix_getpid();
	//
	//if (!empty($__log[$pid]) ) {
	//	unset($__log[$pid]);
	//}
	//
	//$__log[$pid] = new __mc_log(); 
	//$log = $__log[$pid];

	$__log = new __mc_log();
	if ($__log->init($dir, $file, $level, $info, $flush))
	{
		return true;
	}

	return false;
}
//
///**
// * ub_log_pushnotice       压入NOTICE日志,和UB_LOG_XXX接受的参数相同(不同于ub_log同名函数))  
// *                         
// * @param string $fmt      格式字符串
// * @param mixed  $arg      data
// * @return void
// */
//function ub_log_pushnotice()
//{
//	global $__log;
//	$arr = func_get_args();
//
//	$pid = posix_getpid();
//
//	if (!empty($__log[$pid])) {
//		$log = $__log[$pid];
//		$format = $arr[0];
//		/* shift $type and $format, arr_data left */
//		array_shift($arr);
//		$log->push_notice($format, $arr);
//	} else {
//		/* nothing to do */
//	}
//}
//
///**
// * ub_log_clearnotice       清除目前的NOTICE日志,每次调用UB_LOG_ACCESS都会调用本函数
// * 
// * @return void
// */
//function ub_log_clearnotice()
//{
//	global $__log;
//	$arr = func_get_args();
//
//	$pid = posix_getpid();
//	if (!empty($__log[$pid]))
//	{
//		$log = $__log[$pid];
//		$log->clear_notice();
//	}
//	else
//	{
//		/* nothing to do */
//	}
//}

/**
 * ub_log_addbasic       添加日志的基本信息,字段可以参考 BASIC_FIELD 
 * 
 * @ignore
 *
 * @param mixed $arr_basic 基本信息的数组 
 * @return void
 */
function ub_log_addbasic($arr_basic)
{
	global $__log;
	$arr = func_get_args();

	$pid = posix_getpid();

	if (!empty($__log[$pid])) {
		$log = $__log[$pid];
		$log->add_basicinfo($arr_basic);
	} else {
		/* nothing to do */
	}
}

function cy_init_log()
{
	$c = $_ENV['config']['log'];
	if(PHP_SAPI === 'cli')
	{
		$name = basename($_SERVER['SCRIPT_NAME']);
		$dir  = dirname ($_SERVER['SCRIPT_NAME']);
		$base = basename($dir);
		if($base === 'sbin')
		{
			$c['name'] = $name;
		}
	}

	ub_log_init($c['path'], $c['name'], $c['level'], [], $c['fflush']);
}

/**
 * 返回统一的logid
 *
 * 
 * @return int logid
 */
function cy_log_id()
{
	if(isset($_ENV['logid']))
	{
		return $_ENV['logid'];
	}

	return cy_log_id_renew();	
}

function cy_log_id_renew()
{
	$_SERVER['REQUEST_TIME'] = time();

	return $_ENV['logid'] = uniqid();
}

/**
 * 记日志的函数
 *
 * @param int $level level maybe [CYE_ERROR|CYE_WARNING|CYE_ACCESS|...]  
 */
function cy_log($level)
{
	$arg = func_get_args();
	array_shift($arg);
	__ub_log($level, $arg );
}

/* //TEST
$r = ub_log_init("/tmp", "test", 16, array("logid"=>12345 , "reqip"=>"210.23.55.33"), true);

//var_dump($__log);
UB_LOG_FATAL("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_NOTICE("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_DEBUG("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_WARNING("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_ACCESS("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_MONITOR("fatal %d  %s !!!", 1231324, "asdfasdfsf");

ub_log_pushnotice("notice %s %d", "asdfasdf", 111);
ub_log_pushnotice("notice %s %d", "asdfasdf", 222);
UB_LOG_ACCESS("fatal %d  %s !!!", 1231324, "asdfasdfsf");
UB_LOG_ACCESS("fatal %d  %s !!!", 1231324, "asdfasdfsf");

ub_log_addbasic( array("uid"=>1234, "uname"=>2323 ));
UB_LOG_ACCESS("fatal %d  %s !!!", 1231324, "asdfasdfsf");

//var_dump($__log);

//*/

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
