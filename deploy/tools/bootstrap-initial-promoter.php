<?php

$configFile = $argv[1] ?? '';
$outputFile = $argv[2] ?? '';
if ($configFile === '' || $outputFile === '' || !is_file($configFile)) {
    fwrite(STDERR, "Usage: php bootstrap-initial-promoter.php database.php output.txt\n");
    exit(2);
}

$config = include $configFile;
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['hostname'],
    $config['hostport'],
    $config['database']
);
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$login = 'partner_root';
$pdo->beginTransaction();
try {
    $statement = $pdo->prepare('SELECT id,user_pass FROM jl_user WHERE user_login=? LIMIT 1 FOR UPDATE');
    $statement->execute([$login]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    $password = '';

    if (!$user) {
        $password = 'Np!' . rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
        $passwordHash = '###' . md5(md5($config['authcode'] . $password));
        $statement = $pdo->prepare(
            'INSERT INTO jl_user '
            . '(user_login,user_nickname,user_pass,user_type,user_status,avatar,signature,create_time) '
            . "VALUES (?,? ,?,2,1,'avatar.png','N/A',?)"
        );
        $statement->execute([$login, 'Neuranet Partner', $passwordHash, time()]);
        $userId = (int) $pdo->lastInsertId();
    } else {
        $userId = (int) $user['id'];
    }

    $statement = $pdo->prepare(
        'SELECT id,code FROM jl_invitation_code '
        . 'WHERE owner_user_id=? AND is_self_generated=1 ORDER BY id LIMIT 1 FOR UPDATE'
    );
    $statement->execute([$userId]);
    $invitation = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$invitation) {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $statement = $pdo->prepare('SELECT COUNT(*) FROM jl_invitation_code WHERE code=?');
            $statement->execute([$code]);
        } while ((int) $statement->fetchColumn() > 0);

        $statement = $pdo->prepare(
            'INSERT INTO jl_invitation_code '
            . '(owner_user_id,code,max_use_count,used_count,status,is_self_generated,create_time,update_time) '
            . 'VALUES (?,?,0,0,1,1,?,?)'
        );
        $statement->execute([$userId, $code, time(), time()]);
    } else {
        $code = $invitation['code'];
        $pdo->prepare('UPDATE jl_invitation_code SET status=1,update_time=? WHERE id=?')
            ->execute([time(), $invitation['id']]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

$lines = [
    'Username: ' . $login,
    'Invitation code: ' . $code,
    'Registration URL: https://neuranet.site/app/sign/register?invitation_code=' . $code,
];
if ($password !== '') {
    $lines[] = 'Password: ' . $password;
} else {
    $lines[] = 'Password: unchanged (see the original credential record)';
}
$lines[] = 'Created: ' . date('c');

if (file_put_contents($outputFile, implode("\n", $lines) . "\n", LOCK_EX) === false) {
    throw new RuntimeException('Credential file could not be written');
}
chmod($outputFile, 0600);

echo "INITIAL_PROMOTER=PASS\n";
echo "USER_ID={$userId}\n";
echo "INVITATION_CODE={$code}\n";
