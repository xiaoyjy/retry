--TEST--
Redis client test

--FILE--
<?php

include dirname(__DIR__).'/src/init.php';

$rd = new CY_Util_Redis();
$dt = $rd->mSet(['aaa' => '============', 'bbb' => '====+++']);


var_dump($dt);
var_dump($rd->mGet(['aaa' => 1, 'bbb' => 'bbb']));

var_dump($rd->delete(['aaa' => 1, 'bbb' => 'bbb']));

?>
--EXPECT--
array(2) {
  ["errno"]=>
  int(0)
  ["data"]=>
  array(2) {
    ["bbb"]=>
    bool(true)
    ["aaa"]=>
    bool(true)
  }
}
array(2) {
  ["errno"]=>
  int(0)
  ["data"]=>
  array(2) {
    [1]=>
    string(12) "============"
    ["bbb"]=>
    string(7) "====+++"
  }
}
array(2) {
  ["errno"]=>
  int(0)
  ["data"]=>
  int(2)
}
