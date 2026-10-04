# Phase 1 — SMS Core Design

## Goal
Build a provider-agnostic WordPress SMS core that exposes one stable internal API while supporting customer-owned REST/HTTP/SOAP/custom SMS gateways.

## Architecture
The plugin core owns message requests, provider selection, normalized responses, errors, and logging boundaries. Providers implement a common adapter contract; the core never depends on provider-specific request formats.

### Provider contract
- `send(string $mobile, string $message): SMSResponse`
- `testConnection(): bool`
- `getBalance(): ?float`

### Generic API capabilities
- HTTP methods including GET and POST.
- URL templates.
- Query parameters and headers.
- Authentication: none, API key, bearer token, basic auth, username/password, custom header, query parameter.
- Body templates with `{{mobile}}`, `{{message}}`, `{{sender}}`, and credential placeholders.
- Request/response field mapping.
- Configurable timeout.
- Test connection and test SMS operations.

## Safety
Credentials must not be written to logs or returned in diagnostic output. HTTP failures, timeouts, malformed provider responses, and provider-declared failures become normalized SMS errors without leaking secrets.

## Logging boundary
The core emits structured log records containing timestamp, provider, status, HTTP status when available, response summary, error code, and duration. Secrets and authentication material are excluded.

## Documentation
Each release records added, changed, fixed, security, and documentation changes. Architecture and provider guides live under `docs/`.

## Phase 1 acceptance
A developer can install the plugin, register/select a provider adapter, send an SMS through the normalized core API, test connectivity, receive a normalized success/failure response, and inspect a sanitized log record.
