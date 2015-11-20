<?php

class CY_Util_Net
{
	protected $tasks   = array();
	protected $sockets = array();

	protected $timeout   = 0.6;
	protected $protocol  = 'tcp://';

	function __construct($options = array())
	{
		isset($options['timeout']) && $this->timeout = $options['timeout'];
	}

	function __destruct()
	{
		$this->cleanup();
	}

	function cleanup()
	{
		foreach($this->sockets as $sock)
		{
			fclose($sock);
		}

		$this->sockets = array();
		$this->tasks   = array();
	}

	function prepare($key, $req)
	{
		if(empty($req['server']))
		{
			return false;
		}

		if(empty($req['body']))
		{
			return false;
		}

		/*
		if(!cy_ctl_check($req['server'], $_SERVER['REQUEST_TIME']))
		{
			cy_log(CYE_WARNING, $req['server']." net_prepare server ".$req['server']." is blocked.");
			return false;
		}
		*/

		$to = $_ENV['config']['timeout']['net_default'];
		isset($req['timeout'  ])  || $req['timeout'  ] = (int)$to;
		isset($req['timeout_s'])  || $req['timeout_s'] = ($to - (int)$to)*1000000;

		$flags  = STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT;
		$host   = $this->protocol.$req['server'];
		$stream = stream_socket_client($host, $errno, $error, 1, $flags); 
		if(!$stream)
		{
			cy_log(CYE_ERROR, 'stream_socket_client errno '.$errno." ".$error);
			return false;
		}

		$req['status'] = 0;
		$req['key']    = $key;

		/* set no block. */
		stream_set_blocking($stream, 0);

		stream_set_timeout ($stream, $req['timeout'], $req['timeout_s']);

		/* add task */
		$id = (int)$stream;
		$this->sockets[$id] = $stream;
		$this->tasks  [$id] = $req; 
		return true;
	}

	function get()
	{
		$finished = $costs = 0;
		if(empty($this->sockets))
		{
			return ['errno' => 0, 'data' => [], 'message' => 'empty clients.', 'costs' => $costs];
		}

		$t1 = microtime(true);

		$s_writes = $s_excepts = $this->sockets;
		$s_reades = array();
		do
		{
			$reades  = $s_reades;
			$writes  = $s_writes;
			$excepts = $s_excepts;
			$num     = stream_select($reades, $writes, $excepts, 0, 100000);
			if($num === false)
			{
				return array('errno' => CYE_NET_ERROR, 'costs' => 0);
			}

			foreach($writes as $id => $stream)
			{
				$task = &$this->tasks[$id];

				unset($s_writes[$id]);
				$method = isset($task['write_func']) ? $task['write_func'] : 'CY_Util_Stream::default_write';
				$dt = call_user_func($method, $stream, $task['body'], $task);
				if($dt['errno'] !== 0)
				{
					$task['status'] = -1;
					unset($s_excepts[$id]);
					//cy_ctl_fail($task['server'], $_SERVER['REQUEST_TIME']);
					continue;
				}

				$s_reades[$id] = $stream;
				$task['status'] = 1;
			}

			foreach($reades as $id => $stream)
			{
				$task = &$this->tasks[$id];

				unset($s_reades[$id]);	
				$method = isset($task['read_func']) ? $task['read_func'] : 'CY_Util_Stream::default_read';
				$dt = call_user_func($method, $stream, $task);
				if($dt['errno'] !== 0)
				{
					$task['status'] = -1;
					//cy_ctl_fail($task['server'], $_SERVER['REQUEST_TIME']);
					unset($s_excepts[$id]);
					continue;
				}

				$task['status'] = 2;
				$task['data'  ] = $dt['data'];
				//cy_ctl_succ($task['server'], $_SERVER['REQUEST_TIME']);
			}

			$costs = microtime(true) - $t1;
		}
		while((!empty($s_reades) || !empty($s_writes)) && $costs < $this->timeout);

		$data = array();
		foreach($this->tasks as $t)
		{
			$part = array('errno' => 0);
			if($t['status'] == 2)
			{
				$part['data'] = $t['data'];
			}
			else if($t['status'] == 1)
			{
				$part['errno'] = CYE_NET_TIMEOUT; 
			}
			else
			{
				$part['errno'] = CYE_SYSTEM_ERROR; 
			}

			$key = $t['key'];
			$data[$key] = $part;
		}
		
		$this->cleanup();
		return ['errno' => 0, 'data' => $data, 'costs' => $costs*1000000];
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
