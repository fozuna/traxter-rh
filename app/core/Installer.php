<?php
class Installer
{
    /**
     * Instalado = existe o lock OU já há um config.php com banco configurado
     * (ex.: instalação feita pelo scripts/setup_full.php). Isso impede que o
     * instalador web seja reaberto e sobrescreva a configuração.
     */
    public static function isInstalled(): bool
    {
        if (is_file(self::installLockPath())) {
            return true;
        }
        $configPath = self::configPath();
        if (is_file($configPath)) {
            $cfg = @include $configPath;
            if (is_array($cfg) && trim((string)($cfg['database']['dsn'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    public static function requirements(): array
    {
        $checks = [];
        $checks[] = self::check('PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='));
        $checks[] = self::check('Extensão PDO', extension_loaded('pdo'));
        $checks[] = self::check('Extensão pdo_mysql', extension_loaded('pdo_mysql'));
        $checks[] = self::check('Diretório storage gravável', self::isWritableDir(self::storagePath()));
        $checks[] = self::check('Diretório uploads gravável', self::isWritableDir(self::basePath() . '/uploads'));
        $checks[] = self::check('Arquivo schema.sql disponível', is_file(self::basePath() . '/database/schema.sql'));
        return $checks;
    }

    public static function run(array $input, callable $logger): array
    {
        Logger::init(Config::app());
        self::ensureDir(self::storagePath() . '/logs');
        $installLog = self::storagePath() . '/logs/install-' . date('Ymd-His') . '.log';
        $log = function (string $message) use ($logger, $installLog): void {
            $line = '[' . date('c') . '] ' . $message;
            $logger($line);
            @file_put_contents($installLog, $line . PHP_EOL, FILE_APPEND);
        };

        $log('Iniciando processo de instalação.');
        Logger::info('Installer started', Logger::captureContext(http_response_code(), ['installer' => ['step' => 'start']]));
        if (self::isInstalled()) {
            throw new RuntimeException('Instalação já foi concluída anteriormente.');
        }

        $requirements = self::requirements();
        foreach ($requirements as $item) {
            if (!$item['ok']) {
                throw new RuntimeException('Requisito não atendido: ' . $item['label']);
            }
        }

        $config = self::buildConfig($input);

        // Validações ANTES de gravar qualquer arquivo: se algo estiver errado,
        // nada é criado e o instalador continua disponível para nova tentativa.
        self::validateAdminInput($input);
        $pdo = self::connect($config['database'], $log);

        $configMode = strtolower(trim((string)($input['config_mode'] ?? 'config')));
        $targetConfigPath = $configMode === 'local' ? self::localConfigPath() : self::configPath();
        self::ensureDir(dirname($targetConfigPath));
        $allowOverwrite = ((string)($input['allow_overwrite_config'] ?? '') === '1');
        $createdConfig = false;
        if (!is_file($targetConfigPath)) {
            self::writeConfigAtomic($targetConfigPath, $config);
            $createdConfig = true;
            $log('Arquivo de configuração criado: ' . str_replace(self::basePath() . '/', '', $targetConfigPath));
        } elseif ($allowOverwrite) {
            self::writeConfigAtomic($targetConfigPath, $config);
            $log('Arquivo de configuração sobrescrito: ' . str_replace(self::basePath() . '/', '', $targetConfigPath));
        } else {
            $log('Arquivo de configuração existente preservado: ' . str_replace(self::basePath() . '/', '', $targetConfigPath));
        }

        try {
            // Recarrega a configuração recém-gravada para que Database/SchemaManager usem o banco informado
            Config::reload();
            Database::reset();
            self::importSchema($pdo, $log);
            self::runSchemaEnsure($log);
            self::createAdminIfNeeded($input, $log);
            self::ensureRuntimeDirs($log);
        } catch (Throwable $e) {
            // Desfaz o config.php criado nesta tentativa, para o instalador não ficar bloqueado
            if ($createdConfig && is_file($targetConfigPath)) {
                @unlink($targetConfigPath);
                Config::reload();
                $log('Configuração desfeita. Corrija o problema e tente instalar novamente.');
            }
            throw $e;
        }

        self::writeInstallLock($log);
        $log('Instalação concluída com sucesso.');
        Logger::info('Installer finished', Logger::captureContext(http_response_code(), ['installer' => ['step' => 'done']]));

        return [
            'log_file' => $installLog,
            'self_delete' => self::trySelfDelete($log),
        ];
    }

    private static function buildConfig(array $input): array
    {
        $app = Config::app();
        $dbCurrent = $app['database'] ?? [];
        $mailCurrent = $app['mail'] ?? [];
        $secCurrent = $app['security'] ?? [];
        $logCurrent = $app['logging'] ?? [];

        $dsn = trim((string)($input['db_dsn'] ?? ($dbCurrent['dsn'] ?? '')));
        $user = trim((string)($input['db_user'] ?? ($dbCurrent['user'] ?? '')));
        $pass = array_key_exists('db_pass', $input) ? (string)$input['db_pass'] : (string)($dbCurrent['pass'] ?? '');
        $mailFrom = trim((string)($input['mail_from'] ?? ($mailCurrent['from'] ?? '')));
        $mailTo = trim((string)($input['mail_to_hr'] ?? ($mailCurrent['to_hr'] ?? '')));
        $supervisorEmail = trim((string)($input['supervisor_email'] ?? ($secCurrent['supervisor_email'] ?? '')));
        $supervisorPassword = array_key_exists('supervisor_password', $input) ? (string)$input['supervisor_password'] : (string)($secCurrent['supervisor_password'] ?? '');
        $env = trim((string)($input['app_env'] ?? ($app['env'] ?? 'prod')));
        $logLevel = trim((string)($input['log_level'] ?? ($logCurrent['level'] ?? 'INFO')));
        $alertEmail = trim((string)($input['log_alert_email'] ?? ($logCurrent['alert_email'] ?? '')));
        $viewerKey = trim((string)($input['log_viewer_key'] ?? ($logCurrent['viewer_key'] ?? '')));
        if ($dsn === '' || $user === '' || $mailFrom === '' || $mailTo === '' || $supervisorEmail === '' || $supervisorPassword === '') {
            throw new RuntimeException('Preencha todos os campos obrigatórios do instalador.');
        }

        $env = strtolower($env);
        $env = in_array($env, ['dev', 'development', 'debug'], true) ? 'development' : 'production';

        return [
            'app' => [
                'env' => $env,
            ],
            // Identidade do cliente (ajuste após instalar)
            'cliente' => [
                'nome' => '',
                'cnpj' => '',
                'email_privacidade' => '',
                'retencao_meses' => 12,
                'logo' => '',
                'site' => '',
                'cores' => [],
            ],
            // Em produção, aponte para uma pasta FORA da pasta pública do site
            'storage' => [
                'path' => '',
            ],
            'security' => [
                'supervisor_email' => $supervisorEmail,
                'supervisor_password' => $supervisorPassword,
            ],
            'mail' => [
                'enabled' => true,
                'from' => $mailFrom,
                'to_hr' => $mailTo,
            ],
            'logging' => [
                'level' => $logLevel === '' ? 'INFO' : strtoupper($logLevel),
                'alert_email' => $alertEmail,
                'viewer_key' => $viewerKey,
            ],
            'database' => [
                'dsn' => $dsn,
                'user' => $user,
                'pass' => $pass,
            ],
        ];
    }

    private static function writeConfigAtomic(string $path, array $config): void
    {
        $export = var_export($config, true);
        $content = "<?php\nreturn " . $export . ";\n";
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            throw new RuntimeException('Não foi possível gravar arquivo temporário de configuração.');
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Não foi possível finalizar escrita do arquivo de configuração.');
        }
        @chmod($path, 0644);
    }

    public static function preflightSummary(): array
    {
        $requirements = self::requirements();
        $failed = [];
        foreach ($requirements as $req) {
            if (!$req['ok']) {
                $failed[] = $req['label'];
            }
        }
        $configFiles = [
            'config' => is_file(self::configPath()),
            'local' => is_file(self::localConfigPath()),
            'lock' => is_file(self::installLockPath()),
        ];
        return [
            'ok' => count($failed) === 0,
            'failed_requirements' => $failed,
            'config_files' => $configFiles,
        ];
    }

    private static function connect(array $db, callable $log): PDO
    {
        $log('Conectando ao banco de dados.');
        try {
            $pdo = new PDO(
                (string)$db['dsn'],
                (string)$db['user'],
                (string)$db['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            return $pdo;
        } catch (Throwable $e) {
            throw new RuntimeException('Falha na conexão com banco de dados: ' . $e->getMessage());
        }
    }

    private static function importSchema(PDO $pdo, callable $log): void
    {
        $schemaFile = self::basePath() . '/database/schema.sql';
        $sql = (string)file_get_contents($schemaFile);
        if (trim($sql) === '') {
            throw new RuntimeException('schema.sql está vazio.');
        }
        $log('Importando schema base.');
        $pdo->exec($sql);
    }

    private static function runSchemaEnsure(callable $log): void
    {
        $log('Executando migrações incrementais.');
        SchemaManager::ensure();
    }

    private static function validateAdminInput(array $input): void
    {
        $email = trim((string)($input['admin_email'] ?? ''));
        $password = (string)($input['admin_password'] ?? '');
        if ($email === '' || $password === '') {
            throw new RuntimeException('Informe e-mail e senha do administrador inicial.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail do administrador inválido.');
        }
        $policy = PasswordPolicy::validate($password);
        if (!$policy['valid']) {
            throw new RuntimeException('Senha do administrador fraca: ' . implode(' ', $policy['errors'] ?? []));
        }
    }

    private static function createAdminIfNeeded(array $input, callable $log): void
    {
        $email = trim((string)($input['admin_email'] ?? ''));
        $password = (string)($input['admin_password'] ?? '');
        if ($email === '' || $password === '') {
            $log('Credenciais de admin não informadas. Etapa de admin ignorada.');
            return;
        }
        $existing = User::findByEmail($email);
        if ($existing) {
            $log('Usuário admin já existe: ' . $email);
            return;
        }
        $policy = PasswordPolicy::validate($password);
        if (!$policy['valid']) {
            throw new RuntimeException('Senha do admin fraca: ' . implode(' ', $policy['errors'] ?? []));
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = User::create('Administrador', $email, $hash, 'admin');
        User::setActiveStatus($id, true); // conta já nasce ativa (senão o login retorna "conta inativa")
        $log('Usuário admin criado: ' . $email);
    }

    private static function ensureRuntimeDirs(callable $log): void
    {
        $dirs = [
            self::storagePath() . '/sessions',
            self::storagePath() . '/resumes',
            self::storagePath() . '/ratelimit',
            self::storagePath() . '/audit',
            self::storagePath() . '/logs',
            self::basePath() . '/uploads/logos',
        ];
        foreach ($dirs as $dir) {
            self::ensureDir($dir);
        }
        $log('Diretórios de runtime verificados.');
    }

    private static function writeInstallLock(callable $log): void
    {
        $lockFile = self::installLockPath();
        $payload = json_encode(['installed_at' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($lockFile, $payload . PHP_EOL) === false) {
            throw new RuntimeException('Não foi possível criar lock de instalação.');
        }
        $log('Lock de instalação criado.');
    }

    private static function trySelfDelete(callable $log): bool
    {
        // Em deploy via Git (Hostinger, cPanel Git), apagar arquivos versionados gera conflito
        // no próximo "git pull". O instalador já fica bloqueado pelo config.php/lock.
        if (is_dir(self::basePath() . '/.git')) {
            $log('Deploy via Git detectado: install.php mantido (bloqueado automaticamente).');
            return false;
        }
        $deletedAny = false;
        foreach ([self::basePath() . '/install.php', self::basePath() . '/public/install.php'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (@unlink($file)) {
                $deletedAny = true;
                $log('Instalador removido: ' . basename(dirname($file)) . '/' . basename($file));
            } else {
                $log('Não foi possível remover ' . $file . '. Remova manualmente (o instalador já está bloqueado).');
            }
        }
        return $deletedAny;
    }

    private static function check(string $label, bool $ok): array
    {
        return ['label' => $label, 'ok' => $ok];
    }

    private static function isWritableDir(string $path): bool
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        return is_dir($path) && is_writable($path);
    }

    private static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        if (!is_dir($path)) {
            throw new RuntimeException('Não foi possível criar diretório: ' . $path);
        }
    }

    private static function basePath(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function storagePath(): string
    {
        return defined('STORAGE_PATH') ? \STORAGE_PATH : self::basePath() . '/storage';
    }

    private static function localConfigPath(): string
    {
        return self::basePath() . '/app/config/local.php';
    }

    private static function configPath(): string
    {
        return self::basePath() . '/app/config/config.php';
    }

    private static function installLockPath(): string
    {
        return self::storagePath() . '/install.done';
    }
}
