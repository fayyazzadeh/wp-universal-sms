<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin;

final class AdminSettings
{
    private const OPTION = 'wp_universal_sms_gateway';

    public function __construct(private $storage = null)
    {
    }

    public function getGatewayConfig(): array
    {
        $stored = $this->read();

        return [
            'provider' => (string) ($stored['provider'] ?? ''),
            'sender' => (string) ($stored['sender'] ?? ''),
            'test_recipient' => (string) ($stored['test_recipient'] ?? ''),
        ];
    }

    public function getSafeGatewayConfig(): array
    {
        return GatewayConfig::safe($this->read());
    }

    public function getRawGatewayConfig(): array
    {
        return $this->read();
    }

    public function saveGatewayConfig(array $config): void
    {
        $current = $this->read();
        $next = array_merge($current, [
            'provider' => (string) ($config['provider'] ?? ''),
            'sender' => (string) ($config['sender'] ?? ''),
            'test_recipient' => (string) ($config['test_recipient'] ?? ''),
        ]);

        foreach (['api_key', 'endpoint', 'method', 'headers', 'query', 'body', 'auth', 'response', 'connection', 'timeout'] as $key) {
            if (array_key_exists($key, $config)) {
                $next[$key] = $config[$key];
            }
        }

        $this->write($next);
    }

    private function read(): array
    {
        if ($this->storage instanceof \Closure) {
            $value = ($this->storage)('get', self::OPTION, []);
            return is_array($value) ? $value : [];
        }

        if (function_exists('get_option')) {
            $value = get_option(self::OPTION, []);
            return is_array($value) ? $value : [];
        }

        return [];
    }

    private function write(array $value): void
    {
        if ($this->storage instanceof \Closure) {
            ($this->storage)('set', self::OPTION, $value);
            return;
        }

        if (function_exists('update_option')) {
            update_option(self::OPTION, $value, false);
        }
    }
}
