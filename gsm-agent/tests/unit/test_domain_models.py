from datetime import datetime, timezone

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.domain.models import MessageRecord


def test_message_record_contains_stable_ids_and_queue_state():
    now = datetime.now(timezone.utc)

    message = MessageRecord(
        message_id="msg_123",
        client_request_id="req_123",
        destination="+989121234567",
        body="Hello",
        status=MessageStatus.QUEUED,
        created_at=now,
        updated_at=now,
    )

    assert message.message_id == "msg_123"
    assert message.client_request_id == "req_123"
    assert message.status is MessageStatus.QUEUED
    assert message.destination == "+989121234567"
