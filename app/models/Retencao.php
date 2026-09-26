<?php
/**
 * Retenção e anonimização de dados de candidatos (LGPD).
 *
 * Regra de prazo: candidaturas sem nenhuma movimentação há mais de N meses
 * (cliente.retencao_meses) são anonimizadas. Candidatos na etapa "Contratado"
 * ficam de fora: passam a ter vínculo de trabalho, com outra base legal.
 *
 * Anonimizar = apagar o currículo e substituir nome, e-mail, telefone, CPF e
 * experiência por valores sem identificação. A candidatura continua existindo
 * (as estatísticas do painel não mudam), mas não identifica mais ninguém.
 */
class Retencao
{
    private static bool $columnReady = false;

    public static function ensureColumn(): void
    {
        if (self::$columnReady) {
            return;
        }
        $pdo = Database::conn();
        $exists = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidaturas' AND COLUMN_NAME = 'anonimizado_em'"
        )->fetchColumn();
        if ($exists === 0) {
            $pdo->exec('ALTER TABLE candidaturas ADD COLUMN anonimizado_em DATETIME NULL DEFAULT NULL');
        }
        self::$columnReady = true;
    }

    /** IDs das candidaturas com prazo de guarda vencido. */
    public static function vencidas(int $meses): array
    {
        self::ensureColumn();
        $meses = max(1, $meses);
        $sql = "SELECT c.id
                FROM candidaturas c
                LEFT JOIN pipeline_stages s ON s.id = c.stage_id
                WHERE c.anonimizado_em IS NULL
                  AND (s.nome IS NULL OR LOWER(TRIM(s.nome)) <> 'contratado')
                  AND GREATEST(
                        c.created_at,
                        COALESCE((SELECT MAX(pm.created_at) FROM pipeline_movements pm WHERE pm.candidatura_id = c.id), c.created_at),
                        COALESCE((SELECT MAX(h.created_at) FROM candidatura_historico h WHERE h.candidatura_id = c.id), c.created_at),
                        COALESCE((SELECT MAX(n.created_at) FROM notas_recrutador n WHERE n.candidatura_id = c.id), c.created_at)
                      ) < DATE_SUB(NOW(), INTERVAL {$meses} MONTH)
                ORDER BY c.id";
        return array_map('intval', Database::conn()->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function isAnonimizada(array $candidatura): bool
    {
        return !empty($candidatura['anonimizado_em']);
    }

    /**
     * Anonimiza uma candidatura. Retorna false se ela não existir ou já estiver anonimizada.
     * $motivo: 'prazo' (rotina automática) ou 'solicitacao' (pedido do titular).
     */
    public static function anonimizar(int $id, ?int $actorUserId, string $motivo, ?string $ip = null): bool
    {
        self::ensureColumn();
        Consentimento::ensureTable();
        $pdo = Database::conn();

        $stmt = $pdo->prepare('SELECT id, pdf_path, anonimizado_em FROM candidaturas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !empty($row['anonimizado_em'])) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE candidaturas SET
                    nome = 'Candidato anonimizado',
                    email = CONCAT('anonimizado+', id, '@invalid.local'),
                    telefone = '00000000000',
                    cpf = CONCAT('X', LPAD(id, 10, '0')),
                    experiencia = '',
                    pdf_path = '',
                    anonimizado_em = NOW()
                 WHERE id = ?"
            )->execute([$id]);
            $pdo->prepare('DELETE FROM notas_recrutador WHERE candidatura_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE candidatura_historico SET observacoes = NULL WHERE candidatura_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM lgpd_consentimentos WHERE candidatura_id = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Arquivo só é apagado depois que o banco confirmou a anonimização
        $pdf = basename((string)$row['pdf_path']);
        if ($pdf !== '') {
            @unlink(STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR . $pdf);
        }

        $detalhe = 'Candidatura #' . $id . ' anonimizada (' . ($motivo === 'solicitacao' ? 'pedido do titular' : 'prazo de retenção') . ')';
        AuditLog::log($actorUserId, null, 'candidato_anonimizado', $detalhe, $ip);
        return true;
    }
}
