<?php
require __DIR__ . '/lib.php';

$update = json_decode(file_get_contents('php://input'), true);
if(!$update){ http_response_code(200); exit; }

// ===== Anti-duplicate updates =====
$updId = $update['update_id'] ?? null;
if ($updId !== null) {
  $lockFile = __DIR__ . '/data/last_update_id.txt';
  if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0755, true);
  $last = is_file($lockFile) ? (int)trim((string)@file_get_contents($lockFile)) : 0;
  if ($updId <= $last) { http_response_code(200); echo "OK"; exit; }
  @file_put_contents($lockFile, (string)$updId, LOCK_EX);
}

$usersPath   = __DIR__ . '/data/users.json';
$jobsPath    = __DIR__ . '/data/jobs.json';
$pendingPath = __DIR__ . '/data/pending_reply.json';

$users   = tm_json_load($usersPath);
$jobs    = tm_json_load($jobsPath);
$pending = tm_json_load($pendingPath);
$i18n    = tm_i18n();

function reply_msg($chat_id, $text, $extra=[]){
  $payload = array_merge([
    'chat_id'=>$chat_id,
    'text'=>$text,
    'disable_web_page_preview'=>true
  ], $extra);
  return tm_api('sendMessage', $payload);
}

function upsert_user(&$users, $chat_id, $from){
  $now=time();
  $cur = $users[(string)$chat_id] ?? [];
  $users[(string)$chat_id] = array_merge($cur, [
    'chat_id'=>(int)$chat_id,
    'user_id'=>(int)($from['id']??0),
    'first_name'=>$from['first_name']??'',
    'username'=>$from['username']??'',
    'lang'=>$cur['lang']??'en',
    'subscribed'=>($cur['subscribed']??true),
    'blocked'=>($cur['blocked']??false),
    'last_seen'=>$now
  ]);
}

function user_keyboard($lang){
  // Reply keyboard (красивые кнопки)
  return json_encode([
    'keyboard'=>[
      [ ['text'=>"📧 New email"], ['text'=>"📋 Copy email"] ],
      [ ['text'=>"📥 Inbox"], ['text'=>"🔄 Refresh"] ],
      [ ['text'=>"🌍 Provider"], ['text'=>"🌐 Language"] ],
      [ ['text'=>"🔔 Auto-check"], ['text'=>"🆘 Support"] ],
      [ ['text'=>"☕️ Support us"], ['text'=>"🌐 Open Web App"] ],
    ],
    'resize_keyboard'=>true,
    'input_field_placeholder'=>'Temporary email'
  ], JSON_UNESCAPED_UNICODE);
}

function support_keyboard(){
  return json_encode([
    'keyboard'=>[
      [ ['text'=>"❌ Cancel support"] ],
    ],
    'resize_keyboard'=>true
  ], JSON_UNESCAPED_UNICODE);
}

function stats_text($users, $lang){
  $now=time(); $total=0;$subs=0;$a24=0;$a7=0;$blocked=0;
  foreach($users as $u){
    $total++;
    if(!empty($u['subscribed'])) $subs++;
    if(!empty($u['blocked'])) $blocked++;
    $ls=$u['last_seen']??0;
    if($now-$ls<=86400) $a24++;
    if($now-$ls<=86400*7) $a7++;
  }
  return ($lang==='ru')
    ? "📊 Статистика\nВсего: $total\nПодписаны: $subs\nАктивны 24ч: $a24\nАктивны 7д: $a7\nЗаблокированы: $blocked"
    : "📊 Stats\nTotal: $total\nSubscribed: $subs\nActive 24h: $a24\nActive 7d: $a7\nBlocked: $blocked";
}

function admin_menu($chat_id, $lang, $i18n){
  $t=$i18n[$lang];
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$t['admin_panel']."\n".$t['admin_choose'],
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [
          ['text'=>$t['admin_stats'],'callback_data'=>'A:STATS'],
          ['text'=>$t['admin_last'],'callback_data'=>'A:LAST'],
        ],
        [
          ['text'=>$t['admin_bcast'],'callback_data'=>'A:BCAST'],
          ['text'=>$t['admin_cancel'],'callback_data'=>'A:CANCEL'],
        ],
        [
          ['text'=>$t['admin_web'],'callback_data'=>'A:WEBLOGIN']
        ]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
}

