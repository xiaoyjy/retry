<?php

class CY_Srv_HB
{
	protected $cli;
	protected $srv;
	protected $callback;

	protected $requests = 0;
	protected $max_requests = CY_SRV_MAX_REQUEST; 

	protected $type;
	protected $pid = 0; 

	protected $lp;
	protected $aw  = NULL;
	protected $tw  = NULL;
	protected $rw  = NULL;

	protected $active_num = 0;
	protected $last_request_time = 0;

	function __construct($addr, $callback, $type)
	{
		$this->type      = $type;
		$this->addr      = $addr;
		$this->lp        = new EvLoop(Ev::recommendedBackends()); 
		$this->callback  = $callback;
		if(!is_callable($callback))
		{
			cy_log(CYE_ERROR, 'invalid callback function');
			exit;
		}

		$this->srv = stream_socket_server($this->addr, $errno, $error, STREAM_SERVER_BIND);
		if(!$this->srv)
		{
			// TODO notify father to stop process.
			cy_log(CYE_ERROR, $this->addr.' bind failed, errno '.$errno.' error '.$error);
			exit;
		}

		/* libev只支持水平触发，而水平触发的多进程模型是会惊群的，但阻塞句柄不会惊群 */
		stream_set_blocking($this->srv, 0);
		stream_set_timeout ($this->srv, 0, 0.100);

		$this->aw = $this->lp->io    ($this->srv, Ev::READ, array($this, 'process'));
		$this->tw = $this->lp->timer (0, 1, array($this, 'timer'));
	}

	function __destruct()
	{
		$this->lp->stop();
	}

	function loop()
	{
		/* loop init start. */
		$this->pid = posix_getpid();

		/* 为了防止所有进程在同一时刻全部退出, 增加一个随机量. */
		mt_srand($this->pid);
		$this->max_requests += mt_rand(0, $this->max_requests/10);

		/* loop init end */
		$this->lp->run(); /* start loop here. */
	}

	function process()
	{
		$request = stream_socket_recvfrom($this->srv, 1500, 0, $this->cli);
		if($request == false)
		{
			cy_log(CYE_ERROR, "read request from srv fd error.");
			return false;
		}

		$this->request_init();
		$this->request($request);
		$this->request_shutdown();
	}

	function request($request)
	{
		try
		{
			$dt = call_user_func($this->callback, NULL, $request);
			if(!empty($dt['data']))
			{
				stream_socket_sendto($this->srv, $dt['data'], 0, $this->cli);
			}
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, "exception while processing.");
		}
	}

	function request_init()
	{
		$_SERVER['REQUEST_TIME_FLOAT'] = $this->lp->now();
		$_SERVER['REQUEST_TIME']       = (int)$_SERVER['REQUEST_TIME_FLOAT'];
		$_SERVER['REMOTE_ADDR']        = $this->addr;

		$this->aw->stop();
		$this->active_num ++;
	}

	function request_shutdown()
	{
		$this->active_num --;
		$this->aw->start();
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
	}

	function srv_shutdown()
	{
		$this->sw && $this->sw->stop();
		$this->aw && $this->aw->stop();
		$this->tw && $this->tw->stop();
		$this->rw && $this->rw->stop();
		$this->lp && $this->lp->stop();
	}

	function srv_finish()
	{
		$error = error_get_last(); 
		if($error['type'] === E_ERROR)
		{
			cy_log(CYE_ERROR, 'cy_srv_finish fatal errors found %s', json_encode($error));
		}

		cy_i_del('bps_srv_term_'.$this->pid, CY_TYPE_SYS);
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
