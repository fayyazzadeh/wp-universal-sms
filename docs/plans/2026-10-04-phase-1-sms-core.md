# Phase 1 — SMS Core Implementation Plan

> **For agentic workers:** Use the host's available task-by-task implementation workflow. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the provider-agnostic SMS core and generic API boundary for WP Universal SMS.

**Architecture:** A small core service delegates to adapters implementing `SMSProviderInterface`. Generic HTTP/REST behavior is isolated from WordPress-facing orchestration so future providers and the GSM Agent can reuse the same normalized message contract.

**Tech Stack:** PHP 8.1+, WordPress plugin APIs, PHPUnit, Composer PSR-4 autoloading.

## Global Constraints

- Keep the core provider-agnostic.
- Support REST/HTTP first in the initial implementation; preserve adapter boundaries for SOAP and GSM Agent.
- Never log API keys, bearer tokens, passwords, or authorization headers.
- Normalize provider success/failure into a stable response object.
- Keep public internal SMS calls independent of provider-specific payload formats.
- Maintain documentation and changelog with each release.

---

### Task 1: Plugin foundation and contracts

**Files:** Create `wp-universal-sms.php`, `composer.json`, `src/Contracts/SMSProviderInterface.php`, `src/Contracts/SMSResponse.php`; Test `tests/Unit/Contracts/SMSResponseTest.php`.

**Interfaces:** Produces `SMSProviderInterface` and immutable normalized `SMSResponse`.

- [ ] Add failing response-construction tests for success and failure.
- [ ] Verify the focused PHPUnit command fails because the response class does not exist.
- [ ] Implement the response value object and provider interface.
- [ ] Verify the focused PHPUnit command passes.
- [ ] Run Composer syntax/autoload validation.
- [ ] Commit the passing foundation.

### Task 2: Core dispatcher and provider registry

**Files:** Create `src/Core/SMS.php`, `src/Core/ProviderRegistry.php`; Test `tests/Unit/Core/SMSCoreTest.php`.

**Interfaces:** Consumes `SMSProviderInterface::send()` and produces `SMS::send(string $mobile, string $message): SMSResponse`.

- [ ] Add tests for registered-provider dispatch and missing-provider failure.
- [ ] Verify focused tests fail for the missing dispatcher.
- [ ] Implement registry and dispatcher with one active provider boundary.
- [ ] Verify focused tests pass.
- [ ] Run the full unit suite.
- [ ] Commit the passing core.

### Task 3: Generic REST/HTTP adapter

**Files:** Create `src/Providers/GenericHttpProvider.php`, `src/Providers/RequestTemplate.php`, `src/Providers/ResponseMapper.php`; Test `tests/Unit/Providers/GenericHttpProviderTest.php`.

**Interfaces:** Consumes normalized mobile/message values and provider configuration; produces `SMSResponse`.

- [ ] Add tests for POST body substitution, headers, API-key authentication, successful response mapping, timeout/error normalization, and secret redaction.
- [ ] Verify focused tests fail because the adapter is absent.
- [ ] Implement the minimum request-template and response-mapping behavior.
- [ ] Verify focused tests pass.
- [ ] Run the full suite and PHP syntax checks.
- [ ] Commit the adapter.

### Task 4: WordPress integration and documentation

**Files:** Modify `wp-universal-sms.php`; Create `src/Plugin.php`, `docs/architecture/sms-core.md`, `docs/guides/provider-setup.md`, `docs/releases/v0.1.0-beta.md`; Modify `CHANGELOG.md`; Test `tests/Unit/PluginBootstrapTest.php`.

**Interfaces:** WordPress bootstrap initializes the core without requiring provider credentials. Documentation describes installation, provider configuration, normalized API, and release changes.

- [ ] Add bootstrap test asserting the plugin loads core classes without credentials.
- [ ] Verify focused test fails before bootstrap exists.
- [ ] Implement bootstrap and documentation.
- [ ] Verify focused and full tests pass.
- [ ] Run PHP lint and Composer test command.
- [ ] Commit the release-ready phase.
