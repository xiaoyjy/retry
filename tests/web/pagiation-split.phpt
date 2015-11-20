--TEST--
Pagiation split test

--FILE--
<?php

include dirname(dirname(__DIR__)).'/src/init.php';

$a = new CY_Web_Pagiation();
print_r($a->split(5, 100, 10));
print_r($a->split(1, 11, 10));
print_r($a->split(3, 11, 10));
print_r($a->split(20, 1, 10));

?>
--EXPECT--
Array
(
    [prev] => 4
    [next] => 6
    [pages] => Array
        (
            [0] => 3
            [1] => 4
            [2] => 5
            [3] => 6
            [4] => 7
        )

    [current] => 5
)
Array
(
    [prev] => 1
    [next] => 2
    [pages] => Array
        (
            [0] => 1
            [1] => 2
        )

    [current] => 1
)
Array
(
    [prev] => 1
    [next] => 2
    [pages] => Array
        (
            [0] => 1
            [1] => 2
        )

    [current] => 1
)
Array
(
    [prev] => 1
    [next] => 1
    [pages] => Array
        (
            [0] => 1
        )

    [current] => 1
)
