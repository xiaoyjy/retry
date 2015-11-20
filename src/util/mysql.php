<?php

if (!defined('MYSQL_OPT_READ_TIMEOUT'))
{
	define('MYSQL_OPT_READ_TIMEOUT',  11);
}

if (!defined('MYSQL_OPT_WRITE_TIMEOUT'))
{
	define('MYSQL_OPT_WRITE_TIMEOUT', 12);
}

/**
 * mysql operation class
 **/
class CY_Util_MySQL
{
	protected $tasks = array();
	protected $hd    = array();
	protected $db    = NULL;
	protected $mysqlc;
	protected $transaction = 0;
	protected $microtime  = 0;

	protected $task_num = 0;
	protected $default_config = NULL;
	protected $config;

	protected function config_key($config)
	{
		if(!$config)
		{
			return 'default';
		}

		return md5(implode($config[0]));
	}

	function __construct($config = NULL, $options = [])
	{
		$this->config = $config ? $config : $this->default_config;
		$this->mysqlc = new CY_Util_Mysqlc();
		if(!empty($options['transaction']))
		{
			$this->transaction = 1;
		}
	}

	function __destruct()
	{
		if($this->db)
		{
			$this->db->close();
			//$this->mysqlc->restore($this->config_key($this->config), $this->db); 
		}
	}

	function add($key, $c, $config = NULL)
	{
		$config || $config = $this->config;
		$this->tasks[$key] = ['c' => $c, 'config' => $config, 'config_key' => $this->config_key($config)];
		$this->task_num++;
	}

	function tasks()
	{
		return cy_dt(0, ['tasks' => $this->tasks, 'task_num' => $this->task_num]);
	}

