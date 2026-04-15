<?php
require __DIR__ . '/config.php';

function tm_api($method, $data = []){
  $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_TIMEOUT => 25,
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  if($res === false) return ['ok'=>false,'error'=>$err];
  $j = json_decode($res, true);
  return $j ?: ['ok'=>false,'error'=>'bad_json'];
}

function tm_json_load($path){
  if(!file_exists($path)) return [];
  $raw = file_get_contents($path);
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function tm_json_save($path, $data){
  $dir = dirname($path);
  if(!is_dir($dir)) mkdir($dir, 0755, true);
  file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function tm_i18n(){
  return [
    'en' => [
      'welcome' => "✅ Welcome to TeMail.pro\nTap the button below to open the Web App.",
      'help' => "ℹ️ Help\nSupport: info@temail.pro\nLanguage: /lang",
      'btn_open' => "🌐 Open TeMail.pro",
      'btn_support' => "💬 Write to support",
      'thanks' => "✅ Thanks! Support will reply here soon.",
      'lang_pick' => "Choose language:",
      'lang_set_en' => "✅ Language set to English.",
      'lang_set_ru' => "✅ Язык установлен на русский.",
      'admin_panel' => "🛠 Admin panel",
      'admin_choose' => "Choose an action:",
      'admin_stats' => "📊 Stats",
      'admin_last' => "🕒 Last activity",
      'admin_bcast' => "📣 Broadcast",
      'admin_cancel' => "⛔ Cancel",
      'admin_web' => "🔐 Open Web Admin",
      'admin_bcast_prompt' => "Send broadcast text now (it will start queueing).\nCancel with /cancel JOB_ID.",
      'admin_bcast_created' => "📣 Broadcast created. JOB_ID: %s\nStatus: queued",
      'admin_cancel_prompt' => "Send /cancel JOB_ID",
      'admin_cancel_ok' => "✅ Canceled: %s",
      'admin_no_job' => "⚠️ Job not found.",
      'admin_reply_set' => "↩️ Reply mode enabled. Send your next message to reply.",
      'admin_sent' => "✅ Sent.",
      'admin_blocked' => "⛔ User blocked: %s",
      'admin_link' => "🔐 Admin web login (valid %d min):\n%s",
      'unknown_cmd' => "Command not found. Use /help",
    ],
    'ru' => [
      'welcome' => "✅ Добро пожаловать в TeMail.pro\nНажмите кнопку ниже, чтобы открыть Web App.",
      'help' => "ℹ️ Помощь\nПоддержка: info@temail.pro\nЯзык: /lang",
      'btn_open' => "🌐 Открыть TeMail.pro",
      'btn_support' => "💬 Написать в поддержку",
      'thanks' => "✅ Спасибо! Поддержка ответит вам здесь.",
      'lang_pick' => "Выберите язык:",
      'lang_set_en' => "✅ Language set to English.",
      'lang_set_ru' => "✅ Язык установлен на русский.",
      'admin_panel' => "🛠 Панель администратора",
      'admin_choose' => "Выберите действие:",
      'admin_stats' => "📊 Статистика",
      'admin_last' => "🕒 Последняя активность",
      'admin_bcast' => "📣 Рассылка",
      'admin_cancel' => "⛔ Отмена",
      'admin_web' => "🔐 Открыть Web Admin",
      'admin_bcast_prompt' => "Отправьте текст рассылки (будет очередь).\nОтменить: /cancel JOB_ID.",
      'admin_bcast_created' => "📣 Рассылка создана. JOB_ID: %s\nСтатус: queued",
      'admin_cancel_prompt' => "Отправьте: /cancel JOB_ID",
      'admin_cancel_ok' => "✅ Отменено: %s",
      'admin_no_job' => "⚠️ Рассылка не найдена.",
      'admin_reply_set' => "↩️ Режим ответа включен. Следующее сообщение будет отправлено пользователю.",
      'admin_sent' => "✅ Отправлено.",
      'admin_blocked' => "⛔ Пользователь заблокирован: %s",
      'admin_link' => "🔐 Вход в Web Admin (действует %d мин):\n%s",
      'unknown_cmd' => "Команда не найдена. /help",
    ]
  ];
}

function tm_get_user_lang($users, $chat_id){
  $lang = $users[(string)$chat_id]['lang'] ?? 'en';
  return in_array($lang, ['en','ru'], true) ? $lang : 'en';
}
function tm_fmt_time($ts){
  if(!$ts) return '-';
  return gmdate('Y-m-d H:i', (int)$ts);
}
function tm_is_admin($user_id, $ADMINS){
  return in_array((int)$user_id, $ADMINS, true);
}
function tm_sign($payload){
  return hash_hmac('sha256', $payload, ADMIN_SIGNING_SECRET);
}
function tm_make_admin_token($admin_id){
  $nonce = bin2hex(random_bytes(12));
  $exp = time() + ADMIN_LOGIN_TTL;
  $payload = $admin_id . '|' . $exp . '|' . $nonce;
  $sig = tm_sign($payload);
  return [base64_encode($payload . '|' . $sig), $exp];
}
function tm_store_admin_token($admin_id, $token, $exp){
  $path = __DIR__ . '/data/admin_tokens.json';
  $db = tm_json_load($path);
  $db[$token] = ['admin_id'=>(int)$admin_id,'exp'=>(int)$exp,'used'=>false];
  $now=time();
  foreach($db as $k=>$v){ if(($v['exp']??0) < $now-3600) unset($db[$k]); }
  tm_json_save($path, $db);
}
function tm_validate_admin_token($token){
  $path = __DIR__ . '/data/admin_tokens.json';
  $db = tm_json_load($path);
  $row = $db[$token] ?? null;
  if(!$row) return [false,'not_found'];
  if(!empty($row['used'])) return [false,'used'];
  if(($row['exp']??0) < time()) return [false,'expired'];
  $decoded = base64_decode($token, true);
  if(!$decoded) return [false,'bad_token'];
  $parts = explode('|', $decoded);
  if(count($parts) !== 4) return [false,'bad_parts'];
  [$admin_id,$exp,$nonce,$sig] = $parts;
  $payload = $admin_id.'|'.$exp.'|'.$nonce;
  if(!hash_equals(tm_sign($payload), $sig)) return [false,'bad_sig'];
  $db[$token]['used']=true;
  tm_json_save($path, $db);
  return [true,(int)$admin_id];
}
