# GSM Agent Phase 3 Implementation Plan

> **For agentic workers:** Use the host's available task-by-task implementation workflow. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first production-oriented GSM Agent foundation in Python that can securely accept SMS jobs from WP Universal SMS, persist and retry them locally in SQLite, communicate with USB/COM GSM modems through AT commands, and expose a safe path for inbound SMS back to WordPress.

**Architecture:** The Agent is an independent FastAPI service under `gsm-agent/`, with a transport/API layer separated from the persistent queue, message state machine, modem abstraction, and platform-specific serial implementation. WordPress communicates with the Agent over authenticated HTTPS; for the user's shared-host deployment, a Raspberry Pi behind MikroTik NAT is a first-class deployment mode, while inbound SMS is delivered from the Agent to a WordPress webhook. The core remains independent of WordPress and network topology.

**Tech Stack:** Python 3.11+ (implementation target), FastAPI, Pydantic, SQLite, SQLAlchemy 2.x, pySerial, pytest, httpx, Uvicorn, and GitHub Actions. SQLAlchemy is an engineering recommendation for transactional persistence and testability; SQLite remains the only required database.

## Global Constraints

- GSM Agent is independent of WordPress and must remain usable by other clients such as a CRM, FastAPI application, or n8n.
- USB/COM GSM modem communication uses AT commands through a modem abstraction; business logic must never depend directly on pySerial.
- Persistent local queue is mandatory. A queued message must survive Agent restart.
- Core outbound states are `queued`, `sending`, `sent`, `delivered`, `failed`, `failed_permanently`, `pending`, and `unknown`.
- `sent` means the modem/transport accepted the message for transmission. `delivered` is only recorded when a reliable delivery report is available.
- Retry classification must distinguish retryable failures, pending/state-dependent conditions, permanent failures, and unknown transmission outcomes.
- Unknown transmission outcomes must never trigger a blind resend.
- Every outbound request has a client request/idempotency identifier; duplicate submissions with the same identifier must not create duplicate logical messages.
- Pairing and authenticated tokens are required before protected operations can be used.
- Cryptographically secure token generation, token replacement/revocation, rate limiting, audit logging, and secret-safe logs are required.
- HTTPS is required for non-local network communication.
- The Agent must never expose an unauthenticated public SMS-sending endpoint.
- Administrative operations and normal message operations must have separate authorization boundaries.
- The user's deployment scenario is supported explicitly: WordPress on shared hosting such as Netafraz, public static IP at home, MikroTik NAT, Raspberry Pi running the Agent, USB GSM modem, outbound WordPress → Agent, and inbound Agent → WordPress webhook.
- For public deployment, TLS should terminate on the Raspberry Pi or a reverse proxy in front of the Agent. A DNS hostname is recommended over using a bare public IP so a normal publicly trusted TLS certificate can be issued.
- MikroTik is treated as network/firewall/NAT infrastructure, not as part of GSM message processing.
- Public exposure must be limited to the required HTTPS service port; Agent admin/state endpoints must remain authenticated and must not leak credentials.
- Inbound SMS is architecturally supported in Phase 3 and must be normalized/persisted, while a complete inbound automation UI remains out of scope.
- Multi-modem readiness is required in the internal data model even though the initial operational UX may use one modem.
- Windows, Linux, and Raspberry Pi are supported targets; platform-specific service installation and serial permissions stay outside the core domain logic.
- Real hardware tests are isolated from CI; CI uses a fake serial/modem transport.
- Existing WP Universal SMS PHP code, Composer tooling, and PHPUnit tests must continue to pass unchanged unless an explicit integration boundary requires a change.
- The implementation should be developed in small independently testable commits.

---

### Task 1: Establish the standalone GSM Agent package and domain contracts

