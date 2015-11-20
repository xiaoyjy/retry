<?php

class CY_Srv_TCP
{
	protected $srv;
	protected $callback;

	protected $requests = 0;
	protected $max_requests = CY_SRV_MAX_REQUEST; 
	protected $max_lock_seconds = 10;

	protected $type;
	protected $lock_name;
	protected $rnum_name;
	protected $stat_name = '';
	protected $pid = 0; 

	protected $lp;
	protected $sw  = NULL;//signal watcher
	protected $aw  = NULL;
	protected $tw  = NULL;
	protected $rw  = NULL;

	protected $client = NULL;
	protected $client_addr = '127.0.0.1';

	protected $last_request_time = 0;
	protected $lock_time;

	protected $active_num = 0;//client number of dealing with
	protected $stopping   = 0;//1:grace quit; 2:force terminate

	function __construct($addr, $callback, $type)
	{
		//$this->lock_name = 'bps_'.$type.'_lock';
		$this->rnum_name = 'bps_srv_'.$type.'_req_num';
		$this->type      = $type;
		$this->lp        = new EvLoop(Ev::recommendedBackends()); 

		//cy_create_lock($this->lock_name);
		$this->srv = stream_socket_server($addr, $errno, $error, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN);
		if(!$this->srv)
		{
			// TODO notify father to stop process.
			cy_log(CYE_ERROR, $addr.' bind failed, errno '.$errno.' error '.$error);
			exit;
		}

		if(!is_callable($callback))
		{
			cy_log(CYE_ERROR, 'invalid callback function');
			exit;
		}

		// set block mode
		stream_set_blocking($this->srv, 1);
		stream_set_timeout ($this->srv, 0, 0.8);
		$this->callback = $callback;
	}

	function __destruct()
	{
		$this->lp->stop();
	}

	/*
	   function lock()
	   {
	   $ret = cy_lock($this->lock_name);
	   cy_i_set($this->stat_name, CY_TYPE_SYS, 1);
	   $this->lock_time = $this->lp->now();
	   return $ret;
	   }

	   function unlock()
	   {
	   if(cy_i_get($this->stat_name, CY_TYPE_SYS) !== 1)
	   {
	   return false;
	   }

	   $ret = cy_unlock($this->lock_name);
	   cy_i_set($this->stat_name, CY_TYPE_SYS, 0);
	   $this->lock_time = 0;
	   return $ret;
	   }
	 */

	function loop()
	{
		/* loop init start. */
		$this->pid       = posix_getpid();
		//$this->stat_name = 'bps_srv_lock_'.$this->pid;

		/* 为了防止所有进程在同一时刻全部退出, 增加一个随机量. */
		mt_srand($this->pid);
		$this->max_requests += mt_rand(0, $this->max_requests/10);
		/* loop init end */

		//$this->lock();
		$this->last_request_time = 0;
		$this->aw = $this->lp->io    ($this->srv, Ev::READ, array($this, 'process'));
		$this->tw = $this->lp->timer (0, 1, array($this, 'timer'));
		$this->lp->run(); /* start loop here. */
		//$this->unlock();
	}

	function timer()
	{
		if(cy_i_get('bps_srv_term_'.$this->pid, CY_TYPE_SYS) === 1) // grace quit
		{
			if($this->srv)
			{
				fclose($this->srv);
				$this->srv = NULL;
			}

			if($this->active_num <= 0)
			{
				$this->srv_shutdown();
			}

			return;
		}

		$now = $this->lp->now();
		if($this->lock_time && $now - $this->lock_time > $this->max_lock_seconds)
		{
			cy_log(CYE_DEBUG, "%d waiting more than %d seconds, force next loop.", $this->pid, $this->max_lock_seconds);
			$this->next_loop_force();
			return;
		}

		/* 如果长连接一段时间没有请求，关闭一次 */
		if($this->client && $this->last_request_time && $now - $this->last_request_time > $this->max_lock_seconds*2)
		{
			cy_log(CYE_DEBUG, "%d closed, no request in a while.", $this->client);
			$this->next_loop_force();
			return;
		}
	}

	function next_loop()
	{
		if(!$this->client)
		{
			cy_log(CYE_WARNING, 'do next_loop but last loop is bad');
			return;
		}

		/*
		if(cy_i_get($this->stat_name, CY_TYPE_SYS) === 1)
		{
			cy_log(CYE_ERROR, 'next_loop bad lock found.');
		}
		*/

		$this->next_loop_force();
	}

