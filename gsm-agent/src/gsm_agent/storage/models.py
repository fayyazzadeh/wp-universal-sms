from dataclasses import dataclass
from datetime import datetime


@dataclass(frozen=True)
class StoredAttempt:
    attempt_id: str
    message_id: str
    attempt_number: int
    started_at: datetime
    finished_at: datetime | None
    outcome: str | None
    error_code: str | None
    safe_metadata: dict