**Files:**
- Create: `gsm-agent/pyproject.toml`
- Create: `gsm-agent/README.md`
- Create: `gsm-agent/src/gsm_agent/__init__.py`
- Create: `gsm-agent/src/gsm_agent/domain/models.py`
- Create: `gsm-agent/src/gsm_agent/domain/enums.py`
- Create: `gsm-agent/src/gsm_agent/domain/errors.py`
- Create: `gsm-agent/src/gsm_agent/domain/interfaces.py`
- Create: `gsm-agent/tests/unit/test_domain_models.py`
- Create: `gsm-agent/tests/unit/test_state_machine.py`
- Modify: `docs/specs/2026-10-05-gsm-agent-design.md` to record the concrete public-HTTPS/NAT and WordPress-webhook deployment mode already approved for Phase 3.
- Create: `gsm-agent/.gitignore`

**Interfaces:**
- Produces `MessageStatus`, `MessageRecord`, `MessageAttempt`, `ModemStatus`, `PairingRecord), and normalized inbound-message types.
- Produces `MessageStateMachine.can_transition(current, target) -> bool`.
- Produces `MessageStateMachine.transition(current, target) -> MessageStatus`, raising a domain error for illegal transitions.
- Produces modem boundary protocols such as `SerialTransport`, `ModemAdapter`, and `ModemManager` without importing pySerial into domain code.
- Produces `Clock` and identifier-generation seams so time/idempotency behavior is deterministic in tests.

- [ ] **Step 1: Add the focused failing tests**
  - Verify every supported message status can be represented.
  - Verify legal transitions such as `queued → sending → sent`, `sending → failed`, `sending → unknown`, and `sent → delivered`.
  - Verify illegal transitions such as `failed_permanently → sending` are rejected.
  - Verify message records contain a stable internal message ID and client request ID.
  - Verify modem and inbound-message models do not require a concrete serial implementation.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/unit/test_domain_models.py tests/unit/test_state_machine.py -q`
  Expected: collection/import failures because the domain package and contracts do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Define enums and immutable/read-oriented Pydantic/domain models for the normalized state.
  - Define the state-transition table explicitly instead of encoding transitions as scattered conditionals.
  - Define protocol interfaces for storage, serial transport, modem adapter, modem manager, clock, and ID generation.
  - Keep domain modules independent of FastAPI, SQLite, and pySerial.
  - Use a generated internal Message ID distinct from the caller's idempotency key.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/unit/test_domain_models.py tests/unit/test_state_machine.py -q`
  Expected: all focused tests pass.

