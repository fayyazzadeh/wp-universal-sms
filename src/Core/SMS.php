<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSLogEntry;
use Fayyazdeh\UniversalSms\Contracts\SMSLoggerInterface;
use Fayyazdeh\UniversalSms\Contracts\SMSResponse;

final class SMS
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly SMSLoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function send(string $mobile, string $message, ?string $providerId = null): SMSResponse
    {
        $provider = $providerId === null
            ? $this->providers->getDefault()
            : $this->providers->get($providerId);

        if ($provider === null) {
            $response = SMSResponse::failure(
                'core',
                $providerId === null ? 'provider_not_selected' : 'provider_not_found',
                $providerId === null
                    ? 'No SMS provider has been selected.'
                    : 'The selected SMS provider was not found.'
            );
            $this->logger->record(new SMSLogEntry('core', false, null, $response->errorCode, null));
            return $response;
        }

        $response = $provider->send($mobile, $message);

        $this->logger->record(new SMSLogEntry(
            $response->provider,
            $response->success,
            $response->httpStatus,
            $response->errorCode,
            $response->durationMs
        ));

        return $response;
    }
}
