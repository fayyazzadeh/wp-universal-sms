# Provider Configuration UI Implementation Plan

> **For agentic workers:** Use the host's available task-by-task implementation workflow. Steps use checkbox syntax for tracking.

**Goal:** Turn the Phase 2 Provider page into a secure, real Generic HTTP/REST gateway configuration interface with safe connection testing and explicit test-SMS sending.

**Architecture:** Keep one active gateway and use ProviderDefinitions as the capability source. Keep persistence behind AdminSettings, route real sends through the existing SMS/provider layer, and keep admin rendering/action handling separate from provider transport behavior. Secrets remain masked and are preserved unless a new credential is explicitly supplied.

**Tech Stack:** PHP 8.3, WordPress Admin APIs, existing PHP provider abstraction, PHPUnit 10.5, existing CSS/JS assets, Composer.

## Global Constraints

- One active gateway only.
- Generic HTTP/REST is the first configurable provider.
- Supported methods: GET, POST, PUT, PATCH.
- Supported auth modes: none, API key, bearer token, basic authentication, custom header, query parameter.
- Configuration includes endpoint, method, sender, auth, headers, query, body/template, request/response mapping, and timeout.
- Supported placeholders: {{mobile}}, {{message}}, {{sender}}, {{api_key}}.
- Secrets must never appear in read-only summaries, notices, logs, debug output, or test results.
- Existing masked secrets must not be overwritten unless a new credential is explicitly submitted.
- Invalid configuration must not be persisted.
- Test Connection must never send an SMS.
- Send Test SMS requires explicit action and a configured test recipient.
- All state-changing admin actions require manage_options and WordPress nonce verification.
- The UI is RTL-first and responsive.
- Existing Phase 1 tests must continue to pass.
- Out of scope: multi-provider failover, GSM Agent, licensing, provider presets, operational log persistence, WooCommerce/OTP/form integrations.

---

### Task 1: Introduce a testable gateway configuration model and secure persistence boundary

**Files:**
- Modify: src/Admin/AdminSettings.php
- Create: src/Admin/GatewayConfig.php
- Test: tests/Unit/Admin/AdminSettingsTest.php
- Create: tests/Unit/Admin/GatewayConfigTest.php

**Interfaces:**
- Consumes: existing AdminSettings::getGatewayConfig(), AdminSettings::getRawGatewayConfig(), and AdminSettings::saveGatewayConfig(array $config): void.
- Produces: GatewayConfig::validate(array $input, array $definition): array and a normalized gateway configuration contract used by the Providers page and admin actions.

- [ ] Add focused failing tests.
  - Valid Generic HTTP configuration normalizes provider, endpoint, method, sender, auth, request, response, and timeout.
  - Unsupported provider and HTTP method are rejected.
  - Invalid endpoint and out-of-range timeout are rejected.
  - Masked credential input preserves the stored credential.
  - A new credential replaces the stored credential.
  - Invalid configuration does not reach the storage writer.
- [ ] Verify the relevant failure.
  - Run: vendor/bin/phpunit tests/Unit/Admin/GatewayConfigTest.php tests/Unit/Admin/AdminSettingsTest.php
  - Expected: new tests fail because the configuration normalization/validation contract is not implemented.
- [ ] Implement the minimum behavior.
  - Add a focused GatewayConfig value/normalization service rather than placing validation logic into page rendering.
  - Keep AdminSettings as the WordPress option boundary.
  - Preserve existing stored fields for backward compatibility.
  - Separate safe read data from raw credential-bearing storage.
  - Treat a masked UI credential as preserve-existing-value, while a non-masked non-empty value replaces it.
  - Normalize arrays for headers/query and nested request/response/connection structures.
  - Use the provider definition for supported methods/auth modes.
  - Keep the configured timeout bounded to the range defined by the implementation contract.
  - Do not expose credentials through getGatewayConfig().
- [ ] Verify the focused pass.
  - Run: vendor/bin/phpunit tests/Unit/Admin/GatewayConfigTest.php tests/Unit/Admin/AdminSettingsTest.php
  - Expected: all focused configuration tests pass.
