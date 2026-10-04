<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Admin;

use Fayyazdeh\UniversalSms\Admin\GatewayConfig;
use Fayyazdeh\UniversalSms\Admin\ProviderDefinitions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GatewayConfigTest extends TestCase
{
    private function definition(): array
    {
        return ProviderDefinitions::all()['generic-http'];
    }

    public function test_valid_generic_http_configuration_is_normalized(): void
    {
        $config = GatewayConfig::validate([
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'post',
            'sender' => '3000',
            'test_recipient' => '09120000000',
            'auth' => ['type' => 'api_key', 'name' => 'X-API-Key', 'value' => 'secret'],
            'headers' => [['name' => 'Content-Type', 'value' => 'application/json']],
            'query' => [['name' => 'format', 'value' => 'json']],
            'body' => '{"to":"{{mobile}}","text":"{{message}}"}',
            'response' => ['success_path' => 'ok', 'success_value' => true, 'message_id_path' => 'id'],
            'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
            'timeout' => 30,
        ], $this->definition());

        self::assertSame('generic-http', $config['provider']);
        self::assertSame('POST', $config['method']);
        self::assertSame('secret', $config['auth']['value']);
        self::assertSame('application/json', $config['headers']['Content-Type']);
        self::assertSame(['to' => '{{mobile}}', 'text' => '{{message}}'], $config['body']);
        self::assertSame('https://sms.example.test/health', $config['connection']['endpoint']);
        self::assertSame(30, $config['timeout']);
    }

    public function test_unsupported_provider_method_and_bad_endpoint_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GatewayConfig::validate([
            'provider' => 'other',
            'endpoint' => 'ftp://invalid',
            'method' => 'DELETE',
            'connection' => ['endpoint' => 'https://sms.example.test/health'],
        ], $this->definition());
    }

    public function test_timeout_is_bounded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GatewayConfig::validate([
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'connection' => ['endpoint' => 'https://sms.example.test/health'],
            'timeout' => 121,
        ], $this->definition());
    }

    public function test_masked_credential_preserves_existing_secret(): void
    {
        $config = GatewayConfig::validate([
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'auth' => ['type' => 'bearer', 'value' => GatewayConfig::MASKED_SECRET],
            'connection' => ['endpoint' => 'https://sms.example.test/health'],
        ], $this->definition(), [
            'auth' => ['type' => 'bearer', 'value' => 'old-secret'],
        ]);

        self::assertSame('old-secret', $config['auth']['value']);
    }

    public function test_new_credential_replaces_existing_secret(): void
    {
        $config = GatewayConfig::validate([
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'auth' => ['type' => 'bearer', 'value' => 'new-secret'],
            'connection' => ['endpoint' => 'https://sms.example.test/health'],
        ], $this->definition(), [
            'auth' => ['type' => 'bearer', 'value' => 'old-secret'],
        ]);

        self::assertSame('new-secret', $config['auth']['value']);
    }
}
