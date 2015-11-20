<?php

class CY_Driver_Tair_Get extends CY_Driver_Tair_Base
{
	protected $key;
	protected $area;

	function __construct($req, $options = [])
	{
		$this->key = $req['key'];
		$this->area= isset($options['area']) ? $options['area'] : 0;

		$this->global = self::$i_global;
		$this->global['code'] = [CY_TYPE_UINT32, 2];
		//$this->expired = isset($options['expired']) ? $options['expired'] : 0;
	}

	function inputs()
	{
		$key    = self::$i_meta;
		$key['size'] = [CY_TYPE_UINT32, strlen($this->key)];
		$key['body'] = [CY_TYPE_STRING, $this->key];

		$this->global['size'] = [CY_TYPE_UINT32, 47 /* sizeof(global)/16bytes + sizeof(header)/7bytes + sizeof(meta) */ +
			strlen($this->key)];

		return cy_pack([
			"global"  => [CY_TYPE_OBJECT, $this->global], /* 16bytes. */

			/* 7 bytes */
			"flag"    => [CY_TYPE_UINT8 , 0],
			"area"    => [CY_TYPE_UINT16, $this->area],
			"count"   => [CY_TYPE_UINT32, 1],

			/* 40 bytes + strlen($this->key) */
			"key"     => [CY_TYPE_OBJECT, $key]
			]);
	}

	function outputs($binary)
	{
		$c_data = [
			[CY_TYPE_OBJECT, 'meta', self::$c_meta],
			[CY_TYPE_UINT32, 'size'],
			[CY_TYPE_STRING, 'data', 'size']
				];

		$c_pair = [
			[CY_TYPE_OBJECT, 'key', $c_data],
			[CY_TYPE_OBJECT, 'val', $c_data]
				];

		$c_lists = [
			[CY_TYPE_UINT32, "count"],
			[CY_TYPE_OBJECT, 'list'  , $c_pair, 'count']
				];

		$config = [
			[CY_TYPE_OBJECT, 'global', self::$c_global],
			[CY_TYPE_UINT32, "version"],
			[CY_TYPE_COND32, NULL, [0 => $c_lists, CY_COMMON_COND => NULL]]
				];

		$dt = cy_unpack($binary, $config);
		if($dt['errno'] > 0)
		{
			$dt['errno'] -= 4294967296; 
		}

		return $dt;
	}

}

?>
