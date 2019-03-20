<?php

// Location where hosted images will be stored
define('IMG_ROOT', '/var/images/');
// Pre-shared key - must match IMAGE_PSK in gazelle config
define('PSK', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');

ini_set('memory_limit', '256M');

function image_type($Data) {
  if (!strncmp($Data, pack('H*', '89504E47'), 4)) return 'png';
  if (!strncmp($Data, pack('H*', 'FFD8'), 2)) return 'jpeg';
  if (!strncmp($Data, 'GIF', 3)) return 'gif';
  if (strlen($Data) > 35 && !substr_compare($Data, 'webm', 31, 4)) return 'webm';
  if (!strncmp($Data, 'BM', 2)) return 'bmp';
  if (!strncmp($Data, 'II', 2) || !strncmp($Data, 'MM', 2)) return 'tiff';
  return '';
}

$ImgURL = $_GET['i'];
$Auth = rawurldecode($_GET['h']);
$ImgURLHash = hash('sha256', $ImgURL);

// Deletion
if (!empty($_GET['d'])) {
  $ImgURL = $_GET['d'];
  $ImgURLHash = hash('sha256', $ImgURL);
  if (base64_encode(hash_hmac('sha256', $ImgURL, strrev(PSK), true)) != $Auth) {
    echo 'Auth failure';
    die();
  }
  if (file_exists(IMG_ROOT.substr($ImgURLHash,0,2).'/'.$ImgURLHash)) {
    unlink(IMG_ROOT.substr($ImgURLHash,0,2).'/'.$ImgURLHash);
    echo 'Success';
  } else {
    echo 'File does not exist';
  }
  die();
}

// Normal use
if (base64_encode(hash_hmac('sha256', $ImgURL, PSK, true)) != $Auth) {
  // Authentication is incorrect. Something other than the paired Gazelle instance is attempting to use the host.
  header('Content-type: image/png');
  echo file_get_contents('imgs/unauthorized.png');
  die();
}
if (file_exists(IMG_ROOT.substr($ImgURLHash,0,2).'/'.$ImgURLHash)) {
  // The file is cached. Serve it.
  $Img = file_get_contents(IMG_ROOT.substr($ImgURLHash,0,2).'/'.$ImgURLHash);
  $FileType = image_type($Img);
  header('Content-type: '.(($FileType=='webm')?'video':'image').'/'.$FileType);
  echo $Img;
} else {
  // The file is not cached. Fetch it, cache it, and serve it.
  $Img = @file_get_contents($ImgURL, 0, stream_context_create(['http' => ['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.2987.133 Safari/537.36'], 'ssl' => ['verify_peer' => false]]), 0, 134217728);
  $FileType = image_type($Img);
  if (!empty($FileType)) {
    if (!file_exists(IMG_ROOT.substr($ImgURLHash,0,2))) {
      mkdir(IMG_ROOT.substr($ImgURLHash,0,2));
    }
    file_put_contents(IMG_ROOT.substr($ImgURLHash,0,2).'/'.$ImgURLHash, $Img);
    header('Content-type: '.(($FileType=='webm')?'video':'image').'/'.$FileType);
    echo $Img;
  } else {
    header('Content-type: image/png');
    echo file_get_contents('imgs/invalid.png');
  }
}
?>