- [ ] Run the affected integration check.
  - Run: composer test
  - Expected: existing Phase 1 and Phase 2 tests remain green.
- [ ] Commit the passing deliverable.
  - git add src/Admin/AdminSettings.php src/Admin/GatewayConfig.php tests/Unit/Admin/AdminSettingsTest.php tests/Unit/Admin/GatewayConfigTest.php
  - git commit -m "feat: add secure gateway configuration model"

---

### Task 2: Build the real Provider Configuration form and responsive section UI

**Files:**
- Modify: src/Admin/Pages/ProvidersPage.php
- Modify: src/Admin/ProviderDefinitions.php
- Modify: assets/admin.css
- Modify: assets/admin.js
- Test: tests/Unit/Admin/ProviderDefinitionsTest.php
- Create: tests/Unit/Admin/ProvidersPageTest.php

**Interfaces:**
- Consumes: ProviderDefinitions::all(): array, AdminSettings safe/raw configuration access, and the normalized gateway configuration contract from Task 1.
- Produces: rendered Provider Configuration sections and field names consumed by the save/test actions.

- [ ] Add focused failing tests.
  - Provider page renders Basic Configuration, Authentication, Request, Mapping, and Connection sections.
  - Generic HTTP provider exposes GET/POST/PUT/PATCH.
  - Auth controls reflect registered auth modes.
  - Existing credentials render masked and never as raw values.
  - Configured endpoint, sender, method, timeout, headers/query/body, and mappings are rendered.
  - Form contains separate Save, Test Connection, and Send Test SMS actions.
- [ ] Verify the relevant failure.
  - Run: vendor/bin/phpunit tests/Unit/Admin/ProvidersPageTest.php tests/Unit/Admin/ProviderDefinitionsTest.php
  - Expected: new page assertions fail against the current display-only Provider page.
- [ ] Implement the minimum behavior.
  - Replace display-only provider cards with the section/accordion configuration form.
  - Keep one active provider selection.
  - Render fields dynamically from ProviderDefinitions where capability choices exist.
  - Render auth-specific fields only for the selected auth mode.
  - Use password-style inputs for secrets and a stable masked sentinel recognized server-side as unchanged.
  - Add editable headers/query rows and request/response mapping fields without vendor coupling.
  - Preserve JavaScript-free basic form submission; use JS for progressive enhancement.
  - Keep Save visually separate from external test actions.
  - Add safe field descriptions explaining placeholders and test behavior.
- [ ] Verify the focused pass.
  - Run: vendor/bin/phpunit tests/Unit/Admin/ProvidersPageTest.php tests/Unit/Admin/ProviderDefinitionsTest.php
  - Expected: all Provider page rendering assertions pass.
- [ ] Run the affected integration check.
  - Run: composer test
  - Expected: all current tests pass.
- [ ] Commit the passing deliverable.
  - git add src/Admin/Pages/ProvidersPage.php src/Admin/ProviderDefinitions.php assets/admin.css assets/admin.js tests/Unit/Admin/ProviderDefinitionsTest.php tests/Unit/Admin/ProvidersPageTest.php
  - git commit -m "feat: add provider configuration UI"

---

### Task 3: Wire secure save, Test Connection, and Send Test SMS actions

**Files:**
- Modify: src/Plugin.php
- Modify: src/Admin/AdminSettings.php if required by Task 1 integration
- Create: src/Admin/AdminActions.php
- Create: tests/Unit/Admin/AdminActionsTest.php
- Modify: src/Providers/GenericHttpProvider.php only where required to preserve the safe connection-test boundary

**Interfaces:**
- Consumes: Provider form field contract from Task 2, normalized gateway configuration from Task 1, existing SMS, ProviderRegistry, GenericHttpProvider::testConnection(): bool, and SMS::send().
- Produces: admin actions for save_gateway, test_connection, and send_test_sms, each returning safe admin-facing results.