	/**
	 * multi_query
	 *
	 * @param $options['async']  if async is set, the function will return every $options['cycle'].
	 * @param $options['cycle'] 
	 * @param $options['timeout'] 每一个spec的，timeout
	 */
	function mGet($options = [])
	{
		if($this->transaction)
		{
			return cy_dt(CYE_DB_MGET_LIMIT, 'mGet not allowed in transaction mode');
		}

		$t1 = microtime(true);

		/* overload mysql read timeout when $options['timeout'] is set. */
		$timeout = isset($options['timeout']) ? $options['timeout'] : $_ENV['config']['timeout']['mysql_read'];
		$cycle   = isset($options['cycle']  ) ? $options['cycle']   : 1.0/* defalut 1.0s */;
		$async   = isset($options['async']  ) ? $options['async']   : false;
		if(!$async)
		{
			$cycle = $timeout;
		}

		$processed = 0;

		$links = $errors = $reject = array();
		$data  = array();

		// start process
		foreach($this->tasks as $key => $task)
		{
			/* useful when async request */
			if(isset($task['running']))
			{
				continue;
			}

			$db = $this->mysqlc->fetch($task['config_key'], $task['config']);
			if(!$db || !$db->ping())
			{
				$config = empty($task['config']) ? 'default config' : implode("-", $task['config']);
				$error  = !$db ?  'error db handler'   : $db->error;
				$errno  = !$db ?  CYE_DB_UNKNOWN_ERROR : $db->errno;
				cy_log(CYE_WARNING, "MySQL connect error %s %s ", $config, $error);

				$data[$key] = ['errno' => $errno, 'error' => $error, 'c' => $task['c']];
				$task['c']->callback($data[$key]);

				unset($this->tasks[$key]);
				$this->task_num--;
				$processed++;
				continue;
			}

			$query = $task['c']->inputs(['db' => $db]);
			if($query && !empty($query['sql']))
			{
				/* 如果每一个spec自己设置过timeout，就以每一个spec自己的为准 */
				$ops = $task['c']->options();
				$tmo = isset($ops['timeout']) ? $ops['timeout'] : $timeout;

				$db->options(MYSQLI_OPT_READ_TIMEOUT, $tmo);
				$db->query  ($query['sql'], MYSQLI_ASYNC);
				$db->setkey ($key);

				$this->hd[$key] = $db;
				$this->tasks[$key]['running'] = 1;
			}
			else
			{
				$data[$key] = ['errno' => CYE_PARAM_ERROR, 'error' => "sql statment is empty."];
				$task['c']->callback($data[$key]);

				unset($this->tasks[$key]);
				$this->task_num--;
				$processed++;
			}
		}

		if(empty($this->hd))
		{
			return ['errno' => 0, 'data' => $data, 'processed' => $processed, 'message' => 'empty hd'];
		}

		do
		{
			$links = $errors = $reject = $this->hd;
			if(!mysqli_poll($links, $errors, $reject, 0, 100000/* 100ms. */)) // timeout 100ms.
			{
				/* timeout */
				goto one_loop;
			}

			foreach($links as $link)
			{
				$key = $link->getkey();
				$c   = $this->tasks[$key]['c'];
				if($result = $link->reap_async_query())
				{
					if (is_object($result))
					{
						// select/ show /...
						$rows = $result->fetch_all(MYSQLI_ASSOC);
						$data[$key] = ['errno' => 0, 'data' => $c->outputs($rows)];
						mysqli_free_result($result);
					}
					else
					{
						// create/drop/update/insert/delete.
						$r = ['affected_rows' => $link->affected_rows, 'insert_id' => $link->insert_id];
						$data[$key] = ['errno' => 0, 'data' => $r, 'info' => $link->info];
					}
				}
				else
				{
					// error.
					cy_log(CYE_ERROR, 'mysql reap_async_query error! [%d] [%s]', $link->errno, $link->error);
					$data[$key] = ['errno' => $link->errno, 'error' => $link->error];
				}

				$c->callback($data[$key]);

				$this->mysqlc->restore($this->tasks[$key]['config_key'], $link);
				unset($this->hd[$key], $this->tasks[$key]);

				$this->task_num--;
				$processed++;
			}

			foreach($errors as $link)
			{
				$key = $link->getkey();
				$data[$key] = ['errno' => $link->errno, 'error' => $link->error];
				$this->tasks[$key]['c']->callback($data[$key]);

				$link->close();
				unset($this->hd[$key], $this->tasks[$key]);

				$this->task_num--;
				$processed++;
			}

			foreach($reject as $link)
			{
				/* never reached here. */
				$key = $link->getkey();
				$data[$key] = ['errno' => $link->errno, 'error' => $link->error];
				$this->tasks[$key]['c']->callback($data[$key]);

				$link->close();
				unset($this->hd[$key], $this->tasks[$key]);

				$this->task_num--;
				$processed++;
			}

one_loop:
			$t2 = microtime(true);
		}
		while(!empty($this->hd) && $t2 - $t1 < $cycle);

		if(!$async)
		{
			foreach($this->hd as $key => $link)
			{
				cy_log(CYE_ERROR, 'mysql query timeout %s %s', $link->host_info, $link->error);
				$data[$key] = ['errno' => CYE_NET_TIMEOUT, 'timeout'];

				$this->tasks[$key]['c']->callback($data[$key]);
				$link->close();

				//$this->mysqlc->restore($this->tasks[$key]['config_key'], $link);
				unset($this->hd[$key], $this->tasks[$key]);
				$this->task_num--;
				$processed++;
			}

			// end process.
			$cost = (microtime(true) - $t1)*1000000;
			cy_stat('mysql-multi-query', $cost);
		}

		return array('errno' => 0, 'data' => $data, 'processed' => $processed, 'running' => $this->task_num);
	}

	function escape_string($str)
	{
		return addslashes($str);
	}

	function is_avaliable()
	{
		$t1 = microtime(true);
		$new = empty($this->db) || $t1 - $this->microtime < 5/* 小于5s间隔的请求就不用ping了 */;
		$this->db || $this->db = $this->mysqlc->fetch($this->config_key($this->config), $this->config);
		$avaliable = $this->db && !$new ? $this->db->ping() : !empty($this->db);
		if(!$avaliable)
		{
			$string = empty($this->config) ? 'default config' : implode("-", $this->config);
			$error  = !$this->db ?  'error db handler'   : $this->db->error;
			$errno  = !$this->db ?  CYE_DB_UNKNOWN_ERROR : $this->db->errno;
			cy_log(CYE_WARNING, "MySQL connect error %s %s ", $string, $error);
			return false;
		}

		if($new && $this->transaction)
		{
			$this->db->autocommit(false);
		}

		$this->microtime = $t1;
		return true;
	}

