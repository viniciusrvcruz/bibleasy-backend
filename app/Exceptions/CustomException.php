<?php

namespace App\Exceptions;

use Exception;

abstract class CustomException extends Exception
{
    protected int $statusCode = 500;
    protected string $errorType;

    public function __construct(string $type, string $message)
    {
        $this->errorType = $type;
        parent::__construct($message);
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function render()
    {
        return response()->json([
            'error' => $this->getErrorType(),
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}