<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Providers;

use Fayyazdeh\UniversalSms\Providers\GenericHttpProvider;
use PHPUnit\Framework\TestCase;

final class GenericHttpProviderTest extends TestCase
{
    public function test_post_body_and_api_key_are_mapped_and_success_is_normalized(): void
    {
        $captured = null;

        $provider = new GenericHttpProvider(
            'generic',
            [
                'endpoint' => 'https://sms.example.test/send',
                'method' => 'POST',
                'headers' => ['Content-Type' => 'application/json', 'X-Custom' => 'demo'],
                'body' => [
                    'to' => '{{mobile}}',
                    'text' => '{{message}}',
                ],
                'auth' => ['type' => 'header', 'name' => 'X-API-Key', 'value' => 'secret-key'],
                'response' => [
                    'success_path' => 'ok',
                    'success_value' => true,
                    'message_id_path' => 'id',
                ],
            ],
            static function (string $url, array $args) use (&$captured): array {
                $captured = [$url, $args];

                return [
                    'status' => 200,
                    'body' => json_encode(['ok' => true, 'id' => 'msg-42']),
                ];
            }
        );

        $response = $provider->send('09120000000', 'Hello');

        self::assertTrue($response->success);
        self::assertSame('msg-42', $response->messageId);
        self::assertSame('https://sms.example.test/send', $captured[0]);
        self::assertSame('secret-key', $captured[1]['headers']['X-API-Key']);

        $decodedBody = json_decode((string) $captured[1]['body'], true);
        self::assertSame('09120000000', $decodedBody['to']);
        self::assertSame('Hello', $decodedBody['text']);
    }

    public function test_timeout_is_normalized_without_exposing_secrets(): void
    {
        $provider = new GenericHttpProvider(
            'generic',
            [
                'endpoint' => 'https://sms.example.test/send',
                'auth' => ['type' => 'header', 'name' => 'Authorization', 'value' => 'Bearer secret-token'],
            ],
            static function (): array {
                throw new \RuntimeException('Request timed out');
            }
        );

        $response = $provider->send('09120000000', 'Hello');

        self::assertFalse($response->success);
        self::assertSame('request_exception', $response->errorCode);
        self::assertStringNotContainsString('secret-token', $response->message);
    }

    public function test_connection_uses_a_dedicated_non_sending_endpoint(): void
    {
        $captured = null;

        $provider = new GenericHttpProvider(
            'generic',
            [
                'endpoint' => 'https://sms.example.test/send',
                'connection' => [
                    'endpoint' => 'https://sms.example.test/health',
                    'method' => 'GET',
                    'response' => [
                        'success_path' => 'ok',
                        'success_value' => true,
                    ],
                ],
            ],
            static function (string $url, array $args) use (&$captured): array {
                $captured = [$url, $args];

                return ['status' => 200, 'body' => json_encode(['ok' => true])];
            }
        );

        self::assertTrue($provider->testConnection());
        self::assertSame('https://sms.example.test/health', $captured[0]);
        self::assertSame('GET', $captured[1]['method']);
        self::assertArrayNotHasKey('body', $captured[1]);
    }
}
