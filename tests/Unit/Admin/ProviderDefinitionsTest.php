<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Admin;

use Fayyazdeh\UniversalSms\Admin\ProviderDefinitions;
use PHPUnit\Framework\TestCase;

final class ProviderDefinitionsTest extends TestCase
{
    public function test_generic_http_definition_exposes_supported_methods_and_auth_modes(): void
    {
        $definition = ProviderDefinitions::all()['generic-http'];

        self::assertSame('http', $definition['type']);
        self::assertContains('POST', $definition['methods']);
        self::assertArrayHasKey('api_key', $definition['auth']);
        self::assertArrayHasKey('bearer', $definition['auth']);
        self::assertArrayHasKey('basic', $definition['auth']);
    }
}
