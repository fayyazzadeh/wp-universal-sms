from datetime import datetime
from typing import Protocol, TypeVar

T = TypeVar("T")


class Clock(Protocol):
    def now(self) -> datetime: ...


class IdGenerator(Protocol):
    def new_id(self, prefix: str) -> str: ...


class SerialTransport(Protocol):
    def open(self) -> None: ...

    def close(self) -> None: ...

    def write(self, data: bytes) -> None: ...

    def read_until(self, terminator: bytes, timeout: float) -> bytes: ...


class ModemAdapter(Protocol):
    def send_sms(self, destination: str, body: str): ...

    def get_status(self): ...


class ModemManager(Protocol):
    def send(self, modem_id: str, destination: str, body: str): ...

    def get_status(self, modem_id: str): ...
