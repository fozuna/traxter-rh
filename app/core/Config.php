<?php
class Config
{
    private static ?array $cache = null;

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $baseConfigPath = __DIR__ . '/../config/config.php';
        $config = [
            'app' => [
                'name' => 'TRAXTER RH',
                'version' => '1.0.0',
                'release_date' => '',
                'base_url' => '',
                'public_jobs_url' => '',
                'env' => 'auto',
                'timezone' => 'America/Campo_Grande'
            ],
            'cliente' => [
                'nome' => '',
                'cnpj' => '',
                'email_privacidade' => '',
                'retencao_meses' => 12,
                'logo' => '',
                'site' => '',
                'cores' => []
            ],
            'storage' => [
                'path' => ''
            ],
            'recrutamento' => [
                'reaplicacao_meses' => 6,     // mesma vaga só pode receber nova candidatura do mesmo CPF após N meses
                'alerta_triagem_dias' => 3,   // candidatura na primeira etapa há mais de N dias = "não triada"
                'alerta_parado_dias' => 7     // candidatura sem movimentação há mais de N dias = "parada"
            ],
            'suporte' => [
                'nome' => 'TRAXTER Sistemas e Automações',
                'site' => 'https://traxter.com.br/',
                'whatsapp' => '5567998723814'
            ],
            'database' => [
                'dsn' => '',
                'user' => '',
                'pass' => ''
            ],
            'security' => [
                'csrf_key' => 'csrf_token',
                'session_name' => 'TRXRHSESSID',
                'supervisor_email' => '',
                'allowed_upload_mime' => ['application/pdf'],
                'max_upload_bytes' => 5 * 1024 * 1024,
                'allowed_image_mime' => ['image/png', 'image/jpeg', 'image/webp'],
                'max_image_bytes' => 2 * 1024 * 1024
            ],
            'mail' => [
                'enabled' => false,
                'from' => '',
                'to_hr' => '',
                'subject_new_application' => 'Nova candidatura recebida',
                'subject_password_recovery' => 'Recuperação de senha',
                'subject_password_changed' => 'Senha redefinida',
                'subject_supervisor_created' => 'Usuário Supervisor criado'
            ],
            'logging' => [
                'level' => 'INFO',
                'alert_email' => '',
                'viewer_key' => ''
            ]
        ];
        if (is_file($baseConfigPath)) {
            $base = self::load($baseConfigPath);
            if (is_array($base)) {
                $config = array_replace_recursive($config, $base);
            }
        }

        $localPath = __DIR__ . '/../config/local.php';
        if (is_file($localPath)) {
            $local = self::load($localPath);
            if (is_array($local)) {
                $config = array_replace_recursive($config, $local);
            }
        }

        $buildPath = __DIR__ . '/../config/build.php';
        if (is_file($buildPath)) {
            $build = require $buildPath;
            if (is_array($build)) {
                $config = array_replace_recursive($config, $build);
            }
        }

        $env = self::detectEnv((string)($config['app']['env'] ?? 'auto'));
        $config['app']['env'] = $env;
        $config['app']['base_url'] = self::detectBaseUrl((string)($config['app']['base_url'] ?? ''));
        $config['app']['public_jobs_url'] = self::detectPublicJobsUrl(
            (string)($config['app']['public_jobs_url'] ?? ''),
            (string)$config['app']['base_url']
        );

        self::$cache = $config;
        return self::$cache;
    }

    /**
     * Carrega um arquivo de configuração. Erro de digitação no config.php mostra uma
     * mensagem clara (arquivo e linha) em vez de uma página 500 em branco.
     */
    private static function load(string $path)
    {
        try {
            return require $path;
        } catch (\ParseError $e) {
            error_log('Config inválido: ' . $path . ' linha ' . $e->getLine() . ': ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Erro de sintaxe em ' . basename(dirname($path)) . '/' . basename($path) . ', linha ' . $e->getLine() . ': ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Configuração inválida</title>'
                . '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:10vh auto;padding:24px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#7f1d1d">'
                . '<h1 style="font-size:20px;margin:0 0 8px">Erro no arquivo de configuração</h1>'
                . '<p style="margin:0">Há um erro de digitação em <code>app/config/' . htmlspecialchars(basename($path)) . '</code>, perto da <strong>linha ' . (int)$e->getLine() . '</strong>.</p>'
                . '<p style="margin:8px 0 0;font-size:14px">Confira vírgulas, parênteses e aspas nessa linha e na anterior.</p></div>';
            exit;
        }
    }

    /** Descarta o cache (usado pelo instalador logo após gravar o config.php). */
    public static function reload(): void
    {
        self::$cache = null;
    }

    public static function app(): array
    {
        $cfg = self::get();
        return [
            'name' => $cfg['app']['name'],
            'product_name' => $cfg['app']['name'],
            'version' => (string)($cfg['app']['version'] ?? '1.0.0'),
            'release_date' => (string)($cfg['app']['release_date'] ?? ''),
            'base_url' => (string)($cfg['app']['base_url'] ?? ''),
            'public_jobs_url' => (string)($cfg['app']['public_jobs_url'] ?? ''),
            'env' => (string)($cfg['app']['env'] ?? 'development'),
            'timezone' => (string)($cfg['app']['timezone'] ?? 'America/Campo_Grande'),
            'security' => $cfg['security'],
            'mail' => $cfg['mail'],
            'logging' => $cfg['logging'],
            'database' => [
                'dsn' => $cfg['database']['dsn'],
                'user' => $cfg['database']['user'],
                'pass' => $cfg['database']['pass'],
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_TIMEOUT => 5
                ]
            ]
        ];
    }

    private static function detectEnv(string $configured): string
    {
        $configured = strtolower(trim($configured));
        if ($configured !== '' && $configured !== 'auto') {
            return $configured;
        }
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '' && PHP_SAPI === 'cli') {
            return 'development';
        }
        if (
            $host === 'localhost' ||
            str_starts_with($host, 'localhost:') ||
            $host === '127.0.0.1' ||
            str_starts_with($host, '127.0.0.1:') ||
            $host === '::1' ||
            (bool)preg_match('/\.(test|local|localhost)(:\d+)?$/', $host)
        ) {
            return 'development';
        }
        return 'production';
    }

    private static function detectBaseUrl(string $configured): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $proto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $isHttps =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
            strtolower($proto) === 'https';
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $basePath = rtrim(dirname($scriptName), '/');
        if ($basePath === '.' || $basePath === '\\' || $basePath === '/') {
            $basePath = '';
        }
        return rtrim($scheme . '://' . $host . $basePath, '/');
    }

    private static function detectPublicJobsUrl(string $configured, string $baseUrl): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            if (preg_match('#^https?://#i', $configured)) {
                return rtrim($configured, '/');
            }
            return rtrim($baseUrl, '/') . '/' . ltrim($configured, '/');
        }
        return rtrim($baseUrl, '/') . '/vagas';
    }
}
