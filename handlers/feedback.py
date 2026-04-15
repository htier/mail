import logging

from telegram import Update
from telegram.ext import ContextTypes, ConversationHandler

logger = logging.getLogger(__name__)

FEEDBACK_TEXT = 1


async def cmd_feedback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    await update.message.reply_text(
        "✉️ Напишите ваше сообщение для разработчика.\n\nОтмена: /cancel"
    )
    return FEEDBACK_TEXT


async def feedback_received(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    user = update.effective_user
    text = update.message.text
    config = context.bot_data["config"]

    logger.info("Обратная связь от %s (%s): %s", user.full_name, user.id, text)

    # Отправить администраторам, если заданы
    for admin_id in config.admin_ids:
        try:
            await context.bot.send_message(
                chat_id=admin_id,
                text=(
                    f"📩 <b>Новое сообщение</b>\n"
                    f"От: <a href='tg://user?id={user.id}'>{user.full_name}</a> "
                    f"(ID: <code>{user.id}</code>)\n\n"
                    f"{text}"
                ),
                parse_mode="HTML",
            )
        except Exception as exc:
            logger.warning("Не удалось отправить сообщение админу %s: %s", admin_id, exc)

    await update.message.reply_text(
        "✅ Спасибо! Ваше сообщение отправлено разработчику."
    )
    return ConversationHandler.END


async def feedback_cancel(update: Update, context: ContextTypes.DEFAULT_TYPE) -> int:
    await update.message.reply_text("❌ Отменено.")
    return ConversationHandler.END
