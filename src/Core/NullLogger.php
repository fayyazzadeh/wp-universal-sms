<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSLogEntry;
use Fayyazdeh\UniversalSms\Contracts\SMSLoggerInterface;

final class NullLogger implements SMSLoggerInterface
{
    public function record(SMSLogEntry $entry): void
    {
    }
}
