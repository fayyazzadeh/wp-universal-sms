from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class ProcessingResult:
    processed: bool
    message_id: str | None = None
    status: str | None = None


class QueueWorker:
    """Application-level worker boundary; modem integration is added in Task 3/5."""

    def __init__(self, queue_service):
        self.queue_service = queue_service

    def process_one(self) -> ProcessingResult:
        return ProcessingResult(processed=False)
