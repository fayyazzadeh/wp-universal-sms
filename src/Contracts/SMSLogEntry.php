<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Contracts;

final readonly class SMSLogEntry
{
    public function __construct(
        public string $provider,
        public bool $success,
        public ?int $httpStatus,
        public ?string $errorCode,
        public ?int $durationMs,
    ) {
    }
}
