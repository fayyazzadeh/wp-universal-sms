from __future__ import annotations

import json
from datetime import datetime, timedelta, timezone
from typing import Any

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.domain.models import MessageAttempt, MessageRecord
from gsm_agent.domain.state_machine import MessageStateMachine

from .database import Database


def _iso(value: datetime) -> str:
    return value.astimezone(timezone.utc).isoformat()


def _dt(value: str) -> datetime:
    return datetime.fromisoformat(value)


class MessageRepository:
    def __init__(self, database: Database):
        self.db = database
        self.db.initialize()

    def create_or_get_by_request_id(
        self,
        client_scope: str,
        record: dict[str, Any] | MessageRecord,
    ) -> MessageRecord:
        if isinstance(record, MessageRecord):
            record = record.model_dump()
        connection = self.db.connect()
        with self.db.transaction() as tx:
            existing = tx.execute(
                "SELECT * FROM messages WHERE client_scope = ? AND client_request_id = ?",
                (client_scope, record["client_request_id"]),
            ).fetchone()
            if existing is not None:
                return self._row_to_record(existing)

            now = record["created_at"]
            tx.execute(
                """
                INSERT INTO messages (
                    message_id, client_scope, client_request_id, destination, body,
                    status, created_at, updated_at, modem_id, sender, next_attempt_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    record["message_id"],
                    client_scope,
                    record["client_request_id"],
                    record["destination"],
                    record["body"],
                    MessageStatus(record["status"]).value,
                    _iso(record["created_at"]),
                    _iso(record["updated_at"]),
                    record.get("modem_id"),
                    record.get("sender"),
                    _iso(now),
                ),
            )
            row = tx.execute(
                "SELECT * FROM messages WHERE message_id = ?",
                (record["message_id"],),
            ).fetchone()
            return self._row_to_record(row)

    def get(self, message_id: str) -> MessageRecord | None:
        row = self.db.connect().execute(
            "SELECT * FROM messages WHERE message_id = ?", (message_id,)
        ).fetchone()
        return self._row_to_record(row) if row else None

    def count_messages(self) -> int:
        return int(self.db.connect().execute("SELECT COUNT(*) FROM messages").fetchone()[0])

    def claim_next_eligible(self, now: datetime) -> MessageRecord | None:
        with self.db.transaction() as tx:
            row = tx.execute(
                """
                SELECT * FROM messages
                WHERE status = ? AND next_attempt_at <= ?
                ORDER BY created_at ASC
                LIMIT 1
                """,
                (MessageStatus.QUEUED.value, _iso(now)),
            ).fetchone()
            if row is None:
                return None

            MessageStateMachine.transition(
                MessageStatus(row["status"]), MessageStatus.SENDING
            )
            tx.execute(
                """
                UPDATE messages
                SET status = ?, claimed_at = ?, updated_at = ?
                WHERE message_id = ? AND status = ?
                """,
                (
                    MessageStatus.SENDING.value,
                    _iso(now),
                    _iso(now),
                    row["message_id"],
                    MessageStatus.QUEUED.value,
                ),
            )
            updated = tx.execute(
                "SELECT * FROM messages WHERE message_id = ?",
                (row["message_id"],),
            ).fetchone()
            return self._row_to_record(updated)

    def record_attempt_and_transition(
        self,
        message_id: str,
        *,
        status: MessageStatus,
        attempt_number: int,
        started_at: datetime,
        attempt_id: str | None = None,
        finished_at: datetime | None = None,
        outcome: str | None = None,
        error_code: str | None = None,
        safe_metadata: dict[str, Any] | None = None,
    ) -> MessageRecord:
        current = self.get(message_id)
        if current is None:
            raise KeyError(message_id)
        MessageStateMachine.transition(current.status, status)
        attempt_id = attempt_id or f"attempt-{message_id}-{attempt_number}"
        with self.db.transaction() as tx:
            self._insert_attempt(
                tx,
                MessageAttempt(
                    attempt_id=attempt_id,
                    message_id=message_id,
                    attempt_number=attempt_number,
                    started_at=started_at,
                    finished_at=finished_at,
                    outcome=outcome,
                    error_code=error_code,
                    safe_metadata=safe_metadata or {},
                ),
            )
            now = finished_at or started_at
            tx.execute(
                """
                UPDATE messages
                SET status = ?, updated_at = ?, claimed_at = NULL
                WHERE message_id = ?
                """,
                (status.value, _iso(now), message_id),
            )
            row = tx.execute(
                "SELECT * FROM messages WHERE message_id = ?", (message_id,)
            ).fetchone()
            return self._row_to_record(row)

    def release_for_retry(self, message_id: str, next_attempt_at: datetime) -> MessageRecord:
        current = self.get(message_id)
        if current is None:
            raise KeyError(message_id)
        MessageStateMachine.transition(current.status, MessageStatus.QUEUED)
        with self.db.transaction() as tx:
            tx.execute(
                """
                UPDATE messages
                SET status = ?, next_attempt_at = ?, claimed_at = NULL, updated_at = ?
                WHERE message_id = ?
                """,
                (
                    MessageStatus.QUEUED.value,
                    _iso(next_attempt_at),
                    _iso(next_attempt_at),
                    message_id,
                ),
            )
            return self._row_to_record(
                tx.execute("SELECT * FROM messages WHERE message_id = ?", (message_id,)).fetchone()
            )

    def recover_stale_sending(self, now: datetime, lease_timeout: timedelta) -> int:
        cutoff = now - lease_timeout
        with self.db.transaction() as tx:
            cursor = tx.execute(
                """
                UPDATE messages
                SET status = ?, next_attempt_at = ?, claimed_at = NULL, updated_at = ?
                WHERE status = ? AND claimed_at IS NOT NULL AND claimed_at <= ?
                """,
                (
                    MessageStatus.QUEUED.value,
                    _iso(now),
                    _iso(now),
                    MessageStatus.SENDING.value,
                    _iso(cutoff),
                ),
            )
            return cursor.rowcount

    def _insert_attempt(self, tx, attempt: MessageAttempt) -> None:
        tx.execute(
            """
            INSERT INTO message_attempts (
                attempt_id, message_id, attempt_number, started_at, finished_at,
                outcome, error_code, safe_metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                attempt.attempt_id,
                attempt.message_id,
                attempt.attempt_number,
                _iso(attempt.started_at),
                _iso(attempt.finished_at) if attempt.finished_at else None,
                attempt.outcome,
                attempt.error_code,
                json.dumps(attempt.safe_metadata, sort_keys=True),
            ),
        )

    @staticmethod
    def _row_to_record(row) -> MessageRecord:
        return MessageRecord(
            message_id=row["message_id"],
            client_request_id=row["client_request_id"],
            destination=row["destination"],
            body=row["body"],
            status=MessageStatus(row["status"]),
            created_at=_dt(row["created_at"]),
            updated_at=_dt(row["updated_at"]),
            modem_id=row["modem_id"],
            sender=row["sender"],
        )
