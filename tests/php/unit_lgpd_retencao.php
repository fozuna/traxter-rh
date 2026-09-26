<?php
declare(strict_types=1);
if (@fsockopen('127.0.0.1', 3306, $errno, $errstr, 1) === false) {
    echo "SKIP unit_lgpd_retencao (MySQL indisponível)\n";
    exit(0);
}
require_once __DIR__ . '/../../app/core/bootstrap.php';

$assert = static function (bool $cond, string $msg): void {
    if (!$cond) { fwrite(STDERR, "Falha: {$msg}\n"); exit(1); }
};

$pdo = Database::conn();
Retencao::ensureColumn();
Consentimento::ensureTable();
$suffix = (string)random_int(100000, 999999);
$cpf = str_pad((string)random_int(1, 99999999999), 11, '0', STR_PAD_LEFT);

$vagaId = Vaga::create(['titulo' => 'Vaga Retencao ' . $suffix, 'descricao' => 'x', 'requisitos' => 'x', 'area' => 'x', 'local' => 'x', 'ativo' => 1]);

// currículo fictício no storage
$pdfName = bin2hex(random_bytes(20)) . '.pdf';
$pdfPath = STORAGE_PATH . '/resumes/' . $pdfName;
@mkdir(dirname($pdfPath), 0770, true);
file_put_contents($pdfPath, "%PDF-1.4\n%%EOF\n");

$candId = Candidatura::create([
    'vaga_id' => $vagaId, 'nome' => 'Pessoa Retencao ' . $suffix, 'email' => "ret_{$suffix}@example.test",
    'telefone' => '67999990000', 'cpf' => $cpf, 'cargo_pretendido' => 'Teste', 'experiencia' => 'Experiência sigilosa',
    'pdf_path' => $pdfName, 'status' => 'novo',
]);
Consentimento::registrar($candId, '10.0.0.1', 'teste');
$pdo->prepare("INSERT INTO notas_recrutador (candidatura_id, usuario_id, nota) SELECT ?, id, 'nota pessoal' FROM usuarios ORDER BY id LIMIT 1")->execute([$candId]);

// recente: não vence
$assert(!in_array($candId, Retencao::vencidas(12), true), 'candidatura recente não deveria vencer');

// envelhece 13 meses (candidatura e movimentações)
$pdo->prepare('UPDATE candidaturas SET created_at = DATE_SUB(NOW(), INTERVAL 13 MONTH) WHERE id = ?')->execute([$candId]);
$pdo->prepare('UPDATE notas_recrutador SET created_at = DATE_SUB(NOW(), INTERVAL 13 MONTH) WHERE candidatura_id = ?')->execute([$candId]);
$pdo->prepare('UPDATE candidatura_historico SET created_at = DATE_SUB(NOW(), INTERVAL 13 MONTH) WHERE candidatura_id = ?')->execute([$candId]);
$pdo->prepare('UPDATE pipeline_movements SET created_at = DATE_SUB(NOW(), INTERVAL 13 MONTH) WHERE candidatura_id = ?')->execute([$candId]);
$assert(in_array($candId, Retencao::vencidas(12), true), 'candidatura de 13 meses deveria vencer com prazo de 12');

// contratado nunca vence
$contratado = (int)$pdo->query("SELECT id FROM pipeline_stages WHERE LOWER(nome) = 'contratado' LIMIT 1")->fetchColumn();
if ($contratado > 0) {
    $pdo->prepare('UPDATE candidaturas SET stage_id = ? WHERE id = ?')->execute([$contratado, $candId]);
    $assert(!in_array($candId, Retencao::vencidas(12), true), 'contratado não deveria vencer');
    $pdo->prepare('UPDATE candidaturas SET stage_id = 1 WHERE id = ?')->execute([$candId]);
}

// anonimiza
$assert(Retencao::anonimizar($candId, null, 'prazo') === true, 'anonimização deveria ocorrer');
$c = Candidatura::find($candId);
$assert($c['nome'] === 'Candidato anonimizado', 'nome não anonimizado');
$assert(str_starts_with((string)$c['cpf'], 'X'), 'CPF não anonimizado');
$assert(!str_contains((string)$c['email'], 'example.test'), 'e-mail não anonimizado');
$assert((string)$c['experiencia'] === '' && (string)$c['pdf_path'] === '', 'experiência/currículo não removidos');
$assert(!empty($c['anonimizado_em']), 'data de anonimização ausente');
$assert(!is_file($pdfPath), 'arquivo do currículo deveria ter sido apagado');
$assert(Consentimento::daCandidatura($candId) === null, 'registro de consentimento deveria ter sido removido');
$notas = $pdo->prepare('SELECT COUNT(*) FROM notas_recrutador WHERE candidatura_id = ?'); $notas->execute([$candId]);
$assert((int)$notas->fetchColumn() === 0, 'notas do recrutador deveriam ter sido removidas');
$assert(!in_array($candId, Retencao::vencidas(12), true), 'anonimizada não deveria voltar à lista');
$assert(Retencao::anonimizar($candId, null, 'prazo') === false, 'segunda anonimização deveria ser ignorada');
$assert(!Candidatura::cpfExists($cpf), 'CPF original não deveria mais existir (candidato pode se candidatar de novo)');

// limpeza
$pdo->prepare('DELETE FROM candidaturas WHERE id = ?')->execute([$candId]);
$pdo->prepare('DELETE FROM vagas WHERE id = ?')->execute([$vagaId]);

echo "OK unit_lgpd_retencao\n";