- [ ] Add focused failing tests.
  - Unauthorized save/test requests are rejected.
  - Nonce-protected save persists only validated configuration.
  - Test Connection calls provider connection testing and never calls SMS send.
  - Send Test SMS requires a configured test recipient.
  - Send Test SMS routes through the SMS Core/provider adapter.
  - Success and failure operations produce safe messages without credentials.
  - Invalid configuration is rejected before any network operation.
  - Saving configuration alone never invokes either test operation.
- [ ] Verify the relevant failure.
  - Run: vendor/bin/phpunit tests/Unit/Admin/AdminActionsTest.php
  - Expected: new action tests fail because the dedicated action boundary does not exist.
- [ ] Implement the minimum behavior.
  - Extract admin POST dispatch from Plugin::handleAdminActions() into a focused AdminActions boundary if this keeps action responsibilities testable.
  - Keep capability and nonce checks at the state-changing boundary.
  - Validate and persist configuration before reporting success.
  - Test Connection uses the selected provider with saved validated configuration and invokes only testConnection().
  - Send Test SMS uses saved gateway configuration, selected provider adapter, configured test recipient, and a safe test message through SMS Core.
  - Never expose raw provider response credentials in notices.
  - Keep Test Connection and Send Test SMS as separate POST actions.
  - Ensure a missing connection endpoint cannot silently fall back to the SMS send endpoint.
  - Preserve WordPress admin notice behavior already used by the plugin.
- [ ] Verify the focused pass.
  - Run: vendor/bin/phpunit tests/Unit/Admin/AdminActionsTest.php
  - Expected: all admin action tests pass.
- [ ] Run the affected integration check.
  - Run: composer test
  - Expected: all tests pass, including existing Generic HTTP safe-connection coverage.
- [ ] Commit the passing deliverable.
  - git add src/Plugin.php src/Admin/AdminActions.php src/Admin/AdminSettings.php src/Providers/GenericHttpProvider.php tests/Unit/Admin/AdminActionsTest.php
  - git commit -m "feat: add secure provider test actions"

---

### Task 4: Final verification, documentation, and Phase 2 release update

**Files:**
- Modify: docs/architecture/admin-ui.md
- Modify: docs/releases/v0.2.0-beta.md
- Modify: CHANGELOG.md
- Test: existing tests/Unit/**/*.php

**Interfaces:**
- Consumes: completed Provider Configuration UI, secure admin actions, and configuration model.
- Produces: documented configuration behavior and a verified Phase 2 beta state.

- [ ] Confirm tests cover configuration validation, secret preservation, page rendering, Test Connection isolation, Send Test SMS, and authorization boundaries.
- [ ] Run: composer test
  - Expected: PHPUnit reports 0 failures and 0 errors.
- [ ] Run: vendor/bin/phpunit
  - Expected: same passing test count and assertions as the Composer test command.
- [ ] Run: git status --short
  - Expected: only intentional implementation/documentation changes remain.
- [ ] Run: git diff --check
  - Expected: no whitespace errors.
- [ ] Update documentation with the real Provider Configuration flow, storage boundary, secret masking, safe connection test, and explicit test SMS behavior.
- [ ] Update the Phase 2 beta release note and changelog with the completed feature set.
- [ ] Do not claim Phase 2 is complete unless the other Phase 2 scope items are also complete; this plan only completes Provider Configuration UI.
- [ ] Commit the verified documentation/release update.
  - git add docs/architecture/admin-ui.md docs/releases/v0.2.0-beta.md CHANGELOG.md tests
  - git commit -m "docs: document provider configuration beta"

---

## Unresolved externally observable decisions

1. Test message text: requirements specify an explicit test message but not its exact text. The implementation should use one fixed, clearly identifiable test message unless the product chooses a configurable message.
2. Test recipient ownership: the current gateway model already contains test_recipient; this plan treats that stored field as the recipient for Send Test SMS.
3. Connection endpoint availability: Generic HTTP Test Connection requires a dedicated safe connection configuration. The existing provider already models connection.endpoint, so the UI must expose it rather than falling back to the SMS endpoint.
