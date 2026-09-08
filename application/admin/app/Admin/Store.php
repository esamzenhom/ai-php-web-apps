<?php
declare(strict_types=1);
namespace App\Admin;
use PDO;
use RuntimeException;
final class Store
{
    private static ?PDO $db = null;
    public static function db(): PDO
    {
        if (self::$db) return self::$db;
        $path = BASE_PATH . '/data/control';
        if (!is_dir($path)) throw new RuntimeException('Start the app with start.sh first.');
        self::$db = new PDO('sqlite:' . $path . '/admin.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        self::$db->exec('PRAGMA journal_mode=DELETE; PRAGMA busy_timeout=5000;');
        self::$db->exec('CREATE TABLE IF NOT EXISTS control_migrations (filename TEXT PRIMARY KEY)');
        self::$db->exec('BEGIN IMMEDIATE');
        try {
            $migrations=glob(BASE_PATH.'/admin/migrations/*.sql')?:[]; sort($migrations);
            foreach($migrations as $file) {
                $statement=self::$db->prepare('SELECT 1 FROM control_migrations WHERE filename=?');
                $statement->execute([basename($file)]); $applied=$statement->fetchColumn(); $statement->closeCursor();
                if ($applied) continue;
                self::$db->exec(file_get_contents($file));
                self::$db->prepare('INSERT INTO control_migrations(filename) VALUES(?)')->execute([basename($file)]);
            }
            self::$db->exec('COMMIT');
        } catch (\Throwable $error) { self::$db->exec('ROLLBACK'); throw $error; }
        return self::$db;
    }
    public static function settings(): ?array { return self::db()->query('SELECT * FROM settings WHERE id=1')->fetch() ?: null; }
    public static function path(): string { return self::settings()['path'] ?? '/admin'; }
    public static function secret(string $value, bool $decrypt = false): string
    {
        $key = file_get_contents(BASE_PATH . '/data/control/key');
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Encryption key unavailable. Restore the app configuration backup.');
        if (!$decrypt) { $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, $key)); }
        $raw = base64_decode($value, true);
        if (!$raw || strlen($raw) < 25) throw new RuntimeException('Saved API key could not be opened.');
        $result = sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), $key);
        if ($result === false) throw new RuntimeException('Saved API key could not be opened.');
        return $result;
    }
    public static function job(string $id): array
    {
        $s = self::db()->prepare('SELECT * FROM jobs WHERE id=?'); $s->execute([$id]);
        return $s->fetch() ?: throw new RuntimeException('Conversation not found.');
    }
    public static function update(string $id, string $status, ?string $error = null): void
    {
        self::db()->prepare('UPDATE jobs SET status=?, error=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$error,$id]);
    }
}
