from __future__ import annotations

from datetime import datetime, timedelta
from enum import StrEnum


class RetryDecision(StrEnum):
    RETRYABLE = "retryable"
    PERMANENT = "permanent"
    PENDING = "pending"
    UNKNOWN = "unknown"


class RetryPolicy:
    def __init__(self, base_delay: int = 5, max_delay: int = 300):
        if base_delay <= 0 or max_delay < base_delay:
            raise ValueError("invalid retry bounds")
        self.base_delay = base_delay
        self.max_delay = max_delay

    def classify(self, error: str) -> RetryDecision:
        value = error.lower()
        if value in {"timeout", "modem_busy", "temporary_network", "no_signal"}:
            return RetryDecision.RETRYABLE
        if value in {"sim_not_ready", "modem_disconnected", "network_unavailable", "initializing"}:
            return RetryDecision.PENDING
        if value in {"invalid_destination", "invalid_message", "unsupported_operation", "configuration_error"}:
            return RetryDecision.PERMANENT
        return RetryDecision.UNKNOWN if value == "unknown_outcome" else RetryDecision.PERMANENT

    def should_retry(self, decision: RetryDecision) -> bool:
        return decision is RetryDecision.RETRYABLE

    def next_attempt_at(self, attempt_count: int, now: datetime) -> datetime:
        if attempt_count < 1:
            raise ValueError("attempt_count must be >= 1")
        seconds = min(self.max_delay, self.base_delay * (2 ** (attempt_count - 1)))
        return now + timedelta(seconds=seconds)
