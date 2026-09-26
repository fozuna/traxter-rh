<?php
/**
 * Rotina LGPD: anonimiza candidaturas com prazo de guarda vencido.
 *
 * Uso:
 *   php scripts/lgpd_retencao.php           # simulação: só lista quantas seriam anonimizadas
 *   php scripts/lgpd_retencao.php --apply   # executa
 *
 * Agende no cron (Hostinger: hPanel → Avançado → Cron Jobs), uma vez por dia:
 *   /usr/bin/php /home/USUARIO/.../scripts/lgpd_retencao.php --apply
 *
 * Prazo: cliente.retencao_meses no config.php (padrão 12). Candidatos na etapa
 * "Contratado" não são afetados.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/core/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$meses = Brand::retentionMonths();
$ids = Retencao::vencidas($meses);

echo date('Y-m-d H:i:s') . ' ' . ($apply ? '[EXECUÇÃO]' : '[SIMULAÇÃO]')
    . " Prazo: {$meses} meses. Candidaturas vencidas: " . count($ids) . "\n";

if (!$apply) {
    if ($ids) {
        echo 'IDs: ' . implode(', ', $ids) . "\n";
    }
    echo "Nada foi alterado. Use --apply para anonimizar.\n";
    exit(0);
}

$ok = 0;
$erros = 0;
foreach ($ids as $id) {
    try {
        if (Retencao::anonimizar($id, null, 'prazo')) {
            $ok++;
        }
    } catch (Throwable $e) {
        $erros++;
        Logger::exception($e, 'ERROR', ['lgpd_retencao' => ['candidatura_id' => $id]]);
        fwrite(STDERR, "Erro ao anonimizar candidatura {$id}\n");
    }
}

echo "Anonimizadas: {$ok}. Erros: {$erros}.\n";
Logger::info('Rotina LGPD de retenção executada', ['lgpd_retencao' => ['anonimizadas' => $ok, 'erros' => $erros, 'prazo_meses' => $meses]]);
exit($erros > 0 ? 1 : 0);
