<?php
/**
 * CY_Util_Redis
 *
 * redis client 比redis 扩展更轻量级的一个实现基础类，支持并行
 * @file redis.php
 */


/**
 * CY_Util_Redis
 *
 *
 */
class CY_Util_Redis
{
	protected static $chunk_size = 0;
	protected static $servers = NULL;
	protected static $srv_num = 0;

	protected $net;

	protected static function init_server()
	{
		$svconfig = $_ENV['config']['redis'];
		foreach($svconfig as $idc => $matrix)
		{
			foreach($matrix as $i => $list)
			{
				foreach($list as $j => $srv)
				{
					if(!cy_ctl_check($srv[0].':'.$srv[1], $_SERVER['REQUEST_TIME']))
					{
						unset($svconfig[$idc][$i][$j]);
					}
				}
			}
		}

		$idc = strstr(gethostname(), '-', true);
		if(!isset($svconfig[$idc]))
		{
			$idc = array_rand($svconfig, 1);
		}

		self::$servers = $svconfig[$idc];
		self::$srv_num = count(self::$servers);
		if(self::$srv_num === 0)
		{
			cy_log (CYE_ERROR, 'rc-'.__FUNCTION__.' no servers config found.');
		}
	}

	static function hash($key)
	{
		if(isset($key[0]))
		{
			return ((int)hexdec($key[0]))%(self::$srv_num);
		}

		return crc32($key)%self::$srv_num;
	}

	function __construct($options = array())
	{
		self::$servers || self::init_server();
		$options['timeout'] = $_ENV['config']['timeout']['redis_read'];
		if(self::$chunk_size == 0)
		{
			self::$chunk_size = $_ENV['config']['chunk']['redis'];
		}

		$this->net = new CY_Util_Net($options);
	}

	function __destruct()
	{
		//gc_collect_cycles();
	}

	/**
	 * partitions
	 *
	 * 
	 */
	function partitions($maps, $options)
	{
		$hashes = $partitions = array();
		foreach($maps as $k => $v)
		{
			$i = self::hash($k);
			if(empty($hashes[$i]))
			{
				$srv_list = self::$servers[$i];
				$hashes[$i] = array('maps' => array($k => $v), 'servers' => $srv_list);
			}
			else
			{
				$hashes[$i]['maps'][$k] = $v;
			}
		}

		foreach($hashes as $i => $hash)
		{
			$srv_list = $hash['servers'];
			foreach(array_chunk($hash['maps'], self::$chunk_size, true) as $chunk)
			{
				$srv_k = array_rand($srv_list);
				$srv   = $srv_list[$srv_k];

				$partitions[] = ['maps' => $chunk, 'server' => $srv[0].':'.$srv[1]];
			}
		}

		return $partitions;
	}

