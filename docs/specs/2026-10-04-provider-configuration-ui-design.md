# Provider Configuration UI Design Specification

**Status:** Draft for review  
**Date:** 2026-10-04  
**Scope:** Phase 2 — Provider Configuration UI  
**Branch:** `feature/phase-2-ui-ux`

## 1. Goal

Replace the current Provider page's display-only adapter cards with a real configuration interface for one active customer-owned SMS gateway.

The interface must configure a generic HTTP/REST provider without coupling the UI to a specific SMS vendor. The same configuration model must remain extensible for future provider presets.

## 2. Scope

### In scope

- One active gateway at a time.
- Generic HTTP/REST provider.
- Endpoint and HTTP method.
- Authentication configuration.
- Headers and query parameters.
- Request body/template.
- Request placeholders.
- Response mapping.
- Connection timeout.
- Safe Test Connection action.
- Send Test SMS action.
- Secure secret handling.
- WordPress capability and nonce protection.
- Server-side validation and human-readable errors.
- RTL responsive admin UI.
- Persistent gateway configuration using the existing `AdminSettings` abstraction.
- Unit tests for configuration normalization/validation and secret behavior.

### Out of scope

- Multiple active providers.
- Automatic failover.
- GSM Agent.
- License management.
- Provider-specific presets beyond the generic definition.
- Operational log persistence.
- WooCommerce/OTP/form integrations.

## 3. Architecture Decision

The plugin uses a **single active Gateway** model.

The Provider Configuration page edits the configuration of that gateway. The selected provider definition determines which protocol/authentication options are available.

Target flow:

WordPress Admin → Provider Configuration → SMS Core → Provider Adapter → Customer SMS API

The UI must not bypass the SMS Core or directly implement provider-specific sending logic.

## 4. UI Structure

Use sections/accordion rather than one long form.

### Basic Configuration

- Provider Type
- Endpoint
- HTTP Method
- Sender

### Authentication

- Authentication type
- Credentials appropriate to the selected authentication mode

Supported authentication modes:

- None
- API Key
- Bearer Token
- Basic Authentication
- Custom Header
- Query Parameter

### Request

- Headers
- Query parameters
- Body format
- Body template

Supported request placeholders:

- `{{mobile}}`
- `{{message}}`
- `{{sender}}`
- `{{api_key}}`

### Mapping

Request mapping must make the relationship between internal SMS fields and provider request fields explicit.

Response mapping must support:

- Success indicator/value
- Message ID
- Error/message field

### Connection

- Timeout
- Test Connection
- Send Test SMS

The two test actions are intentionally separate.

## 5. Provider Definition

The current `ProviderDefinitions` registry remains the source of UI capabilities.

The Generic HTTP/REST definition provides:

- Provider ID: `generic-http`
- Type: `http`
- Methods: GET, POST, PUT, PATCH
- Authentication modes listed above

The UI should consume provider definitions rather than hard-code provider-specific forms.

## 6. Configuration Model

The gateway configuration is conceptually:

```
gateway
├── provider
├── sender
├── endpoint
├── method
├── auth
├── credentials
├── headers
├── query
├── body
├── request
├── response
└── connection
    └── timeout
```

The existing `AdminSettings` class remains the persistence boundary.

The implementation may normalize UI fields into the existing storage shape, but callers outside the admin layer must not need to know UI field names.

## 7. Secret Handling

Secrets include API keys, bearer tokens, passwords, and other credential values.

Rules:

1. Secrets are never returned by the public/read-only gateway summary.
2. Existing secrets are rendered masked.
3. A masked value submitted without an explicit replacement does not overwrite the stored secret.
4. A newly supplied credential replaces the existing credential only after successful validation.
5. Secrets must not appear in dashboard cards, notices, logs, debug output, or test responses.
6. Server-side sanitization is mandatory; client-side validation is supplementary only.

## 8. Validation

Server-side validation is authoritative.

Required validation includes:

- Provider ID must be a registered provider.
- HTTP method must be supported by the selected provider definition.
- Endpoint must be a valid HTTP/HTTPS URL.
- Timeout must be a positive bounded integer.
- Authentication-specific fields must satisfy their selected mode.
- Header/query names must be non-empty after sanitization.
- Request body must be valid for the selected body format.
- Response mapping fields must have valid paths/keys.