- [ ] **Step 5: Run the affected integration check**
  Run: `cd gsm-agent && python -m pytest -q`
  Expected: the complete initial Agent test suite passes.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent docs/specs/2026-10-05-gsm-agent-design.md && git commit -m "feat: establish GSM Agent domain contracts"`

---

### Task 2: Implement transactional SQLite persistence, queue semantics, idempotency, and retry policy

**Files:**
- Create: `gsm-agent/src/gsm_agent/storage/database.py`
- Create: `gsm-agent/src/gsm_agent/storage/models.py`
- Create: `gsm-agent/src/gsm_agent/storage/repositories.py`
- Create: `gsm-agent/src/gsm_agent/queue/service.py`
- Create: `gsm-agent/src/gsm_agent/queue/retry.py`
- Create: `gsm-agent/src/gsm_agent/queue/worker.py`
- Create: `gsm-agent/tests/integration/test_sqlite_persistence.py`
- Create: `gsm-agent/tests/unit/test_retry_policy.py`
- Create: `gsm-agent/tests/unit/test_idempotency.py`
- Create: `gsm-agent/tests/integration/test_queue_recovery.py`

**Interfaces:**
- Consumes `MessageRecord`, `MessageAttempt`, `MessageStatus`, `Clock`, and storage protocols from Task 1.
- Produces `MessageRepository.create_or_get_by_request_id(...)`, `get(message_id)`, `claim_next_eligible(now)`, `record_attempt(...)`, `transition(...)`, and `release_for_retry(...)`.
- Produces `RetryPolicy.classify(error) -> RetryDecision` and `RetryPolicy.next_attempt_at(attempt_count, now) -> datetime`.
- Produces `QueueService.submit(request) -> MessageRecord` and `QueueService.process_one() -> ProcessingResult`.

- [ ] **Step 1: Add the focused failing tests**
  - Insert a message and verify it remains present after closing and reopening SQLite.
  - Submit the same client request ID twice and verify both calls return the same logical Message ID and only one message row exists.
  - Verify `queued` work can be claimed exactly once while claimed.
  - Verify retryable errors calculate bounded exponential backoff.
  - Verify permanent errors move directly to `failed_permanently`.
  - Verify unknown transmission outcomes move to `unknown` and are not automatically requeued.
  - Verify a message stuck in `sending` can be recovered according to an explicit worker lease/timeout policy without duplicating a completed attempt.
  - Verify transaction rollback leaves the message state unchanged when persistence of an attempt fails.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_sqlite_persistence.py tests/unit/test_retry_policy.py tests/unit/test_idempotency.py tests/integration/test_queue_recovery.py -q`
  Expected: failures because repository, retry, and queue implementations do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Use SQLite transactions for message creation, idempotency lookup, attempt creation, and state changes.
  - Create tables for messages, message_attempts, modems, pairings, settings, audit_logs, incoming_messages, and delivery_reports, with the future-facing tables present but only the required Phase 3 columns exposed through active code.
  - Add a unique database constraint on client request ID within the logical sender/client scope selected by the API contract.
  - Store attempt outcome, timestamps, modem ID, safe error classification, and transport/provider response metadata without credentials.
  - Implement bounded exponential backoff with a fixed maximum delay.
  - Keep unknown outcomes out of automatic retry.
  - Use a worker lease/claim mechanism so two workers cannot process the same queued record simultaneously.
  - Make queue recovery deterministic after process restart.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_sqlite_persistence.py tests/unit/test_retry_policy.py tests/unit/test_idempotency.py tests/integration/test_queue_recovery.py -q`
  Expected: all focused persistence, retry, idempotency, and recovery tests pass.

- [ ] **Step 5: Run the affected integration check**
  Run: `cd gsm-agent && python -m pytest -q`
  Expected: all Agent tests pass.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent && git commit -m "feat: add persistent GSM message queue"`

---

### Task 3: Implement modem abstraction, AT command engine, fake modem, and USB/COM transport

**Files:**
- Create: `gsm-agent/src/gsm_agent/modem/transport.py`
- Create: `gsm-agent/src/gsm_agent/modem/at.py`
- Create: `gsm-agent/src/gsm_agent/modem/adapter.py`
- Create: `gsm-agent/src/gsm_agent/modem/manager.py`
- Create: `gsm-agent/src/gsm_agent/modem/pyserial_transport.py`
- Create: `gsm-agent/src/gsm_agent/modem/errors.py`
- Create: `gsm-agent/tests/unit/test_at_parser.py`
- Create: `gsm-agent/tests/unit/test_modem_adapter.py`
- Create: `gsm-agent/tests/integration/test_fake_modem.py`

**Interfaces:**
- Consumes `SerialTransport` and modem-domain contracts from Task 1.
- Produces `ATCommandEngine.execute(command, timeout) -> ATResponse`.
- Produces `GSMModem.send_sms(destination, text) -> ModemSendResult`.
- Produces `GSMModem.get_status() -> ModemStatus`.
- Produces `ModemManager.send(modem_id, destination, text)` and modem discovery/status methods.
- `PySerialTransport` is the only module permitted to depend directly on pySerial.

