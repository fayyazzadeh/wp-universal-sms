# GSM Agent — Phase 3 Design Specification

**Date:** 2026-10-05  
**Status:** Approved design  
**Project:** WP Universal SMS

## 1. Purpose

GSM Agent is a standalone SMS gateway service that connects customer-owned GSM modems/SIM cards to applications such as WP Universal SMS. It is independent of WordPress and is designed for Windows, Linux, and Raspberry Pi.

Phase 3 focuses on reliable outbound SMS while preparing the architecture for inbound SMS, delivery reports, multiple modems, and future transports.

## 2. Design Goals

- Standalone service, independent of WordPress.
- USB/COM serial GSM modem support using AT commands.
- Persistent local queue.
- Reliable retry handling with duplicate protection.
- Secure WordPress-to-Agent communication.
- Pairing-based onboarding.
- SQLite local storage.
- Cross-platform core.
- Multi-modem-ready internal architecture.
- Clear message lifecycle and auditability.
- Inbound SMS architecture prepared but not the primary v1 workflow.

## 3. Architecture

WordPress Plugin
      |
   HTTPS / Pairing
      |
      v
+-------------------------+
|       GSM Agent         |
| REST API                |
| Authentication          |
| Pairing                 |
| Queue                   |
| Retry Engine            |
| Message State Machine   |
| Modem Manager           |
| Audit Log               |
| SQLite                  |
+------------+------------+
             |
       Modem Abstraction
             |
       +-----+-----+
       |     |     |
    Modem1 Modem2 Modem3
     USB/COM USB/COM USB/COM
             |
            SIM
             |
          GSM/SMS

The Agent is a service boundary. WordPress is a client/integration layer; it does not own modem state or serial communication.

## 4. Components

### 4.1 REST API
Exposes authenticated operations for pairing, health, message submission, status retrieval, and administrative state.

### 4.2 Authentication and Pairing
The Agent supports secure pairing and token-based authentication. Tokens are high-entropy secrets, replaceable/revocable, and never exposed in ordinary logs.

### 4.3 Message Queue
Messages are persisted before transmission. Queue states survive Agent restarts and temporary network/modem failures.

### 4.4 Retry Engine
Classifies failures into retryable, pending-state, permanent, and unknown outcomes. Uses bounded exponential backoff.

### 4.5 Message State Machine
Core states include:
- queued
- sending
- sent
- delivered
- failed
- failed_permanently
- pending
- unknown

sent means the modem/transport accepted the message for transmission. delivered is only recorded when a reliable delivery report is available.

### 4.6 Modem Manager
Manages one or more modem instances. Each modem has its own identity, serial configuration, readiness state, network state, and operational errors.

### 4.7 Modem Abstraction
The core depends on an interface rather than a platform-specific serial implementation. The implementation handles serial I/O, AT command execution, timeouts, parsing, and modem initialization.

### 4.8 Storage
SQLite is the local persistent store. It holds queue state, attempts, modem metadata, pairing/configuration data, and audit records.

### 4.9 Audit Logging
Operational events are recorded with timestamps, severity, component, request/message identifiers, and safe error information. Credentials and unnecessary sensitive message data are not written to ordinary logs.

## 5. Data Flow

### Outbound

1. WordPress submits an authenticated message.
2. Agent validates the request.
3. Agent creates a unique Message ID.
4. Message is persisted as queued.
5. Queue worker selects eligible work.
6. Modem Manager selects an available modem.
7. Message transitions to sending.
8. Modem Adapter executes the required AT command sequence.
9. Agent classifies the modem result.
10. Message becomes sent, delivered, retry/pending, or failed.

The HTTP request from WordPress does not need to remain open until the GSM network completes delivery.

### Inbound

The modem layer will expose an inbound-message capability even though the first implementation prioritizes outbound SMS. Incoming messages will be normalized into an IncomingMessage model and persisted for future WordPress/webhook workflows.

## 6. REST API Principles

The API is versioned and transport-neutral.

Initial logical endpoints:

- GET /api/v1/health
- GET /api/v1/status
- POST /api/v1/pair
- POST /api/v1/messages
- GET /api/v1/messages/{message_id}
- GET /api/v1/modems
- GET /api/v1/incoming
- GET /api/v1/audit

Exact payload schemas are implementation-plan concerns, but all responses must use stable machine-readable status/error structures.

Every message submission should support a client/request identifier for idempotency and duplicate protection.

## 7. SQLite Model

Initial logical entities:

- messages
- message_attempts
- modems
- pairings
- settings
- audit_logs
- future incoming_messages
- future delivery_reports

The schema must support transactions so queue insertion and state changes are durable and recoverable.

## 8. Retry and Error Handling

Errors are classified as:

