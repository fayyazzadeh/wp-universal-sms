# SMS Core Architecture

## Purpose

WP Universal SMS separates WordPress integrations from provider-specific transport logic.

```text
WordPress
   ↓
SMS Core
   ↓
Provider Registry
   ↓
SMSProviderInterface
   ├── Generic HTTP/REST
   ├── SOAP (future adapter)
   └── GSM Agent (future adapter)
```

## Internal API

- `SMS::send(string $mobile, string $message, ?string $providerId = null): SMSResponse`
- `ProviderRegistry::register(SMSProviderInterface $provider): void`
- `ProviderRegistry::setDefault(string $id): void`

A provider returns `SMSResponse` so callers never need to understand provider-specific response formats.

## Generic HTTP provider

Configuration can define endpoint and HTTP method, query parameters, headers, request body, header/query authentication, sender and API key placeholders, timeout, response success path/value, and message ID path.

Supported template variables include `{{mobile}}`, `{{message}}`, `{{sender}}`, and `{{api_key}}`.

## Error model

Core failures are normalized with codes such as `provider_not_selected`, `provider_not_found`, `invalid_configuration`, `request_exception`, `invalid_response`, and `provider_rejected`.

## Logging

The core emits `SMSLogEntry` records through `SMSLoggerInterface`. Persistence is intentionally separated from the core so Phase 2 can add the WordPress admin log UI without coupling transport code to presentation.