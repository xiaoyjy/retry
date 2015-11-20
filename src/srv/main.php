<?php

define('CMD_QUIT'       , 1); //grace quit
define('CMD_TERM'       , 2); //force terminate

define('WKST_SPAWN'     , 1 ); //just spawn worker
define('WKST_RUNNING'   , 2 ); //stable running worker and if not run,cat restart
define('WKST_QUITING'   , 3 ); //will quit and not restart
define('WKST_END'       , 4 ); //not start worker

define('MAX_DLC_COUNT'  , 10); // max dead lock check count

class CY_Srv_Main
{
	protected $workers;
	protected $servers;

	protected $srv;
	protected $cmd;

	protected $loop;
	protected $flag;

	protected $status;
	protected $type;
	protected $gid;

	function __construct()
	{
		$this->servers = [];
		$this->workers = [];
		$this->status  = ['pid' => 0, 'time' => time()];
		$this->cmd = implode(" ", $_SERVER['argv']);
	}

	function deamonlize()
	{
		$pid = pcntl_fork();
		if($pid < 0)
		{
			cy_log(CYE_ERROR, "pcntl_fork($key) error.");
			exit(-1);
		}

		if($pid > 0)
		{
			exit(0);
		}

		$d=  posix_setsid();

		fclose(STDIN );
		fclose(STDOUT);
		fclose(STDERR);
	}

	function start()
	{
		//$this->deamonlize();

		cy_i_drop(CY_TYPE_SYS);
		cy_i_init(CY_TYPE_SYS);
		cy_i_set('bps_srv_status', CY_TYPE_SYS, 0);

		$pid  = posix_getpid();
		$param= [];
		$name = '';

		foreach($_ENV['server'] as $type => $arr)
		{ 
			if(empty($pid))
			{
				break;
			}

			if(empty($arr['enable']))
			{
				continue;
			}

			$name  = $type;
			$param = $arr ;
			switch($pid = pcntl_fork())
			{
				case -1:
					cy_log(CYE_ERROR, "pcntl_fork($key) error.");
					exit(-1);

				case 0:
					$this->type = $type;
					break;

				default:
					break;
			}
		}

		if($pid || empty($name) || empty($param))
		{
			exit(0);
		}

		$ptname = "CY_Srv_Protocol_".$name;
		if(!class_exists($ptname))
		{
			exit("unkown server implements ".$ptname."\n");
		}

		$wkname = 'CY_Srv_'.$param['worker'];
		if(!class_exists($wkname))
		{
			exit("unkown server implements ".$wkname."\n");
		}

		$worker = new $wkname($param['listen'], [new $ptname(), 'run'], $name);
		$this->servers = ['stat' => ['number' => 0, 'status' => 0], 'list' => [], 'worker' => $worker];
		cy_i_set('bps_srv_'.$name.'_num'    , CY_TYPE_SYS, 0);
		cy_i_set('bps_srv_'.$name.'_req_num', CY_TYPE_SYS, 0);
        
		$this->flag = WKST_RUNNING;
		$this->loop = EvLoop::defaultLoop();
		$this->worker_start();

		// master process is here.
		//cy_title($this->cmd." [master]");

		// check dead lock. check every 10 second.
		$this->watcher['ta'] = $this->loop->timer(0, 2, [$this, 'master_timer']);

		// master loop here.
		$this->loop->run();
	}

	function restart()
	{	
		cy_i_set('bps_srv_status', CY_TYPE_SYS, 1);
	}

	function stop()
	{
		cy_i_set('bps_srv_status', CY_TYPE_SYS, 2);
	}

	function worker_start()
	{
		$k = $this->type;
		$v = $_ENV['server'][$k];
		$n = $v['number'];
        
		for($i = 0; $i < $n; $i++)
		{
			$pid = $this->worker_fork($k);
		}
	}

	function worker_fork($key)
	{
		switch($pid = pcntl_fork())
		{
			case -1:
				cy_log(CYE_ERROR, "pcntl_fork($key) error.");
				break;

			case 0:	
				$this->worker_init();

				// child process loop here.
				$this->servers['worker']->loop();

				// child process exit normal.
				exit(0);

			default:
				cy_i_inc('bps_srv_'.$key.'_num', CY_TYPE_SYS, 1);
				$w = [];
				$w['pw'  ] = $this->loop->child($pid, false, [$this, 'worker_onexit']);
				$w['key' ] = $key;
				$w['flag'] = $this->flag;
				$this->workers[$pid] = $w;
				break;
		}
	}

	function worker_init()
	{
		foreach($this->workers as $pid => $value)
		{
			if(empty($value['pw']))
			{
				continue;
			}

			$value['pw']->stop();
		}

		if(isset($this->watcher['ta'])) { $this->watcher['ta']->stop();  }
		$this->watcher = [];
		$this->workers = [];

		$this->loop->stop();

		// change display title.
		//cy_title($this->cmd." [worker $key]");

		register_shutdown_function(array($this->servers['worker'], 'srv_finish'));
	}

