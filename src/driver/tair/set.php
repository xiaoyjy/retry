<?php

class CY_Driver_Tair_Set extends CY_Driver_Tair_Base
{
	protected $key, $val;

	protected $area;
	protected $expired;

	function __construct($req, $options = [])
	{
		$this->key = $req['key'];
		$this->val = $req['val'];

		$this->area    = isset($options['area']   ) ? $options['area'   ] : 0;
		$this->expired = isset($options['expired']) ? $options['expired'] : 0;

		$this->global = self::$i_global;
		$this->global['code'] = [CY_TYPE_UINT32, 1];
	}

	function inputs()
	{
		$key    = self::$i_meta;
		$val    = self::$i_meta;
		$key['size'] = [CY_TYPE_UINT32, strlen($this->key)];
		$key['body'] = [CY_TYPE_STRING, $this->key];

		$val['size'] = [CY_TYPE_UINT32, strlen($this->val)];
		$val['body'] = [CY_TYPE_STRING, $this->val];

		$this->global['size'] = [CY_TYPE_UINT32, 89 /* sizeof(global)/16bytes + sizeof(header)/9bytes + sizeof(meta)*2 */ +
			strlen($this->key) + strlen($this->val)];

		return cy_pack([
				"global"  => [CY_TYPE_OBJECT, $this->global], /* 16bytes. */

				/* 9 bytes */
				"flag"    => [CY_TYPE_UINT8 , 0],
				"area"    => [CY_TYPE_UINT16, $this->area],
				"version" => [CY_TYPE_UINT16, 0],
				"expired" => [CY_TYPE_UINT32, $this->expired],

				/* 80 bytes + strlen($this->key) + strlen($this->val) */
				"key"    => [CY_TYPE_OBJECT, $key],
				"value"  => [CY_TYPE_OBJECT, $val],
				]);
	}

	function outputs($binary)
	{
		$config = [
			[CY_TYPE_OBJECT, 'global', self::$c_global],
			[CY_TYPE_UINT32, "version"],
			[CY_TYPE_UINT32, "errno"],
			[CY_TYPE_STRING, "msg"],
			];

		return cy_unpack($binary, $config);
	}

}

?>
