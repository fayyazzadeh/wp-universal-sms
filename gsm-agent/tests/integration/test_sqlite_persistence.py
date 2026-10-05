from datetime import datetime, timezone

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.storage.database import Database
from gsm_agent.storage.repositories import MessageRepository


def make_record(message_id="msg-1", request_id="req-1"):
    now = datetime.now(timezone.utc)
    return {
        "message_id": message_id,
        "client_request_id": request_id,
        "destination": "+989121234567",
        "body": "hello",
        "status": MessageStatus.QUEUED,
        "created_at": now,
        "updated_at": now,
    }


def test_message_survives_database_reopen(tmp_path):
    path = tmp_path / "agent.db"
    db = Database(path)
    db.initialize()
    repo = MessageRepository(db)

    created = repo.create_or_get_by_request_id("client-a", make_record())
    db.close()

    db2 = Database(path)
    db2.initialize()
    loaded = MessageRepository(db2).get(created.message_id)

    assert loaded is not None
    assert loaded.message_id == created.message_id
    assert loaded.status == MessageStatus.QUEUED
    db2.close()


def test_claim_next_eligible_is_exclusive(tmp_path):
    db = Database(tmp_path / "agent.db")
    db.initialize()
    repo = MessageRepository(db)
    repo.create_or_get_by_request_id("client-a", make_record())

    first = repo.claim_next_eligible(datetime.now(timezone.utc))
    second = repo.claim_next_eligible(datetime.now(timezone.utc))

    assert first is not None
    assert first.status == MessageStatus.SENDING
    assert second is None
    db.close()


def test_attempt_failure_rolls_back_state_change(tmp_path, monkeypatch):
    db = Database(tmp_path / "agent.db")
    db.initialize()
    repo = MessageRepository(db)
    created = repo.create_or_get_by_request_id("client-a", make_record())

    def fail(*args, **kwargs):
        raise RuntimeError("attempt write failed")

    monkeypatch.setattr(repo, "_insert_attempt", fail)

    try:
        repo.record_attempt_and_transition(
            created.message_id,
            status=MessageStatus.SENDING,
            attempt_number=1,
            started_at=datetime.now(timezone.utc),
        )
    except RuntimeError:
        pass
    else:
        raise AssertionError("expected persistence failure")

    assert repo.get(created.message_id).status == MessageStatus.QUEUED
    db.close()
