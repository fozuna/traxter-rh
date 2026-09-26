<?php
/**
 * Alertas para o recrutador:
 *  - Não triadas: candidaturas na primeira etapa do funil há mais de N dias (recrutamento.alerta_triagem_dias).
 *  - Paradas: candidaturas em etapas intermediárias sem movimentação há mais de N dias (recrutamento.alerta_parado_dias).
 * Etapas finais (Contratado, Rejeitado, Reprovado, Desistente) e candidaturas anonimizadas ficam de fora.
 */
class Alertas
{
    private const ETAPAS_FINAIS = ['contratado', 'rejeitado', 'reprovado', 'desistente', 'desistiu'];

    public static function diasTriagem(): int
    {
        return max(1, (int)(Config::get()['recrutamento']['alerta_triagem_dias'] ?? 3));
    }

    public static function diasParado(): int
    {
        return max(1, (int)(Config::get()['recrutamento']['alerta_parado_dias'] ?? 7));
    }

    /** @return array{nao_triadas: array<int,array>, paradas: array<int,array>} */
    public static function pendencias(int $limite = 50): array
    {
        Retencao::ensureColumn();
        $pdo = Database::conn();
        $primeira = (int)$pdo->query('SELECT id FROM pipeline_stages ORDER BY ordem, id LIMIT 1')->fetchColumn();
        $finais = "'" . implode("','", self::ETAPAS_FINAIS) . "'";
        $limite = max(1, min(500, $limite));

        // Última movimentação: criação, mudança de etapa, histórico ou nota do recrutador
        $base = "SELECT c.id, c.nome, c.created_at, v.titulo AS vaga_titulo, COALESCE(s.nome, 'Novo') AS etapa,
                        GREATEST(
                            c.created_at,
                            COALESCE((SELECT MAX(pm.created_at) FROM pipeline_movements pm WHERE pm.candidatura_id = c.id), c.created_at),
                            COALESCE((SELECT MAX(h.created_at) FROM candidatura_historico h WHERE h.candidatura_id = c.id), c.created_at)
                        ) AS ultima_mov
                 FROM candidaturas c
                 LEFT JOIN vagas v ON v.id = c.vaga_id
                 LEFT JOIN pipeline_stages s ON s.id = c.stage_id
                 WHERE c.anonimizado_em IS NULL
                   AND (s.nome IS NULL OR LOWER(TRIM(s.nome)) NOT IN ({$finais}))";

        $naoTriadas = $pdo->prepare(
            "SELECT * FROM ({$base}) t
             WHERE t.id IN (SELECT id FROM candidaturas WHERE stage_id IS NULL OR stage_id = ?)
               AND t.created_at < DATE_SUB(NOW(), INTERVAL " . self::diasTriagem() . " DAY)
             ORDER BY t.created_at ASC LIMIT {$limite}"
        );
        $naoTriadas->execute([$primeira]);

        $paradas = $pdo->prepare(
            "SELECT * FROM ({$base}) t
             WHERE t.id IN (SELECT id FROM candidaturas WHERE stage_id IS NOT NULL AND stage_id <> ?)
               AND t.ultima_mov < DATE_SUB(NOW(), INTERVAL " . self::diasParado() . " DAY)
             ORDER BY t.ultima_mov ASC LIMIT {$limite}"
        );
        $paradas->execute([$primeira]);

        $agora = time();
        $fmt = static function (array $rows, string $campo) use ($agora): array {
            foreach ($rows as &$r) {
                $r['dias'] = (int)floor(($agora - strtotime((string)$r[$campo])) / 86400);
            }
            return $rows;
        };

        return [
            'nao_triadas' => $fmt($naoTriadas->fetchAll(PDO::FETCH_ASSOC), 'created_at'),
            'paradas' => $fmt($paradas->fetchAll(PDO::FETCH_ASSOC), 'ultima_mov'),
        ];
    }
}
