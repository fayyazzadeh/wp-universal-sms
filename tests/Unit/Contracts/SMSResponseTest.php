<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Tests\Unit\Contracts;

use Fayyazdeh\UniversalSms\Contracts\SMSResponse;
use PHPUnit\Framework\TestCase;

final class SMSResponseTest extends TestCase
{
    public function test_success_response_exposes_normalized_values(): void
    {
        $response = SMSResponse::success(
            provider: 'test-provider',
            messageId: 'abc-123',
            raw: ['status' => 'ok']
        );

        self::assertTrue($response->success);
        self::assertSame('test-provider', $response->provider);
        self::assertSame('abc-123', $response->messageId);
        self::assertSame(['status' => 'ok'], $response->raw);
        self::assertNull($response->errorCode);
    }

    public function test_failure_response_exposes_normalized_error(): void
    {
        $response = SMSResponse::failure(
            provider: 'test-provider',
            errorCode: 'timeout',
            message: 'Provider request timed out.'
        );

        self::assertFalse($response->success);
        self::assertSame('timeout', $response->errorCode);
        self::assertSame('Provider request timed out.', $response->message);
    }
}
