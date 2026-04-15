<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$allowed = [
  'get_email_address',
  'get_email_list',
  'fetch_email',
  'del_email'
];

$f = $_GET['f'] ?? '';
if (!in_array($f, $allowed)) {
  http_response_code(400);
  echo json_encode(['error'=>'Invalid method']);
  exit;
}

$query = http_build_query($_GET);
$url = "https://www.guerrillamail.com/ajax.php?$query";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_USERAGENT => 'TeMail.pro'
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response ?: json_encode(['error'=>'No response']);
