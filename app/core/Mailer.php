<?php
/**
 * Envio de e-mails.
 *
 * - Com mail.smtp configurado no config.php: envia por SMTP autenticado
 *   (recomendado; ex.: Hostinger smtp.hostinger.com:465 ssl, com a caixa do remetente).
 * - Sem SMTP: usa mail() do PHP, com envelope sender (-f) do remetente.
 *
 * Falhas não interrompem o fluxo (candidatura continua gravada), mas ficam no log
 * e em Mailer::lastError() para diagnóstico na tela "Teste de e-mail".
 */
class Mailer
{
    private static string $lastError = '';
    private static string $lastMethod = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function lastMethod(): string
    {
        return self::$lastMethod;
    }

    public static function notifyHR(string $subject, string $message): bool
    {
        $cfg = Config::app()['mail'];
        return self::sendTo((string)($cfg['to_hr'] ?? ''), $subject, $message);
    }

    public static function sendTo(string $to, string $subject, string $message): bool
    {
        self::$lastError = '';
        $cfg = Config::app()['mail'];
        if (empty($cfg['enabled'])) {
            self::$lastMethod = 'desativado';
            self::$lastError = 'Envio de e-mail desativado (mail.enabled = false).';
            return false;
        }
        $from = trim((string)($cfg['from'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            self::$lastMethod = 'validação';
            self::$lastError = 'Destinatário ou remetente inválido (confira mail.from e mail.to_hr).';
            self::logFailure($to);
            return false;
        }
        $fromName = Brand::clientName();
        $smtp = (array)($cfg['smtp'] ?? []);

        $ok = trim((string)($smtp['host'] ?? '')) !== ''
            ? self::sendSmtp($smtp, $from, $fromName, $to, $subject, $message)
            : self::sendNative($from, $fromName, $to, $subject, $message);

        if (!$ok) {
            self::logFailure($to);
        }
        return $ok;
    }

    public static function notifyUserPasswordChanged(string $to, string $userName, int $adminId): bool
    {
        $cfg = Config::app();
        $subject = (string)($cfg['mail']['subject_password_changed'] ?? 'Senha alterada');
        $baseUrl = rtrim((string)($cfg['base_url'] ?? ''), '/');
        $loginUrl = $baseUrl !== '' ? $baseUrl . '/login' : '/login';
        $timestamp = date('d/m/Y H:i:s');
        $message = "Olá {$userName},\n\nSua senha foi alterada por um administrador.\nData e hora da alteração: {$timestamp}\nID do administrador responsável: {$adminId}\n\nPara acessar o sistema, utilize o link: {$loginUrl}\nCaso não reconheça esta ação, entre em contato imediatamente com o suporte de TI.\n";
        return self::sendTo($to, $subject, $message);
    }

    // ------------------------------------------------------------------

    private static function sendNative(string $from, string $fromName, string $to, string $subject, string $body): bool
    {
        self::$lastMethod = 'mail() do PHP';
        $headers = implode("\r\n", [
            'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: TRAXTER-RH',
        ]);
        $ok = @mail($to, self::encodeHeader($subject), self::normalizeBody($body), $headers, '-f' . $from);
        if (!$ok) {
            $err = error_get_last();
            self::$lastError = 'mail() recusou o envio' . ($err ? ': ' . $err['message'] : '') . '. Configure SMTP (mail.smtp) no config.php.';
        }
        return $ok;
    }

    private static function sendSmtp(array $smtp, string $from, string $fromName, string $to, string $subject, string $body): bool
    {
        $host = trim((string)$smtp['host']);
        $port = (int)($smtp['port'] ?? 465);
        $secure = strtolower((string)($smtp['secure'] ?? ($port === 465 ? 'ssl' : 'tls')));
        $user = (string)($smtp['user'] ?? $from);
        $pass = (string)($smtp['pass'] ?? '');
        self::$lastMethod = "SMTP {$host}:{$port} ({$secure})";

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            self::$lastError = "Não conectou em {$host}:{$port} ({$errstr}). Confira host, porta e 'secure'.";
            return false;
        }
        stream_set_timeout($fp, 15);

        try {
            self::expect($fp, [220]);
            $ehloHost = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
            self::cmd($fp, "EHLO {$ehloHost}", [250]);
            if ($secure === 'tls') {
                self::cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Falha ao iniciar TLS.');
                }
                self::cmd($fp, "EHLO {$ehloHost}", [250]);
            }
            if ($pass !== '') {
                self::cmd($fp, 'AUTH LOGIN', [334]);
                self::cmd($fp, base64_encode($user), [334]);
                self::cmd($fp, base64_encode($pass), [235], 'Usuário ou senha do SMTP recusados.');
            }
            self::cmd($fp, "MAIL FROM:<{$from}>", [250]);
            self::cmd($fp, "RCPT TO:<{$to}>", [250, 251]);
            self::cmd($fp, 'DATA', [354]);

            $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
            $headers = [
                'Date: ' . date('r'),
                'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'X-Mailer: TRAXTER-RH',
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n"
                . rtrim(chunk_split(base64_encode(self::normalizeBody($body)), 76, "\r\n")) . "\r\n.";
            self::cmd($fp, $data, [250], 'Servidor SMTP recusou a mensagem.');
            @fwrite($fp, "QUIT\r\n");
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            @fclose($fp);
            return false;
        }
    }

    /** @param resource $fp */
    private static function cmd($fp, string $line, array $okCodes, string $friendly = ''): string
    {
        fwrite($fp, $line . "\r\n");
        return self::expect($fp, $okCodes, $friendly);
    }

    /** @param resource $fp */
    private static function expect($fp, array $okCodes, string $friendly = ''): string
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            $detail = trim(preg_replace('/\s+/', ' ', $response) ?? '');
            throw new RuntimeException(($friendly !== '' ? $friendly . ' ' : '') . 'Resposta do servidor: ' . ($detail !== '' ? $detail : 'sem resposta (timeout).'));
        }
        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function encodeName(string $name): string
    {
        $name = str_replace(['"', "\r", "\n"], '', $name);
        return preg_match('/[^\x20-\x7E]/', $name) ? self::encodeHeader($name) : '"' . $name . '"';
    }

    private static function normalizeBody(string $body): string
    {
        return str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
    }

    private static function logFailure(string $to): void
    {
        $domain = substr(strrchr($to, '@') ?: '', 1);
        Logger::warning('Falha no envio de e-mail', [
            'mail' => ['metodo' => self::$lastMethod, 'erro' => self::$lastError, 'dominio_destino' => $domain],
        ]);
    }
}