// --- NEW: helper texts EN/RU (минимально, без нагрузки) ---
function t2($lang, $key){
  $x = [
    'en'=>[
      'welcome'=>"✅ <b>TeMail.pro</b>\nOpen the Web App to generate email and read inbox.",
      'hint_copy'=>"📋 To copy email: open Web App → tap email → Copy.",
      'hint_actions'=>"Use the Web App for: New email / Inbox / Refresh / Provider.",
      'support_prompt'=>"🆘 Send your message. Press ❌ Cancel support to exit.",
      'support_sent'=>"✅ Sent. Support will reply here.",
      'support_cancel'=>"✅ Support cancelled.",
      'support_us'=>"☕️ Support us:",
      'open_web'=>"🌐 Open Web App:",
    ],
    'ru'=>[
      'welcome'=>"✅ <b>TeMail.pro</b>\nОткрой Web App, чтобы создать email и читать входящие.",
      'hint_copy'=>"📋 Чтобы копировать email: открой Web App → нажми на email → Copy.",
      'hint_actions'=>"Web App: New email / Inbox / Refresh / Provider.",
      'support_prompt'=>"🆘 Напиши сообщение. Нажми ❌ Cancel support чтобы выйти.",
      'support_sent'=>"✅ Отправлено. Поддержка ответит сюда.",
      'support_cancel'=>"✅ Поддержка отменена.",
      'support_us'=>"☕️ Поддержать нас:",
      'open_web'=>"🌐 Открыть Web App:",
    ]
  ];
  if(!isset($x[$lang])) $lang='en';
  return $x[$lang][$key] ?? $key;
}

/* Callbacks (оставляем твою старую логику) */
if(isset($update['callback_query'])){
  $cq=$update['callback_query'];
  $data=$cq['data']??'';
  $from=$cq['from']??[];
  $chat_id=$cq['message']['chat']['id']??0;
  $user_id=$from['id']??0;

  upsert_user($users, $chat_id, $from);
  $lang = tm_get_user_lang($users, $chat_id);
  $isAdmin = tm_is_admin($user_id, $ADMINS);

  if(str_starts_with($data,'U:LANG:')){
    $new=substr($data,7);
    if(in_array($new,['en','ru'],true)){
      $users[(string)$chat_id]['lang']=$new;
      tm_json_save($usersPath,$users);
      tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);
      reply_msg($chat_id, ($new==='ru'?'✅ Язык: Русский':'✅ Language: English'), [
        'reply_markup'=> user_keyboard($new)
      ]);
    }
    exit;
  }

  // admin callbacks (оставляем как было)
  if(!$isAdmin){
    tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);
    exit;
  }
  tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);

  if($data==='A:STATS'){ reply_msg($chat_id, stats_text($users,$lang)); exit; }

  // остальные callback оставь как у тебя было, если нужно
  exit;
}

/* Messages */
$msg=$update['message']??null;
if(!$msg){ http_response_code(200); exit; }

$chat_id=$msg['chat']['id'];
$from=$msg['from']??[];
$user_id=$from['id']??0;
$text=trim($msg['text']??'');

upsert_user($users,$chat_id,$from);
$lang=tm_get_user_lang($users,$chat_id);
$isAdmin=tm_is_admin($user_id,$ADMINS);

// ignore blocked users
if(!empty($users[(string)$chat_id]['blocked'])){
  tm_json_save($usersPath,$users);
  http_response_code(200); echo "OK"; exit;
}
tm_json_save($usersPath,$users);

// ===== NEW: Support mode via pending.json (легко) =====
if($text === "🆘 Support"){
  $pending['support'][(string)$chat_id] = true;
  tm_json_save($pendingPath,$pending);
  reply_msg($chat_id, t2($lang,'support_prompt'), [
    'reply_markup'=>support_keyboard()
  ]);
  exit;
}

