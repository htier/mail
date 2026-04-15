<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code=400){
  http_response_code($code);
  echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
if(empty($_SESSION['blog_admin']) && ($_GET['action'] ?? '') !== 'list' && ($_GET['action'] ?? '') !== 'get'){
  fail("Unauthorized", 401);
}

$dataDir = __DIR__ . "/../data";
$uploadDir = __DIR__ . "/../uploads";
@mkdir($dataDir, 0755, true);
@mkdir($uploadDir, 0755, true);
$postsFile = $dataDir . "/posts.json";
if(!file_exists($postsFile)) file_put_contents($postsFile, json_encode(['posts'=>[]], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

$db = json_decode(file_get_contents($postsFile), true);
if(!$db || !isset($db['posts'])) $db = ['posts'=>[]];

function save_db($postsFile, $db){
  file_put_contents($postsFile, json_encode($db, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? 'list';

if($action === 'list'){
  $all = ($_GET['all'] ?? '') === '1';
  $posts = $db['posts'];
  // sort by published_at desc
  usort($posts, function($a,$b){
    return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
  });
  if(!$all){
    $posts = array_values(array_filter($posts, fn($p)=>($p['status'] ?? 'published') === 'published'));
  }
  $limit = intval($_GET['limit'] ?? 200);
  $posts = array_slice($posts, 0, max(1, min($limit, 500)));
  echo json_encode(['posts'=>$posts], JSON_UNESCAPED_UNICODE);
  exit;
}

if($action === 'get'){
  $id = $_GET['id'] ?? '';
  foreach($db['posts'] as $p){
    if(($p['id'] ?? '') === $id){
      echo json_encode(['post'=>$p], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }
  fail("Not found", 404);
}

if($action === 'save'){
  $id = $_POST['id'] ?? '';
  $title = trim($_POST['title'] ?? '');
  $content = $_POST['content'] ?? '';
  $tags = array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
  $status = $_POST['status'] ?? 'published';
  $published_at = $_POST['published_at'] ?? date('Y-m-d');
  if($title === '' || $content === '') fail("Title and content required");

  $coverUrl = null;
  if(!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK){
    $tmp = $_FILES['cover']['tmp_name'];
    $name = basename($_FILES['cover']['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if(!in_array($ext, ['jpg','jpeg','png','webp','gif'])) fail("Unsupported image type");
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $fname = $safe . "_" . time() . "." . $ext;
    $dest = $uploadDir . "/" . $fname;
    if(!move_uploaded_file($tmp, $dest)) fail("Upload failed");
    $coverUrl = "/uploads/" . $fname;
  }

  if($id === ''){
    $id = bin2hex(random_bytes(8));
    $post = [
      'id'=>$id,
      'title'=>$title,
      'content'=>$content,
      'tags'=>$tags,
      'status'=>$status,
      'published_at'=>$published_at,
      'cover'=>$coverUrl,
      'updated_at'=>date('c')
    ];
    array_unshift($db['posts'], $post);
    save_db($postsFile, $db);
    echo json_encode(['post'=>$post], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // update
  foreach($db['posts'] as &$p){
    if(($p['id'] ?? '') === $id){
      $p['title']=$title;
      $p['content']=$content;
      $p['tags']=$tags;
      $p['status']=$status;
      $p['published_at']=$published_at;
      if($coverUrl) $p['cover']=$coverUrl;
      $p['updated_at']=date('c');
      save_db($postsFile, $db);
      echo json_encode(['post'=>$p], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }
  fail("Not found", 404);
}

if($action === 'delete'){
  $raw = file_get_contents("php://input");
  $j = json_decode($raw, true);
  $id = $j['id'] ?? '';
  if($id==='') fail("Missing id");
  $db['posts'] = array_values(array_filter($db['posts'], fn($p)=>($p['id'] ?? '') !== $id));
  save_db($postsFile, $db);
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
  exit;
}

fail("Unknown action");
