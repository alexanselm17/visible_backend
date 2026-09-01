<?php

namespace App\Exceptions;

use RuntimeException;

class ScreenshotVerificationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $upstreamStatus = null,
        public readonly ?string $requestId = null
    ) {
        parent::__construct($message);
    }
}
