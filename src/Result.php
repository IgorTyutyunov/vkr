<?php
namespace Igrik\Vkr;

class Result
{
    private array $errors = [];

    private bool $isSuccess = true;

    public function __construct()
    {
    }

    /**
     * @param string $messageError - текст ошибки
     * @param string $errorCode - код ошибки
     */
    public function addError(string $messageError, string $errorCode)
    {
        $this->errors[] = [
            'CODE' => $errorCode,
            'MESSAGE' => $messageError
        ];

        $this->isSuccess = false;
    }

    public function isSuccess():bool
    {
        return $this->isSuccess;
    }

    public function getErrorMessages()
    {
        return array_column($this->errors, 'MESSAGE');
    }
}