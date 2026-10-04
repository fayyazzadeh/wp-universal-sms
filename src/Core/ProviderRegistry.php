<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;

final class ProviderRegistry
{
    /** @var array<string, SMSProviderInterface> */
    private array $providers = [];

    public function register(SMSProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    public function get(string $id): ?SMSProviderInterface
    {
        return $this->providers[$id] ?? null;
    }
}
