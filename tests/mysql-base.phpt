--TEST--
MySQL base test

--FILE--
<?php

include dirname(__DIR__).'/src/init.php';

$db = new CY_Util_Mysql();

$sql = 'DROP TABLE IF EXISTS __test__poi__';
$r = $db->query($sql);
//print_r($r);

$sql = <<<SQL
CREATE TABLE `__test__poi__` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand` longblob,
  `hotel` longblob,
  `movie` longblob,
  `ctime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `mtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
SQL;

$r = $db->query($sql);
//print_r($r);

$data = ['id' => '1', 'hotel' => 'Good hotel'];
$r = $db->insert('__test__poi__', $data);

$data = ['id' => '1', 'hotel' => 'Good hotel', 'movie' => 'Good movie'];
$r = $db->insert('__test__poi__', $data);

$c = new CY_Driver_DB_Default('__test__poi__', ['data' => [$data]], ['method' => 'insert', 'update' => true]);
$db->add('c', $c);
$r = $db->mGet();
//print_r($r);

$sql = 'SELECT * FROM __test__poi__ WHERE `id`=1';
$r = $db->query($sql);
//print_r($r);
echo $r['data'][0]['movie']."\n";

$data = ['brand' => 'Great brand'];
$r = $db->update('__test__poi__', $data, 'id=1');


$c = new CY_Driver_DB_Default('__test__poi__', ['where' => ['id' => 1]], []);
$db->add('c', $c);
$r = $db->mGet();
echo $r['data']['c']['data'][1]['brand'], "\n";

$r = $db->query('desc __test__poi__');

$db->query('DROP TABLE __test__poi__');

?>
--EXPECT--
Good movie
Great brand