- [ ] **Step 1: Add the focused failing tests**
  - Parse successful AT responses, command errors, timeouts, and unsolicited lines.
  - Verify SMS submission performs the modem command sequence without coupling the queue to raw AT strings.
  - Verify modem responses normalize into success, retryable failure, permanent failure, or unknown outcome.
  - Verify a fake serial transport can simulate a successful SMS and each major failure class.
  - Verify modem disconnect/readiness states are distinguishable from SMS send failures.
  - Verify modem manager can represent multiple modem identities even if only one is active in the initial configuration.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/unit/test_at_parser.py tests/unit/test_modem_adapter.py tests/integration/test_fake_modem.py -q`
  Expected: failures because the AT engine, adapter, and fake transport do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Keep AT parsing deterministic and testable from strings/bytes.
  - Implement the SMS send sequence behind `GSMModem`; the exact modem command sequence must be isolated in the adapter so different modem models can later be supported.
  - Normalize modem errors into the retry classification used by Task 2.
  - Implement pySerial port opening, baud rate, read/write timeout, and close behavior behind `PySerialTransport`.
  - Ensure disconnects and ambiguous modem responses can produce `unknown` rather than pretending the SMS failed.
  - Do not put platform-specific device discovery into the queue or API layer.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/unit/test_at_parser.py tests/unit/test_modem_adapter.py tests/integration/test_fake_modem.py -q`
  Expected: all focused modem tests pass without real hardware.

- [ ] **Step 5: Run the affected integration check**
  Run: `cd gsm-agent && python -m pytest -q`
  Expected: all Agent tests pass.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent && git commit -m "feat: add GSM modem abstraction and AT transport"`

---

### Task 4: Build authenticated FastAPI API, pairing, rate limiting, audit, and outbound message flow

**Files:**
- Create: `gsm-agent/src/gsm_agent/api/app.py`
- Create: `gsm-agent/src/gsm_agent/api/dependencies.py`
- Create: `gsm-agent/src/gsm_agent/api/auth.py`
- Create: `gsm-agent/src/gsm_agent/api/schemas.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/health.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/pairing.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/messages.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/modems.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/status.py`
- Create: `gsm-agent/src/gsm_agent/api/rate_limit.py`
- Create: `gsm-agent/src/gsm_agent/security/secrets.py`
- Create: `gsm-agent/src/gsm_agent/audit/service.py`
- Create: `gsm-agent/tests/api/test_auth.py`
- Create: `gsm-agent/tests/api/test_pairing.py`
- Create: `gsm-agent/tests/api/test_messages.py`
- Create: `gsm-agent/tests/api/test_rate_limit.py`
- Create: `gsm-agent/tests/api/test_safe_responses.py`

**Interfaces:**
- Produces:
  - `GET /api/v1/health`
  - `GET /api/v1/status`
  - `POST /api/v1/pair`
  - `POST /api/v1/messages`
  - `GET /api/v1/messages/{message_id}`
  - `GET /api/v1/modems`
  - `GET /api/v1/audit`
- `POST /api/v1/messages` accepts destination, message body, client request ID, and optional sender metadata; it returns a stable message ID and current queue state rather than waiting for GSM delivery.
- Pairing produces a high-entropy bearer token after a short-lived pairing authorization flow.
- Protected endpoints accept the bearer token and reject missing, invalid, revoked, or expired authorization material according to the selected token policy.
- Audit service records safe event metadata and never persists raw authorization headers or credentials.

- [ ] **Step 1: Add the focused failing tests**
  - Verify health is available without SMS credentials but exposes no secrets.
  - Verify protected message submission fails without authentication.
  - Verify valid pairing establishes a usable token.
  - Verify revoked/invalid tokens are rejected.
  - Verify repeated client request IDs return the existing message rather than enqueueing a second message.
  - Verify message submission returns quickly after durable queue insertion.
  - Verify rate limiting blocks excessive message submissions with a machine-readable error.
  - Verify status and modem responses never contain API tokens, pairing secrets, or raw authorization headers.
  - Verify audit records contain event type, timestamp, request/message IDs, and safe outcome information.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/api/test_auth.py tests/api/test_pairing.py tests/api/test_messages.py tests/api/test_rate_limit.py tests/api/test_safe_responses.py -q`
  Expected: failures because the FastAPI application and security boundary do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Create the FastAPI application with dependency-injected repositories, queue service, modem manager, and configuration.
  - Implement pairing as a deliberately narrow onboarding path; pairing credentials must be short-lived and rate-limited.
  - Hash or otherwise store only non-recoverable representations of long-lived authentication secrets where practical; the runtime must still be able to validate presented tokens.
  - Add explicit scopes/roles so message clients cannot automatically use administrative endpoints.
  - Add request IDs to API responses and audit records.
  - Persist the message before returning success from `POST /api/v1/messages`.
  - Return normalized JSON error objects with stable error codes.
  - Never include message credentials, pairing codes, bearer tokens, or raw modem secrets in response bodies.
  - Add an application-level rate limiter backed by the local SQLite store or a bounded in-memory mechanism appropriate for a single Agent process; the chosen mechanism must be deterministic in tests and must not silently disappear for a configured public endpoint.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/api/test_auth.py tests/api/test_pairing.py tests/api/test_messages.py tests/api/test_rate_limit.py tests/api/test_safe_responses.py -q`
  Expected: all focused API/security tests pass.

- [ ] **Step 5: Run the affected integration check**
  Run: `cd gsm-agent && python -m pytest -q`
  Expected: all Agent tests pass.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent && git commit -m "feat: add authenticated GSM Agent API"`

