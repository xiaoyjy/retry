<?php

class CY_Driver_Tair_Group extends CY_Driver_Tair_Base
{
	protected $name;

	function __construct($req, $options = [])
	{
		$this->name   = $req['name'];
		$this->global = self::$i_global;
		$this->global['code'] = [CY_TYPE_UINT32, 1002];
	}

	function inputs()
	{
		$size = strlen($this->name) + 1;
		$this->global['size'] = [CY_TYPE_UINT32, 8 + $size];
		$binary = cy_pack([
				"global"  => [CY_TYPE_OBJECT, $this->global], /* 16bytes. */
				"version" => [CY_TYPE_UINT32, 0],
				"length"  => [CY_TYPE_UINT32, $size],
				"name"    => [CY_TYPE_STRING, $this->name.chr(0)]
				]);

		return $binary;
	}

	function outputs($binary)
	{
		$c_clist = [
			[CY_TYPE_STRING, "key", 64  ],
			[CY_TYPE_STRING, "val", 1024],
			];

		$c_host  = [
			[CY_TYPE_UINT32, "host"],	
			[CY_TYPE_STRING, "port", 4],
				];

		$c_host1 = [
			[CY_TYPE_UINT32, "port"],
			[CY_TYPE_STRING, "host"],	
				];

		$config = [
			[CY_TYPE_OBJECT, 'global', self::$c_global],
			[CY_TYPE_UINT32, "bucket_count"],
			[CY_TYPE_UINT32, "copy_count"  ],
			[CY_TYPE_UINT32, "version"     ],
			[CY_TYPE_UINT32, "config_count"],
			[CY_TYPE_OBJECT, "config_list", $c_clist, "config_count"],

			[CY_TYPE_UINT32, "hash_size"],
			[CY_TYPE_STRING, "hash_data", "hash_size"],

			[CY_TYPE_UINT32, "server_count"],
			[CY_TYPE_OBJECT, "server_available", $c_host1, "server_count"]
				];

		$array = cy_unpack($binary, $config);
		if(empty($array['global']['flag']))
		{
			return cy_dt(-1, 'error respones');
		}

		$table = gzuncompress($array['hash_data']);
		$count = strlen($table)/8;
		$list  = cy_unpack($table, [[CY_TYPE_OBJECT, "list", $c_host, $count]]);
		$srvl  = [];
		foreach($list['list'] as $srv)
		{
			$port   = unpack("V", $srv['port']);
			$srvl[] = long2ip($srv['host']).':'.$port[1];
		}

		foreach($array['server_available'] as &$srv)
		{
			$host   = unpack("V", $srv['host']);
			$srv['host'] = long2ip($host[1]);
		} 
		unset($srv);
		
		$data  = [];
		$data['server_list']      = $srvl;
		$data['server_available'] = $array['server_available'];
		$data['config_list']      = $array['config_list'];
		return cy_dt(0, $data);
	}

}

?>
