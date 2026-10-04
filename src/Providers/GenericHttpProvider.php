<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Providers;

use Closure;
use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;
use Fayyazdeh\UniversalSms\Contracts\SMSResponse;
use Throwable;

final class GenericHttpProvider implements SMSProviderInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly string $id,
        private readonly array $config,
        private readonly ?Closure $requester = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function send(string $mobile, string $message): SMSResponse
    {
        return $this->request($mobile, $message, false);
    }

    public function testConnection(): bool
    {
        $connection = $this->config['connection'] ?? null;

        if (!is_array($connection) || empty($connection['endpoint'])) {
            return false;
        }

        $connectionConfig = array_merge($connection, [
            'auth' => $this->config['auth'] ?? [],
            'headers' => array_merge($this->config['headers'] ?? [], $connection['headers'] ?? []),
            'query' => array_merge($this->config['query'] ?? [], $connection['query'] ?? []),
            'response' => $connection['response'] ?? ($this->config['response'] ?? []),
        ]);

        return $this->request(
            '',
            '',
            true,
            (string) $connection['endpoint'],
            strtoupper((string) ($connection['method'] ?? 'GET')),
            $connectionConfig
        )->success;
    }

    public function getBalance(): ?float
    {
        return null;
    }

    private function request(
        string $mobile,
        string $message,
        bool $connectionTest,
        ?string $endpointOverride = null,
        ?string $methodOverride = null,
        ?array $connectionConfig = null
    ): SMSResponse {
        $started = microtime(true);
        $endpoint = $endpointOverride ?? (string) ($this->config['endpoint'] ?? '');
        $method = $methodOverride ?? strtoupper((string) ($this->config['method'] ?? 'POST'));
        $config = $connectionConfig ?? $this->config;

        if ($endpoint === '') {
            return SMSResponse::failure($this->id, 'invalid_configuration', 'Provider endpoint is missing.');
        }

        $variables = [
            'mobile' => $mobile,
            'message' => $message,
            'sender' => $this->config['sender'] ?? '',
            'api_key' => $this->config['api_key'] ?? '',
        ];

        $url = RequestTemplate::interpolate($endpoint, $variables);
        $query = RequestTemplate::interpolate($config['query'] ?? [], $variables);
        $headers = RequestTemplate::interpolate($config['headers'] ?? [], $variables);
        $body = RequestTemplate::interpolate($config['body'] ?? [], $variables);

        $auth = $config['auth'] ?? [];
        $authType = (string) ($auth['type'] ?? '');

        if ($authType === 'header' || $authType === 'api_key') {
            $name = (string) ($auth['name'] ?? 'X-API-Key');
            if ($name !== '') {
                $headers[$name] = (string) RequestTemplate::interpolate($auth['value'] ?? '{{api_key}}', $variables);
            }
        } elseif ($authType === 'query') {
            $name = (string) ($auth['name'] ?? '');
            if ($name !== '') {
                $query[$name] = (string) RequestTemplate::interpolate($auth['value'] ?? '', $variables);
            }
        } elseif ($authType === 'bearer') {
            $token = (string) RequestTemplate::interpolate($auth['value'] ?? '{{api_key}}', $variables);
            $headers['Authorization'] = 'Bearer ' . $token;
        } elseif ($authType === 'basic') {
            $username = (string) RequestTemplate::interpolate($auth['username'] ?? '', $variables);
            $password = (string) RequestTemplate::interpolate($auth['password'] ?? '', $variables);
            $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        if (isset($headers['Content-Type']) && stripos((string) $headers['Content-Type'], 'application/json') !== false && is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => (int) ($config['timeout'] ?? $this->config['timeout'] ?? 15),
        ];

        if (!$connectionTest && $method !== 'GET') {
            $args['body'] = $body;
        }

        try {
            $raw = $this->requester !== null
                ? ($this->requester)($url, $args)
                : $this->wordpressRequest($url, $args);
        } catch (Throwable) {
            return SMSResponse::failure(
                $this->id,
                'request_exception',
                'The SMS provider request failed.',
                [],
                null,
                $this->duration($started)
            );
        }

        $status = isset($raw['status']) ? (int) $raw['status'] : null;
        $decoded = json_decode((string) ($raw['body'] ?? ''), true);

        if (!is_array($decoded)) {
            return SMSResponse::failure(
                $this->id,
                'invalid_response',
                'The SMS provider returned an invalid response.',
                [],
                $status,
                $this->duration($started)
            );
        }

        $responseConfig = $config['response'] ?? [];
        $success = ResponseMapper::get($decoded, $responseConfig['success_path'] ?? null);
        $expected = $responseConfig['success_value'] ?? true;

        if ($success !== $expected) {
            return SMSResponse::failure(
                $this->id,
                'provider_rejected',
                'The SMS provider rejected the request.',
                $this->sanitizeRaw($decoded),
                $status,
                $this->duration($started)
            );
        }

        $messageId = ResponseMapper::get($decoded, $responseConfig['message_id_path'] ?? null);

        return SMSResponse::success(
            $this->id,
            is_scalar($messageId) ? (string) $messageId : null,
            $this->sanitizeRaw($decoded),
            $status,
            $this->duration($started)
        );
    }

    private function wordpressRequest(string $url, array $args): array
    {
        if (!function_exists('wp_remote_request')) {
            throw new \RuntimeException('WordPress HTTP API is unavailable.');
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        return [
            'status' => wp_remote_retrieve_response_code($response),
            'body' => wp_remote_retrieve_body($response),
        ];
    }

    private function duration(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function sanitizeRaw(array $raw): array
    {
        foreach ($raw as $key => $value) {
            if (preg_match('/token|secret|password|api[_-]?key|authorization/i', (string) $key)) {
                $raw[$key] = '[redacted]';
            }
        }

        return $raw;
    }
}
