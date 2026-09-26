<?php
declare(strict_types=1);
require_once __DIR__ . '/app/core/bootstrap.php';
$app = Config::app();

if (($app['env'] ?? 'prod') === 'dev' && isset($_GET['__force500'])) {
    throw new RuntimeException('Falha forçada para teste de logging 500');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = $_SESSION['install_csrf'] ?? '';
if ($token === '') {
    $token = bin2hex(random_bytes(32));
    $_SESSION['install_csrf'] = $token;
}

$requirements = Installer::requirements();
$allOk = true;
foreach ($requirements as $item) {
    if (!$item['ok']) {
        $allOk = false;
    }
}

$defaults = [
    'app_env' => (string)($app['env'] ?? 'prod'),
    'config_mode' => 'config',
    'db_dsn' => (string)($app['database']['dsn'] ?? ''),
    'db_user' => (string)($app['database']['user'] ?? ''),
    'mail_to_hr' => (string)($app['mail']['to_hr'] ?? ''),
    'mail_from' => (string)($app['mail']['from'] ?? ''),
    'supervisor_email' => (string)($app['security']['supervisor_email'] ?? ''),
    'admin_email' => '',
    'log_level' => (string)($app['logging']['level'] ?? 'INFO'),
    'log_alert_email' => (string)($app['logging']['alert_email'] ?? ''),
    'log_viewer_key' => (string)($app['logging']['viewer_key'] ?? ''),
];

$messages = [];
$success = false;
$selfDeleted = false;

if (Installer::isInstalled()) {
    http_response_code(403);
    $messages[] = 'Instalador bloqueado: a aplicação já está instalada.';
    Logger::warning('Installer blocked: already installed', Logger::captureContext(403));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Installer::isInstalled()) {
    $postedToken = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($token, $postedToken)) {
        $messages[] = 'Token de segurança inválido.';
        Logger::warning('Installer invalid CSRF token', Logger::captureContext(400));
    } else {
        $requiredKey = getenv('INSTALLER_KEY');
        if (is_string($requiredKey) && trim($requiredKey) !== '') {
            $postedKey = (string)($_POST['installer_key'] ?? '');
            if (!hash_equals(trim($requiredKey), trim($postedKey))) {
                $messages[] = 'Chave do instalador inválida.';
                Logger::warning('Installer invalid key', Logger::captureContext(403));
            }
        }
    }

    if (count($messages) === 0) {
        try {
            $result = Installer::run([
                'app_env' => (string)($_POST['app_env'] ?? 'prod'),
                'config_mode' => (string)($_POST['config_mode'] ?? 'config'),
                'allow_overwrite_config' => isset($_POST['allow_overwrite_config']) ? '1' : '0',
                'db_dsn' => (string)($_POST['db_dsn'] ?? ''),
                'db_user' => (string)($_POST['db_user'] ?? ''),
                'db_pass' => (string)($_POST['db_pass'] ?? ''),
                'mail_from' => (string)($_POST['mail_from'] ?? ''),
                'mail_to_hr' => (string)($_POST['mail_to_hr'] ?? ''),
                'supervisor_email' => (string)($_POST['supervisor_email'] ?? ''),
                'supervisor_password' => (string)($_POST['supervisor_password'] ?? ''),
                'admin_email' => (string)($_POST['admin_email'] ?? ''),
                'admin_password' => (string)($_POST['admin_password'] ?? ''),
                'log_level' => (string)($_POST['log_level'] ?? 'INFO'),
                'log_alert_email' => (string)($_POST['log_alert_email'] ?? ''),
                'log_viewer_key' => (string)($_POST['log_viewer_key'] ?? ''),
                'cliente_nome' => (string)($_POST['cliente_nome'] ?? ''),
                'cliente_cnpj' => (string)($_POST['cliente_cnpj'] ?? ''),
                'cliente_site' => (string)($_POST['cliente_site'] ?? ''),
                'cliente_email_privacidade' => (string)($_POST['cliente_email_privacidade'] ?? ''),
                'cliente_retencao_meses' => (string)($_POST['cliente_retencao_meses'] ?? '12'),
                'cor_escuro' => (string)($_POST['cor_escuro'] ?? ''),
                'cor_medio' => (string)($_POST['cor_medio'] ?? ''),
                'cor_claro' => (string)($_POST['cor_claro'] ?? ''),
                'storage_path' => (string)($_POST['storage_path'] ?? ''),
            ], function (string $line) use (&$messages): void {
                $messages[] = $line;
            });
            $success = true;
            $selfDeleted = (bool)($result['self_delete'] ?? false);
        } catch (Throwable $e) {
            $messages[] = 'Erro na instalação: ' . $e->getMessage();
            Logger::exception($e, 'CRITICAL', Logger::captureContext(500, ['installer' => ['phase' => 'run']]));
        }
    }
}

