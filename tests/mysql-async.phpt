--TEST--
MySQL Parallel test

--FILE--
<?php

include dirname(__DIR__).'/src/init.php';

function my_callback($data)
{
	echo $data['requests']['sql'], "\n";
	return $data;
}

$c1 = new CY_Driver_DB_Default('', ['sql' => 'SELECT 1'], ['method' => 'sql', 'callback' => 'my_callback']);
$c2 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.1)'], ['method' => 'sql', 'callback' => 'my_callback']);
$c3 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.3)'], ['method' => 'sql', 'callback' => 'my_callback']);
$c4 = new CY_Driver_DB_Default('', ['sql' => 'SELECT sleep(0.5)'], ['method' => 'sql', 'callback' => 'my_callback']);

$db = new CY_Util_Mysql();
$db->add('c1', $c1);
$db->add('c2', $c2);
$db->add('c3', $c3);
$db->add('c4', $c4);

do
{
	$options = ['async' => true, 'cycle' => 0.1];
	$r  = $db->mGet($options);
}
while($r['running'] > 0);

?>
--EXPECT--
SELECT 1
SELECT sleep(0.1)
SELECT sleep(0.3)
SELECT sleep(0.5)
