<?php
namespace Igrik\Vkr\Rabbit;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Splav\API\Rabbit\Helper;
use Bitrix\Main\Config\Option;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

/**
 * Класс для работы с RabbitMQ (получение сообщений, создание exchange, очередей, роутинга, формирование подключения).
 * Тут же будет метод, который будет запускаться в процессе для чтения сообщений из очереди.
 *
 * Class RabbitMQ
 * @package Splav\API\Rabbit
 */
class RabbitMQ
{
    const MAX_USAGE_MEMORY = 128000000;

    /**
     * Соответствие ключей маршрутизации и классов для обработки сообщений
     */
    const MAPPING_RK_CLASS = [
        Storage::ROUTING_KEY => Storage::class,
    ];

    /**
     * Метод отправки сообщения в RabbitMQ
     */
    public static function sendMessage($message, $routingKey)
    {
        $exchange = Option::get('splav.api.1c','rabbitmq_exchange');

        $channel = RabbitMQ::getChannel();
        $message = new AMQPMessage($message, array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange, $routingKey);
    }

    /**
     * Метод запускает Consumer для прослушивания очереди $queue
     * @param string $queue - название очереди, которую нужно прослушивать
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function runWorkConsumer(string $queue)
    {
        self::initRabbitConfig();

        /** @var PhpAmqpLib\Channel\AMQPChannel $channel */
        $channel = self::getChannel();

        $channel->basic_consume($queue, 'splav_site', false, false, false, false, 'Splav\API\Rabbit\RabbitMQ::process_message_callback');
        $timeout = 0;
        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, $timeout);
            } catch (\Exception $e) {
                if (!is_null($channel)) {
                    $channel->close();
                }
            }
            if (memory_get_usage(true) > self::MAX_USAGE_MEMORY) {
                break;
            }
        }
    }

    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public static function process_message_callback(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        self::process_message($message->getRoutingKey(),$message->getBody());
        $message->ack();
    }


    /**
     * Метод для обработки сообщения
     * @param string $routing_key
     * @param string $message
     * @return array
     */
    private static function process_message(string $routing_key, string $message):array
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

        if($arResult['success'] !== true && Option::get('splav.api.1c', "rabbitmq_is_send_mail_error") == 'Y')
        {
            $mails = Option::get('splav.api.1c', "rabbitmq_list_mail_error");
            if(!empty(Option::get('splav.api.1c', "rabbitmq_list_mail_error")))
            {
                $template = "Ключ маршрутизации: " . $routing_key . "\n";
                $template .= "Сообщение: " . $message . "\n";
                $template .= "Ошибки: " . json_encode($arResult, JSON_UNESCAPED_UNICODE) . "\n";

                mail($mails, 'Ошибка при обработке сообщения', $template);
            }
        }

        return $arResult;
    }

    /**
     * @return string Метод возвращает название класса для обработки сообщения
     */
    private static function getClassNameByRoutingKey($routingKey):string
    {
        if(isset(self::MAPPING_RK_CLASS[$routingKey]))
        {
            return self::MAPPING_RK_CLASS[$routingKey];
        }

        return '';
    }

    /**
     * Метод возвращает объект AMQPChannel
     * @return AMQPChannel
     */
    private static function getChannel():AMQPChannel
    {
        $host = Option::get('splav.api.1c','rabbitmq_host');
        $user = Option::get('splav.api.1c','rabbitmq_login');
        $password = Option::get('splav.api.1c','rabbitmq_password');
        $vhost = 'splav';
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
     * Метод создания инфраструктуры в Rabbit(exchange, очереди, роутинг)
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    private static function initRabbitConfig()
    {
        $channel = self::getChannel();
        $exchange = Option::get('splav.api.1c','rabbitmq_exchange');
        if(!empty($exchange))
        {
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

            /**
             * @var aMessageProcessing $CLASS
             */
            foreach (self::MAPPING_RK_CLASS as $CLASS)
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

        $result = self::process_message($routing_key['routing_key'], $message);

        $response->getBody()->write(json_encode($result,JSON_UNESCAPED_UNICODE));

        return $response;
    }

}
