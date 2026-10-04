# Admin UI Architecture

Phase 2 introduces the WordPress Admin presentation layer without moving provider logic into the UI.

WordPress Admin
   ↓
AdminMenu
   ├── DashboardPage
   ├── GatewayPage
   ├── ProvidersPage
   └── LogsPage
        ↓
AdminSettings / ProviderDefinitions
        ↓
SMS Core + Provider adapters

## Menu
- Dashboard
- Gateway
- Providers
- Logs

License, Updates, Integrations, and other commercial controls remain reserved for later phases.

## Security boundary
All mutating admin actions require the manage_options capability and a WordPress nonce. Secret configuration values are retained in the settings store but excluded from the dashboard read model.

## UI direction
The UI is card-based, responsive, and RTL-first. It uses WordPress Admin controls rather than replacing the WordPress admin shell.

## Extension boundary
ProviderDefinitions describes available adapter capabilities. This lets future provider presets and the GSM Agent reuse the same provider-selection surface without changing the dashboard structure.