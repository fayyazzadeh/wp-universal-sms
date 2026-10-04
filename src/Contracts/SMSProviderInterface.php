<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Contracts;

interface SMSProviderInterface
{
    public function getId(): string;

    public function send(string $mobile, string $message): SMSResponse;

    public function testConnection(): bool;

    public function getBalance(): ?float;
}
