<?php
session_start();
require __DIR__ . '/../lib.php';

$siteConfig = json_decode(file_get_contents(__DIR__ . '/../../config/config.json'), true);

function require_login() {
    if (!empty($_SESSION['admin_id'])) return;
    header('Location: login.php'); exit;
}
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function load_users()  { return tm_json_load(__DIR__ . '/../data/users.json'); }
function load_jobs()   { return tm_json_load(__DIR__ . '/../data/jobs.json'); }
function load_premium(){ return tm_json_load(__DIR__ . '/../data/premium_codes.json'); }
function load_posts()  { return tm_json_load(__DIR__ . '/../../data/posts.json'); }
function save_jobs($j)  { tm_json_save(__DIR__ . '/../data/jobs.json', $j); }
function save_users($u) { tm_json_save(__DIR__ . '/../data/users.json', $u); }
function save_posts($p) { tm_json_save(__DIR__ . '/../../data/posts.json', $p); }
