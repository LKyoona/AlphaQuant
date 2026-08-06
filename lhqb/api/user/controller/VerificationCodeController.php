<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\user\controller;

use cmf\controller\RestBaseController;
use think\Validate;

class VerificationCodeController extends RestBaseController
{
    private function logVerificationMailStatus($message)
    {
        $logFile = CMF_ROOT . 'data/runtime/log/smtp_verification.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    private function getEmailConfig()
    {
        $configFile = CMF_ROOT . 'data/conf/email.php';
        if (is_file($configFile)) {
            $config = include $configFile;
            if (is_array($config)) {
                return $config;
            }
        }

        $smtpSetting = cmf_get_option('smtp_setting');
        return [
            'enabled'     => !empty($smtpSetting),
            'host'        => isset($smtpSetting['host']) ? $smtpSetting['host'] : '',
            'port'        => isset($smtpSetting['port']) ? $smtpSetting['port'] : 25,
            'smtp_secure' => isset($smtpSetting['smtp_secure']) ? $smtpSetting['smtp_secure'] : '',
            'username'    => isset($smtpSetting['username']) ? $smtpSetting['username'] : '',
            'password'    => isset($smtpSetting['password']) ? $smtpSetting['password'] : '',
            'from'        => isset($smtpSetting['from']) ? $smtpSetting['from'] : '',
            'from_name'   => isset($smtpSetting['from_name']) ? $smtpSetting['from_name'] : 'AI Crypto Star',
            'timeout'     => 20,
        ];
    }

    private function sendVerificationEmail($address, $subject, $message)
    {
        $config = $this->getEmailConfig();
        if (empty($config['enabled'])) {
            return ['error' => 1, 'message' => '邮箱发送配置未启用'];
        }

        list($routeName, $config) = $this->resolveEmailRoute($config, $address);

        $pool = [];
        if (!empty($config['pool']) && is_array($config['pool'])) {
            foreach ($config['pool'] as $account) {
                if (!is_array($account)) {
                    continue;
                }
                $setting = array_merge($config, $account);
                unset($setting['pool']);
                if (empty($setting['from']) && !empty($setting['username'])) {
                    $setting['from'] = $setting['username'];
                }
                $pool[] = $setting;
            }
        } elseif (!empty($config['username'])) {
            if (empty($config['from'])) {
                $config['from'] = $config['username'];
            }
            $pool[] = $config;
        }

        if (empty($pool)) {
            return ['error' => 1, 'message' => 'SMTP 邮箱池为空'];
        }

        $poolSize = count($pool);
        $maxAttempts = max(1, min($poolSize, (int) ($config['max_attempts'] ?? $poolSize)));
        $startIndex = (int) (sprintf('%u', crc32(strtolower($address) . date('YmdHi'))) % $poolSize);
        $result = $this->attemptSendVerificationEmail($pool, $address, $subject, $message, $maxAttempts, true, $routeName);
        if (empty($result['error'])) {
            return $result;
        }

        if (($result['attempts'] ?? 0) === 0 && ($result['skipped'] ?? 0) >= $poolSize) {
            $this->logVerificationMailStatus('all smtp accounts were cooling down, retrying without cooldown gate, target=' . $this->maskEmail($address));
            $retryResult = $this->attemptSendVerificationEmail($pool, $address, $subject, $message, $maxAttempts, false, $routeName);
            if (empty($retryResult['error'])) {
                return $retryResult;
            }
            return $retryResult;
        }

        return $result;
    }

    private function resolveEmailRoute(array $config, $address)
    {
        $defaultRouteName = trim((string) ($config['route_name'] ?? 'poste'));
        $defaultRouteName = $defaultRouteName === '' ? 'poste' : $defaultRouteName;
        $parts = explode('@', strtolower(trim((string) $address)), 2);
        $domain = count($parts) === 2 ? $parts[1] : '';

        if ($domain !== '' && !empty($config['routes']) && is_array($config['routes'])) {
            foreach ($config['routes'] as $routeName => $routeConfig) {
                if (!is_array($routeConfig) || empty($routeConfig['enabled'])) {
                    continue;
                }
                $domains = isset($routeConfig['domains']) && is_array($routeConfig['domains'])
                    ? array_map('strtolower', array_map('trim', $routeConfig['domains']))
                    : [];
                if (!in_array($domain, $domains, true)) {
                    continue;
                }

                $resolved = array_merge($config, $routeConfig);
                if (!array_key_exists('pool', $routeConfig)) {
                    unset($resolved['pool']);
                }
                unset($resolved['routes'], $resolved['domains']);
                return [(string) $routeName, $resolved];
            }
        }

        unset($config['routes']);
        return [$defaultRouteName, $config];
    }

    private function attemptSendVerificationEmail($pool, $address, $subject, $message, $maxAttempts, $respectCooldown, $routeName = 'poste')
    {
        $poolSize = count($pool);
        $startIndex = (int) (sprintf('%u', crc32(strtolower($address) . date('YmdHi'))) % $poolSize);
        $attempts = 0;
        $skipCount = 0;
        $lastMessage = '';

        for ($offset = 0; $offset < $poolSize && $attempts < $maxAttempts; $offset++) {
            $smtpSetting = $pool[($startIndex + $offset) % $poolSize];
            $username = trim((string) ($smtpSetting['username'] ?? ''));
            $cooldownKey = 'smtp_pool_cooldown_' . sha1(strtolower($username));
            if ($username === '') {
                $skipCount++;
                continue;
            }
            if ($respectCooldown && cache($cooldownKey)) {
                $skipCount++;
                continue;
            }

            foreach (['host', 'username', 'password', 'from'] as $field) {
                if (empty($smtpSetting[$field])) {
                    $lastMessage = '邮箱发送配置缺少 ' . $field;
                    cache($cooldownKey, time(), 3600);
                    $this->logVerificationMailStatus('skip invalid smtp account ' . $this->maskEmail($username) . ', reason=' . $lastMessage);
                    continue 2;
                }
            }

            $attempts++;
            $result = $this->sendSmtpMail($smtpSetting, $address, $subject, $message);
            if (empty($result['error'])) {
                $this->logVerificationMailStatus('success route=' . $routeName . ', target=' . $this->maskEmail($address) . ', account=' . $this->maskEmail($username) . ', attempts=' . $attempts . ', skipped=' . $skipCount . ', pool=' . $poolSize . ', respect_cooldown=' . ($respectCooldown ? '1' : '0'));
                return ['error' => 0, 'message' => 'success'];
            }

            $lastMessage = trim((string) ($result['message'] ?? 'SMTP send failed'));
            $isAuthError = (bool) preg_match('/(?:^|\s)(?:534|535)(?:\s|$)|auth(?:entication)?|credential|app password|account disabled/i', $lastMessage);
            $cooldown = $isAuthError ? 3600 : 300;
            cache($cooldownKey, time(), max(60, $cooldown));
            $safeUser = $this->maskEmail($username);
            $safeError = mb_substr(str_replace(["\r", "\n"], ' ', $lastMessage), 0, 300, 'UTF-8');
            trace('SMTP邮箱池发送失败 [' . $safeUser . ']: ' . $safeError, 'error');
            $this->logVerificationMailStatus('failed route=' . $routeName . ', account=' . $safeUser . ', target=' . $this->maskEmail($address) . ', attempts=' . $attempts . ', skipped=' . $skipCount . ', pool=' . $poolSize . ', respect_cooldown=' . ($respectCooldown ? '1' : '0') . ', auth_error=' . ($isAuthError ? '1' : '0') . ', message=' . $safeError);
        }

        $this->logVerificationMailStatus('all attempts exhausted, route=' . $routeName . ', target=' . $this->maskEmail($address) . ', attempts=' . $attempts . ', skipped=' . $skipCount . ', pool=' . $poolSize . ', respect_cooldown=' . ($respectCooldown ? '1' : '0') . ', last=' . mb_substr(str_replace(["\r", "\n"], ' ', $lastMessage), 0, 300, 'UTF-8'));
        return [
            'error' => 1,
            'message' => $lastMessage === '' ? 'SMTP 邮箱池暂无可用账号' : $lastMessage,
            'attempts' => $attempts,
            'skipped' => $skipCount,
        ];
    }

    private function maskEmail($email)
    {
        $parts = explode('@', (string) $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $name = $parts[0];
        return substr($name, 0, min(2, strlen($name))) . '***@' . $parts[1];
    }

    private function readSmtpResponse($socket)
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

    private function smtpCommand($socket, $command, $expectCodes, $label = '')
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->readSmtpResponse($socket);
        $code = substr($response, 0, 3);
        if (!in_array($code, (array) $expectCodes, true)) {
            $safeLabel = empty($label) ? $command : $label;
            throw new \RuntimeException($safeLabel . ' failed: ' . ($response === '' ? 'SMTP 无响应' : $response));
        }

        return $response;
    }

    private function encodeMailHeader($value)
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function htmlToPlainText($html)
    {
        $text = preg_replace('/<(?:br\s*\/?>|\/p>|\/div>|\/h[1-6]>)/i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function buildVerificationEmail($code)
    {
        $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html><body style="margin:0;padding:0;background:#0b0d12;color:#f8efd9;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0d12;padding:32px 12px;">'
            . '<tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#15110c;border:1px solid #6b5025;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="padding:30px 34px 14px;font-family:Arial,sans-serif;color:#d7aa54;font-size:12px;letter-spacing:3px;text-transform:uppercase;">AI Crypto Star</td></tr>'
            . '<tr><td style="padding:0 34px;font-family:Arial,sans-serif;color:#fff7e6;font-size:26px;font-weight:700;">Verify your email</td></tr>'
            . '<tr><td style="padding:12px 34px 0;font-family:Arial,sans-serif;color:#c9bfae;font-size:15px;line-height:1.7;">Use the security code below to continue. Only the newest code is valid.</td></tr>'
            . '<tr><td style="padding:24px 34px;"><div style="padding:19px 16px;background:#0b0d12;border:1px solid #a97b30;border-radius:12px;text-align:center;font-family:Arial,sans-serif;color:#ffd77a;font-size:34px;font-weight:800;letter-spacing:9px;">' . $safeCode . '</div></td></tr>'
            . '<tr><td style="padding:0 34px 30px;font-family:Arial,sans-serif;color:#9f9688;font-size:13px;line-height:1.7;">This code expires in 30 minutes. If you did not request it, you can safely ignore this email.</td></tr>'
            . '<tr><td style="padding:18px 34px;background:#100d09;border-top:1px solid #332718;font-family:Arial,sans-serif;color:#766d61;font-size:12px;">Automated security message. Please do not reply.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private function sendSmtpMail($smtpSetting, $address, $subject, $message)
    {
        $host = $smtpSetting['host'];
        $port = empty($smtpSetting['port']) ? 25 : (int) $smtpSetting['port'];
        $timeout = empty($smtpSetting['timeout']) ? 20 : (int) $smtpSetting['timeout'];
        $secure = strtolower((string) $smtpSetting['smtp_secure']);
        $remoteHost = $secure === 'ssl' ? 'ssl://' . $host : $host;

        $socket = stream_socket_client($remoteHost . ':' . $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['error' => 1, 'message' => '连接 SMTP 失败:' . $errstr . '(' . $errno . ')'];
        }

        stream_set_timeout($socket, $timeout);

        try {
            $response = $this->readSmtpResponse($socket);
            if (substr($response, 0, 3) !== '220') {
                throw new \RuntimeException('SMTP greeting failed: ' . $response);
            }

            $serverName = !empty($smtpSetting['helo_domain'])
                ? $smtpSetting['helo_domain']
                : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
            $this->smtpCommand($socket, 'EHLO ' . $serverName, ['250']);

            if ($secure === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', ['220']);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('开启 TLS 加密失败');
                }
                $this->smtpCommand($socket, 'EHLO ' . $serverName, ['250']);
            }

            $this->smtpCommand($socket, 'AUTH LOGIN', ['334'], 'AUTH LOGIN');
            $this->smtpCommand($socket, base64_encode($smtpSetting['username']), ['334'], 'AUTH username');
            $this->smtpCommand($socket, base64_encode($smtpSetting['password']), ['235'], 'AUTH password');
            $this->smtpCommand($socket, 'MAIL FROM:<' . $smtpSetting['from'] . '>', ['250']);
            $this->smtpCommand($socket, 'RCPT TO:<' . $address . '>', ['250', '251']);
            $this->smtpCommand($socket, 'DATA', ['354']);

            $fromName = empty($smtpSetting['from_name']) ? 'AI Crypto Star' : $smtpSetting['from_name'];
            $messageIdDomain = empty($smtpSetting['message_id_domain'])
                ? substr(strrchr($smtpSetting['from'], '@'), 1)
                : $smtpSetting['message_id_domain'];
            if (!preg_match('/^[A-Za-z0-9.-]+$/', (string) $messageIdDomain)) {
                $messageIdDomain = 'localhost';
            }
            $boundary = '=_lhqb_' . bin2hex(random_bytes(12));
            $plainMessage = $this->htmlToPlainText($message);
            $headers = [
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(16)) . '.' . time() . '@' . $messageIdDomain . '>',
                'From: ' . $this->encodeMailHeader($fromName) . ' <' . $smtpSetting['from'] . '>',
                'To: <' . $address . '>',
                'Subject: ' . $this->encodeMailHeader($subject),
                'Auto-Submitted: auto-generated',
                'X-Auto-Response-Suppress: All',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];
            $body = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($plainMessage)) . "\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($message)) . "\r\n"
                . '--' . $boundary . "--\r\n";
            fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            $response = $this->readSmtpResponse($socket);
            if (substr($response, 0, 3) !== '250') {
                throw new \RuntimeException('发送邮件内容失败: ' . $response);
            }

            // DATA 返回 250 代表邮件已被服务器接收，QUIT 失败不能再触发备用账号重发。
            @fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return ['error' => 0, 'message' => 'success'];
        } catch (\Throwable $e) {
            fclose($socket);
            $message = $e->getMessage();
            return ['error' => 1, 'message' => $message === '' ? get_class($e) : $message];
        }
    }

    public function send()
    {
        $validate = new Validate([
            'username' => 'require',
        ]);

        $validate->message([
            'username.require' => '请输入手机号或邮箱!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $data['username'] = cmf_normalize_verification_account($data['username']);

        $accountType = '';

        if (Validate::is($data['username'], 'email')) {
            $accountType = 'email';
        } else if (cmf_check_mobile($data['username'])) {
            $accountType = 'mobile';
        } else {
            $this->error("请输入正确的手机或者邮箱格式!");
        }

        $retryAfter = cmf_get_verification_retry_after($data['username']);
        if ($retryAfter > 0) {
            $this->error('请在' . $retryAfter . '秒后重新获取验证码', [
                'retry_after' => $retryAfter,
            ]);
        }

        $code = cmf_get_verification_code($data['username']);
        if (empty($code)) {
            $this->error("验证码发送过多,请明天再试!");
        }

        if ($accountType == 'email') {
            try {

                // Verification mail uses one maintained template instead of the legacy DB HTML.
                $message = $this->buildVerificationEmail($code);
                $subject = 'Your AI Crypto Star verification code';
                $result  = $this->sendVerificationEmail($data['username'], $subject, $message);

                if (empty($result['error'])) {
                    cmf_verification_code_log($data['username'], $code);
                    $this->success('验证码已发送。若收件箱未显示，请检查垃圾邮件或促销邮件；仅使用最新一封邮件中的验证码。');
                } else {
                    cmf_release_verification_cooldown($data['username']);
                    $this->error('验证码邮件暂时发送失败，请稍后重试');
                }
            } catch (\think\exception\HttpResponseException $e) {
                throw $e;
            } catch (\Throwable $e) {
                cmf_release_verification_cooldown($data['username']);
                $exceptionMessage = trim((string) $e->getMessage());
                trace('验证码邮件异常: ' . mb_substr(str_replace(["\r", "\n"], ' ', $exceptionMessage), 0, 300, 'UTF-8'), 'error');
                $this->error('验证码邮件暂时发送失败，请稍后重试');
            }

        } else if ($accountType == 'mobile') {

            $code = rand(10000, 99999);
            $param  = ['mobile' => $data['username'], 'code' => $code];
            $result = hook_one("send_mobile_verification_code", $param);

            if ($result !== false && !empty($result['error'])) {
                cmf_release_verification_cooldown($data['username']);
                $this->error($result['message']);
            }

            if ($result === false) {
                cmf_verification_code_log($data['username'], $code);
                $this->success('验证码：' . $code);
            }

            cmf_verification_code_log($data['username'], $code);

            if (!empty($result['message'])) {
                $this->success($result['message']);
            } else {
                $this->success('验证码已经发送成功!');
            }

        }


    }

}
