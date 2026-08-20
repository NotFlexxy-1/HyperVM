<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ProxmoxRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $endpoint = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public function context(): array
    {
        return array_merge($this->context, [
            'endpoint' => $this->endpoint,
            'status' => $this->statusCode,
        ]);
    }
}
