<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php ccxt-memory-acceptance.php <project-root>\n");
    exit(2);
}

ini_set('memory_limit', '512M');
require rtrim($argv[1], '/\\') . '/public/ccxt/ccxt.php';

try {
    $exchange = new \ccxt\binance([
        'apiKey' => 'invalid_acceptance_key',
        'secret' => 'invalid_acceptance_secret',
        'password' => 'invalid',
        'timeout' => 10000,
        'enableRateLimit' => true,
        'options' => [
            'defaultType' => 'spot',
            'adjustForTimeDifference' => true,
            'recvWindow' => 10000,
        ],
    ]);
    $exchange->fetch_balance();
    fwrite(STDERR, "Unexpected authentication success\n");
    exit(3);
} catch (Throwable $e) {
    echo 'CCXT_ERROR_HANDLED=' . get_class($e) . PHP_EOL;
    echo 'PEAK_MEMORY=' . memory_get_peak_usage(true) . PHP_EOL;
    exit(0);
}
