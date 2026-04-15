<?php
require __DIR__ . '/lib.php';

$update = json_decode(file_get_contents('php://input'), true);
if(!$update){ http_response_code(200); exit; }

// ===== Anti-duplicate updates (fix multiple /start replies) =====
$updId = $update['update_id'] ?? null;
if ($updId !== null) {
  $lockFile = __DIR__ . '/data/last_update_id.txt';
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

function last_text($users, $lang){
  $list=array_values($users);
  usort($list, fn($a,$b)=> ($b['last_seen']??0) <=> ($a['last_seen']??0));
  $list=array_slice($list, 0, 12);
  $out = ($lang==='ru') ? "🕒 Последняя активность:\n" : "🕒 Last activity:\n";
  foreach($list as $u){
    $name = $u['username'] ? '@'.$u['username'] : ($u['first_name'] ?: 'user');
    $cid = $u['chat_id'] ?? 0;
    $time = tm_fmt_time($u['last_seen'] ?? 0);
    $sub = !empty($u['subscribed']) ? '✅' : '⛔';
    $blk = !empty($u['blocked']) ? '🚫' : '';
    $out .= "$sub$blk $name ($cid) — $time\n";
  }
  return $out;
}

function create_job(&$jobs, $text, $admin_id){
  $id = bin2hex(random_bytes(6));
  $jobs[$id] = ['id'=>$id,'text'=>$text,'created_by'=>(int)$admin_id,'status'=>'queued','created_at'=>time(),'sent'=>0,'failed'=>0,'cursor'=>0];
  return $id;
}

// ===== Keyboards =====
function user_keyboard($lang){
  $open = ($lang==='ru') ? "🌐 Открыть Web App" : "🌐 Open Web App";
  $langBtn = ($lang==='ru') ? "🌐 Язык (EN/RU)" : "🌐 Language (EN/RU)";
  $support = ($lang==='ru') ? "🆘 Поддержка" : "🆘 Support";
  $supportus = ($lang==='ru') ? "☕️ Поддержать нас" : "☕️ Support us";
  return json_encode([
    'keyboard'=>[
      [ ['text'=>$open] ],
      [ ['text'=>$supportus] ],
      [ ['text'=>$support], ['text'=>$langBtn] ],
    ],
    'resize_keyboard'=>true
  ], JSON_UNESCAPED_UNICODE);
}

function support_keyboard($lang){
  $cancel = ($lang==='ru') ? "❌ Отменить поддержку" : "❌ Cancel support";
  $open = ($lang==='ru') ? "🌐 Открыть Web App" : "🌐 Open Web App";
  return json_encode([
    'keyboard'=>[
      [ ['text'=>$cancel] ],
      [ ['text'=>$open] ],
    ],
    'resize_keyboard'=>true
  ], JSON_UNESCAPED_UNICODE);
}

function admin_reply_keyboard($lang){
  $open = ($lang==='ru') ? "🌐 Открыть Web App" : "🌐 Open Web App";
  $langBtn = ($lang==='ru') ? "🌐 Язык (EN/RU)" : "🌐 Language (EN/RU)";
  $support = ($lang==='ru') ? "🆘 Поддержка" : "🆘 Support";
  $supportus = ($lang==='ru') ? "☕️ Поддержать нас" : "☕️ Support us";

  $stats = ($lang==='ru') ? "📊 Статистика" : "📊 Stats";
  $last = ($lang==='ru') ? "🕒 Активность" : "🕒 Last activity";
  $bcast = ($lang==='ru') ? "📣 Рассылка" : "📣 Broadcast";
  $cancel = ($lang==='ru') ? "⛔ Отмена" : "⛔ Cancel";
  $web = ($lang==='ru') ? "🔐 Web Admin" : "🔐 Web Admin";

  return json_encode([
    'keyboard'=>[
      [ ['text'=>$stats], ['text'=>$last] ],
      [ ['text'=>$bcast], ['text'=>$cancel] ],
      [ ['text'=>$web] ],
      [ ['text'=>$open] ],
      [ ['text'=>$supportus], ['text'=>$support] ],
      [ ['text'=>$langBtn] ],
    ],
    'resize_keyboard'=>true
  ], JSON_UNESCAPED_UNICODE);
}

// ===== Menus =====
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

function user_start($chat_id, $lang){
  $msg = ($lang==='ru')
    ? "👋 <b>TeMail.pro</b>\nНажмите <b>🌐 Открыть Web App</b>, чтобы открыть сайт временной почты и работать с email."
    : "👋 <b>TeMail.pro</b>\nTap <b>🌐 Open Web App</b> to open the temporary email website and manage your inbox.";
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$msg."\n\n/help • /lang",
    'parse_mode'=>'HTML',
    'disable_web_page_preview'=>true,
    'reply_markup'=>user_keyboard($lang)
  ]);
}

