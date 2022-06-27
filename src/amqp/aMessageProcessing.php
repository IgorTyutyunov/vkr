<?php

namespace Igrik\Vkr\AMQP;

use Igrik\Vkr\Result;

abstract class aMessageProcessing
{
    /**
     * Код ошибки - пустое сообщение
     */
    const ERROR_CODE_EMPTY_BODY = "EMPTY_BODY";

    /**
     * Код ошибки -
     */
    const ERROR_CODE_NOT_ACCEPT_VALUE = "NOT_ACCEPT_VALUE";

    /**
     * Код ошибки - не передан обязательный параметр
     */
    const ERROR_CODE_NOT_ISSET_REQUIRE_FIELD = "NOT_ISSET_REQUIRE_FIELD";

    /**
     * Код ошибки - элемент не найден
     */
    const ERROR_CODE_ELEMENT_NOT_FOUND = "ELEMENT_NOT_FOUND";

    /**
     * Код ошибки - прочие ошибки, для которых не хочется создавать что-то отдельное
     */
    const ERROR_CODE_OTHER_ERROR = "OTHER_ERROR";

    /**
     * Код ошибки - какая-то ошибка, которую выдал битрикс
     */
    const ERROR_CODE_BITRIX = "BTRX_ELEMENT_NOT_FOUND";

	/**
	 * Код ошибки - если пустое название склада
	*/
	const ERROR_CODE_EMPTY_TITLE_STORAGE = "EMPTY_TITLE_STORAGE";

	/**
	 * Код ошибки - если пусто значение внешнего кода
	*/
	const ERROR_CODE_EMPTY_EXTERNAL_ID = "EMPTY_EXTERNAL_ID";
    /**
     * Массив с описанием ошибок
     */
    const ERROR_CODE = [
        self::ERROR_CODE_EMPTY_BODY => [
            "MESSAGE" => "Сообщение пустое"
        ],
        self::ERROR_CODE_NOT_ISSET_REQUIRE_FIELD => [
            "MESSAGE" => "Не передан обязательный параметр:"
        ],
        self::ERROR_CODE_NOT_ACCEPT_VALUE => [
            "MESSAGE" => "Передано недопустимое значение:"
        ],
        self::ERROR_CODE_ELEMENT_NOT_FOUND => [
            "MESSAGE" => "Элемент не найден:"
        ],
        self::ERROR_CODE_OTHER_ERROR => [
            "MESSAGE" => ""
        ],
        self::ERROR_CODE_BITRIX => [
            "MESSAGE" => "Ошибка Битрикса:"
        ],
		self::ERROR_CODE_EMPTY_TITLE_STORAGE => [
			"MESSAGE" => "Пустое поле «Название склада»"
		],
		self::ERROR_CODE_EMPTY_EXTERNAL_ID => [
			"MESSAGE" => "Пустое поле «Внешний код»"
		]

    ];

    protected Result $result;
    protected array $message;

    /**
     * @param string $message - сообщение, полученное из очереди
     */
    final function __construct(string $message)
    {
        $this->setMessageData($message);
        $this->initResult();
    }

