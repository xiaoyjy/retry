--TEST--
MySQL Parallel test

--FILE--
<?php

include dirname(__DIR__).'/src/init.php';

$c1 = new CY_Driver_DB_Default('', ['sql' => 'SELECT 1'], ['method' => 'sql']);
$c2 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.5)'], ['method' => 'sql']);
$c3 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.5)'], ['method' => 'sql']);
$c4 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.5)'], ['method' => 'sql']);

$t1 = microtime(true);

$db = new CY_Util_Mysql();
$db->add('c1', $c1);
$db->add('c2', $c2);
$db->add('c3', $c3);
$db->add('c4', $c4);
$r  = $db->mGet();

$t2 = microtime(true);

$n = $t2 - $t1;

echo (0.5 < $n && $n < 1.0), "\n";

?>
--EXPECT--
1

