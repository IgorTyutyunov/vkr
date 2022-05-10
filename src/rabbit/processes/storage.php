<?php
require_once __DIR__ . "/../../../core/init.php";
$queue = 'storage';
\Igrik\Vkr\Rabbit\RabbitMQ::runWorkConsumer($queue);