Invalid configuration must not be persisted.

## 9. Test Connection

Test Connection verifies gateway reachability/configuration without sending an SMS.

Requirements:

- It must use the provider's dedicated connection-test mechanism.
- It must never call the SMS send operation.
- It must use the saved/validated gateway configuration.
- It must return a normalized success/failure result.
- Credentials must never be exposed in the result.
- Network/provider failures must become human-readable admin notices.

If the provider has no dedicated connection endpoint, the generic implementation must use a safe non-SMS request configured for connection testing rather than treating the send endpoint as a connection test.

## 10. Send Test SMS

Send Test SMS is explicitly destructive/externally observable because it sends a real message.

Requirements:

- Require a configured test recipient.
- Require a valid saved gateway configuration.
- Require an explicit user action.
- Use the SMS Core/provider adapter path.
- Display normalized success/failure.
- Never include credentials in the result.
- Never silently send when saving configuration or testing connection.

## 11. Security

All state-changing admin requests must require:

- `manage_options` capability.
- WordPress nonce verification.

All configuration values must be sanitized server-side.

All rendered values must be escaped for their output context.

The implementation must not trust hidden form fields or JavaScript-only restrictions.

## 12. UX Requirements

- RTL-first.
- Responsive within the WordPress admin content area.
- Clear section headings and descriptions.
- Save action remains visually distinct from test actions.
- Test Connection and Send Test SMS are visually differentiated.
- Validation errors appear near the relevant field where practical.
- Global success/failure notices use WordPress-compatible admin notices.
- Sensitive fields use password-style inputs.
- Existing configuration should be easy to inspect without exposing secrets.

The page must remain usable without JavaScript for saving the basic configuration. JavaScript may improve accordion behavior, dynamic fields, and test actions.

## 13. Error Handling

Errors are normalized into safe administrator-facing messages.

The UI must distinguish at least:

- Validation error.
- Authentication/configuration error.
- Connection/network error.
- Provider HTTP error.
- Provider response/mapping error.
- Test SMS failure.

Raw credentials, authorization headers, and sensitive request values must never be included in displayed diagnostics.

## 14. Testing Strategy

Tests should cover:

1. Provider definition capabilities.
2. Configuration validation.
3. HTTP method validation.
4. Endpoint validation.
5. Timeout normalization.
6. Authentication-specific configuration.
7. Secret preservation when masked values are submitted.
8. Secret replacement when a new value is supplied.
9. Rejection of invalid configuration.
10. Test Connection never invokes SMS sending.
11. Send Test SMS uses the configured test recipient.
12. Admin authorization/nonce boundary where practical.

Existing Phase 1 provider tests remain unchanged and must continue passing.

## 15. Acceptance Criteria

The feature is ready for review when:

- An administrator can configure a Generic HTTP/REST gateway from WordPress.
- The configuration persists across page reloads.
- Existing secrets remain masked and are not accidentally overwritten.
- Endpoint, method, auth, request, mapping, and timeout can be configured.
- Invalid configurations are rejected server-side.
- Test Connection performs no SMS send.
- Send Test SMS sends only when explicitly requested.
- Both actions show safe normalized results.
- Unauthorized requests cannot change gateway configuration.
- Existing Phase 1 tests remain green.
- New Provider Configuration tests are green.
- The UI is RTL and responsive.
- No credential is exposed in UI diagnostics or logs.

## 16. Future Compatibility

The configuration model must allow future provider presets to define their capabilities without requiring a rewrite of the generic configuration architecture.

Future work may add:

- Provider presets.
- Additional protocols such as SOAP.
- Custom API mapping enhancements.
- Multiple gateways/failover.
- GSM Agent.
- License-controlled commercial features.

These are not part of this implementation.

## 17. Review Checklist

Before implementation approval:

- [ ] Single active gateway model confirmed.
- [ ] Accordion/section UI confirmed.
- [ ] Generic HTTP/REST is the first provider.
- [ ] Secret handling rules confirmed.
- [ ] Test Connection is guaranteed not to send SMS.
- [ ] Send Test SMS requires explicit action.
- [ ] Server-side validation/security confirmed.
- [ ] Acceptance criteria confirmed.
