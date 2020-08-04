<?php declare(strict_types=1);

//        WARNING         \\
// NOT FOR PRODUCTION USE \\
//                        \\

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

$_GET['_url'] = $_SERVER['REQUEST_URI'];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET,PUT,POST,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type,Accept,Authorization');

require_once __DIR__ . '/public/index.php';