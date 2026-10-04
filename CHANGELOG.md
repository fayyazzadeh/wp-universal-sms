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

### Changed

- Plugin bootstrap now registers admin menu, assets, and admin actions.

### Security

- Gateway mutations require `manage_options` and a WordPress nonce.
- Secret configuration values are excluded from the dashboard read model.

See `docs/releases/v0.2.0-beta.md` for the full release report.

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

See `docs/releases/v0.1.0-beta.md` for the full release report.
