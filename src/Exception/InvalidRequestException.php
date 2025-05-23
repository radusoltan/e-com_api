<?php

namespace App\Exception;

class InvalidRequestException extends \Exception
{
    private ?array $errors;

    public function __construct(string $message = "", array $errors = null, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}