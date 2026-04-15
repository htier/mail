from telegram import Update
from telegram.ext import ContextTypes


async def echo_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    text = update.message.text
    await update.message.reply_html(
        f"<b>{user.first_name}</b>, вы написали:\n<i>{text}</i>\n\n"
        "Используйте /help чтобы увидеть доступные команды."
    )
