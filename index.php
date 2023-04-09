<?php

declare(strict_types=1);


/**
 * image proxy configuration
 */

# important!
ini_set("memory_limit", "256M");

# hosted image storage location
$imageRoot = "/var/www/pictures/";

# preshared key: must match the gazelle config
$presharedKey = "";

# user agent to use when downloading files
# https://techblog.willshouse.com/2012/01/03/most-common-user-agents/
$userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36";

# hmac hash algorithm to use
$algorithm = "sha3-512";


/** */


/**
 * the proxy script itself
 */

if (empty($_GET["i"])) {
    exit;
}

$imageUri = $_GET["i"];
$auth = rawurldecode($_GET["h"]);

$imageUriHash = hash($algorithm, $imageUri);
$imageDirectory = $imageRoot . substr($imageUriHash, 0, 2);
$imagePath = "{$imageDirectory}/{$imageUriHash}";

# deletion
if (!empty($_GET["d"])) {
    $imageUri = $_GET["d"];
    $imageUriHash = hash($algorithm, $imageUri);

    if (base64_encode(hash_hmac($algorithm, $imageUri, strrev($presharedKey), true)) !== $auth) {
        http_response_code(401);
        echo "auth failure";
        exit;
    }

    if (file_exists($imagePath)) {
        unlink(realpath($imagePath));
        http_response_code(200);
        echo "success";
    } else {
        http_response_code(404);
        echo "file doesn't exist";
    }

    exit;
}

# normal use
if (base64_encode(hash_hmac($algorithm, $imageUri, $presharedKey, true)) !== $auth) {
    # authentication is incorrect: something other than the paired gazelle instance is attempting to use the host
    serve("images/unauthorized.png", 401);
    exit;
}

if (file_exists($imagePath)) {
    serve($imagePath);
} else {
    # the file is not cached: fetch it, cache it, and serve it
    $imageData = @file_get_contents($imageUri, false, stream_context_create([
        "http" => ["user_agent" => $userAgent],
        "ssl" => ["verify_peer" => false]
    ]), 0, 134217728);

    $contentType = contentType($imageData);
    if ($contentType !== "application/octet-stream") {
        if (!file_exists($imageDirectory)) {
            mkdir($imageDirectory);
        }

        file_put_contents($imagePath, $imageData);
        serve($imagePath);
    } else {
        serve("images/invalid.png", 415);
    }
}


/** */


/**
 * contentType
 *
 * @param string $data
 * @return string
 */
function contentType(string $data): string
{
    # image/png
    if (!strncmp($data, pack("H*", "89504E47"), 4)) {
        return "image/png";
    }

    # image/jpeg
    if (!strncmp($data, pack("H*", "FFD8"), 2)) {
        return "image/jpeg";
    }

    # image/gif
    if (!strncmp($data, "GIF", 3)) {
        return "image/gif";
    }

    # video/webm
    if (strlen($data) > 35 && !substr_compare($data, "webm", 31, 4)) {
        return "video/webm";
    }

    # image/bmp
    if (!strncmp($data, "BM", 2)) {
        return "image/bmp";
    }

    # image/tiff
    if (!strncmp($data, "II", 2) || !strncmp($data, "MM", 2)) {
        return "image/tiff";
    }

    # application/octet-stream
    return "application/octet-stream";
}


/**
 * serve
 *
 * @param string $path
 * @param int $responseCode
 * @return void
 */
function serve(string $path, int $responseCode = 200): void
{
    $filesize = filesize($path);
    $handle = fopen($path, "r");
    $contentType = contentType(fread($handle, 100));
    fclose($handle);

    header("Accept-Ranges: bytes");
    header("Content-Type: {$contentType}");

    if (isset($_SERVER["HTTP_RANGE"])) {
        preg_match("/bytes=(\d+)-(\d+)?/", $_SERVER["HTTP_RANGE"], $matches);

        if ($matches[1] === "") {
            $matches[1] = $filesize - intval($matches[2] ?? 0);
            $matches[2] = $filesize;
        }

        $start = intval($matches[1] ?? 0);
        $end = min(intval($matches[2] ?? $filesize), $filesize);

        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$filesize}");
        echo file_get_contents($path, false, null, $start, $end - $start);
    } else {
        http_response_code($responseCode);
        header("Content-Length: {$filesize}");
        readfile($path);
    }
}
