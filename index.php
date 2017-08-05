<?php
/**
 * Simple socket chat
 * Created by PhpStorm.
 * User: skipper
 * Date: 05.08.17
 * Time: 17:40
 */
require 'vendor/autoload.php';
$loop = \React\EventLoop\Factory::create();
$config = new \Chat\Utils\Config();
$pool = new \Chat\ConnectionPool($config);

$server = new React\Socket\Server(8000, $loop);

$server->on('connection', function (\React\Socket\ConnectionInterface $connection)use($pool, $config){
    $connection = new \Chat\Connection($connection, $config);
    $pool->add($connection);
});
$loop->addPeriodicTimer(3, function (){
    echo number_format(memory_get_usage(), 3) . 'Kb' . PHP_EOL;
});

$loop->run();