from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone

from gsm_agent.domain.enums import MessageStatus

from gsm_agent.storage.repositories import MessageRepository


@dataclass(frozen=True)
class QueueSubmitRequest:
    client_scope: str
    client_request_id: str
    destination: str
    body: str
    message_id: str


class QueueService:
    def __init__(self, repository: MessageRepository):
        self.repository = repository

    def submit(self, request: QueueSubmitRequest):
        now = datetime.now(timezone.utc)
        return self.repository.create_or_get_by_request_id(
            request.client_scope,
            {
                "message_id": request.message_id,
                "client_request_id": request.client_request_id,
                "destination": request.destination,
                "body": request.body,
                "status": MessageStatus.QUEUED,
                "created_at": now,
                "updated_at": now,
            },
        )
