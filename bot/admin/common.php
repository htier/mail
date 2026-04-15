<?php
session_start();
require __DIR__ . '/../lib.php';
function require_login(){ if(empty($_SESSION['admin_id'])){ header('Location: login.php'); exit; } }
function h($s){ return htmlspecialchars($s??'', ENT_QUOTES,'UTF-8'); }
function load_users(){ return tm_json_load(__DIR__.'/../data/users.json'); }
function load_jobs(){ return tm_json_load(__DIR__.'/../data/jobs.json'); }
function save_jobs($jobs){ tm_json_save(__DIR__.'/../data/jobs.json',$jobs); }
function save_users($users){ tm_json_save(__DIR__.'/../data/users.json',$users); }
