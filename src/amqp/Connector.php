<?php
namespace Igrik\Vkr\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

/**
 * Класс для работы с RabbitMQ (получение сообщений, создание exchange, очередей, роутинга, формирование подключения).
 * Тут же будет метод, который будет запускаться в процессе для чтения сообщений из очереди.
 *
 * Class RabbitMQ
 * @package Splav\API\AMQP
 */
class Connector
{
    const MAX_USAGE_MEMORY = 128000000;

    /**
     * Метод возвращает массив соответствий ключей маршрутизации сообщений и классов для обработки сообщений
     *
     * @return array
     */
    private static function getMappingRoutingKeyClass():array
    {
        return [
            Storage::ROUTING_KEY => Storage::class,
        ];
    }

    private static function getExchange():string
    {
        return "vkr";
    }

    /**
     * Метод отправки сообщения в RabbitMQ
     */
    public static function sendMessage($message, $routingKey)
    {
        self::initRabbitConfig();
        $exchange = self::getExchange();

        $channel = self::getChannel();
        $message = new AMQPMessage($message, array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange, $routingKey);
    }

    /**
     * Метод запускает Consumer для прослушивания очереди $queue
     * @param string $queue - название очереди, которую нужно прослушивать
     */
    public static function runWorkConsumer(string $queue)
    {
        self::initRabbitConfig();

        /** @var \PhpAmqpLib\Channel\AMQPChannel $channel */
        $channel = self::getChannel();

        $channel->basic_consume($queue, '', false, false, false, false,
            '\Igrik\Vkr\AMQP\Connector::process_message_callback');
        $timeout = 0;
        while ($channel->is_consuming()) {
            $channel->wait(null, false, $timeout);
        }
    }

    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public static function process_message_callback(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        self::processMessage($message->getRoutingKey(),$message->getBody());
        $message->ack();
    }


    /**
     * Метод для обработки сообщения
     * @param string $routing_key
     * @param string $message
     * @return array
     */
    private static function processMessage(string $routing_key, string $message):array
    {
        $arResult = ['success' => false];
        /**
         * @var $obj aMessageProcessing
         */
        $className = self::getClassNameByRoutingKey($routing_key);
        if(!empty($className))
        {
            $obj = new $className($message);
            $obj->runWork();
            $arResult = ['success' => $obj->getResult()->isSuccess(), 'errors' => $obj->getResult()->getErrorMessages()];
        }
        else
        {
            $arResult['errors'][] = 'Некорректный ключ маршрутизации:' . $routing_key;
        }

        return $arResult;
    }

    /**
     * @return string Метод возвращает название класса для обработки сообщения
     */
    private static function getClassNameByRoutingKey($routingKey):string
    {
        if(isset(self::getMappingRoutingKeyClass()[$routingKey]))
        {
            return self::getMappingRoutingKeyClass()[$routingKey];
        }

        return '';
    }

    /**
     * Метод создания подключения с брокером сообщений
     * @return AMQPChannel
     */
    private static function getChannel():AMQPChannel
    {
        $host = 'localhost';
        $user = 'guest';
        $password = 'guest';
        $vhost = '/';
        $port = 5672;
        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);

        $shutdown = function($connection, $channel)
        {
            if(!is_null($connection)){
                $connection->close();
            }
            if(!is_null($channel)){
                $channel->close();
            }
        };
        register_shutdown_function($shutdown, $connection, $connection->channel());
        return $connection->channel();
    }

    /**
     * Метод создания инфраструктуры в брокере сообщений
     */
    private static function initRabbitConfig()
    {
        $channel = self::getChannel();
        $exchange = self::getExchange();
        if(!empty($exchange))
        {
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

            /**
             * @var aMessageProcessing $CLASS
             */
            $arRk = self::getMappingRoutingKeyClass();
            foreach ($arRk as $CLASS)
            {
                $queue = $CLASS::getQueueName();
                $routingKey = $CLASS::getRoutingKey();

                if(!empty($queue) && !empty($routingKey))
                {
                    $channel->queue_declare($queue, false, true, false, false);
                    $channel->queue_bind($queue, $exchange, $routingKey);
                }
            }
            $channel->getConnection()->close();
            $channel->close();
        }

    }

    /**
     * Метод для тестирования обработки сообщений через HTTP зппрос
     * @return Response
     */
    public static function rabbitTest(Request $request, Response $response, $args)
    {
        $routing_key = $request->getQueryParams();

        $message = $request->getBody();

        $result = self::processMessage($routing_key['routing_key'], $message);

        $response->getBody()->write(json_encode($result,JSON_UNESCAPED_UNICODE));

        return $response;
    }

}
