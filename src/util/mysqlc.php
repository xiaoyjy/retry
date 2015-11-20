<?php

if (!defined('MYSQLI_OPT_READ_TIMEOUT'))
{
	define('MYSQLI_OPT_READ_TIMEOUT',  11);
}

if (!defined('MYSQLI_OPT_WRITE_TIMEOUT'))
{
	define('MYSQLI_OPT_WRITE_TIMEOUT', 12);
}

define('CY_MAX_IDLE_COUNT', 16);

class CY_Mysqli extends mysqli
{
	protected $key;
	protected $config;

	function __construct()
	{
		$this->init();
	}

	function setcfg($config)
	{
		$this->config = $config;
	}

	function getcfg()
	{
		return $this->config;
	}

	function getkey()
	{
		return $this->key;
	}

	function setkey($key)
	{
		return $this->key = $key;
	}
}

/**
 * mysql operation class
 **/
class CY_Util_Mysqlc
{
	static $ref_count  = 0;
	static $queue_idle = array();

	function __construct()
	{
		self::$ref_count++;
	}

	function __destruct()
	{
		if(--self::$ref_count)
		{
			return;
		}

		foreach(self::$queue_idle as $queue)
		{
			foreach($queue as $db)
			{
				$db->close();
			}
		}

		self::$queue_idle = [];
	}

	function fetch($key, $config , $read=FALSE)
	{
		if(empty(self::$queue_idle[$key]))
		{
			self::$queue_idle[$key] = [];
		}

		while(count(self::$queue_idle[$key]) > CY_MAX_IDLE_COUNT)
		{
			$db = array_pop(self::$queue_idle[$key]);
			$db->close();
		}
		if(($db = array_pop(self::$queue_idle[$key])) && $db->ping())
		{
			return $db;
		}

		if(($db = $this->connect($config)) && $db->ping())
		{
			return $db;
		}

		if(!$read)
		{
			if(($db = $this->connect($_ENV['config']['db']['master'])) && $db->ping())
			{
				return $db;
			}
			return NULL;
		}

		foreach($_ENV['config']['db']['slave'] as $config)
		{
			if(($db = $this->connect($config)) && $db->ping())
			{
				return $db;
			}
		}

		return NULL;
	}

	function restore($key, $db)
	{
		self::$queue_idle[$key][] = $db;
	}

	function connect($config)
	{
		if(empty($config))
		{
			$mysql_conf = $_ENV['config']['db'];
		}
		else
		{
			$mysql_conf = $config;
		}

		if(empty($mysql_conf))
		{
			$mysql_conf = [['host' => '127.0.0.1', 'port' => '3306', 'user' => '', 'password' => '', 'database' => '']];
		}

		$cfg_count  = count($mysql_conf);
		$i          = array_rand($mysql_conf, 1);
		$re         = 0;
		do
		{
			$i = ($i+1)%$cfg_count;
			$config = $mysql_conf[$i];

			$server = $config['host'].':'.$config['port'];
		}
		while(0);
		//while(!cy_ctl_check($server, $_SERVER['REQUEST_TIME']) && $re++ < $cfg_count);

		if(empty($config))
		{
			cy_log(CYE_ERROR, 'Mysqlc::connect DB config is not found.');
			return NULL;
		}

		$t1   = microtime(true);
		$conn = new CY_Mysqli();
		$to   = $_ENV['config']['timeout'];
		$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $to['mysql_connect']);
		$conn->options(MYSQLI_OPT_READ_TIMEOUT   , $to['mysql_read']);
		$conn->options(MYSQLI_OPT_WRITE_TIMEOUT  , 1);
		$ret  = $conn->real_connect($config['host'], $config['user'], $config['password'], $config['database'], $config['port']);
		if(!$ret)
		{
			//cy_log(CYE_WARNING, "connect to [%s] at port [%s] failed, %s", $config['host'], $config['port'], $conn->connect_error);
			//cy_stat('mysql-connect', (microtime(true) - $t1)*1000000, ['errno' => $conn->connect_errno]);
			//cy_ctl_fail($server, $_SERVER['REQUEST_TIME']);
			return NULL;
		}

		$conn->setcfg($config);
		$conn->set_charset("utf8");
		$conn->query("set names utf8,character_set_client=binary");

		//cy_ctl_succ($server, $_SERVER['REQUEST_TIME']);
		cy_stat('mysql-connect', (microtime(true) - $t1)*1000000);
		return $conn;
	}

}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
