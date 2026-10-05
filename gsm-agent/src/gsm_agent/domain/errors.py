class DomainError(Exception):
    """Base class for GSM Agent domain errors."""


class InvalidMessageTransition(DomainError):
    """Raised when a message state transition is not allowed."""
