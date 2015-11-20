--TEST--
hb - echo server tests

--FILE--
<?php
$fp = stream_socket_client("udp://127.0.0.1:20000", $errno, $errstr);

$d = [];
$d['name'] = 'houchangcunlu';
$d['size'] = 642498836;
$d['time'] = 1397968732;
$d['md5']  = '2ed2a9066dba34ae5023e8a46b4aa462';
$d['author'] = 'ian';
$d['bounds'] = '40.1,120.1;39.9,120.2';
$d['staff'] = 'ian@autonavi.com';
$s = json_encode($d);

$m = 'echo';
$c = 'get';
$id= 'unique-id';
$f = 'json';
$h = array
(
 [CY_TYPE_UINT8 , strlen($m)],
 [CY_TYPE_STRING, $m        ],
 [CY_TYPE_UINT8 , strlen($c)],
 [CY_TYPE_STRING, $c],
 [CY_TYPE_UINT8 , strlen($id)],
 [CY_TYPE_STRING, $id],
 [CY_TYPE_UINT8 , strlen($f)],
 [CY_TYPE_STRING, $f],
 [CY_TYPE_UINT16, 0],
);

$head = cy_pack($h);
$a = array
(
// [CY_TYPE_UINT16, 4+strlen($head)+strlen($s)],
 [CY_TYPE_UINT16, strlen($head)],
 [CY_TYPE_STRING, $head        ],
 [CY_TYPE_UINT16, strlen($s)   ],
 [CY_TYPE_STRING, $s           ]
 );



$c = array
(
 [CY_TYPE_UINT8 , 'module_len'],
 [CY_TYPE_STRING, 'module'     , 'module_len'],
 [CY_TYPE_UINT8 , 'method_len'],
 [CY_TYPE_STRING, 'method'     , 'method_len'],
 [CY_TYPE_UINT8 , 'id_len']    ,
 [CY_TYPE_STRING, 'id'         , 'id_len'],
 [CY_TYPE_UINT8 , 'fmt_len']   ,
 [CY_TYPE_STRING, 'fmt'        , 'fmt_len'],
 [CY_TYPE_UINT16, 'version']   ,
);


$d = array
(
 [CY_TYPE_UINT16, 'head_len'],
 [CY_TYPE_OBJECT, 'head', $c], 
 [CY_TYPE_UINT16, 'body_len'],
 [CY_TYPE_STRING, 'body'    ],
);


fwrite($fp, cy_pack($a));    
$bin = fread($fp, 1500);
fclose($fp);

var_dump(cy_unpack($bin, $d));

?>
--EXPECT--
array(4) {
  ["head_len"]=>
  int(26)
  ["head"]=>
  array(9) {
    ["module_len"]=>
    int(4)
    ["module"]=>
    string(4) "echo"
    ["method_len"]=>
    int(3)
    ["method"]=>
    string(3) "get"
    ["id_len"]=>
    int(9)
    ["id"]=>
    string(9) "unique-id"
    ["fmt_len"]=>
    int(4)
    ["fmt"]=>
    string(4) "json"
    ["version"]=>
    int(0)
  }
  ["body_len"]=>
  int(194)
  ["body"]=>
  string(194) "{"errno":0,"data":{"name":"houchangcunlu","size":642498836,"time":1397968732,"md5":"2ed2a9066dba34ae5023e8a46b4aa462","author":"ian","bounds":"40.1,120.1;39.9,120.2","staff":"ian@autonavi.com"}}"
}
