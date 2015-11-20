<?php
/*
 * I hate exception, hate so much..
 *
 */

class CY_Util_mQueue
{
	protected $config = NULL;

	protected $callback;

	protected $connection;
	protected $channel;
	protected $queue;
	protected $exchange;

	protected $queue_name;

	function __construct($config = NULL)
	{
		if(!$config && isset($_ENV['config']['mq']))
		{
			$c = $_ENV['config']['mq'];
			$i = array_rand($c);
			$config = $c[$i];
		}

		$config || $config = ['host' => 'localhost', 'port' => 5672, 'login' => 'guest', 'password' => 'guest', 'vhost' => '/'];
		$this->config      = $config;
	}

	function init($queue_name, $exchange_name = NULL)
	{
		if(!$exchange_name)
		{
 			$exchange_name = $_ENV['config']['project_name'];
		}

		try
		{
			$connection = new AMQPConnection($this->config);
			//$connection->setReadTimeout ($_ENV['config']['timeout']['amqp_read'] );
			//$connection->setWriteTimeout($_ENV['config']['timeout']['amqp_write']);
			if(!$connection->connect())
			{
				cy_log(CYE_ERROR, "AMQPConnection::connect, Cannot connect to the broker");
				return false;
			}


			$this->connection = $connection;
			$this->channel = new AMQPChannel ($connection   );
			//$this->channel->qos(0, 7);

			$this->exchange= new AMQPExchange($this->channel);
			$this->queue   = new AMQPQueue   ($this->channel);

			$this->exchange->setName ($exchange_name     );
			$this->exchange->setType (AMQP_EX_TYPE_DIRECT);
			$this->exchange->setFlags(AMQP_DURABLE       );
			$this->exchange->declareExchange();

			$this->queue->setFlags(AMQP_NOPARAM);
			$this->queue->setFlags(AMQP_DURABLE);
			$this->queue->setName ($queue_name );
			$this->queue->declareQueue();
			$this->queue->setFlags(AMQP_DURABLE|AMQP_PASSIVE);
			$this->queue->bind($exchange_name, $queue_name);
			$this->queue->setArgument('delivery_mode', 2);
			$this->queue_name = $queue_name;
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
			return false;
		}

		return true;
	}

	function send($data)
	{
		try
		{
			if(is_array($data))
			{
				$data = json_encode($data);
			}

			$r = $this->exchange->publish($data, $this->queue_name, AMQP_NOPARAM, ['delivery_mode' => 2]);
			return cy_dt(0, $r);
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
			return cy_dt(CYE_NET_ERROR, $e->getMessage());
		}
	}

	function get($ack = false)
	{
		try
		{
			$flag= $ack ? AMQP_AUTOACK : AMQP_NOPARAM;
			$msg = $this->queue->get($flag);
			if(!$msg)
			{
				return cy_dt(0);
			}

			return cy_dt(0, ['body' => $msg->getBody(), 'tag' => $msg->getDeliveryTag()]);
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
		}

		return cy_dt(CYE_SYSTEM_ERROR);
	}

	function ack($tag)
	{
		return $this->queue->ack($tag);
	}

	function count()
	{
		return $this->queue->declareQueue();
	}

	function clean()
	{
		return $this->queue->purge();
	}

	function drop()
	{
		return $this->queue->delete();
	}

	function recv($envelope)
	{
		/* 切换logid. */
		cy_log_id_renew();
		if(call_user_func($this->callback, $envelope->getBody()))
		{
			$this->queue->ack($envelope->getDeliveryTag());
		}
		else
		{
			$this->queue->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
		}

		/* 统计本次请求的时间 */
		cy_stat_flush();

		gc_collect_cycles();
	}

	function loop($callback)
	{
		while(true)
		{
			try
			{
				$this->callback = $callback;
				$this->queue->consume([$this, 'recv']);
			}
			catch(Exception $e)
			{
				cy_log(CYE_DEBUG, 'timeout or no new data.');
				$this->connection->reconnect();
			}
		}
	}
}

?>
