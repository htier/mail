<?php
/**
 * TeMail.pro — Premium Code API
 * POST /api/premium.php  body: {"action":"redeem","code":"XXXX-XXXX-XXXX-XXXX"}
 * POST /api/premium.php  body: {"action":"check","code":"XXXX-XXXX-XXXX-XXXX"}
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$codesPath = __DIR__ . '/../bot/data/premium_codes.json';

function pc_load(string $path): array {
    if (!file_exists($path)) return [];
    $j = json_decode(file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function pc_save(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function pc_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Parse input
$input = [];
$raw = file_get_contents('php://input');
if ($raw) $input = json_decode($raw, true) ?? [];
// Also accept GET/POST params
$action = $input['action'] ?? $_REQUEST['action'] ?? '';
$code   = strtoupper(trim($input['code'] ?? $_REQUEST['code'] ?? ''));
// Normalise: remove spaces and dashes, then reformat XXXXXXXXXXXXXXXX → XXXX-XXXX-XXXX-XXXX
$code_clean = preg_replace('/[\s\-]/', '', $code);
if (strlen($code_clean) === 16) {
    $code = implode('-', str_split($code_clean, 4));
}

// ===== REDEEM =====
if ($action === 'redeem') {
    if (!$code) pc_json(['ok' => false, 'error' => 'missing_code']);

    $codes = pc_load($codesPath);

    if (!isset($codes[$code])) pc_json(['ok' => false, 'error' => 'invalid_code']);

    $c = $codes[$code];

    // Expired on server?
    if (($c['expires_at'] ?? 0) < time()) pc_json(['ok' => false, 'error' => 'expired_code']);

    // Already redeemed — still return success (idempotent: same browser can re-activate)
    if (!empty($c['redeemed'])) {
        pc_json(['ok' => true, 'already_redeemed' => true,
                 'expires_at' => (int)$c['expires_at'],
                 'user_id'    => (int)($c['user_id'] ?? 0)]);
    }

    // Mark redeemed
    $codes[$code]['redeemed']    = true;
    $codes[$code]['redeemed_at'] = time();
    pc_save($codesPath, $codes);

    pc_json(['ok'         => true,
             'expires_at' => (int)$c['expires_at'],
             'user_id'    => (int)($c['user_id'] ?? 0)]);
}

// ===== CHECK =====
if ($action === 'check') {
    if (!$code) pc_json(['ok' => false, 'error' => 'missing_code']);

    $codes = pc_load($codesPath);
    if (!isset($codes[$code])) pc_json(['ok' => false, 'error' => 'invalid_code']);

    $c     = $codes[$code];
    $valid = ($c['expires_at'] ?? 0) >= time();
    pc_json(['ok'         => $valid,
             'expires_at' => (int)($c['expires_at'] ?? 0),
             'user_id'    => (int)($c['user_id'] ?? 0)]);
}

pc_json(['ok' => false, 'error' => 'unknown_action'], 400);
