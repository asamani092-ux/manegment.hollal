<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$public = dirname(__DIR__) . '/public';
$file = $public . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require $public . '/index.php';
