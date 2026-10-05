from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict

from .enums import MessageStatus, ModemStatus


class MessageRecord(BaseModel):
    model_config = ConfigDict(extra="forbid")

    message_id: str
    client_request_id: str
    destination: str
    body: str
    status: MessageStatus
    created_at: datetime
    updated_at: datetime
    modem_id: str | None = None
    sender: str | None = None


class MessageAttempt(BaseModel):
    model_config = ConfigDict(extra="forbid")

    attempt_id: str
    message_id: str
    attempt_number: int
    started_at: datetime
    finished_at: datetime | None = None
    outcome: str | None = None
    error_code: str | None = None
    safe_metadata: dict[str, Any] = {}


class ModemInfo(BaseModel):
    model_config = ConfigDict(extra="forbid")

    modem_id: str
    status: ModemStatus
    port: str | None = None
    signal_strength: int | None = None
    network: str | None = None
    sim_ready: bool | None = None


class IncomingMessage(BaseModel):
    model_config = ConfigDict(extra="forbid")

    event_id: str
    modem_id: str
    sender: str
    body: str
    received_at: datetime
