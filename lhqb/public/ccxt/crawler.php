<?php

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('forbidden');
}

include_once 'ccxt.php';

function get_exchange_env($exchange, $name)
{
    $value = getenv(strtoupper($exchange) . '_' . $name);
    return $value === false ? '' : $value;
}

if (!isset($argv[1])) {
    die("exchange can't be null");
}

if (!isset($argv[2])) {
    die("market can't be null");
}

$exchange_name = $argv[1];
$market = $argv[2];

if (empty($exchange_name)) {
    die("exchange can't be null");
}

if (empty($market)) {
    die("market can't be null");
}

$apiKey = get_exchange_env($exchange_name, 'API_KEY');
$secret = get_exchange_env($exchange_name, 'SECRET');

if ($apiKey === '' || $secret === '') {
    die("exchange keys don't exist");
}

$className = "\ccxt\\" . $exchange_name;

$exchange = new $className([
    'apiKey' => $apiKey,
    'secret' => $secret,
]);

$result = $exchange->fetch_ticker($market);

$data = [
    'exchange' => $exchange_name,
    'market' => $market,
    'price' => $result['bid'],
    'change' => $result['percentage'],
    'volume' => $result['info']['vol'],
    'update_time' => time(),
];

die(json_encode($data));
