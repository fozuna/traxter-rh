<?php
/**
 * TRAXTER RH - Setup local (Laragon / MySQL / MariaDB)
 *
 * Uso (no Terminal do Laragon, na pasta do projeto):
 *   php scripts/setup_full.php [banco] [email_admin]
 *
 * Padrões: banco "traxter_rh", admin "admin@traxter.local",
 * conexão root sem senha em 127.0.0.1 (padrão do Laragon).
 * Sobrescreva com as variáveis DB_HOST, DB_USER, DB_PASS se precisar.
 *
 * O script:
 *  1. cria o banco (se não existir) e importa database/schema.sql;
 *  2. cria app/config/config.php a partir do config.example.php (se não existir);
 *  3. cria o usuário administrador com senha forte aleatória, exibida uma única vez.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dbname     = $argv[1] ?? 'traxter_rh';
$adminEmail = strtolower($argv[2] ?? 'admin@traxter.local');
$host       = getenv('DB_HOST') ?: '127.0.0.1';
$user       = getenv('DB_USER') ?: 'root';
$pass       = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbname)) {
    fwrite(STDERR, "Nome de banco inválido: use apenas letras, números e _.\n");
    exit(1);
}
if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) && !preg_match('/^[^@\s]+@[^@\s]+\.local$/', $adminEmail)) {
    fwrite(STDERR, "E-mail do administrador inválido.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    // 1. Banco + estrutura
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    echo "Banco '{$dbname}' pronto.\n";

    $schema = file_get_contents($root . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('database/schema.sql não encontrado.');
    }
    $pdo->exec($schema);
    echo "Estrutura importada (database/schema.sql).\n";

    // 2. config.php
    $configPath = $root . '/app/config/config.php';
    if (!is_file($configPath)) {
        $example = (string) file_get_contents($root . '/app/config/config.example.php');
        $example = preg_replace("/'dsn' => ''/", "'dsn' => 'mysql:host={$host};dbname={$dbname};charset=utf8mb4'", $example, 1);
        $example = preg_replace("/'user' => ''/", "'user' => " . var_export($user, true), $example, 1);
        $example = preg_replace("/'pass' => ''/", "'pass' => " . var_export($pass, true), $example, 1);
        file_put_contents($configPath, $example);
        echo "Criado app/config/config.php apontando para '{$dbname}'.\n";
    } else {
        echo "app/config/config.php já existe: mantido sem alterações.\n";
    }

    // 3. Administrador
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if ($stmt->fetchColumn()) {
        echo "Administrador {$adminEmail} já existe: senha não alterada.\n";
        echo "Para redefinir: php scripts/reset_password_cli.php {$adminEmail}\n";
    } else {
        $password = generatePassword();
        $pdo->prepare(
            'INSERT INTO usuarios (nome, email, senha_hash, role, is_supervisor, email_verified_at)
             VALUES (?, ?, ?, ?, 1, NOW())'
        )->execute(['Administrador', $adminEmail, password_hash($password, PASSWORD_DEFAULT), 'admin']);

        echo "\nAdministrador criado.\n";
        echo "  E-mail: {$adminEmail}\n";
        echo "  Senha:  {$password}\n";
        echo "Guarde a senha agora: ela não será exibida novamente.\n";
    }

    echo "\nSetup concluído.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

/** Senha aleatória de 16 caracteres que atende à PasswordPolicy. */
function generatePassword(): string
{
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?'];
    $chars = [];
    foreach ($sets as $set) {
        $chars[] = $set[random_int(0, strlen($set) - 1)];
    }
    $all = implode('', $sets);
    while (count($chars) < 16) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
