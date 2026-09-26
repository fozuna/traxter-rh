<?php
declare(strict_types=1);
if (@fsockopen('127.0.0.1', 3306, $errno, $errstr, 1) === false) {
    echo "SKIP unit_reaplicacao (MySQL indisponível)\n";
    exit(0);
}
require_once __DIR__ . '/../../app/core/bootstrap.php';
$assert = static function (bool $cond, string $msg): void {
    if (!$cond) { fwrite(STDERR, "Falha: {$msg}\n"); exit(1); }
};
$pdo = Database::conn();
$sfx = (string)random_int(100000, 999999);
$cpf = str_pad((string)random_int(1, 99999999999), 11, '0', STR_PAD_LEFT);
$vagaA = Vaga::create(['titulo' => 'Vaga A ' . $sfx, 'descricao' => 'x', 'requisitos' => 'x', 'area' => 'x', 'local' => 'x', 'ativo' => 1]);
$vagaB = Vaga::create(['titulo' => 'Vaga B ' . $sfx, 'descricao' => 'x', 'requisitos' => 'x', 'area' => 'x', 'local' => 'x', 'ativo' => 1]);
$dados = fn(int $vaga) => ['vaga_id' => $vaga, 'nome' => 'Reaplic ' . $sfx, 'email' => "r{$sfx}@example.test", 'telefone' => '67999990003',
    'cpf' => $cpf, 'cargo_pretendido' => 'x', 'experiencia' => 'x', 'pdf_path' => '', 'status' => 'novo'];

$assert(Candidatura::bloqueioReaplicacao($cpf, $vagaA) === null, 'primeira candidatura deveria ser livre');
$c1 = Candidatura::create($dados($vagaA));
$assert(Candidatura::bloqueioReaplicacao($cpf, $vagaA) !== null, 'mesma vaga logo em seguida deveria ser bloqueada');
$assert(Candidatura::bloqueioReaplicacao($cpf, $vagaB) === null, 'outra vaga deveria ser liberada');
$c2 = Candidatura::create($dados($vagaB)); // UNIQUE antigo teria impedido este insert
$assert($c2 > 0, 'segunda candidatura (outra vaga) deveria ser gravada');

$pdo->prepare('UPDATE candidaturas SET created_at = DATE_SUB(NOW(), INTERVAL 5 MONTH) WHERE id = ?')->execute([$c1]);
$assert(Candidatura::bloqueioReaplicacao($cpf, $vagaA) !== null, '5 meses ainda deveria bloquear a mesma vaga');
$pdo->prepare('UPDATE candidaturas SET created_at = DATE_SUB(NOW(), INTERVAL 6 MONTH) - INTERVAL 1 DAY WHERE id = ?')->execute([$c1]);
$assert(Candidatura::bloqueioReaplicacao($cpf, $vagaA) === null, 'após 6 meses a mesma vaga deveria ser liberada');

$pdo->prepare('DELETE FROM candidaturas WHERE id IN (?, ?)')->execute([$c1, $c2]);
$pdo->prepare('DELETE FROM vagas WHERE id IN (?, ?)')->execute([$vagaA, $vagaB]);
echo "OK unit_reaplicacao\n";