	function inc($maps, $options = array())
	{
		$partitions = $this->partitions($maps, $options);
		foreach($partitions as $i => &$part)
		{
			$pieces = ['*', 1, "\r\n$6\r\nINCRBY\r\n"];
			$count  = &$pieces[1];
			foreach($part['maps'] as $k => $v)
			{
				$pieces[] = "$".strlen($k)."\r\n".$k."\r\n";
				$pieces[] = "$".strlen($v)."\r\n".$v."\r\n";
				$pieces[] = "$1\r\n".$v."\r\n";
				$count += 3;
			}

			$part['body'] = implode($pieces);
			unset($count);
			unset($part['maps']);
			$this->net->prepare($i, array_merge($part, $options));
		}

		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, "rc-inc net get error.");
			cy_stat('rc-inc', $dt['costs'], array('errno' => $dt['errno']));
			return $dt;
		}

		cy_stat('rc-inc', $dt['costs']);
		$number = 0;
		foreach($dt['data'] as $i => $d)
		{
			if($d['errno'] !== 0)
			{
				cy_log(CYE_ERROR, 'rc-inc '.$partitions[$i]['server'].' failed.');
				continue;
			}

			$number += substr($d['data'], 1);
		}

		return array('errno' => 0, 'data' => $number);
	}

	function setbit($maps, $options = array())
	{
		$partitions = $this->partitions($maps, $options);
		foreach($partitions as $i => &$part)
		{
			$pieces = ['*', 1, "\r\n$6\r\nSETBIT\r\n"];
			$count  = &$pieces[1];
			foreach($part['maps'] as $k => $v)
			{
				$pieces[] = "$".strlen($k)."\r\n".$k."\r\n";
				if(is_numeric($v))
				{
					$pieces[] = "$".strlen($v)."\r\n".$v."\r\n";
					$pieces[] = "$1\r\n1\r\n";
				}
				else
				{
					list($k1, $v1) = $v;
					$pieces[] = "$".strlen($k1)."\r\n".$k1."\r\n";
					$pieces[] = "$1\r\n".$v1."\r\n";
				}

				$count += 3;
			}

			$part['body'] = implode($pieces);
			unset($count);
			unset($part['maps']);
			$this->net->prepare($i, array_merge($part, $options));
		}

		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, "rc-setbit net get error.");
			cy_stat('rc-setbit', $dt['costs'], array('errno' => $dt['errno']));
			return $dt;
		}

		cy_stat('rc-setbit', $dt['costs']);
		$number = 0;
		foreach($dt['data'] as $i => $d)
		{
			if($d['errno'] !== 0)
			{
				cy_log(CYE_ERROR, 'rc-setbit '.$partitions[$i]['server'].' failed.');
				continue;
			}

			$number += substr($d['data'], 1);
		}

		return array('errno' => 0, 'data' => $number);
	}

	function delete($maps, $options = array())
	{
		$partitions = $this->partitions($maps, $options);
		foreach($partitions as $i => &$part)
		{
			$pieces = ['*', 1, "\r\n$3\r\nDEL\r\n"];
			$count  = &$pieces[1];
			foreach($part['maps'] as $k => $v)
			{
				$pieces[] = "$".strlen($k)."\r\n".$k."\r\n";
				$count++;
			}


			$part['body'] = implode($pieces);
			unset($count);
			unset($part['maps']);
			$this->net->prepare($i, array_merge($part, $options));
		}


		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, "rc-delete net get error.");
			cy_stat('rc-delete', $dt['costs'], array('errno' => $dt['errno']));
			return $dt;
		}

		cy_stat('rc-delete', $dt['costs']);
		$number = 0;
		foreach($dt['data'] as $i => $d)
		{
			if($d['errno'] !== 0)
			{
				cy_log(CYE_ERROR, 'rc-delete '.$partitions[$i]['server'].' failed.');
				continue;
			}

			$number += substr($d['data'], 1);
		}

		return array('errno' => 0, 'data' => $number);
	}

	function sets($maps, $options = array())
	{
		$indexs = array();
		$partitions = $this->partitions($maps, $options);
		foreach($partitions as $i => &$part)
		{
			$index  = array();
			$pieces = ['*', 1, "\r\n$4\r\nMSET\r\n"];
			$count  = &$pieces[1];
			foreach($part['maps'] as $k => $v)
			{
				$pieces[] = "$".strlen($k)."\r\n".$k."\r\n";
				$pieces[] = "$".strlen($v)."\r\n".$v."\r\n";
				$index [] = $k;
				$count += 2;
			}


			$part['body'] = implode($pieces);
			unset($count);
			unset($part['maps']);

			$indexs[$i] = $index;
			$this->net->prepare($i, array_merge($part, $options));
		}


		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, "rc-mSet net get error.");
			cy_stat('rc-mSet', $dt['costs'], array('errno' => $dt['errno']));
			return $dt;
		}

		cy_stat('rc-mSet', $dt['costs']);

		$data = array();
		foreach($dt['data'] as $i => $d)
		{
			if($d['errno'] !== 0)
			{
				cy_log(CYE_ERROR, 'rc-mSet '.$partitions[$i]['server'].' failed.');
				continue;
			}

			$val = false;
			if(strncmp($d['data'], '+OK', 3) === 0)
			{
				$val = true;
			}

			$index = $indexs[$i];
			foreach($index as $kk => $vv)
			{
				$data[$vv] = $val;
			}
		}

		krsort($data);
		return array('errno' => 0, 'data' => $data);
	}

	function gets($list, $options = array())
	{
		$maps = array_combine($list, $list);
		return $this->mGet($maps, $options);
	}

	function mGet($maps, $options = array())
	{
		$indexs = array();
		$partitions = $this->partitions($maps, $options);
		foreach($partitions as $i => &$part)
		{
			$index  = array();
			$pieces = ['*', 1, "\r\n$4\r\nMGET\r\n"];
			$count  = &$pieces[1];
			foreach($part['maps'] as $k => $v)
			{
				$pieces[] = "$".strlen($k)."\r\n".$k."\r\n";
				$index [] = $v;
				$count++;
			}
			
			$part['body'] = implode($pieces);
			$part['read_func' ] = array($this, 'rc_read');
			unset($part['maps']);

			$indexs[$i] = $index;
			$this->net->prepare($i, array_merge($part, $options));
		}

		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, "rc-mGet net get error.");
			cy_stat('rc-mGet', $dt['costs'], array('errno' => $dt['errno']));
			return $dt;
		}

		cy_stat('rc-mGet', $dt['costs']);

		$data = array();
		foreach($dt['data'] as $i => $d)
		{
			if($d['errno'] !== 0)
			{
				cy_log(CYE_ERROR, 'rc-mGet '.$partitions[$i]['server'].' failed.');
				continue;
			}

			$idx = $indexs[$i];
			foreach($d['data'] as $n => $val)
			{
				$data[$idx[$n]] = $val; 
			}
		}

		krsort($data);
		return array('errno' => 0, 'data' => $data);
	}

	function rc_read($stream, $options)
	{
		static $max_line_size = 512;
		if(($str = fgets($stream, $max_line_size)) == false)
		{
			goto error;
		}

		$data = array();
		$nums = (int)substr($str, 1);
		$size = 0;

		stream_set_blocking($stream, 1);
		while($size++ < $nums)
		{
			if(($str = fgets($stream, $max_line_size)) == false)
			{
				goto error;
			}
		
			$len = (int)substr($str, 1);
			if($len > 0)
			{
				$left = $len + 2;
				$str  = '';

				do
				{
					if(($p = fread($stream, $left)) == false)
					{
						goto error;
					}

					$str  .= $p;
					$left -= strlen($p);
				}
				while($left > 0);

				$data[] = substr($str, 0, -2); 
			}
			else
			{
				$data[] = NULL;
			}
		}

		return array('errno' => 0, 'data' => $data);
error:
		$id = (int)$stream;
		cy_log(CYE_ERROR, $options['server'].' rc_read error fd: %d .', $id);
		return array('errno' => CYE_NET_ERROR, 'message' => $options['server']." read error.");
	}
	
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
