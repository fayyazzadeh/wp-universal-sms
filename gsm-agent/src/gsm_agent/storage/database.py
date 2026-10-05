from __future__ import annotations

import sqlite3
from pathlib import Path


SCHEMA = """
CREATE TABLE IF NOT EXISTS messages (
    message_id TEXT PRIMARY KEY,
    client_scope TEXT NOT NULL,
    client_request_id TEXT NOT NULL,
    destination TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    modem_id TEXT,
    sender TEXT,
    next_attempt_at TEXT NOT NULL,
    claimed_at TEXT,
    UNIQUE (client_scope, client_request_id)
);

CREATE INDEX IF NOT EXISTS idx_messages_queue
    ON messages(status, next_attempt_at, created_at);

CREATE TABLE IF NOT EXISTS message_attempts (
    attempt_id TEXT PRIMARY KEY,
    message_id TEXT NOT NULL REFERENCES messages(message_id),
    attempt_number INTEGER NOT NULL,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    outcome TEXT,
    error_code TEXT,
    safe_metadata TEXT NOT NULL DEFAULT '{}'
);

CREATE INDEX IF NOT EXISTS idx_attempts_message
    ON message_attempts(message_id, attempt_number);

CREATE TABLE IF NOT EXISTS modems (
    modem_id TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    port TEXT,
    signal_strength INTEGER,
    network TEXT,
    sim_ready INTEGER
);

CREATE TABLE IF NOT EXISTS pairings (
    pairing_id TEXT PRIMARY KEY,
    client_scope TEXT NOT NULL,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id TEXT PRIMARY KEY,
    event_type TEXT NOT NULL,
    occurred_at TEXT NOT NULL,
    request_id TEXT,
    message_id TEXT,
    safe_metadata TEXT NOT NULL DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS incoming_messages (
    event_id TEXT PRIMARY KEY,
    modem_id TEXT NOT NULL,
    sender TEXT NOT NULL,
    body TEXT NOT NULL,
    received_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS delivery_reports (
    report_id TEXT PRIMARY KEY,
    message_id TEXT NOT NULL,
    status TEXT NOT NULL,
    reported_at TEXT NOT NULL,
    safe_metadata TEXT NOT NULL DEFAULT '{}'
);
"""


class Database:
    def __init__(self, path: str | Path):
        self.path = Path(path)
        self._connection: sqlite3.Connection | None = None

    def connect(self) -> sqlite3.Connection:
        if self._connection is None:
            if str(self.path) != ":memory:":
                self.path.parent.mkdir(parents=True, exist_ok=True)
            self._connection = sqlite3.connect(
                self.path,
                timeout=30,
                isolation_level=None,
                check_same_thread=False,
            )
            self._connection.row_factory = sqlite3.Row
            self._connection.execute("PRAGMA foreign_keys = ON")
            self._connection.execute("PRAGMA journal_mode = WAL")
        return self._connection

    def initialize(self) -> None:
        self.connect().executescript(SCHEMA)

    def close(self) -> None:
        if self._connection is not None:
            self._connection.close()
            self._connection = None

    def transaction(self):
        return _Transaction(self.connect())


class _Transaction:
    def __init__(self, connection: sqlite3.Connection):
        self.connection = connection

    def __enter__(self):
        self.connection.execute("BEGIN IMMEDIATE")
        return self.connection

    def __exit__(self, exc_type, exc_value, traceback):
        if exc_type is None:
            self.connection.execute("COMMIT")
        else:
            self.connection.execute("ROLLBACK")
        return False
