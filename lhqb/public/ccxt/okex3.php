<?php

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('forbidden');
}

include_once 'ccxt.php';

$exchange_name = 'okex3';
$apiKey = getenv('OKEX3_API_KEY') ?: '';
$secret = getenv('OKEX3_SECRET') ?: '';
$password = getenv('OKEX3_PASSWORD') ?: '';

if ($apiKey === '' || $secret === '' || $password === '') {
    die("exchange keys don't exist");
}

$className = "\ccxt\\" . $exchange_name;

$exchange = new $className([
    'apiKey' => $apiKey,
    'secret' => $secret,
    'password' => $password,
]);

$params = ['type' => 'swap'];
$result = $exchange->fetch_balance($params);

print_r($result);
