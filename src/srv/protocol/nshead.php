<?php

class CY_Srv_Protocol_NsHead
{
	protected $nshead;

	function __construct()
	{
		$this->nshead = new CY_Util_NsHead();
	}

	function run($client)
	{
		$errno   = 0;
		$display = 'json';

		$ret = $this->nshead->nshead_read($client);
		if(!$ret)
		{
			$errno = CYE_PARAM_ERROR;
			$error = 'nshead_read error';
			goto error;
		}

		if($ret['body_len'] == 0)
		{
			return array('errno' => CYE_DATA_EMPTY);
		}

		if(!($r = json_decode($ret['buf'], true)))
		{
			$errno = CYE_PARAM_ERROR;
			$error = 'request is not json';
			goto error;
		}

		if(!isset($r['module']) || !isset($r['id']))
		{
			$errno = CYE_PARAM_ERROR;
			$error = 'request has no module or id';
			goto error;
		}

		$options = isset($r['query']) ? $r['query'] : [];

		$_ENV['module'] = $module = $r['module'];
		$_ENV['id']     = $id     = $r['id']; 
		$_ENV['method'] = $method = isset($r['method'] ) ? $r['method']  : 'get'; 
		$_ENV['display']= $display= isset($r['display']) ? $r['display'] : 'json'; 
		$_ENV['version']= $version= isset($r['version']) ? $r['version'] : 1;

		$classname = 'CY_App_'.$version.'_'.$module;
		if(!method_exists($classname, $method))
		{
			$errno = CYE_PARAM_ERROR;
			$error = "method is not exists $classname:$method";
			goto error;
		}

		$eny = new $classname;
		$dt  = $eny->get($id, $options, $_ENV);
		unset($eny);

response:
		switch($display)
		{
		case 'json':
			$body = json_encode($dt);
			break;
		//case 'mcpack':
		//	$body = mc_pack_array2pack($dt);
			break;
		}

		$hdr  = array('body_len' => strlen($body));
		$this->nshead->nshead_write($client, $hdr, $body);
		return array('errno' => $errno);

error:
		$dt = array('errno' => $errno, 'error' => $error);
		cy_log(CYE_WARNING, "$client ".$error);
		goto response;
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