---

### Task 5: Connect the API queue to the modem worker and implement inbound SMS persistence/webhook delivery

**Files:**
- Modify: `gsm-agent/src/gsm_agent/queue/worker.py`
- Create: `gsm-agent/src/gsm_agent/inbound/models.py`
- Create: `gsm-agent/src/gsm_agent/inbound/service.py`
- Create: `gsm-agent/src/gsm_agent/inbound/webhook.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/incoming.py`
- Create: `gsm-agent/src/gsm_agent/api/routes/delivery.py`
- Create: `gsm-agent/tests/integration/test_end_to_end_queue.py`
- Create: `gsm-agent/tests/integration/test_inbound_sms.py`
- Create: `gsm-agent/tests/integration/test_inbound_webhook.py`
- Create: `gsm-agent/tests/integration/test_unknown_outcome.py`

**Interfaces:**
- Consumes `QueueService`, `ModemManager`, `MessageRepository`, and `AuditService`.
- Produces a worker loop that claims queued messages, sends through `ModemManager`, records attempts, and applies the state machine/retry policy.
- Produces normalized `IncomingMessage` persistence.
- Produces authenticated Agent-to-WordPress webhook delivery for inbound SMS.
- Produces `GET /api/v1/incoming` and a future-capable delivery-report boundary without making DLR a required hardware capability.

