<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/core/bootstrap.php';

// Com config.php contendo DSN, o instalador web deve se considerar instalado
// (impede que alguém reabra install.php e sobrescreva a configuração).
$cfg = @include BASE_PATH . '/app/config/config.php';
$hasDsn = is_array($cfg) && trim((string)($cfg['database']['dsn'] ?? '')) !== '';
if ($hasDsn && !Installer::isInstalled()) {
    fwrite(STDERR, "Falha: config.php com DSN deveria bloquear o instalador.\n");
    exit(1);
}
echo "OK unit_installer_lock\n";