$isLocked = Installer::isInstalled();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Instalador TRAXTER. - Recrutamento e Seleção</title>
  <style>
    body{font-family:Montserrat,system-ui,-apple-system,sans-serif;background:#f7fafc;margin:0}
    .wrap{max-width:900px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(15,23,42,.06)}
    h1{margin:0 0 12px;color:#0f172a}
    h2{font-size:18px;margin:18px 0 10px;color:#0f172a}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .field{display:flex;flex-direction:column;gap:6px}
    label{font-size:13px;color:#334155}
    input,select{border:1px solid #cbd5e1;border-radius:8px;padding:10px;font-size:14px}
    button{background:#0d1321;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:600;cursor:pointer}
    button:disabled{opacity:.5;cursor:not-allowed}
    .ok{color:#1d2d44}
    .bad{color:#991b1b}
    .log{background:#0b1220;color:#dbeafe;padding:12px;border-radius:10px;white-space:pre-wrap;font-size:12px;max-height:260px;overflow:auto}
    .note{font-size:13px;color:#475569}
    h3{grid-column:1/-1;font-size:15px;margin:14px 0 0;color:#0f172a;border-top:1px solid #e2e8f0;padding-top:12px}
    .full{grid-column:1/-1}
    .colors{display:flex;gap:8px}.colors input{width:56px;height:40px;padding:2px}
    @media(max-width:760px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Instalador Web TRAXTER. - Recrutamento e Seleção</h1>
      <p class="note">Preencha os dados e clique em instalar. O processo cria configuração local, importa banco, aplica migrações e prepara diretórios.</p>

      <h2>Requisitos do servidor</h2>
      <ul>
        <?php foreach ($requirements as $req): ?>
          <li class="<?= $req['ok'] ? 'ok' : 'bad' ?>">
            <?= $req['ok'] ? 'OK' : 'FALHA' ?> - <?= htmlspecialchars($req['label']) ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($isLocked): ?>
        <p class="bad"><strong>Instalador bloqueado:</strong> instalação já concluída.</p>
      <?php else: ?>
      <?php
        // Reaproveita o que foi digitado quando a instalação falha (exceto senhas)
        $v = static function (string $k, string $default = '') use ($defaults): string {
            $val = $_POST[$k] ?? ($defaults[$k] ?? $default);
            return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
        };
        $storageSugestao = Installer::suggestStoragePath();
      ?>
      <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="app_env" value="prod">
        <input type="hidden" name="config_mode" value="config">
        <?php if (is_string(getenv('INSTALLER_KEY')) && trim((string)getenv('INSTALLER_KEY')) !== ''): ?>
        <div class="field full"><label>Chave do instalador</label><input name="installer_key" type="password" required></div>
        <?php endif; ?>

        <h3>1. Empresa cliente</h3>
        <div class="field"><label>Nome da empresa *</label><input name="cliente_nome" required value="<?= $v('cliente_nome') ?>" placeholder="Ex.: Agro Exemplo Ltda"></div>
        <div class="field"><label>CNPJ</label><input name="cliente_cnpj" value="<?= $v('cliente_cnpj') ?>" placeholder="00.000.000/0001-00"></div>
        <div class="field"><label>Site da empresa</label><input name="cliente_site" value="<?= $v('cliente_site') ?>" placeholder="https://www.empresa.com.br"></div>
        <div class="field"><label>E-mail para pedidos LGPD</label><input name="cliente_email_privacidade" type="email" value="<?= $v('cliente_email_privacidade') ?>" placeholder="privacidade@empresa.com.br"></div>
        <div class="field"><label>Guardar dados de candidatos por (meses)</label><input name="cliente_retencao_meses" type="number" min="1" max="60" value="<?= $v('cliente_retencao_meses', '12') ?>"></div>
        <div class="field"><label>Cores da marca (escura · média · clara)</label>
          <div class="colors">
            <input type="color" name="cor_escuro" value="<?= $v('cor_escuro', '#0d1321') ?>" title="Escura: cabeçalho e menu">
            <input type="color" name="cor_medio" value="<?= $v('cor_medio', '#1d2d44') ?>" title="Média: botões">
            <input type="color" name="cor_claro" value="<?= $v('cor_claro', '#3e5c76') ?>" title="Clara: detalhes">
          </div>
        </div>

        <h3>2. Banco de dados</h3>
        <div class="field full"><label>DSN *</label><input name="db_dsn" required placeholder="mysql:host=localhost;dbname=NOME_DO_BANCO;charset=utf8mb4" value="<?= $v('db_dsn') ?>"></div>
        <div class="field"><label>Usuário *</label><input name="db_user" required value="<?= $v('db_user') ?>"></div>
        <div class="field"><label>Senha</label><input name="db_pass" type="password"></div>

        <h3>3. Acessos</h3>
        <p class="note full">Senhas: 12+ caracteres, com maiúscula, minúscula, número e símbolo. Use e-mails reais e diferentes.</p>
        <div class="field"><label>E-mail do administrador (cliente) *</label><input name="admin_email" type="email" required placeholder="rh@empresa.com.br" value="<?= $v('admin_email') ?>"></div>
        <div class="field"><label>Senha do administrador *</label><input name="admin_password" type="password" required></div>
        <div class="field"><label>E-mail do supervisor (TRAXTER) *</label><input name="supervisor_email" type="email" required value="<?= $v('supervisor_email') ?>"></div>
        <div class="field"><label>Senha do supervisor *</label><input name="supervisor_password" type="password" required></div>

        <h3>4. E-mails do sistema</h3>
        <div class="field"><label>Recebe avisos de candidatura *</label><input name="mail_to_hr" type="email" required placeholder="rh@empresa.com.br" value="<?= $v('mail_to_hr') ?>"></div>
        <div class="field"><label>Remetente (do seu domínio) *</label><input name="mail_from" type="email" required placeholder="no-reply@traxter.com.br" value="<?= $v('mail_from') ?>"></div>
        <div class="field"><label>Alertas de erro</label><input name="log_alert_email" type="email" placeholder="dev@traxter.com.br" value="<?= $v('log_alert_email') ?>"></div>
        <input type="hidden" name="log_level" value="INFO">

        <h3>5. Arquivos privados</h3>
        <div class="field full"><label>Pasta para currículos, logs e sessões (fora do site)</label>
          <input name="storage_path" value="<?= $v('storage_path', $storageSugestao) ?>">
          <span class="note">Sugestão calculada para este servidor, uma pasta por cliente. A pasta é criada automaticamente. Deixe vazio só em ambiente local.</span>
        </div>
        <input type="hidden" name="log_viewer_key" value="">

        <div class="full" style="display:flex;gap:12px;align-items:center;margin-top:8px">
          <button type="submit" <?= $allOk ? '' : 'disabled' ?>>Instalar agora</button>
          <span class="note">Nada é gravado se algum dado estiver errado. Após o sucesso, o instalador é bloqueado.</span>
        </div>
      </form>
      <?php endif; ?>

      <?php if (count($messages) > 0): ?>
        <h2>Log da instalação</h2>
        <div class="log"><?php foreach ($messages as $line) { echo htmlspecialchars($line) . "\n"; } ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <p class="ok"><strong>Instalação concluída.</strong> <?= $selfDeleted ? 'O instalador foi removido automaticamente.' : 'O instalador já está bloqueado; se desejar, remova install.php do servidor.' ?></p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
