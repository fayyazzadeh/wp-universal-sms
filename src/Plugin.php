<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms;

use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct(private readonly SMS $sms)
    {
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            $registry = new ProviderRegistry();
            self::$instance = new self(new SMS($registry));
        }

        return self::$instance;
    }

    public function sms(): SMS
    {
        return $this->sms;
    }
}