- [ ] **Step 1: Add the focused failing tests**
  - Verify a queued message reaches the fake modem and becomes `sent` on accepted transmission.
  - Verify retryable modem failure returns the message to an eligible retry state after the configured backoff.
  - Verify permanent modem failure becomes `failed_permanently`.
  - Verify unknown modem outcome remains `unknown` and is not blindly retried.
  - Verify inbound SMS is normalized and persisted with sender, body, timestamp, modem ID, and unique event identity.
  - Verify duplicate inbound events do not create duplicate stored messages.
  - Verify the Agent can POST an inbound webhook to a fake WordPress endpoint with authentication/signature metadata and a request ID.
  - Verify webhook retry behavior is bounded and does not lose the persisted inbound event if WordPress is temporarily unavailable.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_end_to_end_queue.py tests/integration/test_inbound_sms.py tests/integration/test_inbound_webhook.py tests/integration/test_unknown_outcome.py -q`
  Expected: failures because the worker integration and inbound delivery path do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Run the worker as an explicit application service rather than blocking FastAPI request handlers.
  - Record an attempt before and after modem transmission so the audit trail can distinguish accepted, failed, pending, and unknown outcomes.
  - Do not resend an unknown outcome automatically.
  - Persist inbound SMS before attempting webhook delivery.
  - Use an authenticated webhook request from Agent to WordPress. The implementation should use a dedicated webhook secret and include a timestamp plus unique request/event ID so WordPress can reject stale/replayed requests.
  - Keep webhook delivery asynchronous from modem reception so a WordPress outage cannot prevent local receipt/persistence.
  - Keep the existing `GET /api/v1/incoming` endpoint authenticated and safe.
  - Keep delivery reports as an extension point; do not claim `delivered` unless the modem provides a trustworthy DLR.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_end_to_end_queue.py tests/integration/test_inbound_sms.py tests/integration/test_inbound_webhook.py tests/integration/test_unknown_outcome.py -q`
  Expected: all focused queue/modem/inbound tests pass.

- [ ] **Step 5: Run the affected integration check**
  Run: `cd gsm-agent && python -m pytest -q`
  Expected: all Agent tests pass.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent && git commit -m "feat: connect GSM queue and inbound webhook"`

---

### Task 6: Add deployment packaging, configuration, CI, documentation, and hardware-validation runbooks

**Files:**
- Create: `gsm-agent/src/gsm_agent/config.py`
- Create: `gsm-agent/src/gsm_agent/main.py`
- Create: `gsm-agent/tests/integration/test_application_startup.py`
- Create: `.github/workflows/gsm-agent.yml`
- Create: `gsm-agent/docs/deployment-raspberry-pi.md`
- Create: `gsm-agent/docs/deployment-windows.md`
- Create: `gsm-agent/docs/deployment-linux.md`
- Create: `gsm-agent/docs/network-public-https-mikrotik.md`
- Create: `gsm-agent/docs/modem-validation.md`
- Create: `gsm-agent/docs/api.md`
- Create: `gsm-agent/docs/security.md`
- Modify: `README.md` to link the GSM Agent documentation and clarify the two-product boundary.
- Modify: `CHANGELOG.md` with the Phase 3 Agent milestone.

**Interfaces:**
- Produces a runnable entry point such as `python -m gsm_agent` or an equivalent console script.
- Configuration must support database path, bind address, port, logging level, pairing policy, authentication secret storage, modem configuration, webhook target, and webhook secret without hard-coding customer values.
- CI must run Agent unit/integration tests without requiring a physical modem.
- Deployment documentation must describe:
  - Raspberry Pi + USB modem
  - Windows serial/service deployment
  - Linux serial/service deployment
  - MikroTik port forwarding/firewall
  - HTTPS/TLS termination
  - DNS hostname recommendation
  - WordPress webhook configuration
  - token/pairing procedure
  - backup/recovery of SQLite
  - safe log handling

- [ ] **Step 1: Add the focused failing tests**
  - Verify the application can start with an isolated temporary SQLite database.
  - Verify configuration rejects structurally invalid runtime values while preserving the externally defined API behavior.
  - Verify health reports Agent version, uptime, queue depth, and modem readiness without exposing secrets.
  - Verify startup does not require a physical modem when running in test mode.
  - Verify CI configuration invokes the same pytest command used locally.

- [ ] **Step 2: Verify the relevant failure**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_application_startup.py -q`
  Expected: failures because the application entry point and runtime configuration do not yet exist.

