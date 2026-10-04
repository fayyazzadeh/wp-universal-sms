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
        return $this->request($mobile, $message);
    }

    public function testConnection(): bool
    {
        $response = $this->request('', '');
        return $response->success;
    }

    public function getBalance(): ?float
    {
        return null;
    }

    private function request(string $mobile, string $message): SMSResponse
    {
        $started = microtime(true);
        $endpoint = (string) ($this->config['endpoint'] ?? '');
        $method = strtoupper((string) ($this->config['method'] ?? 'POST'));

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
        $query = RequestTemplate::interpolate($this->config['query'] ?? [], $variables);
        $headers = RequestTemplate::interpolate($this->config['headers'] ?? [], $variables);
        $body = RequestTemplate::interpolate($this->config['body'] ?? [], $variables);

        $auth = $this->config['auth'] ?? [];
        if (($auth['type'] ?? '') === 'header') {
            $name = (string) ($auth['name'] ?? '');
            if ($name !== '') {
                $headers[$name] = (string) RequestTemplate::interpolate($auth['value'] ?? '', $variables);
            }
        } elseif (($auth['type'] ?? '') === 'query') {
            $name = (string) ($auth['name'] ?? '');
            if ($name !== '') {
                $query[$name] = (string) RequestTemplate::interpolate($auth['value'] ?? '', $variables);
            }
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $args = [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => (int) ($this->config['timeout'] ?? 15),
        ];

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

        $responseConfig = $this->config['response'] ?? [];
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
