<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/core/init.php";

$keyData = ['city', 'RUS'];
$ttl = 3600;
$tag = 'city';

$cache = new \Igrik\Vkr\Cache($keyData, 50000, 'tag_1');
if(!$data = $cache->getCache())
{
    $data = [
        'Орёл', 'Брянск', 'Москва', 'Тула'
    ];
    $cache->setCache($data);
}
$cache = new \Igrik\Vkr\Cache([1], 50000, 'tag_1');
if(!$data = $cache->getCache())
{
    $data = [
        'Орёл', 'Брянск',
    ];
    $cache->setCache($data);
}

var_dump($cache->getCacheByTag('tag_1'));
//\Igrik\Vkr\AMQP\RabbitMQ::sendMessage(json_encode($data), 'storage');