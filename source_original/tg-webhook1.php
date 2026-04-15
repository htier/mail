<?php
/**
 * Telegram Webhook for TeMail.pro Mini App
 * Основной язык: English
 * Дополнительный язык: Русский
 */

// ======================
// НАСТРОЙКИ
// ======================
$BOT_TOKEN = "7504100898:AAEgfXddJKxpH_YtEqeiR_Ad_FHPYdHAmY8"; // <-- ВСТАВЬ СЮДА ТОКЕН БОТА
$APP_URL   = "https://temail.pro/"; // Mini App URL
$API_URL   = "https://api.telegram.org/bot" . $BOT_TOKEN;

// ======================
// ПОЛУЧАЕМ ДАННЫЕ ОТ TELEGRAM
// ======================
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    exit;
}

$message = $update["message"] ?? null;
if (!$message) {
    exit;
}

$chat_id = $message["chat"]["id"] ?? null;
$text    = $message["text"] ?? "";

if (!$chat_id) {
    exit;
}

// ======================
// КОМАНДА /start
// ======================
if (strpos($text, "/start") === 0) {

    // Текст сообщения (EN + RU)
    $message_text =
        "👋 Welcome to TeMail.pro!\n".
        "Disposable & temporary email service.\n\n".
        "👇 Click the button below to open the Mini App inside Telegram.\n\n".
        "———————————————\n".
        "👋 Добро пожаловать в TeMail.pro!\n".
        "Сервис одноразовой и временной электронной почты.\n\n".
        "👇 Нажмите кнопку ниже, чтобы открыть мини-приложение внутри Telegram.";

    // Кнопка Mini App
    $reply_markup = [
        "inline_keyboard" => [
            [
                [
                    "text" => "▶️ Open TeMail.pro",
                    "web_app" => [
                        "url" => $APP_URL
                    ]
                ]
            ]
        ]
    ];

    // Отправка сообщения
    $params = [
        "chat_id" => $chat_id,
        "text" => $message_text,
        "reply_markup" => json_encode($reply_markup, JSON_UNESCAPED_UNICODE)
    ];

    file_get_contents($API_URL . "/sendMessage?" . http_build_query($params));
}

echo "OK";