	function worker_onexit($pw, $revents)
	{
		cy_log(CYE_DEBUG, "in worker_onexit");
		if(!is_object($pw) && get_class($pw) !== 'EvChild')
		{	
			cy_log(CYE_ERROR, 'error type param, in worker_onexit');
			return;
		}

		$pw->stop();
		$pid = $pw->rpid;
		pcntl_waitpid($pid, $status, WNOHANG);

		$wiexit = pcntl_wifexited($status);
		$status = $pw->rstatus;
		$worker = $this->workers[$pid];
		$key    = $worker['key' ];
		unset($this->workers[$pid]);

		if($this->flag === WKST_RUNNING || $this->flag === WKST_SPAWN)
		{
			$this->worker_fork($key);
		}
		elseif($this->flag === WKST_END)
		{
			$now_num = cy_i_get('bps_srv_'.$key.'_num', CY_TYPE_SYS);
			if($now_num === 0)
			{
				$this->loop->stop();
			}
		}

		cy_i_dec("bps_srv_".$key."_num", CY_TYPE_SYS, 1);
		cy_i_del("bps_srv_lock_".$pid  , CY_TYPE_SYS);
		if($wiexit)
		{
			return;
		}

		/* 子进程没有正常退出, 加保护性代码,防止进程因为被kill而死锁 */
		if($flag === WKST_QUITING)
		{
			cy_log(CYE_TRACE, $pid.' exit, receive master cmd.');
		}
		else
		{
			cy_log(CYE_ERROR, $pid.' is not normal exited.');

		}

		// TCP Server Only
		$stat_lock = cy_i_get($stat_name, CY_TYPE_SYS);
		if($stat_lock)
		{
			cy_unlock('bps_'.$key.'_lock');
		}

		usleep(100000);
	}

	function master_timer($tw)
	{
		if(!is_object($tw) && get_class($tw) !== 'EvTimer')
		{
			cy_log(CYE_ERROR, 'wrong type param called in master_timer');
			return;
		}

		//restart by luohaibin
		if (cy_i_get('bps_srv_status', CY_TYPE_SYS) == 1)
		{
			cy_i_set('bps_srv_status', CY_TYPE_SYS, 0);

			$this->flag = WKST_SPAWN;
			$this->worker_start(); 
			$this->worker_clean();
			$this->flag = WKST_RUNNING; 
		}

		//force stop 
		if (cy_i_get('bps_srv_status', CY_TYPE_SYS) == 2)
		{
			cy_i_set('bps_srv_status', CY_TYPE_SYS, 0);

			//master exit
			$this->flag = WKST_END;

			//worker force terminate
			$this->worker_clean();

			// TODO need wait.
			//$this->loop->stop();
		}

		/* Dead lock detect. */
		/*
		$max = isset($_ENV['config']['max_lock_time']) ? $_ENV['config']['max_lock_time'] : 1;
		$now = time();
		foreach($this->workers as $pid => $worker)
		{
			if(cy_i_get('bps_srv_lock_'.$pid, CY_TYPE_SYS))
			{
				if($this->status['pid'] != $pid)
				{
					$this->status['pid']  = $pid;
					$this->status['time'] = $now;
					break;
				}

				if($now - $this->status['time'] > $max)
				{
					cy_log(CYE_ERROR, 'kill process '.$pid.' who had lock more than '.$max.'s');
					cy_i_set('bps_srv_term_'.$pid, CY_TYPE_SYS, 1);
				}

				break;
			}
		}

		*/
	}

	//only for current sync model of worker
	function worker_clean()
	{
		foreach($this->workers as $pid => $worker)
		{
			if($worker[$flag] == WKST_RUNNING)
			{
				$this->workers[$pid]['flag'] = WKST_QUITING;
				cy_i_set('bps_srv_term_'.$pid, CY_TYPE_SYS, 1);
			}
		}
	}

	/*
	function check_running($key)
	{
		/* is running的条件是有锁被打开，或者有请求进来（如果有请求进来里，有锁被打开的检查不是充分必要条件） * /
		$req_num1 = cy_i_get('bps_srv_'.$key.'_req_num', CY_TYPE_SYS);
		foreach($this->workers as $pid => $bool)
		{
			if(cy_i_get('bps_srv_lock_'.$pid, CY_TYPE_SYS))
			{
				return true;
			}
		}

		$req_num2 = cy_i_get('bps_srv_'.$key.'_req_num', CY_TYPE_SYS);
		return $req_num1 != $req_num2;
	}

	function master_signals($watcher)
	{
		//var_dump($watcher->data);
		cy_log(CYE_TRACE, "in master_signals:receive ".$watcher->data);
	}

	*/
}

?>
