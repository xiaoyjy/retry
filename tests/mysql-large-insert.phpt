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

$data = [];
for($i = 0; $i < 5000; $i++)
{
	$data[] = ['id' => $i, 'hotel' => 'Good hotel', 'movie' => 'Good movie'];	
}

$dbm = new CY_Model_Default('__test__poi__');
$r = $dbm->mSet($data, ['chunk_size' => 100]);

$r = $dbm->mGet(['id' => [4900,4999]]);
echo isset($r['data'][4999]), "\n";

?>
--EXPECT--
1
