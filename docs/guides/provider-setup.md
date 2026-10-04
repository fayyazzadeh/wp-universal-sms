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
]
```

The WordPress HTTP API is used for real requests. Tests inject a deterministic HTTP requester so provider behavior can be tested without sending real SMS.

Never put API keys, passwords, bearer tokens, or authorization headers in logs or screenshots.
