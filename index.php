<?php

// Location where hosted images will be stored
define('IMG_ROOT', '/var/images/');
// Pre-shared key - must match IMAGE_PSK in gazelle config
define('PSK', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
// User agent to use when downloading files
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36');

ini_set('memory_limit', '256M');

function content_type($data) {
  if (!strncmp($data, pack('H*', '89504E47'), 4)) return 'image/png';
  if (!strncmp($data, pack('H*', 'FFD8'), 2)) return 'image/jpeg';
  if (!strncmp($data, 'GIF', 3)) return 'image/gif';
  if (strlen($data) > 35 && !substr_compare($data, 'webm', 31, 4)) return 'video/webm';
  if (!strncmp($data, 'BM', 2)) return 'image/bmp';
  if (!strncmp($data, 'II', 2) || !strncmp($data, 'MM', 2)) return 'image/tiff';
  return 'application/octet-stream';
}

function serve($path, $rescode = 200) {
  $filesize = filesize($path);
  $handle = fopen($path, "r");
  $content_type = content_type(fread($handle, 100));
  fclose($handle);
  header('Accept-Ranges: bytes');
  header("Content-Type: $content_type");
  if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
    if ($matches[1] == "") {
      $matches[1] = $filesize - intval($matches[2] ?? 0);
      $matches[2] = $filesize;
    }
    $start = intval($matches[1] ?? 0);
    $end = min(intval($matches[2] ?? $filesize), $filesize);
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$filesize");
    echo file_get_contents($path, false, null, $start, $end-$start);
  } else {
    http_response_code($rescode);
    header("Content-Length: $filesize");
    readfile($path);
  }
}

if (empty($_GET['i'])) die();

$img_url = $_GET['i'];
$auth = rawurldecode($_GET['h']);
$img_url_hash = hash('sha256', $img_url);
$debug = !empty($_GET['debug']);

$img_dir = IMG_ROOT.substr($img_url_hash, 0, 2);
$img_path = $img_dir.'/'.$img_url_hash;

// Deletion
if (!empty($_GET['d'])) {
  $img_url = $_GET['d'];
  $img_url_hash = hash('sha256', $img_url);
  if (base64_encode(hash_hmac('sha256', $img_url, strrev(PSK), true)) != $auth) {
    http_response_code(401);
    echo 'Auth failure';
    die();
  }
  if (file_exists($img_path)) {
    unlink($img_path);
    http_response_code(200);
    echo 'Success';
  } else {
    http_response_code(404);
    echo 'File does not exist';
  }
  die();
}

// Normal use
if (base64_encode(hash_hmac('sha256', $img_url, PSK, true)) != $auth) {
  // Authentication is incorrect. Something other than the paired Gazelle instance is attempting to use the host.
  serve('imgs/unauthorized.png', 401);
  die();
}
if (file_exists($img_path)) {
  serve($img_path);
} else {
  // The file is not cached. Fetch it, cache it, and serve it.
  $img_data = @file_get_contents($img_url, 0, stream_context_create([
    'http' => ['user_agent' => USER_AGENT],
    'ssl' => ['verify_peer' => false]
  ]), 0, 134217728);
  $content_type = content_type($img_data);
  if ($content_type != 'application/octet-stream') {
    if (!file_exists($img_dir)) mkdir($img_dir);
    file_put_contents($img_path, $img_data);
    serve($img_path);
  } else {
    serve('imgs/invalid.png', 415);
  }
}
?>
