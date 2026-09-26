<?php
class Database
{
    private static ?PDO $pdo = null;

    /** Fecha a conexão atual (a próxima chamada a conn() reconecta com a config vigente). */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function conn(): \PDO
    {
        if (self::$pdo === null) {
            $config = Config::app()['database'];
            
            try {
                self::$pdo = new \PDO($config['dsn'], $config['user'], $config['pass'], $config['options']);
            } catch (\PDOException $e) {
                // Em desenvolvimento, log o erro mas não pare a aplicação
                if (Config::app()['env'] === 'dev') {
                    error_log('Database connection failed: ' . $e->getMessage());
                    throw new \RuntimeException('Não foi possível conectar ao banco de dados. Verifique se o MySQL/MariaDB está ativo (no Laragon: Start All) e os dados em app/config/config.php.');
                }
                throw new \RuntimeException('Erro ao conectar ao banco de dados: ' . $e->getMessage());
            }
        }
        
        return self::$pdo;
    }
}