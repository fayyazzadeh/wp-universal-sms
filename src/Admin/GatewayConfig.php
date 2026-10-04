<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin;

use InvalidArgumentException;

final class GatewayConfig
{
    public const MASKED_SECRET = '__WPU_SMS_SECRET_MASKED__';
    public const MIN_TIMEOUT = 1;
    public const MAX_TIMEOUT = 120;

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    public static function validate(array $input, array $definition, array $existing = []): array
    {
        $provider = self::text($input['provider'] ?? '');
        if ($provider === '' || $provider !== ($definition['id'] ?? $provider)) {
            throw new InvalidArgumentException('The selected SMS provider is not registered.');
        }

        $methods = array_map('strtoupper', array_map('strval', $definition['methods'] ?? []));
        $method = strtoupper(self::text($input['method'] ?? 'POST'));
        if (!in_array($method, $methods, true)) {
            throw new InvalidArgumentException('The selected HTTP method is not supported.');
        }

        $endpoint = self::url($input['endpoint'] ?? '');
        $sender = self::text($input['sender'] ?? '');
        $testRecipient = self::text($input['test_recipient'] ?? '');

        $timeout = (int) ($input['timeout'] ?? 15);
        if ($timeout < self::MIN_TIMEOUT || $timeout > self::MAX_TIMEOUT) {
            throw new InvalidArgumentException('The timeout must be between 1 and 120 seconds.');
        }

        $auth = self::normalizeAuth(
            is_array($input['auth'] ?? null) ? $input['auth'] : [],
            $definition['auth'] ?? [],
            is_array($existing['auth'] ?? null) ? $existing['auth'] : []
        );

        return [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'method' => $method,
            'sender' => $sender,
            'test_recipient' => $testRecipient,
            'auth' => $auth,
            'headers' => self::pairs($input['headers'] ?? []),
            'query' => self::pairs($input['query'] ?? []),
            'body' => self::normalizeBody($input['body'] ?? ''),
            'response' => self::normalizeResponse($input['response'] ?? []),
            'connection' => self::normalizeConnection($input['connection'] ?? [], $endpoint, $method, $input['response'] ?? []),
            'timeout' => $timeout,
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function safe(array $raw): array
    {
        $safe = $raw;
        if (isset($safe['auth']) && is_array($safe['auth'])) {
            $auth = $safe['auth'];
            foreach (['value', 'password'] as $key) {
                if (array_key_exists($key, $auth) && $auth[$key] !== '') {
                    $auth[$key] = self::MASKED_SECRET;
                }
            }
            $safe['auth'] = $auth;
        }

        return $safe;
    }

    private static function normalizeAuth(array $auth, array $allowed, array $existing): array
    {
        $type = self::text($auth['type'] ?? 'none');
        if (!array_key_exists($type, $allowed)) {
            throw new InvalidArgumentException('The selected authentication mode is not supported.');
        }

        $result = ['type' => $type];

        if (in_array($type, ['api_key', 'bearer', 'header', 'query'], true)) {
            $name = self::text($auth['name'] ?? '');
            if (in_array($type, ['api_key', 'header', 'query'], true) && $name === '') {
                $name = $type === 'api_key' ? 'X-API-Key' : '';
            }
            if (in_array($type, ['header', 'query'], true) && $name === '') {
                throw new InvalidArgumentException('Authentication parameter name is required.');
            }

            $value = $auth['value'] ?? '';
            if ($value === self::MASKED_SECRET) {
                $value = $existing['value'] ?? '';
            } else {
                $value = self::text($value);
            }
            if ($value === '') {
                throw new InvalidArgumentException('Authentication credential is required.');
            }

            $result['name'] = $name;
            $result['value'] = $value;
        } elseif ($type === 'basic') {
            $username = self::text($auth['username'] ?? ($existing['username'] ?? ''));
            $password = $auth['password'] ?? '';
            $password = $password === self::MASKED_SECRET
                ? (string) ($existing['password'] ?? '')
                : self::text($password);

            if ($username === '' || $password === '') {
                throw new InvalidArgumentException('Basic authentication requires username and password.');
            }

            $result['username'] = $username;
            $result['password'] = $password;
        }

        return $result;
    }

    private static function normalizeConnection(mixed $connection, string $fallbackEndpoint, string $fallbackMethod, mixed $response): array
    {
        $connection = is_array($connection) ? $connection : [];
        $endpoint = self::url($connection['endpoint'] ?? '');
        if ($endpoint === '') {
            throw new InvalidArgumentException('A dedicated connection-test endpoint is required.');
        }

        $method = strtoupper(self::text($connection['method'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            throw new InvalidArgumentException('The connection-test HTTP method is not supported.');
        }

        return [
            'endpoint' => $endpoint,
            'method' => $method,
            'headers' => self::pairs($connection['headers'] ?? []),
            'query' => self::pairs($connection['query'] ?? []),
            'body' => self::normalizeBody($connection['body'] ?? ''),
            'response' => self::normalizeResponse($connection['response'] ?? $response),
        ];
    }

    private static function normalizeResponse(mixed $response): array
    {
        $response = is_array($response) ? $response : [];
        $successPath = self::text($response['success_path'] ?? '');
        $messageIdPath = self::text($response['message_id_path'] ?? '');
        $successValue = $response['success_value'] ?? true;

        return [
            'success_path' => $successPath,
            'success_value' => is_bool($successValue) || is_numeric($successValue) ? $successValue : self::text($successValue),
            'message_id_path' => $messageIdPath,
        ];
    }

    private static function normalizeBody(mixed $body): mixed
    {
        if (is_array($body)) {
            return $body;
        }

        $body = trim((string) $body);
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException('The request body must contain a valid JSON object.');
        }

        return $decoded;
    }

    private static function pairs(mixed $pairs): array
    {
        if (!is_array($pairs)) {
            return [];
        }

        $result = [];
        foreach ($pairs as $pair) {
            if (is_array($pair) && isset($pair['name'], $pair['value'])) {
                $name = self::text($pair['name']);
                if ($name !== '') {
                    $result[$name] = self::text($pair['value']);
                }
                continue;
            }

            if (is_string($pair) && str_contains($pair, '=')) {
                [$name, $value] = array_map('trim', explode('=', $pair, 2));
                if ($name !== '') {
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    private static function url(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Endpoint must use HTTP or HTTPS.');
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Endpoint must be a valid URL.');
        }

        return $value;
    }

    private static function text(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        return trim(strip_tags($value));
    }
}