function user_help($chat_id, $lang){
  $lines = ($lang==='ru')
    ? "ℹ️ <b>Помощь</b>\n\n• 🌐 Открыть Web App — открыть сайт и работать с почтой\n• 🆘 Поддержка — написать в поддержку\n• ☕️ Поддержать нас — BuyMeACoffee\n• 🌐 Язык (EN/RU) — сменить язык\n\nПоддержка: info@temail.pro"
    : "ℹ️ <b>Help</b>\n\n• 🌐 Open Web App — open the website and use email\n• 🆘 Support — contact support\n• ☕️ Support us — BuyMeACoffee\n• 🌐 Language (EN/RU) — change language\n\nSupport: info@temail.pro";
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$lines,
    'parse_mode'=>'HTML',
    'disable_web_page_preview'=>true,
    'reply_markup'=>user_keyboard($lang)
  ]);
}

function lang_picker($chat_id, $lang, $i18n){
  $t=$i18n[$lang];
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$t['lang_pick'],
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [['text'=>'🇬🇧 English','callback_data'=>'U:LANG:en'],['text'=>'🇷🇺 Русский','callback_data'=>'U:LANG:ru']]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
}

function send_weblogin($admin_id, $chat_id, $lang, $i18n){
  [$token,$exp]=tm_make_admin_token($admin_id);
  tm_store_admin_token($admin_id, $token, $exp);
  $minutes=(int)ceil(ADMIN_LOGIN_TTL/60);
  $url=WEB_BASE."/bot/admin/login.php?token=".urlencode($token);
  $t=$i18n[$lang];
  reply_msg($chat_id, sprintf($t['admin_link'], $minutes, $url), ['reply_markup'=>admin_reply_keyboard($lang)]);
}

