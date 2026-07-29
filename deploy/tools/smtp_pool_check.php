<?php

$configFile = $argv[1] ?? '';
if ($configFile === '' || !is_file($configFile)) {
    fwrite(STDERR, "Usage: php smtp_pool_check.php /path/to/email.php\n");
    exit(2);
}

$config = include $configFile;
$pool = isset($config['pool']) && is_array($config['pool']) ? $config['pool'] : [];
$requestedLimit = isset($argv[2]) ? (int) $argv[2] : (int) ($config['max_attempts'] ?? 5);
$limit = min(count($pool), max(1, $requestedLimit));

function readResponse($socket)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return trim($response);
}

function command($socket, $value)
{
    fwrite($socket, $value . "\r\n");
    return readResponse($socket);
}

function maskEmail($email)
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return substr($name, 0, min(2, strlen($name))) . '***@' . $domain;
}

for ($index = 0; $index < $limit; $index++) {
    $setting = array_merge($config, $pool[$index]);
    unset($setting['pool']);
    $username = (string) ($setting['username'] ?? '');
    $host = (string) ($setting['host'] ?? '');
    $port = (int) ($setting['port'] ?? 465);
    $timeout = (int) ($setting['timeout'] ?? 15);
    $secureHost = strtolower((string) ($setting['smtp_secure'] ?? '')) === 'ssl'
        ? 'ssl://' . $host
        : $host;

    $socket = @stream_socket_client($secureHost . ':' . $port, $errno, $error, $timeout);
    if (!$socket) {
        echo maskEmail($username) . " CONNECT_ERROR {$errno}\n";
        continue;
    }

    stream_set_timeout($socket, $timeout);
    $greeting = readResponse($socket);
    $ehlo = command($socket, 'EHLO lhqb.local');
    $tlsResponse = 'SKIP';
    if (strtolower((string) ($setting['smtp_secure'] ?? '')) === 'tls') {
        $tlsResponse = command($socket, 'STARTTLS');
        if (substr($tlsResponse, 0, 3) !== '220' || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            echo maskEmail($username) . " STARTTLS_ERROR " . substr($tlsResponse, 0, 3) . "\n";
            fclose($socket);
            continue;
        }
        $ehlo = command($socket, 'EHLO lhqb.local');
    }
    $auth = command($socket, 'AUTH LOGIN');
    $userResponse = command($socket, base64_encode($username));
    $passwordResponse = command($socket, base64_encode((string) ($setting['password'] ?? '')));
    @fwrite($socket, "QUIT\r\n");
    fclose($socket);

    printf(
        "%s GREETING=%s TLS=%s EHLO=%s AUTH=%s USER=%s PASSWORD=%s\n",
        maskEmail($username),
        substr($greeting, 0, 3),
        substr($tlsResponse, 0, 3),
        substr($ehlo, 0, 3),
        substr($auth, 0, 3),
        substr($userResponse, 0, 3),
        substr($passwordResponse, 0, 3)
    );
}
