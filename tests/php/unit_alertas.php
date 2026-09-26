<?php
declare(strict_types=1);
if (@fsockopen('127.0.0.1', 3306, $errno, $errstr, 1) === false) {
    echo "SKIP unit_alertas (MySQL indisponível)\n";
    exit(0);
}
require_once __DIR__ . '/../../app/core/bootstrap.php';
$assert = static function (bool $cond, string $msg): void {
    if (!$cond) { fwrite(STDERR, "Falha: {$msg}\n"); exit(1); }
};
$pdo = Database::conn();
Retencao::ensureColumn();
$sfx = (string)random_int(100000, 999999);
$vaga = Vaga::create(['titulo' => 'Vaga Alerta ' . $sfx, 'descricao' => 'x', 'requisitos' => 'x', 'area' => 'x', 'local' => 'x', 'ativo' => 1]);
$stage = static fn(string $nome) => (int)$pdo->query("SELECT id FROM pipeline_stages WHERE LOWER(nome) = " . $pdo->quote(strtolower($nome)) . " LIMIT 1")->fetchColumn();
$primeira = (int)$pdo->query('SELECT id FROM pipeline_stages ORDER BY ordem, id LIMIT 1')->fetchColumn();
$meio = (int)$pdo->query('SELECT id FROM pipeline_stages ORDER BY ordem, id LIMIT 1 OFFSET 2')->fetchColumn();
$novo = static function (string $nome, int $stageId, int $diasAtras) use ($vaga, $pdo, $sfx): int {
    $id = Candidatura::create(['vaga_id' => $vaga, 'nome' => $nome . ' ' . $sfx, 'email' => strtolower($nome) . $sfx . '@example.test',
        'telefone' => '67999990004', 'cpf' => str_pad((string)random_int(1, 99999999999), 11, '0', STR_PAD_LEFT),
        'cargo_pretendido' => 'x', 'experiencia' => 'x', 'pdf_path' => '', 'status' => 'novo']);
    $pdo->prepare("UPDATE candidaturas SET stage_id = ?, created_at = DATE_SUB(NOW(), INTERVAL ? DAY) WHERE id = ?")->execute([$stageId, $diasAtras, $id]);
    $pdo->prepare("DELETE FROM pipeline_movements WHERE candidatura_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM candidatura_historico WHERE candidatura_id = ?")->execute([$id]);
    return $id;
};
$semTriagem = $novo('SemTriagem', $primeira, 5);
$recente    = $novo('Recente', $primeira, 1);
$parada     = $novo('Parada', $meio, 12);
$movida     = $novo('Movida', $meio, 12);
$pdo->prepare("INSERT INTO pipeline_movements (candidatura_id, stage_anterior_id, stage_novo_id, usuario_id) VALUES (?, ?, ?, NULL)")->execute([$movida, $primeira, $meio]); // movimentada hoje
$rejeitada  = $novo('Rejeitada', $stage('Rejeitado') ?: $meio, 30);

$p = Alertas::pendencias(500);
$ids = static fn(array $rows) => array_map(fn($r) => (int)$r['id'], $rows);
$nt = $ids($p['nao_triadas']); $pa = $ids($p['paradas']);

$assert(in_array($semTriagem, $nt, true), 'candidatura de 5 dias na 1ª etapa deveria estar em "aguardando triagem"');
$assert(!in_array($recente, $nt, true), 'candidatura de 1 dia não deveria alertar');
$assert(in_array($parada, $pa, true), 'candidatura sem movimentação há 12 dias deveria estar em "paradas"');
$assert(!in_array($movida, $pa, true), 'candidatura movimentada hoje não deveria alertar');
if ($stage('Rejeitado')) {
    $assert(!in_array($rejeitada, $pa, true) && !in_array($rejeitada, $nt, true), 'etapa final não deveria alertar');
}
foreach ([$semTriagem, $recente, $parada, $movida, $rejeitada] as $id) {
    $pdo->prepare('DELETE FROM candidaturas WHERE id = ?')->execute([$id]);
}
$pdo->prepare('DELETE FROM vagas WHERE id = ?')->execute([$vaga]);
echo "OK unit_alertas\n";
