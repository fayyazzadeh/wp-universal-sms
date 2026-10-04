<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Contracts;

final class SMSResponse
{
    private function __construct(
        public bool $success,
        public string $provider,
        public ?string $messageId,
        public ?string $errorCode,
        public string $message,
        public array $raw = [],
        public ?int $httpStatus = null,
        public ?int $durationMs = null,
    ) {
    }

    public static function success(
        string $provider,
        ?string $messageId = null,
        array $raw = [],
        ?int $httpStatus = null,
        ?int $durationMs = null,
        string $message = 'SMS sent successfully.'
    ): self {
        return new self(true, $provider, $messageId, null, $message, $raw, $httpStatus, $durationMs);
    }

    public static function failure(
        string $provider,
        string $errorCode,
        string $message,
        array $raw = [],
        ?int $httpStatus = null,
        ?int $durationMs = null
    ): self {
        return new self(false, $provider, null, $errorCode, $message, $raw, $httpStatus, $durationMs);
    }
}
