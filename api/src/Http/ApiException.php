<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message, string $errorCode = 'VALIDATION_ERROR'): self
    {
        return new self(400, $errorCode, $message);
    }

    public static function forbidden(string $message, string $errorCode = 'FORBIDDEN'): self
    {
        return new self(403, $errorCode, $message);
    }

    public static function notFound(string $message, string $errorCode = 'NOT_FOUND'): self
    {
        return new self(404, $errorCode, $message);
    }

    public static function conflict(string $message, string $errorCode): self
    {
        return new self(409, $errorCode, $message);
    }
}
