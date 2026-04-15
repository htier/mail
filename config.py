import os
from dataclasses import dataclass


@dataclass
class Config:
    token: str
    admin_ids: list[int]
    welcome_message: str = (
        "Привет! Я современный бот. Выбери действие ниже 👇"
    )


def load_config() -> Config:
    token = os.environ.get("BOT_TOKEN", "")
    if not token:
        raise ValueError("BOT_TOKEN не задан в переменных окружения")

    raw_admins = os.environ.get("ADMIN_IDS", "")
    admin_ids = [int(x) for x in raw_admins.split(",") if x.strip().isdigit()]

    return Config(token=token, admin_ids=admin_ids)
