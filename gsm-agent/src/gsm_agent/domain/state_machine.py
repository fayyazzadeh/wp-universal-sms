from .enums import MessageStatus
from .errors import InvalidMessageTransition


class MessageStateMachine:
    _TRANSITIONS: dict[MessageStatus, frozenset[MessageStatus]] = {
        MessageStatus.QUEUED: frozenset({MessageStatus.SENDING, MessageStatus.PENDING}),
        MessageStatus.SENDING: frozenset(
            {
                MessageStatus.SENT,
                MessageStatus.FAILED,
                MessageStatus.FAILED_PERMANENTLY,
                MessageStatus.PENDING,
                MessageStatus.UNKNOWN,
            }
        ),
        MessageStatus.SENT: frozenset({MessageStatus.DELIVERED, MessageStatus.FAILED}),
        MessageStatus.PENDING: frozenset({MessageStatus.QUEUED, MessageStatus.SENDING, MessageStatus.FAILED}),
        MessageStatus.FAILED: frozenset({MessageStatus.QUEUED, MessageStatus.FAILED_PERMANENTLY}),
        MessageStatus.FAILED_PERMANENTLY: frozenset(),
        MessageStatus.DELIVERED: frozenset(),
        MessageStatus.UNKNOWN: frozenset(),
    }

    @classmethod
    def can_transition(cls, current: MessageStatus, target: MessageStatus) -> bool:
        return target in cls._TRANSITIONS.get(current, frozenset())

    @classmethod
    def transition(cls, current: MessageStatus, target: MessageStatus) -> MessageStatus:
        if not cls.can_transition(current, target):
            raise InvalidMessageTransition(
                f"Invalid message transition: {current.value} -> {target.value}"
            )
        return target
