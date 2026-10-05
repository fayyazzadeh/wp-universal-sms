import pytest

from gsm_agent.domain.enums import MessageStatus
from gsm_agent.domain.errors import InvalidMessageTransition
from gsm_agent.domain.state_machine import MessageStateMachine


@pytest.mark.parametrize(
    ("current", "target"),
    [
        (MessageStatus.QUEUED, MessageStatus.SENDING),
        (MessageStatus.SENDING, MessageStatus.SENT),
        (MessageStatus.SENDING, MessageStatus.FAILED),
        (MessageStatus.SENDING, MessageStatus.UNKNOWN),
        (MessageStatus.SENT, MessageStatus.DELIVERED),
    ],
)
def test_supported_message_transitions(current, target):
    assert MessageStateMachine.can_transition(current, target)
    assert MessageStateMachine.transition(current, target) is target


def test_permanent_failure_cannot_return_to_sending():
    with pytest.raises(InvalidMessageTransition):
        MessageStateMachine.transition(
            MessageStatus.FAILED_PERMANENTLY,
            MessageStatus.SENDING,
        )


def test_unknown_outcome_cannot_be_implicitly_retried():
    with pytest.raises(InvalidMessageTransition):
        MessageStateMachine.transition(
            MessageStatus.UNKNOWN,
            MessageStatus.SENDING,
        )