// ===== Callbacks =====
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
      reply_msg($chat_id, $i18n[$new]['lang_set_'.$new], [
        'reply_markup'=> $isAdmin ? admin_reply_keyboard($new) : user_keyboard($new)
      ]);
    }
    exit;
  }

  if($data==='U:SUPPORT'){
    tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);
    reply_msg($chat_id, $i18n[$lang]['thanks'], ['reply_markup'=>user_keyboard($lang)]);
    foreach($ADMINS as $aid){
      tm_api('sendMessage',['chat_id'=>$aid,'text'=>"💬 Support request\nchat_id: $chat_id\n@" . ($from['username']??'-')]);
    }
    exit;
  }

  if(!$isAdmin){
    tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);
    exit;
  }

  tm_api('answerCallbackQuery',['callback_query_id'=>$cq['id'],'text'=>'OK']);

  if($data==='A:STATS'){ reply_msg($chat_id, stats_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if($data==='A:LAST'){ reply_msg($chat_id, last_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if($data==='A:BCAST'){ $pending['bcast'][(string)$user_id]=true; tm_json_save($pendingPath,$pending); reply_msg($chat_id,$i18n[$lang]['admin_bcast_prompt'], ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if($data==='A:CANCEL'){ reply_msg($chat_id,$i18n[$lang]['admin_cancel_prompt'], ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if($data==='A:WEBLOGIN'){ send_weblogin($user_id,$chat_id,$lang,$i18n); exit; }

  if(str_starts_with($data,'A:REPLY:')){
    $to=(int)substr($data,8);
    $pending[(string)$user_id]=['reply_to'=>$to,'ts'=>time()];
    tm_json_save($pendingPath,$pending);
    reply_msg($chat_id,$i18n[$lang]['admin_reply_set'], ['reply_markup'=>admin_reply_keyboard($lang)]);
    exit;
  }
  if(str_starts_with($data,'A:BLOCK:')){
    $to=(int)substr($data,8);
    if(isset($users[(string)$to])){
      $users[(string)$to]['blocked']=true;
      $users[(string)$to]['subscribed']=false;
      tm_json_save($usersPath,$users);
    }
    reply_msg($chat_id, sprintf($i18n[$lang]['admin_blocked'],$to), ['reply_markup'=>admin_reply_keyboard($lang)]);
    exit;
  }
  exit;
}

// ===== Messages =====
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

// ===== Support mode =====
if(($text === "🆘 Support") || ($text === "🆘 Поддержка")){
  $pending['support'][(string)$chat_id]=true;
  tm_json_save($pendingPath,$pending);
  $prompt = ($lang==='ru')
    ? "🆘 Напишите ваше сообщение для поддержки.\n\nЧтобы выйти, нажмите <b>❌ Отменить поддержку</b>."
    : "🆘 Please write your message for support.\n\nTo exit, tap <b>❌ Cancel support</b>.";
  reply_msg($chat_id, $prompt, [
    'parse_mode'=>'HTML',
    'reply_markup'=>support_keyboard($lang)
  ]);
  exit;
}

if(($text === "❌ Cancel support") || ($text === "❌ Отменить поддержку")){
  unset($pending['support'][(string)$chat_id]);
  tm_json_save($pendingPath,$pending);
  $done = ($lang==='ru') ? "✅ Поддержка отменена." : "✅ Support cancelled.";
  reply_msg($chat_id, $done, [
    'reply_markup'=> $isAdmin ? admin_reply_keyboard($lang) : user_keyboard($lang)
  ]);
  exit;
}

if(!$isAdmin && !empty($pending['support'][(string)$chat_id]) && $text!=='' && ($text[0]??'')!=='/'){
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
      ]]], JSON_UNESCAPED_UNICODE),
      'disable_web_page_preview'=>true
    ]);
  }

  $thanks = ($lang==='ru') ? "✅ Сообщение отправлено. Поддержка ответит здесь." : "✅ Sent. Support will reply here.";
  reply_msg($chat_id, $thanks, ['reply_markup'=>user_keyboard($lang)]);
  exit;
}

// ===== Button actions (minimal) =====
if(($text === "🌐 Open Web App") || ($text === "🌐 Открыть Web App")){
  $msgTxt = ($lang==='ru')
    ? "🌐 Нажмите кнопку ниже, чтобы открыть Web App TeMail.pro (временная почта)."
    : "🌐 Tap the button below to open TeMail.pro Web App (temporary email).";
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$msgTxt,
    'disable_web_page_preview'=>true,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [[ 'text'=> ($lang==='ru' ? 'Открыть' : 'Open'), 'web_app'=>['url'=>WEB_BASE.'/'] ]]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  exit;
}

if(($text === "☕️ Support us") || ($text === "☕️ Поддержать нас")){
  $title = ($lang==='ru') ? "☕️ Поддержать TeMail.pro" : "☕️ Support TeMail.pro";
  tm_api('sendMessage', [
    'chat_id'=>$chat_id,
    'text'=>$title,
    'disable_web_page_preview'=>true,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
        [[ 'text'=>'☕️ Buy Me a Coffee', 'url'=>'https://buymeacoffee.com/temail' ]]
      ]
    ], JSON_UNESCAPED_UNICODE)
  ]);
  exit;
}

if(($text === "🌐 Language (EN/RU)") || ($text === "🌐 Язык (EN/RU)")){
  lang_picker($chat_id, $lang, $i18n);
  exit;
}

