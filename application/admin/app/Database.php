<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Thin PDO/Postgres wrapper. One shared connection per process.
 * Use App\Database::pdo() anywhere you need the DB.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function restrictRuntime(): void
    {
        $pdo=self::pdo();
        $pdo->exec('REVOKE ALL ON ALL TABLES IN SCHEMA site FROM app_runtime; REVOKE ALL ON ALL SEQUENCES IN SCHEMA site FROM app_runtime; GRANT USAGE ON SCHEMA site TO app_runtime; GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA site TO app_runtime; GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA site TO app_runtime');
        $pdo->exec('ALTER DEFAULT PRIVILEGES FOR ROLE app_migrator IN SCHEMA site GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO app_runtime; ALTER DEFAULT PRIVILEGES FOR ROLE app_migrator IN SCHEMA site GRANT USAGE,SELECT ON SEQUENCES TO app_runtime');
        if ($pdo->query("SELECT to_regclass('site.builder_migrations')")->fetchColumn()) $pdo->exec('REVOKE ALL ON site.builder_migrations FROM app_runtime');
    }
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: 'postgres',
            getenv('DB_PORT') ?: '5432',
            getenv('DB_NAME') ?: 'app'
        );

        self::$pdo = new PDO(
            $dsn,
            getenv('DB_USER') ?: 'app',
            getenv('DB_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return self::$pdo;
    }
}
