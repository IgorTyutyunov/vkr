<?php
require_once __DIR__ . "/../../../core/init.php";
$queue = 'storage';
\Igrik\Vkr\AMQP\Connector::runWorkConsumer($queue);