<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSResponse;

final class SMS
{
    public function __construct(private readonly ProviderRegistry $providers)
    {
    }

    public function send(string $mobile, string $message, ?string $providerId = null): SMSResponse
    {
        if ($providerId === null) {
            return SMSResponse::failure(
                'core',
                'provider_not_selected',
                'No SMS provider has been selected.'
            );
        }

        $provider = $this->providers->get($providerId);

        if ($provider === null) {
            return SMSResponse::failure(
                'core',
                'provider_not_found',
                'The selected SMS provider was not found.'
            );
        }

        return $provider->send($mobile, $message);
    }
}