- [ ] **Step 3: Implement the minimum behavior**
  - Add a single application composition root that wires configuration, database, repositories, modem manager, queue worker, API, and audit service.
  - Provide an explicit fake-modem/test mode for CI.
  - Keep secrets in environment/configuration inputs and never commit real values.
  - Add GitHub Actions for the Agent on supported Python versions, running formatting/static checks only if they are introduced as project dependencies and do not replace pytest.
  - Document a production topology equivalent to:
    `WordPress shared hosting → HTTPS → static public IP → MikroTik NAT/firewall → Raspberry Pi Agent → USB GSM modem`.
  - Document inbound flow:
    `GSM modem → Agent → authenticated HTTPS webhook → WordPress`.
  - Recommend a DNS hostname such as `sms.example.com` pointing to the static public IP, with TLS terminated on the Raspberry Pi or a reverse proxy.
  - Document that MikroTik should forward only the required TCP port and that the Agent's protected API must remain authenticated.
  - Document hardware validation as a separate manual runbook and never make CI depend on the real modem.

- [ ] **Step 4: Verify the focused pass**
  Run: `cd gsm-agent && python -m pytest tests/integration/test_application_startup.py -q`
  Expected: startup/configuration tests pass.

- [ ] **Step 5: Run the affected integration check**
  Run:
  `cd gsm-agent && python -m pytest -q`
  `cd .. && composer test`
  Expected: all GSM Agent tests pass and the existing WP Universal SMS PHPUnit suite remains green.

- [ ] **Step 6: Commit the passing deliverable**
  `git add gsm-agent .github/workflows/gsm-agent.yml README.md CHANGELOG.md && git commit -m "feat: package and document GSM Agent"`

---

## End-to-end validation gate

After all six tasks are merged:

1. Run `cd gsm-agent && python -m pytest -q`.
   Expected: all automated Agent tests pass with no physical modem required.
2. Run `composer test` from the repository root.
   Expected: the existing WordPress plugin suite remains green.
3. Run the Agent locally in fake-modem mode.
   Expected: authenticated `POST /api/v1/messages` returns a durable message ID/state and the fake modem records the send.
4. Run the Agent on the Raspberry Pi with the real USB/COM modem.
   Expected: modem discovery/status succeeds and a real test SMS transitions at least to `sent`.
5. Configure the MikroTik NAT/firewall and public DNS/TLS endpoint.
   Expected: only the intended HTTPS service is reachable externally; unauthenticated message submission is rejected.
6. Configure the WordPress webhook.
   Expected: an inbound SMS is persisted by the Agent and delivered to WordPress once with replay protection.
7. Force temporary modem/network failures.
   Expected: retryable messages remain durable and retry according to the bounded policy; unknown outcomes are never blindly resent.
8. Restart the Raspberry Pi/Agent while messages are queued.
   Expected: queued messages and attempt history survive restart.
9. Confirm no credentials, bearer tokens, pairing codes, or raw authorization headers appear in normal logs or API responses.

## Explicit unresolved product decisions

These are the remaining decisions that can change implementation details and therefore must be confirmed before the corresponding task is finalized:

1. **Public TLS termination:** Recommended: Caddy on Raspberry Pi in front of Uvicorn, with the Agent bound to localhost/LAN. Alternative: Agent-native TLS. The implementation plan assumes a reverse-proxy-ready Agent and documents Caddy as the recommended deployment path; this does not change the core API.
2. **Authentication secret storage:** The Agent must validate long-lived tokens, but the exact local secret-storage mechanism (hashed token lookup versus encrypted local secret store) should be selected during Task 4 based on the final pairing/token UX.
3. **WordPress inbound webhook authentication:** Recommended: dedicated shared secret + timestamp + request/event ID, with HMAC signing and replay-window validation. The exact signature header names and canonical signing string must be fixed before the webhook is implemented in Task 5.
4. **Single public endpoint versus separated public/admin endpoints:** Recommended: one HTTPS listener with route-level authorization plus optional firewall/interface binding; a second admin listener can be added only if deployment evidence requires it.
5. **Initial modem model(s):** The modem abstraction is hardware-neutral, but real AT behavior varies. The first physical modem model and baud/initialization profile must be selected before hardware validation; no model-specific assumptions should be embedded in the core.

