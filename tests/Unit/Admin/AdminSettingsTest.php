<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Admin;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use PHPUnit\Framework\TestCase;

final class AdminSettingsTest extends TestCase
{
    public function test_default_config_is_deterministic(): void
    {
        $settings = new AdminSettings();

        self::assertSame([
            'provider' => '',
            'sender' => '',
            'test_recipient' => '',
        ], $settings->getGatewayConfig());
    }

    public function test_config_round_trip_preserves_non_secret_fields(): void
    {
        $settings = new AdminSettings();
        $settings->saveGatewayConfig([
            'provider' => 'generic-http',
            'sender' => '3000',
            'test_recipient' => '09120000000',
            'api_key' => 'secret-value',
        ]);

        self::assertSame([
            'provider' => 'generic-http',
            'sender' => '3000',
            'test_recipient' => '09120000000',
        ], $settings->getGatewayConfig());
    }
}
