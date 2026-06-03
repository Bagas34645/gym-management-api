<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;

class ApiException extends Exception
{
    public function __construct(
        public readonly string $userMessage,
        public readonly ?ErrorCode $errorCode = null,
        public readonly int $httpStatus = 400,
        public readonly array $errors = [],
        public readonly ?array $data = null,
    ) {
        parent::__construct($userMessage, $httpStatus);
    }
}
