from datetime import datetime, timezone

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.storage.database import Database
from gsm_agent.storage.repositories import MessageRepository


def record(message_id, request_id):
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


def test_same_client_request_id_returns_same_message(tmp_path):
    db = Database(tmp_path / "agent.db")
    db.initialize()
    repo = MessageRepository(db)

    first = repo.create_or_get_by_request_id("client-a", record("msg-1", "req-42"))
    second = repo.create_or_get_by_request_id("client-a", record("msg-2", "req-42"))

    assert second.message_id == first.message_id
    assert repo.count_messages() == 1
    db.close()


def test_same_request_id_is_scoped_to_client(tmp_path):
    db = Database(tmp_path / "agent.db")
    db.initialize()
    repo = MessageRepository(db)

    first = repo.create_or_get_by_request_id("client-a", record("msg-1", "req-42"))
    second = repo.create_or_get_by_request_id("client-b", record("msg-2", "req-42"))

    assert first.message_id != second.message_id
    assert repo.count_messages() == 2
    db.close()
