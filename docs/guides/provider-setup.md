# Provider Setup

Phase 1 supports customer-owned HTTP/REST-style SMS APIs through the Generic HTTP Provider.

A provider configuration contains endpoint, method, headers, query, body, auth, response, timeout, sender, and api_key.

Example:

```php
[
    'endpoint' => 'https://sms.example.test/send',
    'method' => 'POST',
    'headers' => ['Content-Type' => 'application/json'],
    'body' => [
        'to' => '{{mobile}}',
        'text' => '{{message}}',
    ],
    'auth' => [
        'type' => 'header',
        'name' => 'X-API-Key',
        'value' => '{{api_key}}',
    ],
    'response' => [
        'success_path' => 'ok',
        'success_value' => true,
        'message_id_path' => 'id',
    ],
    'connection' => [
        'endpoint' => 'https://sms.example.test/health',
        'method' => 'GET',
        'response' => [
            'success_path' => 'ok',
            'success_value' => true,
        ],
    ],
]
```

The WordPress HTTP API is used for real requests. Tests inject a deterministic HTTP requester so provider behavior can be tested without sending real SMS.

`testConnection()` uses the dedicated `connection` endpoint and never sends an SMS. A provider without a configured connection endpoint reports that it cannot perform a connection test.

Supported authentication modes include custom header, API key, query parameter, bearer token, and basic authentication.

Never put API keys, passwords, bearer tokens, or authorization headers in logs or screenshots.