	function rollback()
	{
		if(!$this->transaction   ) return cy_dt(CYE_UNKNOWN_EXCEPTION, 'non transaction statments is not allowed here.');
		if(!$this->is_avaliable()) return cy_dt(CYE_DB_CONNECT_ERROR , 'mysql connect is not available.');
		if(!$this->db->rollback())
		{
			cy_log(CYE_ERROR, 'mysql transaction rollback error [%d] [%s]', $this->db->errno, $this->link->error);
			return cy_dt($this->db->errno, $this->link->error);
		}

		return cy_dt(0);
	}

	function commit()
	{
		if(!$this->transaction   ) return cy_dt(CYE_UNKNOWN_EXCEPTION, 'non transaction statments is not allowed here.');
		if(!$this->is_avaliable()) return cy_dt(CYE_DB_CONNECT_ERROR , 'mysql connect is not available.');
		if(!$this->db->commit())
		{
			cy_log(CYE_ERROR, 'mysql transaction commit error [%d] [%s]', $this->db->errno, $this->link->error);
			return cy_dt($this->db->errno, $this->link->error);
		}

		return cy_dt(0);
	}

	function query($sql, $options = [])
	{
		$timeout = isset($options['timeout']) ? $options['timeout'] : $_ENV['config']['timeout']['mysql_read'];

		if(!$this->is_avaliable()) return cy_dt(CYE_DB_CONNECT_ERROR , 'mysql connect is not available.');

		$this->db->query($sql, MYSQLI_ASYNC);
		$finished = 0;
		$t1 = microtime(true);
		while(!$finished)
		{
			$links = $errors = $reject = [$this->db];
			if(!mysqli_poll($links, $errors, $reject, 0, 100000/* 100ms. */)) // timeout 100ms.
			{
				if(microtime(true) - $t1 < $timeout)
				{
					continue;
				}

				$this->db->close();

				cy_log(CYE_ERROR, 'mysql query timeout, over %f second', $timeout);
				$dt = cy_dt(CYE_NET_TIMEOUT, 'mysql query timeout');
				goto end;
			}

			$finished = 1;
		}

		if(!($result = $this->db->reap_async_query()))
		{
			cy_log(CYE_ERROR, 'mysql query error [%d] [%s]', $this->db->errno, $this->db->error);
			$dt = cy_dt($this->db->errno, $this->db->error);
			goto end;
		}

		/* For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query() will return a mysqli_result object */
		if(is_object($result))
		{
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			mysqli_free_result($result);
			$dt = cy_dt(0, $rows);
			goto end;
		}


		/* For other successful queries mysqli_query() will return TRUE. */
		$r = ['affected_rows' => $this->db->affected_rows, 'insert_id' => $this->db->insert_id];
		$dt= ['errno' => 0, 'data' => $r, 'info' => $this->db->info];

end:
		$t2= microtime(true);
		cy_stat('mysql-query', ($t2 - $t1)*1000000);
		return $dt;
	}

	function insert($table, $data, $cond = '')
	{
		$where = array();
		foreach($data as $k => $v)
		{
			$where[] = '`'.$k.'`=\''.addslashes($v).'\'';
		}

		$sql = "INSERT INTO `".$table."` SET ".implode(',', $where).' '.$cond;
		return $this->query($sql);
	}

	function update($table, $data, $cond = '')
	{
		$where = array();
		foreach($data as $k => $v)
		{
			if(strpos($v, 'POINT') !== false || strpos($v, 'LINESTRING') !== false)
			{
				$where[] = '`'.$k."`=GeomFromText('".$v."')";
			}
			else
			{
				$where[] = '`'.$k."`='".addslashes($v)."'";
			}
		}

		$sql = "UPDATE `".$table."` SET ".implode(',', $where).' WHERE '.$cond;
		return $this->query($sql);
	}

}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