if($text === "❌ Cancel support"){
  unset($pending['support'][(string)$chat_id]);
  tm_json_save($pendingPath,$pending);
  reply_msg($chat_id, t2($lang,'support_cancel'), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

// If user in support mode and sends text -> forward to admins
if(!$isAdmin && !empty($pending['support'][(string)$chat_id]) && $text!=='' && $text[0] !== '/'){
  unset($pending['support'][(string)$chat_id]);
  tm_json_save($pendingPath,$pending);

  $u=$users[(string)$chat_id]??[];
  $uname=$u['username']?'@'.$u['username']:($u['first_name']?:'user');

  foreach($ADMINS as $aid){
    tm_api('sendMessage',[
      'chat_id'=>$aid,
      'text'=>"🆘 Support message from $uname\nchat_id: $chat_id\n\n$text",
      'reply_markup'=>json_encode(['inline_keyboard'=>[[
        ['text'=>'↩️ Reply','callback_data'=>'A:REPLY:'.$chat_id],
        ['text'=>'⛔ Block','callback_data'=>'A:BLOCK:'.$chat_id],
        ['text'=>'🔐 Web Admin','callback_data'=>'A:WEBLOGIN'],
      ]]], JSON_UNESCAPED_UNICODE)
    ]);
  }

  reply_msg($chat_id, t2($lang,'support_sent'), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

// ===== NEW: Buttons handling (all open Web App / simple hints) =====
if($text === "🌐 Language"){
  // show inline language picker (как раньше /lang)
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=> ($lang==='ru' ? "Выберите язык:" : "Choose language:"),
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [['text'=>'🇬🇧 English','callback_data'=>'U:LANG:en'],['text'=>'🇷🇺 Русский','callback_data'=>'U:LANG:ru']]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  exit;
}

if($text === "☕️ Support us"){
  reply_msg($chat_id, t2($lang,'support_us'), [
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [ ['text'=>'☕️ Buy Me a Coffee', 'url'=>'https://buymeacoffee.com/temail'] ]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  // вернуть меню
  reply_msg($chat_id, ($lang==='ru'?'Спасибо! 🙏':'Thank you! 🙏'), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

if(in_array($text, ["📧 New email","📥 Inbox","🔄 Refresh","🌍 Provider","🌐 Open Web App"], true)){
  // всё делаем через Web App (чтобы не нагружать хостинг)
  $msgTxt = t2($lang,'hint_actions')."\n\n".t2($lang,'open_web');
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$msgTxt,
    'disable_web_page_preview'=>true,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [['text'=> ($lang==='ru'?'Открыть Web App':'Open Web App'), 'web_app'=>['url'=>WEB_BASE.'/']]]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  // вернуть reply keyboard
  reply_msg($chat_id, "✅", ['reply_markup'=>user_keyboard($lang)]);
  exit;
}

if($text === "📋 Copy email"){
  reply_msg($chat_id, t2($lang,'hint_copy'), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

if($text === "🔔 Auto-check"){
  // Лёгкий вариант: подсказка (у тебя раньше это было на сайте)
  reply_msg($chat_id, ($lang==='ru'
    ? "🔔 Auto-check работает в Web App.\nОткрой Web App и включи автообновление."
    : "🔔 Auto-check works in the Web App.\nOpen Web App and enable auto-refresh."
  ), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

// Commands
if($text==='/start'){
  if($isAdmin){
    admin_menu($chat_id,$lang,$i18n);
    // ещё покажем admin reply keyboard (чтобы красиво)
    reply_msg($chat_id, "🛠 Admin keyboard enabled.", ['reply_markup'=>user_keyboard($lang)]);
  } else {
    reply_msg($chat_id, t2($lang,'welcome'), [
      'reply_markup'=>user_keyboard($lang)
    ]);
    // сразу кнопку WebApp
    tm_api('sendMessage', [
      'chat_id'=>$chat_id,
      'text'=>t2($lang,'open_web'),
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
          [['text'=> ($lang==='ru'?'Открыть Web App':'Open Web App'), 'web_app'=>['url'=>WEB_BASE.'/']]]
        ]
      ], JSON_UNESCAPED_UNICODE)
    ]);
  }
  exit;
}

if($text==='/help'){
  reply_msg($chat_id, ($lang==='ru'
    ? "Кнопки снизу открывают Web App и поддержку.\n/lang — сменить язык."
    : "Use the buttons below to open Web App and contact support.\n/lang — change language."
  ), [
    'reply_markup'=>user_keyboard($lang)
  ]);
  exit;
}

if($text==='/lang'){
  // same as Language button
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=> ($lang==='ru' ? "Выберите язык:" : "Choose language:"),
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [['text'=>'🇬🇧 English','callback_data'=>'U:LANG:en'],['text'=>'🇷🇺 Русский','callback_data'=>'U:LANG:ru']]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  exit;
}

// Unknown command
if($text!=='' && $text[0]==='/'){
  reply_msg($chat_id, ($lang==='ru' ? "Неизвестная команда. /start" : "Unknown command. Use /start"), [
    'reply_markup'=>user_keyboard($lang)
  ]);
}

http_response_code(200);
echo "OK";
