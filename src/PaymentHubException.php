<?php

declare(strict_types=1);

namespace Anhao\PaymentHub;

use RuntimeException;

final class PaymentHubException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
