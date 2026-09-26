<?php
/**
 * Registro de consentimento LGPD dos candidatos.
 * Guarda quando, de onde e qual versão da política foi aceita — prova exigida
 * para demonstrar o consentimento (LGPD, art. 8º, § 2º).
 */
class Consentimento
{
    /** Versão vigente da política de privacidade. Altere ao mudar o texto da política. */
    public const VERSAO_POLITICA = '2026-09';

    private static bool $tableReady = false;

    public static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        Database::conn()->exec("CREATE TABLE IF NOT EXISTS lgpd_consentimentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidatura_id INT NOT NULL,
            versao_politica VARCHAR(20) NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            aceito_em DATETIME NOT NULL,
            CONSTRAINT fk_consent_candidatura FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
            INDEX idx_consent_candidatura (candidatura_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$tableReady = true;
    }

    public static function registrar(int $candidaturaId, ?string $ip, ?string $userAgent): void
    {
        self::ensureTable();
        $stmt = Database::conn()->prepare(
            'INSERT INTO lgpd_consentimentos (candidatura_id, versao_politica, ip, user_agent, aceito_em) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $candidaturaId,
            self::VERSAO_POLITICA,
            $ip !== null ? substr($ip, 0, 45) : null,
            $userAgent !== null ? substr($userAgent, 0, 255) : null,
        ]);
    }

    public static function daCandidatura(int $candidaturaId): ?array
    {
        self::ensureTable();
        $stmt = Database::conn()->prepare(
            'SELECT versao_politica, ip, aceito_em FROM lgpd_consentimentos WHERE candidatura_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$candidaturaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
