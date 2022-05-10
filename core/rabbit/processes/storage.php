<?php
/**
Процесс для обработки очереди "starage". В эту очередь попадают товары склады;
 */
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__) . '/../../../../../../'));

require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('splav.api.1c');

$queue = 'storage';
Splav\API\Rabbit\RabbitMQ::runWorkConsumer($queue);