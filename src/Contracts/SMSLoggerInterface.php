<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Contracts;

interface SMSLoggerInterface
{
    public function record(SMSLogEntry $entry): void;
}
