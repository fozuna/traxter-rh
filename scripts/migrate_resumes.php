<?php
/**
 * Migra currículos de instalações antigas para o padrão seguro:
 *  - move os PDFs para STORAGE_PATH/resumes (pasta configurada em storage.path);
 *  - renomeia arquivos com nome do candidato para nomes aleatórios;
 *  - atualiza candidaturas.pdf_path.
 *
 * Uso:
 *   php scripts/migrate_resumes.php                 # simulação (não altera nada)
 *   php scripts/migrate_resumes.php --apply         # executa
 *   php scripts/migrate_resumes.php --from=/caminho/antigo/resumes --apply
 *
 * Faça backup do banco e da pasta de currículos antes de usar --apply.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/core/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$from = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'resumes';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = rtrim(substr($arg, 7), '/\\');
    }
}
$to = STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes';

echo ($apply ? '[EXECUÇÃO]' : '[SIMULAÇÃO]') . "\n";
echo "Origem:  {$from}\nDestino: {$to}\n\n";

if (!is_dir($to) && $apply && !@mkdir($to, 0770, true)) {
    fwrite(STDERR, "Não foi possível criar o destino: {$to}\n");
    exit(1);
}

$isRandom = static fn(string $name): bool => (bool)preg_match('/^[a-f0-9]{32,64}\.pdf$/', $name);

$pdo = Database::conn();
$rows = $pdo->query("SELECT id, pdf_path FROM candidaturas WHERE pdf_path IS NOT NULL AND pdf_path <> ''")->fetchAll();

$stats = ['ok' => 0, 'ja_seguro' => 0, 'ausente' => 0, 'erro' => 0];
$referenced = [];

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $current = basename((string)$row['pdf_path']);
    $referenced[$current] = true;

    $source = null;
    foreach ([$from, $to] as $dir) {
        if (is_file($dir . DIRECTORY_SEPARATOR . $current)) {
            $source = $dir . DIRECTORY_SEPARATOR . $current;
            break;
        }
    }
    if ($source === null) {
        $stats['ausente']++;
        echo "  [AUSENTE] candidatura {$id}: arquivo não encontrado\n";
        continue;
    }

    $inDestination = realpath(dirname($source)) === realpath($to);
    if ($inDestination && $isRandom($current)) {
        $stats['ja_seguro']++;
        continue;
    }

    $newName = $isRandom($current) ? $current : bin2hex(random_bytes(20)) . '.pdf';
    $target = $to . DIRECTORY_SEPARATOR . $newName;

    if (!$apply) {
        $stats['ok']++;
        echo "  [MOVER] candidatura {$id}: arquivo será " . ($newName === $current ? 'movido' : 'movido e renomeado') . "\n";
        continue;
    }

    if (!@rename($source, $target)) {
        if (!@copy($source, $target) || !@unlink($source)) {
            $stats['erro']++;
            echo "  [ERRO] candidatura {$id}: falha ao mover o arquivo\n";
            continue;
        }
    }
    @chmod($target, 0640);

    try {
        $pdo->prepare('UPDATE candidaturas SET pdf_path = ? WHERE id = ?')->execute([$newName, $id]);
        $stats['ok']++;
    } catch (Throwable $e) {
        @rename($target, $source); // desfaz para manter consistência
        $stats['erro']++;
        echo "  [ERRO] candidatura {$id}: banco não atualizado, arquivo restaurado\n";
    }
}

$orphans = 0;
if (is_dir($from)) {
    foreach (glob($from . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $file) {
        if (!isset($referenced[basename($file)])) {
            $orphans++;
        }
    }
}

echo "\nResumo:\n";
echo "  " . ($apply ? 'Migrados' : 'A migrar') . ": {$stats['ok']}\n";
echo "  Já no padrão seguro: {$stats['ja_seguro']}\n";
echo "  Arquivos ausentes: {$stats['ausente']}\n";
echo "  Erros: {$stats['erro']}\n";
echo "  PDFs na origem sem candidatura vinculada (não movidos): {$orphans}\n";
if (!$apply) {
    echo "\nNada foi alterado. Rode novamente com --apply para executar.\n";
}
exit($stats['erro'] > 0 ? 1 : 0);
