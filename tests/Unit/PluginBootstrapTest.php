<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit;

use Fayyazdeh\UniversalSms\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginBootstrapTest extends TestCase
{
    public function test_plugin_boot_returns_a_bootstrapped_instance_without_provider_credentials(): void
    {
        $plugin = Plugin::boot();

        self::assertInstanceOf(Plugin::class, $plugin);
        self::assertNotNull($plugin->sms());
    }
}