	function next_loop_force()
	{
		/*
		   if(cy_i_get($this->stat_name, CY_TYPE_SYS) === 1)
		   {
		   $this->unlock();
		   }
		 */

		if($this->client && is_resource($this->client))
		{
			fclose($this->client);
			$this->client = NULL;
		}

		if(!empty($this->rw))
		{
			foreach($this->rw as $k => $rw)
			{
				if(isset($this->rw[$k]) && is_object($this->rw[$k]))
				{
					$this->rw[$k]->stop();
				}
				else
				{
					//var_dump($k);
					//var_dump($this->rw[$k]);
					cy_log(CYE_ERROR, 'unkown read watcher: '.$k);
				}
			}

			$this->rw = [];
		}
		$this->last_request_time = 0;
		//$this->lock();
		$this->aw->start();
	}

	function process()
	{
		$this->client = stream_socket_accept($this->srv, 0, $this->client_addr);
		if($this->client == false)
		{
			cy_log(CYE_WARNING, 'stream_socket_accept '.$this->srv.' errno: '.cy_errno());
			return;
		}
		//stream_set_blocking($this->client, 1);
		//$this->unlock();

		stream_set_timeout($this->client, 0.1, CY_SRV_CLI_TIMEOUT);

		$this->active_num++;
		$this->rw[] = $this->lp->io($this->client, ev::READ, array($this, 'request'));
		$this->aw->stop();
	}

	function request()
	{
		if(feof($this->client))
		{
			$this->active_num--;
			return $this->next_loop();
		}

		$this->request_init();
		try
		{
			$dt = call_user_func($this->callback, $this->client);
		}
		catch(Exception $e)
		{
			$this->active_num--;
			cy_log(CYE_ERROR, "srv-request ".$e->getMessage());
			return $this->next_loop();
		}

		/* 当errno 为 CYE_DATA_EMPTY时为无效调用，一般为客户端主动断开连接，忽略 */
		if($dt['errno'] == CYE_DATA_EMPTY)
		{
			$this->active_num--;
			return $this->next_loop();
		}

		cy_i_inc($this->rnum_name, CY_TYPE_SYS, 1);
		$this->request_shutdown();

		/* 为防止memory leak, 每个进程在处理一定量的请求之后就退出。 */
		if(++$this->requests > $this->max_requests)
		{
			$this->srv_shutdown();
			cy_log(CYE_WARNING, "process ".$this->pid." exit after ".$this->max_requests." requests");
		}

		/* 所有处理按长连接进行 */
		$this->last_request_time = microtime(true);
		$this->active_num--;

		// $this->lp->invokePending();
		return $this->next_loop();
	}

	function request_init()
	{
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true); 
		$_SERVER['REQUEST_TIME']       = (int)$_SERVER['REQUEST_TIME_FLOAT'];
		$_SERVER['REMOTE_ADDR']        = $this->client_addr;
		$_SERVER['REQUEST_TIME_START'] = $_SERVER['REQUEST_TIME_FLOAT'];
	}

	function request_shutdown()
	{
		cy_stat_flush();
	}

	function srv_shutdown()
	{
		if($this->client)
		{
			fclose($this->client);
			$this->client = NULL;
		}

		$this->sw && $this->sw->stop();
		$this->aw && $this->aw->stop();
		$this->tw && $this->tw->stop();
		if(!empty($this->rw))
		{
			foreach($this->rw as $rw)
			{
				$rw->stop();
			}
		}
		$this->lp && $this->lp->stop();
	}

	function srv_finish()
	{
		$error = error_get_last(); 
		if($error['type'] === E_ERROR)
		{
			cy_log(CYE_ERROR, 'cy_srv_finish fatal errors found %s', json_encode($error));

			// 防止因为fatal error导致死锁
			/*
			   if($this->unlock())
			   {
			   cy_log(CYE_ERROR, 'cy_srv_finish unlock the process, fatal error happen in process.');
			   }
			 */
		}

		cy_i_del('bps_srv_term_'.$this->pid, CY_TYPE_SYS);
		cy_i_del($this->stat_name, CY_TYPE_SYS);
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
