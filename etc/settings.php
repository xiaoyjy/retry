<?php

/***/
$_ENV['config']['log'] = array
(
 'path'  => CY_HOME.'/log',
 'name'  => 'example',
 'level' => 8,
 'fflush' => true 
);

$_ENV['config']['stat_max_line'] = 256;

$_ENV['config']['xhprof_enable'] = 0;

$_ENV['config']['timeout'] = array
(
 'redis_connect' => 0.2,
 'redis_read' => 0.4,

 'mysql_connect' => 1,
 'mysql_read' => 1,

 'http_connect' => 1,
 'http_read' => 1,

 'net_default' => 0.6,

 'mongo_connect' => 1,
 'mongo_read' => 1,
);

$_ENV['config']['chunk'] = array
(
 'redis' => 50
);


$_ENV['config']['default_moudle'] = 'default';
?>
