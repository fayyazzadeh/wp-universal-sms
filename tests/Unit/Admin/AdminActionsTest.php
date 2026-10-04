<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Admin;

use Fayyazdeh\UniversalSms\Admin\AdminActions;
use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;
use Fayyazdeh\UniversalSms\Contracts\SMSResponse;
use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;
use PHPUnit\Framework\TestCase;

final class AdminActionsTest extends TestCase
{
    public function test_unauthorized_request_is_rejected_before_action(): void
    {
        $_POST = ['wpu_sms_action' => 'save_gateway'];

        $settings = new AdminSettings();
        $registry = new ProviderRegistry();
        $sms = new SMS($registry);
        $called = false;

        $actions = new AdminActions(
            $settings,
            $registry,
            $sms,
            null,
            static fn(): bool => false,
            static function () use (&$called): void { $called = true; }
        );

        $actions->handleRequest();

        self::assertFalse($called);
        $_POST = [];
    }

    public function test_save_is_nonce_protected_and_does_not_run_network_tests(): void
    {
        $store = [];
        $storage = static function (string $operation, string $key, array $value = []) use (&$store): array {
            if ($operation === 'set') {
                $store[$key] = $value;
                return $value;
            }
            return $store[$key] ?? [];
        };

        $settings = new AdminSettings($storage);
        $registry = new ProviderRegistry();
        $sms = new SMS($registry);
        $factoryCalled = false;
        $nonceCalled = false;

        $actions = new AdminActions(
            $settings,
            $registry,
            $sms,
            static function () use (&$factoryCalled): SMSProviderInterface {
                $factoryCalled = true;
                return new FakeProvider();
            },
            static fn(): bool => true,
            static function () use (&$nonceCalled): void { $nonceCalled = true; }
        );

        $_POST = [
            'wpu_sms_action' => 'save_gateway',
            'wpu_sms' => [
                'provider' => 'generic-http',
                'endpoint' => 'https://sms.example.test/send',
                'method' => 'POST',
                'auth' => ['type' => 'bearer', 'value' => 'secret'],
                'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
            ],
        ];

        $actions->handleRequest();

        self::assertTrue($nonceCalled);
        self::assertFalse($factoryCalled);
        self::assertSame('generic-http', $store['wp_universal_sms_gateway']['provider']);
        $_POST = [];
    }

    public function test_connection_calls_connection_test_and_never_sms_send(): void
    {
        $provider = new FakeProvider();
        $actions = $this->actions($provider);

        $result = $actions->dispatch('test_connection', [
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'auth' => ['type' => 'none'],
            'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
        ]);

        self::assertTrue($result['success']);
        self::assertSame(1, $provider->connectionCalls);
        self::assertSame(0, $provider->sendCalls);
        self::assertStringContainsString('No SMS was sent', $result['message']);
    }

    public function test_send_test_sms_requires_recipient(): void
    {
        $provider = new FakeProvider();
        $actions = $this->actions($provider);

        $this->expectException(\InvalidArgumentException::class);

        $actions->dispatch('send_test_sms', [
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'auth' => ['type' => 'none'],
            'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
        ]);
    }

    public function test_send_test_sms_routes_through_sms_core(): void
    {
        $provider = new FakeProvider();
        $actions = $this->actions($provider);

        $result = $actions->dispatch('send_test_sms', [
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'test_recipient' => '09120000000',
            'auth' => ['type' => 'none'],
            'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
        ]);

        self::assertTrue($result['success']);
        self::assertSame(1, $provider->sendCalls);
        self::assertSame('09120000000', $provider->lastMobile);
        self::assertSame('WP Universal SMS connection test.', $provider->lastMessage);
    }

    private function actions(FakeProvider $provider): AdminActions
    {
        $settings = new AdminSettings(static function (string $operation, string $key, array $value = []) {
            static $store = [];
            if ($operation === 'set') {
                $store[$key] = $value;
                return $value;
            }
            return $store[$key] ?? [];
        });

        $registry = new ProviderRegistry();
        $sms = new SMS($registry);

        return new AdminActions(
            $settings,
            $registry,
            $sms,
            static fn(): SMSProviderInterface => $provider,
            static fn(): bool => true,
            static function (): void {}
        );
    }
}

final class FakeProvider implements SMSProviderInterface
{
    public int $connectionCalls = 0;
    public int $sendCalls = 0;
    public string $lastMobile = '';
    public string $lastMessage = '';

    public function getId(): string { return 'generic-http'; }

    public function send(string $mobile, string $message): SMSResponse
    {
        $this->sendCalls++;
        $this->lastMobile = $mobile;
        $this->lastMessage = $message;
        return SMSResponse::success('generic-http', 'test-1');
    }

    public function testConnection(): bool
    {
        $this->connectionCalls++;
        return true;
    }

    public function getBalance(): ?float { return null; }
}
