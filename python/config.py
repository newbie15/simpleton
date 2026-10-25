import os

from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "")
DB = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "database": os.getenv("DB_NAME", "simpleton"),
    "user": os.getenv("DB_USER", "bot_tg"),
    "password": os.getenv("DB_PASSWORD", ""),
}
PIN_MAX_FAILS = int(os.getenv("PIN_MAX_FAILS", "5"))
PIN_LOCK_MINUTES = int(os.getenv("PIN_LOCK_MINUTES", "15"))