    /**
     * @param string $message - сообщение, переданное в конструкторе.
     */
    private final function setMessageData(string $message)
    {
        $this->message = (array)json_decode($message, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array
     */
    protected final function getMessageData(): array
    {
        return $this->message;
    }

    /**
     * Метод возвращает экзеспляр класса \Bitrix\Main\Result. Будет использовать
     * \Bitrix\Main\Result будет использоваться для сбора ошибок и проверки на успешность обработки сообщения
     * @return Result
     */
    private final function initResult()
    {

        $this->result = new Result();
        return $this->result;
    }

    /**
     * Метод возвращает объект \Bitrix\Main\Result созданный методом $this->initResult()
     * @return Result
     */
    public final function getResult(): Result
    {
        return $this->result;
    }

    /**
     *
     * Метод добавляет ошибку в экземпляр класса \Bitrix\Main\Result.
     * Экземпляр класса \Bitrix\Main\Result создаётся с помощью метода initResult
     *
     * @param string $errorCode - код ошибки
     * @param string $addMessage - дополнительный текст к сообщению об ошибке.
     * Например, можно передать код поля, из-за которого возникла ошибка.
     */
    protected final function addError(string $errorCode, string $addMessage = '')
    {
        $messageError = $this->getMessageErrorByMessageCode($errorCode) . $addMessage;
        $this->result->addError($messageError, $errorCode);
    }

    /**
     * Метод возвращает текст ошибки по символьному коду.
     * @param string $errorCode
     * @return string
     */
    protected final function getMessageErrorByMessageCode(string $errorCode = ''): string
    {
        if (isset(self::ERROR_CODE[$errorCode])) {
            return self::ERROR_CODE[$errorCode]['MESSAGE'];
        }

        return 'Неизвестная ошибка';
    }

    /**
     * Метод проверяет наличие обязательных полей в массиве $array
     *
     * @param array $array - массив, в котором нужно проверить наличие обязательных полей
     * @param array $requireFields - массив обязательных полей в формате
     * [
     * [
     * 'FIELD_CODE' => 'only_changes',//код поля
     * 'ACCEPT_VALUES' => [true, false]//допустимые значения поля FIELD_CODE
     * ],-//-
     * ]
     * @param string $addText - дополнительный текст к сообщению об ошибке.
     */
    protected final function checkRequireInArray(array $array, array $requireFields, string $addText = '')
    {
        foreach ($requireFields as $field) {
            $fieldCode = $field['FIELD_CODE'];
            if (!isset($array[$fieldCode])) {
                $this->addError(self::ERROR_CODE_NOT_ISSET_REQUIRE_FIELD, $fieldCode . "::" . $addText);
            } elseif (!empty($field['ACCEPT_VALUES'])) {
                if (!in_array($array[$fieldCode], $field['ACCEPT_VALUES'])) {
                    $this->addError(self::ERROR_CODE_NOT_ACCEPT_VALUE, $fieldCode . "=" . $array[$fieldCode] . "::" . $addText);
                }
            }
        }
    }

    /**
     * Метод изменения сообщения, при необходимости, перед его обработкой
     */
    protected function editMessage(){}

    /**
     * Метод, в котором запускается обработка сообщения.
     * Но перед обработкой запускается проверка наличия всех обязательных полей.
     * Внутри этого метода запускается метод $this->runPreProcessMessage(),
     * который и запускает непосредственную обработку сообщения.
     * @return bool - если обработка сообщения прошла успешно, то метод вернёт true
     */
    public final function runWork(): bool
    {
        $this->runProcessMessage();
        return $this->getResult()->isSuccess();
    }

    /**
     * Метод возвращает одну из конфигураций обязательных полей в сообщении.
     * Все возможные конфигурации получаются методом $this->getRequireFieldsConfigs()
     * @param string $configCode
     * @return mixed
     */
    final protected function getRequireFieldsByConfig(string $configCode)
    {
        return $this->getRequireFieldsConfigs()[$configCode];
    }

    /**
     * Метод проверяет наличие всех обязательных параметров в сообщении $message.
     * Если каких-то полей нет, то информация об это будет добавлена в \Bitrix\Main\Result как ошибка.
     * @return bool - true - все необходимые поля есть, false - не все обязательные поля есть в сообщении.
     */
    protected abstract function checkRequireFields(): bool;

    /**
     * Метод должен вернуть описание обязательных параметров в частях сообщения.
     * Каждый элемент массива - это отдельная конфигурация для отдельной части сообщения
     * Пример массива описания полей:
     * [
     * //конфигурация обязательных параметров для товара и ТП
     * "PRODUCT_REQUIRE_FIELDS" => [
     * [
     * 'FIELD_CODE' => 'external_id',//код поля
     * 'ACCEPT_VALUES' => []//допустимые значения поля FIELD_CODE
     * ],
     * [
     * 'FIELD_CODE' => 'only_changes',
     * 'ACCEPT_VALUES' => [true, false]
     * ],
     * [
     * 'FIELD_CODE' => 'name',
     * 'ACCEPT_VALUES' => []
     * ],
     * ], -//-
     * ]
     *
     * @return array - массив с обязательными полями частей сообщения
     */
    protected abstract function getRequireFieldsConfigs(): array;

    /**
     * Метод непосредственной обработки сообщения $this->message
     */
    protected abstract function runProcessMessage();

    /**
     * Метод возвращает routing_key для AMQP
     *
     * @return string
     */
    abstract static function getRoutingKey():string;

    /**
     * Метод возвращает очередь для AMQP, в которую будут приходить сообщения
     *
     * @return string
     */
    abstract static function getQueueName():string;
    
}
