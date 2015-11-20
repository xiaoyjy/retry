<?php

class CY_Driver_DB_Default implements CY_Driver_Impl
{
	protected $requests;
	protected $options;

	protected $table;

	/**
	 * Parameters
	 * ============================================================
	 * $options['callback'], used for async mysql requests.
	 */
	function __construct($table, $requests, $options = [])
	{
		$this->table   = $table;
		$this->requests= $requests;
		$this->options = $options;
	}

	function options()
	{
		return $this->options;
	}

	/***
	 * Example1
	 * ============================================================
	 * $this->requests['where']
	 *     keyA => [val1, val2]
	 *     keyB => [val3, val4]
	 * 
	 * $this->requests['logic']
	 *     [AND|OR] => AND
	 *
	 * $this->table
	 *     table name => t1
	 * 
	 * return:
	 *  WHERE keyA IN (val1, val2) AND keyB IN (val3, val4)
	 *
	 *
	 * Example2
	 * ============================================================
	 * $this->requests['where']
	 *     'keyA=500'
	 *
	 * return: 
	 *  WHERE keyA=500
	 *
	 *
	 * Example3
	 * ============================================================
	 * $this->requests['where']
	 *     keyA => [(string)val1, (int)val2]
	 *  
	 * $this->options['where']
	 *     keyA => (`keyA`!='%s' OR `keyB`=%d)
	 * 
	 * return:
	 *     WHERE (`keyA`!='val1' OR `keyB`=val2)
	 *
	 *
	 * Example4
	 * ============================================================
	 * $this->requests['where']
	 *     keyA => val1
	 *
	 * return:
	 *     WHERE `keyA`=val1
	 */
	function where($opt, $escape_string)
	{
		if(empty($this->requests['where']))
		{
			return '';
		}

		$factor = $this->requests['where'];
		if(is_string($factor))
		{
			return 'WHERE '.$factor;
		}

		$wheres = [];

		/* if where in options, use sprintf to rewrite. */
		if(isset($this->options['where']))
		{
			foreach($this->options['where'] as $key => $method)
			{
				$v = is_array($factor[$key]) ? $factor[$key] : [$factor[$key]];
				$v = array_map($escape_string, $v);
				$v = array_unshift($v, $method);

				$wheres[] = call_user_func_array('sprintf', $v);
				unset($factor[$key]);
			}
		}

		/* Default where statement. */
		foreach($factor as $key => $value)
		{
			strpos($key, '.') === false && $key = '`'.$key.'`';
			if(is_array($value))
			{
				$value = array_map($escape_string, $value);
				$where = $key." IN ('". implode("','", $value)."')";
			}
			else
			{
				$where = $key."='".call_user_func($escape_string, $value)."'";
			}

			$wheres[] = $where;
		}

		if(empty($wheres))
		{
			return '';
		}

		$logic = isset($this->requests['logic']) ? $this->requests['logic'] : 'AND';
		return "WHERE ".implode(" $logic ", $wheres);
	}


	function order()
	{
		$order = '';
		if(isset($this->options['order']))
		{
			list($k, $v) = $this->options['order'];
			$order = ' ORDER BY '.$k.' '.$v;
		}

		return $order;
	}

	function limit()
	{
		$limit = '';
		if(isset($this->options['limit']))
		{
			$limit = ' LIMIT '.$this->options['limit'];
		}

		return $limit;
	}

	function which()
	{
		$which = '*';
		if(isset($this->options['which']))
		{
			$which = trim($this->options['which']);
		}

		return $which;
	}

	/***
	 * return simple SQL string.
	 */
	function input_sql($opt)
	{
		return ['sql' => $this->requests['sql']];
	}

	/***
	 * @opt 传进来的是系统参数，数据参数在构造函数中指定.
	 *
	 * $this->requests['where']
	 *     keyA => [val1, val2]
	 *     keyB => [val3, val4]
	 * 
	 * $this->requests['logic']
	 *     [AND|OR] => AND
	 *
	 * $this->options['order']
	 *     [col] => [asc|desc]
	 *
	 * $this->options['limit']
	 *      number[, number]
	 * 
	 * $this->table
	 *     table name => t1
	 * 
	 * return:
	 *  sql => SELECT * FROM `t1` WHERE keyA IN (val1, val2) AND keyB IN (val3, val4)
	 */
	function input_get($opt)
	{
		$input = array();
		if(empty($this->requests))
		{
			return $input;
		}

		$escape_string = isset($opt['db']) ? [$opt['db'], 'real_escape_string'] : 'addslashes';

		$table         = (strpos($this->table, '.') !== false) ? $this->table : '`'.$this->table.'`';
		$input['sql']  = "SELECT ".$this->which()." FROM ".$table." ".$this->where($opt, $escape_string).$this->order().$this->limit();
		return $input;
	}

	/***
	 * @opt 传进来的是系统参数，数据参数在构造函数中指定.
	 *
	 * $this->requests['where']
	 *     keyA => [val1, val2]
	 *     keyB => [val3, val4]
	 * 
	 * $this->requests['logic']
	 *     [AND|OR] => AND
	 *
	 * $this->table
	 *     table name => t1
	 * 
	 * return:
	 *  sql => DELETE FROM `t1` WHERE keyA IN (val1, val2) AND keyB IN (val3, val4)
	 */
	function input_delete($opt)
	{
		$input = array();

		/* DELETE without where is not allow. */
		if(empty($this->requests['where']))
		{
			return $input;
		}

		$escape_string = isset($opt['db']) ? [$opt['db'], 'real_escape_string'] : 'addslashes';
		$table         = (strpos($this->table, '.') !== false) ? $this->table : '`'.$this->table.'`';
		$input['sql']  = "DELETE FROM ".$table." ".$this->where($opt, $escape_string).$this->limit();
		return $input;
	}

