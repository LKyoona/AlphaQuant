<?php

$configFile = $argv[1] ?? '';
$outputFile = $argv[2] ?? '';
if ($configFile === '' || $outputFile === '' || !is_file($configFile)) {
    fwrite(STDERR, "Usage: php reset_admin_password.php database.php output.txt\n");
    exit(2);
}

$config = include $configFile;
$password = 'Nn!' . rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
$hash = '###' . md5(md5($config['authcode'] . $password));
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['hostname'],
    $config['hostport'],
    $config['database']
);
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$statement = $pdo->prepare(
    "UPDATE jl_user SET user_pass=?,user_type=1,user_status=1 WHERE id=1 AND user_login='admin'"
);
$statement->execute([$hash]);
if ($statement->rowCount() < 1) {
    throw new RuntimeException('Admin account was not updated');
}

$content = "Username: admin\nPassword: {$password}\nCreated: " . date('c') . "\n";
if (file_put_contents($outputFile, $content, LOCK_EX) === false) {
    throw new RuntimeException('Password file could not be written');
}
chmod($outputFile, 0600);
echo "Admin password updated\n";
