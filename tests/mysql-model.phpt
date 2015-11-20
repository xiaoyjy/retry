--TEST--
MySQL Model tests

--FILE--
<?php

include dirname(__DIR__).'/src/init.php';

$db  = new CY_Util_Mysql();
$sql = 'DROP TABLE IF EXISTS __test__1__';
$r   = $db->query($sql);

$sql = 'DROP TABLE IF EXISTS __test__2__';
$r   = $db->query($sql);


$sql = <<<SQL
CREATE TABLE `__test__1__` (
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


$sql = <<<SQL
CREATE TABLE `__test__2__` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_1` int(11) NOT NULL default 0,
  `content` longblob,
  `ctime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `mtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
SQL;

$r = $db->query($sql);

$data = ['id' => '1', 'hotel' => 'Good hotel'];
$r    = $db->insert('__test__1__', $data);

$data = ['id' => '2', 'hotel' => 'hotel1', 'movie' => 'movie1'];
$r    = $db->insert('__test__1__', $data);


$data = ['id' => '1', 'id_1' => '2', 'content' => 'hello'];
$r    = $db->insert('__test__2__', $data);


$model = new CY_Model_Default('`__test__1__` a LEFT JOIN `__test__2__` b ON a.id=b.id_1');
$r     = $model->mGet(['a.id' => [1, 2]], ['key' => 'aid', 'which' => 'a.id aid, b.id bid, content']);
print_r($r);


//$db->query('DROP TABLE __test__1__');
//$db->query('DROP TABLE __test__2__');

?>
--EXPECT--
Array
(
    [errno] => 0
    [data] => Array
        (
            [1] => Array
                (
                    [aid] => 1
                    [bid] => 
                    [content] => 
                )

            [2] => Array
                (
                    [aid] => 2
                    [bid] => 1
                    [content] => hello
                )

        )

)
