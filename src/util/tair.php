<?php

class CY_Util_Tair
{
	protected $chid = 0;
	protected $net;

	function __construct()
	{	
		$this->net = new CY_Util_Net();
	}

	function server()
	{
		if(empty($_ENV['config']['tair']))
		{
			return '127.0.0.1:5198';
		}


		$srv = $_ENV['config']['tair'];
		$i   = array_rand($srv);
		return $srv[$i];
	}

	function __call($method, $data)
	{
		$method = 'CY_Driver_Tair_'.$method;

		$req = $data[0];
		$opt = isset($data[1]) ? $data[1] : [];
		$opt['chid'] = ++$this->chid;
		$c   = new $method($req, $opt);
		$req = ['body' => $c->inputs($this->net), 'server' => $this->server()];
		if(!$this->net->prepare($this->chid, $req))
		{
			return cy_dt(CYE_PARAM_ERROR);
		}

		$dt = $this->net->get();
		if($dt['errno'] !== 0)
		{
			return $dt;
		}

		$dc = $dt['data'][$this->chid];
		if($dc['errno'] !== 0)
		{
			return $dc;
		}

		return $c->outputs($dc['data']);
	}

}

?>
