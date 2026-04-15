from telegram import Update
from telegram.ext import ContextTypes

from handlers.commands import cmd_about, cmd_help


async def button_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    query = update.callback_query
    await query.answer()

    match query.data:
        case "help":
            text = (
                "<b>Доступные команды:</b>\n\n"
                "/start — Главное меню\n"
                "/help — Эта справка\n"
                "/about — О боте\n"
                "/feedback — Написать разработчику\n"
                "/cancel — Отменить текущее действие\n"
            )
            await query.edit_message_text(text, parse_mode="HTML")

        case "about":
            text = (
                "<b>О боте</b>\n\n"
                "Версия: <code>2.0</code>\n"
                "Стек: Python · python-telegram-bot v20+\n"
                "Архитектура: async/await, ConversationHandler\n\n"
                "Разработан с ❤️"
            )
            await query.edit_message_text(text, parse_mode="HTML")

        case "feedback":
            await query.edit_message_text(
                "✉️ Напишите ваше сообщение, и я передам его разработчику.\n\n"
                "Для отмены отправьте /cancel"
            )

        case _:
            await query.edit_message_text("Неизвестная кнопка.")
