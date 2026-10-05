from datetime import datetime, timedelta, timezone

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.storage.database import Database
from gsm_agent.storage.repositories import MessageRepository


def record():
    now = datetime.now(timezone.utc)
    return {
        "message_id": "msg-recovery",
        "client_request_id": "req-recovery",
        "destination": "+989121234567",
        "body": "hello",
        "status": MessageStatus.QUEUED,
        "created_at": now,
        "updated_at": now,
    }


def test_stale_sending_message_is_requeued_after_lease_timeout(tmp_path):
    path = tmp_path / "agent.db"
    db = Database(path)
    db.initialize()
    repo = MessageRepository(db)
    created = repo.create_or_get_by_request_id("client-a", record())

    claimed_at = datetime.now(timezone.utc)
    claimed = repo.claim_next_eligible(claimed_at)
    assert claimed.message_id == created.message_id

    db.close()

    db2 = Database(path)
    db2.initialize()
    repo2 = MessageRepository(db2)

    lease_timeout = timedelta(seconds=1)
    recovery_at = claimed_at + lease_timeout + timedelta(microseconds=1)
    recovered = repo2.recover_stale_sending(
        now=recovery_at,
        lease_timeout=lease_timeout,
    )

    assert recovered == 1
    assert repo2.get(created.message_id).status == MessageStatus.QUEUED
    db2.close()
