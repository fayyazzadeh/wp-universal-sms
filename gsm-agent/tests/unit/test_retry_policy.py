from datetime import datetime, timedelta, timezone

from gsm_agent.queue.retry import RetryDecision, RetryPolicy


def test_retryable_error_uses_bounded_exponential_backoff():
    policy = RetryPolicy(base_delay=5, max_delay=60)
    now = datetime(2026, 1, 1, tzinfo=timezone.utc)

    decision = policy.classify("timeout")
    assert decision == RetryDecision.RETRYABLE

    assert policy.next_attempt_at(1, now) == now + timedelta(seconds=5)
    assert policy.next_attempt_at(2, now) == now + timedelta(seconds=10)
    assert policy.next_attempt_at(5, now) == now + timedelta(seconds=60)


def test_permanent_error_is_not_retryable():
    policy = RetryPolicy()

    assert policy.classify("invalid_destination") == RetryDecision.PERMANENT
    assert policy.classify("unknown_outcome") == RetryDecision.UNKNOWN
    assert policy.should_retry(RetryDecision.PERMANENT) is False
    assert policy.should_retry(RetryDecision.UNKNOWN) is False
