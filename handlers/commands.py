from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes


def _main_keyboard() -> InlineKeyboardMarkup:
    buttons = [
        [
            InlineKeyboardButton("ℹ️ О боте", callback_data="about"),
            InlineKeyboardButton("❓ Помощь", callback_data="help"),
        ],
        [
            InlineKeyboardButton("✉️ Обратная связь", callback_data="feedback"),
        ],
    ]
    return InlineKeyboardMarkup(buttons)


async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    config = context.bot_data["config"]
    user = update.effective_user
    await update.message.reply_html(
        f"Привет, <b>{user.first_name}</b>! 👋\n\n{config.welcome_message}",
        reply_markup=_main_keyboard(),
    )


async def cmd_help(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    text = (
        "<b>Доступные команды:</b>\n\n"
        "/start — Главное меню\n"
        "/help — Эта справка\n"
        "/about — О боте\n"
        "/feedback — Написать разработчику\n"
        "/cancel — Отменить текущее действие\n"
    )
    await update.message.reply_html(text)


async def cmd_about(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    text = (
        "<b>О боте</b>\n\n"
        "Версия: <code>2.0</code>\n"
        "Стек: Python · python-telegram-bot v20+\n"
        "Архитектура: async/await, ConversationHandler\n\n"
        "Разработан с ❤️"
    )
    await update.message.reply_html(text)
