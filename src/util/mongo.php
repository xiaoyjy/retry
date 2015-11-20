<?php

class CY_Util_Mongo
{
	protected static $instance;
	protected static $mongo = null;
	protected static $mongos;

	protected $c;   

	function __construct()
	{
		self::$mongo = $this->connect(); 
	}

	public function connect()
	{
		$opt = [];
		$opt['connectTimeoutMS'] = $_ENV['config']['timeout']['mongo_connect'] * 1000;
		$opt['socketTimeoutMS' ] = $_ENV['config']['timeout']['mongo_read'   ] * 1000;
		$opt['connect']          = false;
		$config = $_ENV['config']['mongo_ming'];
		$i = array_rand($config);
		$c = $config[$i];
		$this->c = $c;

		$try_times = count($config) - 1;
		do 
		{ 
			try
			{
				$conn =  new MongoClient($c['uri'], $opt);
				return $conn;
			}
			catch(Exception $e)
			{
				$c = $config[$try_times];
				cy_log(CYE_ERROR, $e->getMessage());
			}
		}while($try_times-- > 0);

		return null;
	}


	function __destruct()
	{
		try
		{
			self::$mongo->close();
		}
		catch(Exception $e)
		{
			cy_log(CYE_ERROR, $e->getMessage());
		}
	}

	public static function getInstance()
	{
		return  new self();
	}

	function mSet($data, $options = [])
	{
		if(empty($opt['update']))
		{
			return $this->mInsert($data, $options);
		}

		$array = [];
		foreach($data as $row)
		{
			$dt = $this->save($row, $options);
			if($dt['errno'] !== 0)
			{
				foreach($dt['data'] as $k => $v)
				{
					$array[$k] = $v;    
				}
			}
		}

		return cy_dt(0, $array);
	}

	function __call($method, $args)
	{
		$data = [];
		$call = ['mGet' => 'find', 'save' => 'save', 'mInsert' => 'batchInsert', 'delete' => 'remove', 'update' => 'update'];
		$getid= ['mGet' => false , 'save' => 'save', 'mInsert' => true         , 'delete' => false   , 'update' => true];
		if(empty($call[$method]))
		{
			return cy_dt(-1, 'unkown method.');
		}

		$t1 = microtime(true);

		$table  = array_shift($args);
		$dbname = $this->c['database'];

		try
		{
			if(!self::$mongo)
			{
				self::$mongo = $this->connect(); 
			}

			$db   = self::$mongo->$dbname;

			$c10n = $table == 'file' ? $db->getGridFS() : $db->$table;
			$back = call_user_func_array([$c10n, $call[$method]], $args);
			if(is_object($back))
			{
				$back = iterator_to_array($back);
			}

			else if($getid[$method] && $back)
			{
				$list = array();
				foreach($args as $k => $v)
				{
					$list[$k] = (string)$v['_id'];
				}

				$back = $list;
			}

			$data = cy_dt(0, $back);
			//self::$mongo->close();
		}
		catch(MongoCursorException $e)
		{
			$error= substr($e->getMessage(), 0, 512);
			cy_log(CYE_ERROR, "mongo-$method ".$error);
			$data = cy_dt(CYE_SYSTEM_ERROR, $error);
		}
		catch(MongoCursorTimeoutException $e)
		{
			$error= substr($e->getMessage(), 0, 512);
			cy_log(CYE_ERROR, "mongo-$method ".$error);
			cy_log(CYE_ERROR, "mongo-$method close=%d, reconnect=%d", self::$mongo->close(), self::$mongo->connect);
			$data = cy_dt(CYE_SYSTEM_ERROR, $error);
		}
		catch(MongoConnectionException $e)
		{
			$error= substr($e->getMessage(), 0, 512);
			cy_log(CYE_ERROR, "mongo-$method ".$error);
			$data = cy_dt(CYE_SYSTEM_ERROR, $error);
		}
		catch(Exception $e)
		{
			$error= substr($e->getMessage(), 0, 512);
			cy_log(CYE_ERROR, "mongo-$method ".$error);
			$data = cy_dt(CYE_SYSTEM_ERROR, $error);
		}

		// end process.
		$cost = (microtime(true) - $t1)*1000000;
		cy_stat('mongo-'.$method, $cost);
		return $data;
	}

}


?>
