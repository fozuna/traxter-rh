<?php
set_time_limit(10);
if (@fsockopen('127.0.0.1', 3306, $errno, $errstr, 1) === false) {
    echo "SKIP integration_supervisor_protection (MySQL indisponível)\n";
    exit(0);
}
require_once __DIR__ . '/../../app/core/bootstrap.php';

$cfg = Config::app();
$supervisorEmail = $cfg['security']['supervisor_email'];
if (!filter_var((string)$supervisorEmail, FILTER_VALIDATE_EMAIL)) {
    $supervisorEmail = 'supervisor_' . time() . '@example.test';
}
$supervisorId = User::ensureSupervisorAccount('Supervisor', $supervisorEmail)['id'];
$supervisor = User::findById($supervisorId);
if (!$supervisor || (int)$supervisor->is_supervisor !== 1) {
    fwrite(STDERR, "Falha: Supervisor não foi garantido corretamente.\n");
    exit(1);
}

$actorEmail = 'actor_' . time() . '@example.test';
$actorId = User::create('Actor Admin', $actorEmail, password_hash('Abcd1234!xyz', PASSWORD_BCRYPT), 'admin');
$actor = User::findById($actorId);

$canDelete = User::attemptDelete($supervisorId, $actor, '127.0.0.1');
if ($canDelete) {
    fwrite(STDERR, "Falha: exclusão do supervisor deveria ser bloqueada.\n");
    exit(1);
}

$pdo = Database::conn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM auditoria_usuarios WHERE action = 'blocked_user_delete' AND target_usuario_id = ?");
$stmt->execute([$supervisorId]);
$count = (int)$stmt->fetchColumn();
if ($count < 1) {
    fwrite(STDERR, "Falha: tentativa bloqueada não gerou auditoria.\n");
    exit(1);
}

echo "OK integration_supervisor_protection\n";
