<?php
/**
 * Bootstrap — runs on every request and CLI script.
 * Sets up error handling and a minimal PSR-4-ish autoloader for the
 * App\ namespace, so there is no Composer dependency to manage.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('BASE_PATH', dirname(__DIR__, 2));

// Autoload: App\Foo\Bar  ->  app/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/admin/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});
