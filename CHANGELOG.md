# Changelog

All notable changes to WP Universal SMS are documented here.

## [0.2.0-beta] - 2026-10-04

### Added

- Professional WordPress Admin UI.
- Dashboard, Gateway, Providers, and Logs screens.
- Admin settings boundary.
- Definition-driven provider metadata.
- Responsive RTL-first styling.
- Capability and nonce checks for gateway mutations.
- Secret-safe dashboard read model.
- Real Generic HTTP/REST Provider Configuration UI.
- Endpoint, method, sender, test recipient, timeout, headers, query, body, and response mapping fields.
- Dedicated connection-test configuration.
- Secure credential masking and preservation.
- Explicit Test Connection and Send Test SMS actions.
- GatewayConfig validation/normalization service.
- AdminActions security/action boundary.
- Unit coverage for provider configuration and test actions.

### Changed

- Plugin bootstrap delegates admin POST handling to AdminActions.
- Generic HTTP connection tests can reuse configured authentication without using the SMS send endpoint.
- SMSResponse remains compatible with PHP 8.1.

### Security

- Gateway mutations require manage_options and a WordPress nonce.
- Secret configuration values are excluded from safe read models and are masked in the provider editor.
- Test Connection never sends an SMS.
- Test SMS uses SMS Core and normalized provider responses.

See docs/releases/v0.2.0-beta.md for the full release report.

## [0.1.0-beta] - 2026-10-04

### Added

- Initial WordPress plugin foundation.
- Provider-agnostic SMS provider contract.
- Normalized SMS response model.
- Provider registry and default provider selection.
- Generic HTTP/REST provider with request templating and response mapping.
- Normalized error codes.
- Sanitized logging boundary.
- Composer and PHPUnit configuration.
- Initial architecture and provider documentation.

### Security

- Provider authentication values are excluded from public error messages and diagnostic output.
- Sensitive response fields are redacted from normalized raw data.

See docs/releases/v0.1.0-beta.md for the full release report.
