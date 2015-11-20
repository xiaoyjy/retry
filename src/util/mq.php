<?php
/*
 * 这个不要用了
 */

include CY_LIB_PATH.'/3rd/php-amqplib/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class CY_Util_MQ
{
	protected $config = NULL;
	protected $connection;
	protected $channel;
	protected $queue;
	protected $exchange;

	protected $runing = false;
	
	function __construct($queue, $config = NULL)
	{
		if(!$config && isset($_ENV['config']['mq_ming']))
		{
			$c = $_ENV['config']['mq_ming'];
			$i = array_rand($c);
			$config = $c[$i];
		}

		$config || $config = ['host' => 'localhost', 'port' => 5672, 'login' => 'guest', 'password' => 'guest', 'vhost' => '/'];
		$this->config = $config;
		$this->queue  = $queue;
	}

	function __destruct()
	{
		try
		{
			$this->channel    && $this->channel->close();
			$this->connection && $this->connection->close();
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());	
		}
	}

	function init()
	{
		try
		{
			extract($this->config);
			$connection = new AMQPConnection($host, $port, $login, $password, $vhost); 

			$this->connection = $connection; 
			$this->channel    = $connection->channel();
			$this->channel->queue_declare($this->queue, false, false, false, false);
			$this->runing = true;
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
		}

//		$this->channel    = new AMQPChannel ($connection);
//		$this->exchange   = new AMQPExchange($this->channel);
//		$this->queue      = new AMQPQueue   ($this->channel);
	} 

	function send($data)
	{
		try
		{
			$msg = new AMQPMessage($data, array('delivery_mode' => 2));
			$this->channel->basic_publish($msg, '', $this->queue);
			return cy_dt(OK);
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
			return cy_dt(CYE_NET_ERROR, $e->getMessage());
		}
	}

	function recv($msg)
	{
		/* 切换logid. */
		cy_log_id_renew();		

		if(call_user_func($this->callback, $msg->body))
		{
			$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
		}

		/* 统计本次请求的时间 */
		cy_stat_flush();

		gc_collect_cycles();
	}

	function loop($callback, $nowait = false, $timeout = 0)
	{
		if(!$this->runing)
		{
			cy_exit(CYE_SYSTEM_ERROR);
		}

		try
		{
			$this->callback = $callback;
			$this->channel->basic_qos(null, 1, null);
			$this->channel->basic_consume($this->queue, '', false, false, false, false, [$this, 'recv']);

			do
			{
				$this->channel->wait(null, $nowait, $timeout);
			}
			while(!$nowait);
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
		}
	}
}

?>
