<?php
/**
 * TeMail.pro — Telegram Mini App Auth Endpoint
 *
 * Actions:
 *   auth      — validate initData, return { ok, admin, user_id }
 *   stats     — admin: return usage statistics
 *   users     — admin: return last 20 users from bot/data/users.json
 *   broadcast — admin: send message to all users via Bot API
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://temail.pro");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }

require_once __DIR__ . "/../bot/config.php";

// ── Admin Telegram user IDs (from bot/config.php $ADMINS) ──────
$ADMIN_IDS = $ADMINS ?? [1474039422];

// ── Read request ───────────────────────────────────────────────
$body    = json_decode(file_get_contents("php://input"), true) ?? [];
$action  = trim($_GET["action"] ?? $body["action"] ?? "auth");
$initData = trim($body["initData"] ?? "");

// ── Helpers ────────────────────────────────────────────────────

/**
 * Validate Telegram WebApp initData HMAC-SHA256.
 * Returns parsed user array or null if invalid.
 */
function validateInitData(string $initData, string $botToken): ?array
{
    if ($initData === "") return null;

    parse_str($initData, $params);
    $hash = $params["hash"] ?? "";
    if ($hash === "") return null;
    unset($params["hash"]);

    ksort($params);
    $dataCheckString = implode("\n", array_map(
        fn($k, $v) => "$k=$v",
        array_keys($params),
        array_values($params)
    ));

    $secretKey    = hash_hmac("sha256", $botToken, "WebAppData", true);
    $expectedHash = hash_hmac("sha256", $dataCheckString, $secretKey);

    if (!hash_equals($expectedHash, $hash)) return null;

    return json_decode($params["user"] ?? "{}", true) ?: [];
}

function jsonOut(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function readUsers(): array
{
    $file = __DIR__ . "/../bot/data/users.json";
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── Auth ───────────────────────────────────────────────────────
$user = validateInitData($initData, BOT_TOKEN);

// Allow localhost without valid initData (development only)
$isLocalhost = in_array($_SERVER["HTTP_HOST"] ?? "", ["localhost", "127.0.0.1"], true);

if ($user === null && !$isLocalhost) {
    jsonOut(["ok" => false, "admin" => false, "error" => "Invalid initData"]);
}

$userId  = (int)($user["id"] ?? 0);
$isAdmin = in_array($userId, $ADMIN_IDS, true) || $isLocalhost;

// ── Action: auth ───────────────────────────────────────────────
if ($action === "auth") {
    jsonOut(["ok" => true, "admin" => $isAdmin, "user_id" => $userId]);
}

// All other actions require admin
if (!$isAdmin) {
    jsonOut(["ok" => false, "error" => "Forbidden"]);
}

// ── Action: stats ──────────────────────────────────────────────
if ($action === "stats") {
    $users     = readUsers();
    $total     = count($users);
    $today     = date("Y-m-d");
    $activeToday = 0;
    $gmCount   = 0;
    $mtCount   = 0;

    foreach ($users as $uid => $u) {
        if (str_starts_with($u["last_seen"] ?? "", $today)) $activeToday++;
        if (($u["provider"] ?? "guerrilla") === "mailtm") $mtCount++;
        else $gmCount++;
    }

    jsonOut([
        "ok"    => true,
        "stats" => [
            "total_users"        => $total,
            "active_today"       => $activeToday,
            "guerrilla_sessions" => $gmCount,
            "mailtm_sessions"    => $mtCount,
        ]
    ]);
}

// ── Action: users ──────────────────────────────────────────────
if ($action === "users") {
    $users = readUsers();
    $list  = [];
    foreach ($users as $uid => $u) {
        $list[] = [
            "id"         => $uid,
            "first_name" => $u["first_name"] ?? "",
            "username"   => $u["username"]   ?? "",
            "last_seen"  => $u["last_seen"]  ?? "",
        ];
    }
    // Sort by last_seen descending, return latest 20
    usort($list, fn($a, $b) => strcmp($b["last_seen"], $a["last_seen"]));
    jsonOut(["ok" => true, "users" => array_slice($list, 0, 20)]);
}

// ── Action: broadcast ─────────────────────────────────────────
if ($action === "broadcast") {
    $message = trim($body["message"] ?? "");
    if ($message === "") {
        jsonOut(["ok" => false, "error" => "Empty message"]);
    }

    $users = readUsers();
    $sent  = 0;
    $apiUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    foreach ($users as $uid => $u) {
        $payload = http_build_query([
            "chat_id"    => $uid,
            "text"       => $message,
            "parse_mode" => "HTML",
        ]);
        $result = @file_get_contents($apiUrl . "?" . $payload);
        if ($result !== false) $sent++;
        usleep(35_000); // ~28 req/s — stays under Telegram's 30/s limit
    }

    jsonOut(["ok" => true, "sent" => $sent]);
}

jsonOut(["ok" => false, "error" => "Unknown action"]);