// ===== Admin quick buttons (reply keyboard) =====
if($isAdmin){
  if(($text === "📊 Stats") || ($text === "📊 Статистика")){ reply_msg($chat_id, stats_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if(($text === "🕒 Last activity") || ($text === "🕒 Активность")){ reply_msg($chat_id, last_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if(($text === "📣 Broadcast") || ($text === "📣 Рассылка")){ $pending['bcast'][(string)$user_id]=true; tm_json_save($pendingPath,$pending); reply_msg($chat_id,$i18n[$lang]['admin_bcast_prompt'], ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if(($text === "⛔ Cancel") || ($text === "⛔ Отмена")){ reply_msg($chat_id,$i18n[$lang]['admin_cancel_prompt'], ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
  if(($text === "🔐 Web Admin") ){ send_weblogin($user_id,$chat_id,$lang,$i18n); exit; }
}

// // ===== Admin reply/broadcast modes (original) =====
// if($isAdmin && $text!=='' && ($text[0]??'')!=='/'){
//   $to = $pending[(string)$user_id]['reply_to'] ?? null;
//   if($to){
//     tm_api('sendMessage',['chat_id'=>$to,'text'=>$text,'disable_web_page_preview'=>true]);
//     unset($pending[(string)$user_id]);
//     tm_json_save($pendingPath,$pending);
//     reply_msg($chat_id,$i18n[$lang]['admin_sent'], ['reply_markup'=>admin_reply_keyboard($lang)]);
//     exit;
//   }
//   if(!empty($pending['bcast'][(string)$user_id])){
//     unset($pending['bcast'][(string)$user_id]);
//     tm_json_save($pendingPath,$pending);
//     $jobId = create_job($jobs,$text,$user_id);
//     tm_json_save($jobsPath,$jobs);
//     reply_msg($chat_id, sprintf($i18n[$lang]['admin_bcast_created'],$jobId), ['reply_markup'=>admin_reply_keyboard($lang)]);
//     exit;
//   }
// }

// Admin plain text handling
if($isAdmin && $text!=='' && $text[0]!=='/'){
  // reply mode
  $to = $pending[(string)$user_id]['reply_to'] ?? null;
  if($to){
    tm_api('sendMessage',['chat_id'=>$to,'text'=>$text,'disable_web_page_preview'=>true]);
    unset($pending[(string)$user_id]);
    tm_json_save($pendingPath,$pending);
    reply_msg($chat_id, ($lang==='ru' ? "✅ Отправлено." : "✅ Sent."));
    exit;
  }

  // broadcast mode
  if(!empty($pending['bcast'][(string)$user_id])){
    unset($pending['bcast'][(string)$user_id]);
    tm_json_save($pendingPath,$pending);

    $jobId = create_job($jobs,$text,$user_id);
    tm_json_save($jobsPath,$jobs);
    reply_msg($chat_id, sprintf($i18n[$lang]['admin_bcast_created'],$jobId));
    exit;
  }

  // no mode → do NOT broadcast
  reply_msg($chat_id, ($lang==='ru'
    ? "ℹ️ Для рассылки нажмите 📣 Broadcast (в админ-панели)."
    : "ℹ️ To broadcast, press 📣 Broadcast in admin panel."
  ));
  exit;
}


// ===== Commands =====
// if($text==='/start'){
//   if($isAdmin){
//     admin_menu($chat_id,$lang,$i18n);
//     reply_msg($chat_id, " ", ['reply_markup'=>admin_reply_keyboard($lang)]);
//   } else {
//     user_start($chat_id,$lang);
//   }
//   exit;
// }

if($text==='/start'){
  // reset broadcast mode for safety
  unset($pending['bcast'][(string)$user_id]);
  tm_json_save($pendingPath,$pending);

  if($isAdmin) admin_menu($chat_id,$lang,$i18n);
  else user_start($chat_id,$lang,$i18n);
  exit;
}


if($text==='/help'){
  if($isAdmin){
    $adminHelp = ($lang==='ru')
      ? "🛠 <b>Админ</b>\n• /stats /last\n• /broadcast\n• /cancel JOB_ID\n• /block CHAT_ID\n\nТакже можно использовать кнопки."
      : "🛠 <b>Admin</b>\n• /stats /last\n• /broadcast\n• /cancel JOB_ID\n• /block CHAT_ID\n\nYou can also use the buttons.";
    reply_msg($chat_id, $adminHelp, [
      'parse_mode'=>'HTML',
      'reply_markup'=>admin_reply_keyboard($lang)
    ]);
  } else {
    user_help($chat_id,$lang);
  }
  exit;
}

if($text==='/lang'){ lang_picker($chat_id,$lang,$i18n); exit; }

if($isAdmin && $text==='/stats'){ reply_msg($chat_id, stats_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
if($isAdmin && $text==='/last'){ reply_msg($chat_id, last_text($users,$lang), ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }
if($isAdmin && $text==='/broadcast'){ $pending['bcast'][(string)$user_id]=true; tm_json_save($pendingPath,$pending); reply_msg($chat_id,$i18n[$lang]['admin_bcast_prompt'], ['reply_markup'=>admin_reply_keyboard($lang)]); exit; }

if($isAdmin && str_starts_with($text,'/cancel ')){
  $id=trim(substr($text,8));
  if(isset($jobs[$id])){ $jobs[$id]['status']='canceled'; tm_json_save($jobsPath,$jobs); reply_msg($chat_id,sprintf($i18n[$lang]['admin_cancel_ok'],$id), ['reply_markup'=>admin_reply_keyboard($lang)]); }
  else reply_msg($chat_id,$i18n[$lang]['admin_no_job'], ['reply_markup'=>admin_reply_keyboard($lang)]);
  exit;
}

if($isAdmin && str_starts_with($text,'/block ')){
  $cid=(int)trim(substr($text,7));
  if(isset($users[(string)$cid])){ $users[(string)$cid]['blocked']=true; $users[(string)$cid]['subscribed']=false; tm_json_save($usersPath,$users); }
  reply_msg($chat_id, sprintf($i18n[$lang]['admin_blocked'],$cid), ['reply_markup'=>admin_reply_keyboard($lang)]);
  exit;
}

// // User messages -> forward to admins with Reply/Block/Web buttons
// if(!$isAdmin && $text!=='' && ($text[0]??'')!=='/'){
//   $u=$users[(string)$chat_id]??[];
//   $uname=$u['username']?'@'.$u['username']:($u['first_name']?:'user');
//   foreach($ADMINS as $aid){
//     tm_api('sendMessage',[
//       'chat_id'=>$aid,
//       'text'=>"📩 Message from $uname\nchat_id: $chat_id\n\n$text",
//       'reply_markup'=>json_encode(['inline_keyboard'=>[[
//         ['text'=>'↩️ Reply','callback_data'=>'A:REPLY:'.$chat_id],
//         ['text'=>'⛔ Block','callback_data'=>'A:BLOCK:'.$chat_id],
//         ['text'=>'🔐 Web Admin','callback_data'=>'A:WEBLOGIN'],
//       ]]], JSON_UNESCAPED_UNICODE),
//       'disable_web_page_preview'=>true
//     ]);
//   }
//   reply_msg($chat_id, $i18n[$lang]['thanks'], ['reply_markup'=>user_keyboard($lang)]);
//   exit;
// }

// User messages -> forward to admins ONLY in support mode
if(!$isAdmin && $text!=='' && $text[0]!=='/'){
  // support mode flag
  if (empty($pending['support'][(string)$chat_id])) {
    // НЕ support → просто игнор или короткий ответ
    reply_msg($chat_id, ($lang==='ru'
      ? "ℹ️ Чтобы написать в поддержку, нажмите 🆘 Support."
      : "ℹ️ To contact support, press 🆘 Support."
    ));
    exit;
  }

  // support is ON → forward to admins
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

  reply_msg($chat_id, ($lang==='ru'
    ? "✅ Сообщение отправлено в поддержку. Ответ придёт сюда."
    : "✅ Sent to support. We will reply here."
  ));
  exit;
}


if($text!=='' && ($text[0]??'')==='/'){
  reply_msg($chat_id, $i18n[$lang]['unknown_cmd'], ['reply_markup'=> $isAdmin ? admin_reply_keyboard($lang) : user_keyboard($lang)]);
}

http_response_code(200);
echo "OK";
