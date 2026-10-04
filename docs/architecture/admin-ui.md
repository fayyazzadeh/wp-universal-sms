# Admin UI Architecture

Phase 2 Provider Configuration adds a real, secure Generic HTTP/REST gateway editor while keeping provider transport logic outside the presentation layer.

WordPress Admin
   ↓
AdminActions
   ├── validate + persist → AdminSettings → WordPress option
   ├── Test Connection → Provider::testConnection()
   └── Send Test SMS → SMS Core → Provider::send()
        ↓
ProviderDefinitions / GatewayConfig
        ↓
Generic HTTP/REST adapter

## Menu

- Dashboard
- Gateway
- Providers
- Logs

License, Updates, Integrations, and other commercial controls remain reserved for later phases.

## Provider Configuration

The Providers screen is definition-driven and currently exposes the Generic HTTP/REST adapter with:

- Endpoint
- HTTP method: GET, POST, PUT, PATCH
- Sender
- Test recipient
- Timeout: 1–120 seconds
- Authentication: None, API Key, Bearer Token, Basic Authentication, Custom Header, Query Parameter
- Headers and query parameters
- JSON request body template
- Response success and message-ID mapping
- Dedicated connection-test endpoint and method

Supported request placeholders are {{mobile}}, {{message}}, {{sender}}, and {{api_key}}.

## Persistence and secret boundary

AdminSettings is the WordPress option storage boundary. GatewayConfig validates and normalizes the provider configuration before it is persisted.

Read-only configuration uses a safe representation. Credentials are replaced with a stable masked sentinel in the UI. Submitting an unchanged masked credential preserves the stored value; supplying a new credential replaces it.

Credentials are not intentionally included in admin notices, normalized test results, or read-only dashboard data.

## Action security

State-changing actions are:

- save_gateway
- test_connection
- send_test_sms

Each request is protected by manage_options and the WordPress gateway nonce.

Saving configuration does not execute a network request.

## Test Connection

Test Connection uses the provider's dedicated connection configuration. For Generic HTTP/REST it must have its own connection endpoint; the SMS send endpoint is never used as a fallback.

The Generic HTTP provider can reuse configured authentication and common headers/query parameters for the connection request, while keeping the operation separate from SMS sending.

A successful connection test reports that no SMS was sent.

## Send Test SMS

Send Test SMS is an explicit action. It requires the stored test recipient, registers the selected provider in the provider registry, and routes the message through SMS::send().

The fixed test message is:

WP Universal SMS connection test.

Only a normalized success/failure message is shown to the administrator.

## UI direction

The UI is card-based, responsive, and RTL-first. It uses WordPress Admin controls rather than replacing the WordPress admin shell. JavaScript is progressive enhancement for authentication field visibility and repeatable header/query rows; basic form submission remains available without JavaScript.

## Extension boundary

ProviderDefinitions describes adapter capabilities. GatewayConfig owns validation/normalization, while AdminActions owns the WordPress action boundary. This separation allows future SOAP, provider presets, GSM Agent, multi-gateway, and licensing work without moving transport logic into page rendering.
