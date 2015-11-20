<?php

define("PACKAGE_FLAG", 0x6D426454); // mBdT

abstract class CY_Driver_Tair_Base
{
	/* 40bytes + body */
	static protected $i_meta = [
		"merged"   => [CY_TYPE_UINT8 , 0],
		"area"     => [CY_TYPE_UINT32, 0],
		"flag1"    => [CY_TYPE_UINT16, 0],

		/* meta data start, 29 bytes. */
		"magic"    => [CY_TYPE_UINT16, 0],
		"checksum" => [CY_TYPE_UINT16, 0],
		"keysize"  => [CY_TYPE_UINT16, 0],
		"version"  => [CY_TYPE_UINT16, 0],
		"prefixsize" => [CY_TYPE_UINT32, 0],
		"valsize"  => [CY_TYPE_UINT32, 0],
		"flag2"    => [CY_TYPE_UINT8, 0],
		"cdate"    => [CY_TYPE_UINT32, 0],
		"mdate"    => [CY_TYPE_UINT32, 0],
		"edate"    => [CY_TYPE_UINT32, 0],
		/* meta_data end */

		"size"   => [CY_TYPE_UINT32, 0],
		"body"   => [CY_TYPE_STRING, ""]
			];

	/* 16 bytes */
	static protected $i_global = [
		"flag" => [CY_TYPE_UINT32, PACKAGE_FLAG],
		"chid" => [CY_TYPE_UINT32, 1],
		"code" => [CY_TYPE_UINT32, 0 /* PCODE */],
		"size" => [CY_TYPE_UINT32, 0] /* Body Length of the request */
			];


	static protected $c_meta = [
		/* 7bytes. */
		[CY_TYPE_UINT8 , "merged"  ],
		[CY_TYPE_UINT32, "area"    ],
		[CY_TYPE_UINT16, "flag1"   ],

		/* meta data start, 29 bytes. */
		[CY_TYPE_UINT16, "magic"   ],
		[CY_TYPE_UINT16, "checksum"],
		[CY_TYPE_UINT16, "keysize" ],
		[CY_TYPE_UINT16, "version"],
		[CY_TYPE_UINT32, "prefixsize"],
		[CY_TYPE_UINT32, "valsize"],
		[CY_TYPE_UINT8 , "flag2"],
		[CY_TYPE_UINT32, "cdate"],
		[CY_TYPE_UINT32, "mdate"],
		[CY_TYPE_UINT32, "edate"],
		/* meta_data end */

		//[CY_TYPE_UINT32, "size"],
		//[CY_TYPE_STRING, "body", "size"]
			];

	static protected $c_global = [
		[CY_TYPE_UINT32, "flag"],
		[CY_TYPE_UINT32, "chid"],
		[CY_TYPE_UINT32, "code" /* PCODE */],
		[CY_TYPE_UINT32, "size"] /* Body Length of the request */
			];

}

?>
