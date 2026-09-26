<?php
/**
 * Resumo diário para o RH: candidaturas aguardando triagem e paradas na mesma etapa.
 * Envia para mail.to_hr apenas quando houver pendências.
 *
 * Uso:
 *   php scripts/alertas_rh.php            # mostra o resumo na tela
 *   php scripts/alertas_rh.php --enviar   # envia por e-mail
 *
 * Cron (Hostinger → Avançado → Cron Jobs), dias úteis às 8h:
 *   0 8 * * 1-5  /usr/bin/php /home/USUARIO/.../scripts/alertas_rh.php --enviar
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/core/bootstrap.php';

$enviar = in_array('--enviar', $argv, true);
$p = Alertas::pendencias(50);
$total = count($p['nao_triadas']) + count($p['paradas']);
$base = rtrim((string)(Config::app()['base_url'] ?? ''), '/');
if ($base === '' || str_starts_with($base, 'http://localhost')) {
    $base = rtrim((string)(Brand::clientSite() ?: ''), '/');
}

$linhas = [];
$bloco = static function (string $titulo, array $itens) use (&$linhas, $base): void {
    if (!$itens) {
        return;
    }
    $linhas[] = $titulo . ' (' . count($itens) . '):';
    foreach ($itens as $it) {
        $linhas[] = sprintf('  - %s | %s | %s | %d dias%s',
            $it['nome'], $it['vaga_titulo'] ?? '-', $it['etapa'], (int)$it['dias'],
            $base !== '' ? ' | ' . $base . '/admin/candidaturas/' . (int)$it['id'] : '');
    }
    $linhas[] = '';
};
$bloco('Aguardando triagem há mais de ' . Alertas::diasTriagem() . ' dias', $p['nao_triadas']);
$bloco('Paradas na mesma etapa há mais de ' . Alertas::diasParado() . ' dias', $p['paradas']);

if ($total === 0) {
    echo "Nenhuma pendência. Nada a enviar.\n";
    exit(0);
}

$corpo = "Bom dia!\n\nHá {$total} candidatura(s) precisando de atenção no " . Brand::productName() . ":\n\n"
    . implode("\n", $linhas)
    . "\nCandidatos sem retorno desistem do processo. Vale uma olhada hoje.\n";

if (!$enviar) {
    echo $corpo;
    exit(0);
}

$ok = Mailer::notifyHR('[' . Brand::clientName() . '] ' . $total . ' candidatura(s) precisando de atenção', $corpo);
echo $ok ? "Resumo enviado para o RH.\n" : 'Falha no envio: ' . Mailer::lastError() . "\n";
exit($ok ? 0 : 1);
