from enum import StrEnum


class MessageStatus(StrEnum):
    QUEUED = "queued"
    SENDING = "sending"
    SENT = "sent"
    DELIVERED = "delivered"
    FAILED = "failed"
    FAILED_PERMANENTLY = "failed_permanently"
    PENDING = "pending"
    UNKNOWN = "unknown"


class ModemStatus(StrEnum):
    DISCONNECTED = "disconnected"
    INITIALIZING = "initializing"
    NOT_READY = "not_ready"
    READY = "ready"
    ERROR = "error"
