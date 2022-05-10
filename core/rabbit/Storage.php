<?php

namespace Igrik\Vkr\Rabbit;

use Splav\API\Rabbit\Helper;
use Bitrix\Catalog\StoreTable;

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
		$storeId = Helper::getStoreIdBXmlId($message['external_id']);
		if($storeId == 0){
			$result = StoreTable::add(['XML_ID' => $message['external_id'], 'TITLE' => $message['name'], 'ADDRESS' => $message['address']]);
            if (!$result->isSuccess()) {
                $errors = $result->getErrors();
                foreach ($errors as $error) {
                    $this->addError(self::ERROR_CODE_BITRIX, $error->getMessage());
                }
            }
		}elseif ($storeId != 0 && !empty($storeId)){
			$store = StoreTable::getList(['filter' => ['ID' => $storeId], 'select' => ['TITLE', 'ADDRESS']])->fetchObject();
			if(!empty($message['name'])) {
				$store->setTitle($message['name']);
			}else{
				$this->addError(self::ERROR_CODE_EMPTY_TITLE_STORAGE);
			}
            if(empty($message['address']))
            {
                $message['address'] = $message['name'];
            }
            $store->setAddress($message['address']);
			$store->save();
		}else{
			$this->addError(self::ERROR_CODE_ELEMENT_NOT_FOUND, 'element');
		}
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