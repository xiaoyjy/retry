<?php

class CY_Driver_Http_Default implements CY_Driver_Impl
{
	protected $url = '';
	protected $method;
	protected $headers;
	protected $options;

	function __construct($url, $method = 'GET', $headers = array(), $options = array())
	{
		$this->url    = $url;
		$this->method = $method;
		$this->headers= $headers;
		$this->options= $options;
		$this->request= [];
	}

	function options()
	{
		return $this->options;
	}

	function inputs($opt)
	{
		$request = $this->request;
		$request['url']    = $this->url;
		$request['method'] = $this->method;
		$request['header'] = $this->headers;

///*
		if(isset($this->headers['data']))
		{
			$request['data'] = $this->headers['data'];
			unset($this->headers['data']);
		}
//*/

		return $request;
	}


	function outputs($data)
	{
		return $data;
	}

	function callback($data)
	{

	}
}

?>