	/**
	 * $opt 传进来的是系统参数，数据参数在构造函数中指定.
	 *     db   => mysql handle , used to escape_string.
	 *
	 * $this->requests['where'] 
	 *     keyA => [val1, val2]
	 *     keyB => [val3, val4]
	 *
	 * $this->requests['logic']
	 *     [AND|OR] => AND
	 *
	 * $this->requests['data']
	 *	attrA => val7
	 *	attrB => +=val8
	 *
	 * $this->table => t1
	 * 
	 * return:
	 *  sql => UPDATE `t1` SET attrA=val7, attrB=attrB+val8 WHERE keyA IN (val1, val2) AND keyB IN (val3, val4)
	 */
	function input_update($opt)
	{
		$input = array();

		/* UPDATE without where or data is not allow. */ 
		if(empty($this->requests['where']) || empty($this->requests['data']))
		{
			return $input;
		}

		$data   = $this->requests['data'];
		$option = $this->options;
		$escape_string = isset($opt['db']) ? [$opt['db'], 'real_escape_string'] : 'addslashes';

		/* make data. */
		$values = array();

		$data = array_map($escape_string, $data);
		foreach($data as $k => $v)
		{
			switch(substr($v, 0, 2))
			{
				case '+=':
					$values[] = "`$k`=`$k`+".(int)substr($v, 2);
					break;

				case '-=':
					$values[] = "`$k`=`$k`-".(int)substr($v, 2);
					break;

				default:
					/* Default k=v */
					$values[] = "`$k`='".$v."'";
					break;
			}
		}

		/* make where. */
		$where = $this->where($opt, $escape_string);
		$table         = (strpos($this->table, '.') !== false) ? $this->table : '`'.$this->table.'`';
		$input['sql'] = 'UPDATE '.$table.' SET '.implode(',', $values).' '.$where.$this->limit();
		return $input;
	}

	/**
	 * $opt 传进来的是系统参数，数据参数在构造函数中指定.
	 *     db => mysql handle , used to escape_string.
	 *
	 * $this->requests['data'][0]
	 *	attrA => val1
	 *	attrB => val2
	 *
	 * $this->requests['data'][1]
	 *	attrA => val3
	 *	attrB => val4
	 *
	 * $this->table => t1
	 *
	 * $this->options
	 *	update => true
	 * 
	 * return:
	 * 	sql => INSERT INTO `t1`(attrA, attrB) VALUES(val1, val2), (val3, val4)
	 *               ON DUPLICATE KEY UPDATE attrA=VALUES(attrA), attrB=VALUES(attrB) 
	 */
	function input_insert($opt)
	{
		$input = array();
		$first = current($this->requests['data']);
		if(empty($first) || !is_array($first))
		{
			return $input;
		}

		$noquota = isset($this->requests['noquota']) ? $this->requests['noquota'] : array();

		/* 获取所有要插入的字段 */
		$kk = $kv = array();
		foreach($first as $k => $v)
		{
			$kk[]  = $k;

			if($k !== 'id')
			{
				$kv[] = '`'.$k.'`=VALUES(`'.$k.'`)';
			}
		}

		$escape_string = isset($opt['db']) ? [$opt['db'], 'real_escape_string'] : 'addslashes';
		$values = array();
		foreach($this->requests['data'] as $k => $v)
		{
			foreach($v as &$vv)
			{
				// 主要针对ext之类的字段
				if(is_array($vv))
				{
					$vv = json_encode($vv);
				}

			} unset($vv);

			foreach($v as $k1 => $v1)
			{
				if(!in_array($k1, $noquota) && !empty($v1))
				{
					$v[$k1] = "'".call_user_func($escape_string, $v1)."'";
				}
				elseif(empty($v1))
				{
					$v[$k1] = "''";
				}
			}

			$str  = "(";
			$str .= implode(",", $v);
			$str .= ")";
			$values[] = $str;
		}

		$delayed = isset($this->options['delayed']) ? 'DELAYED ' : '';
		$replace = isset($this->options['replace']) ? 'REPLACE ' : 'INSERT ';
		$table   = (strpos($this->table, '.') !== false) ? $this->table : '`'.$this->table.'`';
		$sql  = $replace.$delayed.'INTO '.$table;
		$sql .= ' (`'.implode("`,`", $kk).'`) VALUES ';
		$sql .= implode(",", $values);

		/* 
		 * 1、要求所有表必须有主键
		 * 2、这样会降低效率、但是会减少处理逻辑 
		 */
		if(!empty($this->options['update']))
		{
			$sql .= " ON DUPLICATE KEY UPDATE ".implode(",", $kv);
		}

		$input['sql'] = $sql;
		return $input;
	}

	function inputs($data)
	{
		$method = 'input_'.(isset($this->options['method']) ? $this->options['method'] : 'get');
		if(method_exists($this, $method))
		{
			$r = $this->$method($data);
			return $r;
		}

		return array();
	}

	function output_get($data)
	{
		$key = isset($this->options['key']) ? $this->options['key'] : 'id';

		$dest = array();
		foreach($data as $row)
		{
			$id = $row[$key];
			$dest[$id] = $row;
		}

		return $dest;
	}

	function outputs($data)
	{
		$method = 'output_'.(isset($this->options['method']) ? $this->options['method'] : 'get');
		if(empty($this->options['rawdata']) && method_exists($this, $method))
		{
			$data = $this->$method($data);
		}

		return $data;
	}

	function callback($data)
	{
		if(isset($this->options['callback']) && is_callable($this->options['callback']))
		{
			$data['requests'] = $this->requests;
			call_user_func($this->options['callback'], $data);
		}
	}
}

?>
