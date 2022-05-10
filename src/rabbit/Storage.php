<?php

namespace Igrik\Vkr\Rabbit;


/**
 * Класс для изменения/создания складов
 *
 * Class Storage
 * @package Splav\API\Rabbit
 */
class Storage extends aMessageProcessing
{
    const QUEUE = 'storage';
    const ROUTING_KEY = 'storage';

	const REQUIRE_FIELDS_PRICE = [
		[
			'FIELD_CODE' => 'external_id',
			'ACCEPT_VALUES' => []
		],
		[
			'FIELD_CODE' => 'name',
			'ACCEPT_VALUES' => []
		]
	];

    /**
     * @inheritDoc
     */
    protected function checkRequireFields(): bool
    {
		$message = $this->getMessageData();
		if(!empty($message)){
			$this->checkRequireInArray($message, $this->getRequireFieldsConfigs());
		}else{
			$this->addError(self::ERROR_CODE_EMPTY_BODY);
		}
		if(empty($message['external_id'])){
			$this->addError(self::ERROR_CODE_EMPTY_EXTERNAL_ID);
		}

		return $this->getResult()->isSuccess();
    }

    /**
     * @inheritDoc
     */
    protected function getRequireFieldsConfigs(): array
    {
		return self::REQUIRE_FIELDS_PRICE;
    }

    protected function editMessage()
    {
        if(empty($this->message['address']))
        {
            $this->message['address'] = $this->message['name'];
        }
    }

    /**
     * @inheritDoc
     */
    protected function runProcessMessage()
    {
        $message = $this->getMessageData();
        var_dump($message);
    }

    /**
     * @inheritDoc
     */
    static function getRoutingKey(): string
    {
        return self::ROUTING_KEY;
    }

    /**
     * @inheritDoc
     */
    static function getQueueName(): string
    {
        return self::QUEUE;
    }
}