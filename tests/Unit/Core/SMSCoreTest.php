<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;
use Fayyazdeh\UniversalSms\Contracts\SMSResponse;
use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;
use PHPUnit\Framework\TestCase;

final class SMSCoreTest extends TestCase
{
    public function test_send_dispatches_to_the_registered_provider(): void
    {
        $provider = new class implements SMSProviderInterface {
            public function getId(): string { return 'fake'; }
            public function send(string $mobile, string $message): SMSResponse
            {
                return SMSResponse::success('fake', 'msg-1', ['mobile' => $mobile, 'message' => $message]);
            }
            public function testConnection(): bool { return true; }
            public function getBalance(): ?float { return 10.0; }
        };

        $registry = new ProviderRegistry();
        $registry->register($provider);

        $response = (new SMS($registry))->send('09120000000', 'Hello');

        self::assertTrue($response->success);
        self::assertSame('msg-1', $response->messageId);
        self::assertSame('fake', $response->provider);
    }

    public function test_send_returns_normalized_failure_when_provider_is_missing(): void
    {
        $response = (new SMS(new ProviderRegistry()))->send('09120000000', 'Hello', 'missing');

        self::assertFalse($response->success);
        self::assertSame('provider_not_found', $response->errorCode);
    }
}