### Retryable
Examples:
- temporary modem busy
- transient network failure
- timeout
- temporary no-signal condition

These use bounded exponential backoff.

### Pending / State-dependent
Examples:
- modem disconnected
- SIM not ready
- network unavailable
- modem initialization in progress

The message remains pending and becomes eligible again when the Agent detects readiness.

### Permanent
Examples:
- invalid destination format
- invalid message configuration
- unsupported operation
- unrecoverable configuration/authentication failure

These are not retried automatically.

### Unknown outcome

If the Agent cannot determine whether a modem accepted a message, the result is unknown. The system must not blindly resend it. Message/request identifiers, modem state, attempt records, and provider/modem response data are used to prevent avoidable duplicate SMS.

## 9. Duplicate Protection

Each outbound message has:
- Message ID
- Client Request ID / idempotency key
- attempt records
- timestamps
- modem identity

Repeated requests with the same idempotency key must not create duplicate logical messages.

The retry engine must distinguish a known failure from an unknown transmission outcome.

## 10. Security

Security requirements:

- Authentication required for protected endpoints.
- Pairing establishes the initial trusted relationship.
- Tokens must be generated with cryptographically secure randomness.
- Tokens must be replaceable/revocable.
- HTTPS is required for non-local network communication.
- Agent should support binding to localhost or a selected LAN interface.
- Rate limiting applies to message-submission endpoints.
- Administrative endpoints are separated from normal message operations.
- Secrets are never returned in ordinary status responses.
- Secrets are never written to ordinary logs.
- Audit records contain safe identifiers rather than credentials.
- Configuration changes require authenticated authorization.

The Agent must not expose an unauthenticated public SMS-sending endpoint.

## 11. Platform Architecture

The core Agent is platform-neutral.

Supported targets:

- Windows 10/11 and Windows Server
- Linux
- Raspberry Pi

Platform-specific concerns such as service installation, startup, serial device discovery, permissions, and packaging live in platform adapters/installers rather than the core messaging logic.

## 12. Multi-Modem Readiness

Version 1 UX may initially configure one modem, but the internal model is multi-modem ready.

Each modem has an independent identity and operational state. Future scheduling can distribute queued messages across available modems without changing the core message model.

Load balancing and modem selection policies are intentionally out of initial scope.

## 13. Inbound SMS and DLR

Inbound SMS and delivery reports are architectural extension points.

The data model and modem abstraction must not prevent:
- receiving SMS
- storing sender/body/timestamp
- correlating delivery reports
- exposing inbound events through the API

Full inbound workflows and DLR UX are deferred until the required modem capabilities are validated.

## 14. Testing Strategy

Testing will be layered:

### Unit tests
- queue transitions
- retry classification
- backoff calculation
- idempotency
- duplicate protection
- state machine rules
- AT command parsing
- modem response normalization
- authentication/pairing primitives

### Integration tests
- SQLite persistence/recovery
- queue worker behavior
- REST API authentication
- modem adapter integration using a fake modem/serial transport

### Platform tests
- Windows serial/service installation
- Linux serial/service installation
- Raspberry Pi serial/device permissions

### Hardware validation
A real GSM modem/SIM will be required for final end-to-end validation. Hardware tests must be isolated from automated CI.

## 15. Operational Requirements

The Agent should provide:
- health status
- Agent version
- uptime
- modem connectivity/readiness
- queue depth
- last successful transmission
- recent safe errors
- audit history

Debug logging may be more verbose, but must still protect credentials and other secrets.

## 16. Scope Boundaries for Phase 3

Included:
- standalone Agent architecture
- REST API foundation
- authentication/pairing design
- SQLite
- persistent outbound queue
- retry/error classification
- modem abstraction
- USB/COM + AT command support
- Windows/Linux/Raspberry Pi architecture
- multi-modem-ready data model
- inbound SMS extension points
- testing strategy

Deferred:
- MQTT
- SMPP
- Android gateway
- network GSM gateways
- advanced load balancing
- full inbound workflows
- commercial licensing integration
- WordPress UI changes beyond the Agent integration required by the implementation plan

## 17. Future Compatibility

The design intentionally leaves extension boundaries for:
- MQTT transport
- SMPP
- network GSM gateways
- Android gateway
- multiple Agents
- advanced modem scheduling
- delivery-report workflows
- richer inbound automation

These must be added through explicit interfaces rather than by coupling them to the core queue or modem implementation.

## 18. Success Criteria

Phase 3 implementation is successful when a customer can install the Agent on a supported platform, pair it securely with WP Universal SMS, connect a USB/COM GSM modem, submit SMS through the API, survive normal temporary failures with persistent queue/retry behavior, inspect message status, and operate without requiring WordPress to remain connected during modem transmission.

---
