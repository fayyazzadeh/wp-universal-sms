<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Core;

use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;

final class ProviderRegistry
{
    /** @var array<string, SMSProviderInterface> */
    private array $providers = [];
    private ?string $defaultProvider = null;

    public function register(SMSProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    public function setDefault(string $id): void
    {
        $this->defaultProvider = $id;
    }

    public function getDefault(): ?SMSProviderInterface
    {
        return $this->defaultProvider === null ? null : $this->get($this->defaultProvider);
    }

    public function get(string $id): ?SMSProviderInterface
    {
        return $this->providers[$id] ?? null;
    }
}
