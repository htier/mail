import logging

from telegram import Update
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ConversationHandler,
    MessageHandler,
    filters,
)

from config import load_config
from handlers.callbacks import button_handler
from handlers.commands import cmd_about, cmd_help, cmd_start
from handlers.feedback import (
    FEEDBACK_TEXT,
    cmd_feedback,
    feedback_cancel,
    feedback_received,
)
from handlers.messages import echo_handler

logging.basicConfig(
    format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
    level=logging.INFO,
)
logger = logging.getLogger(__name__)


async def error_handler(update: object, context) -> None:
    logger.error("Ошибка при обработке обновления:", exc_info=context.error)


def main() -> None:
    config = load_config()

    app = Application.builder().token(config.token).build()
    app.bot_data["config"] = config

    # --- Conversation: /feedback ---
    feedback_conv = ConversationHandler(
        entry_points=[CommandHandler("feedback", cmd_feedback)],
        states={
            FEEDBACK_TEXT: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, feedback_received)
            ],
        },
        fallbacks=[CommandHandler("cancel", feedback_cancel)],
    )

    # --- Command handlers ---
    app.add_handler(CommandHandler("start", cmd_start))
    app.add_handler(CommandHandler("help", cmd_help))
    app.add_handler(CommandHandler("about", cmd_about))
    app.add_handler(feedback_conv)

    # --- Callback (inline buttons) ---
    app.add_handler(CallbackQueryHandler(button_handler))

    # --- Plain messages ---
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, echo_handler))

    app.add_error_handler(error_handler)

    logger.info("Бот запущен. Нажмите Ctrl+C для остановки.")
    app.run_polling(allowed_updates=Update.ALL_TYPES)


if __name__ == "__main__":
    main()
