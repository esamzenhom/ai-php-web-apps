<?php
declare(strict_types=1);
namespace App\Admin;
use App\Security;
use RuntimeException;
final class Auth
{
    public static function start(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_name('builder_admin_'.substr(hash('sha256',getenv('APP_ID')?:'app'),0,12)); Security::startSession(); }
    public static function signedIn(): bool
    {
        self::start(); $s = Store::settings();
        return $s && isset($_SESSION['admin_version']) && (int)$_SESSION['admin_version'] === (int)$s['version'] && ($_SESSION['expires'] ?? 0) > time() && (empty($s['totp_secret']) || ($_SESSION['mfa_verified']??false)===true);
    }
    public static function requireAdmin(): void { if (!self::signedIn()) { http_response_code(401); throw new RuntimeException('Please sign in again.'); } }
    public static function loginSession(array $s,bool $mfa=false): void { self::start(); session_regenerate_id(true); $_SESSION = ['admin_version'=>(int)$s['version'],'expires'=>time()+28800,'mfa_verified'=>$mfa||empty($s['totp_secret'])]; }
    public static function throttle(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
        $s = Store::db()->prepare('SELECT * FROM attempts WHERE address=?'); $s->execute([$ip]); $a=$s->fetch();
        if ($a && $a['failures'] >= 10 && $a['until_at'] > time()) { http_response_code(429); throw new RuntimeException('Too many attempts. Wait 15 minutes and try again.'); }
    }
    public static function failure(): void
    {
        Store::db()->prepare('INSERT INTO attempts(address,failures,until_at) VALUES(?,1,?) ON CONFLICT(address) DO UPDATE SET failures=CASE WHEN until_at < ? THEN 1 ELSE failures+1 END, until_at=excluded.until_at')->execute([$_SERVER['REMOTE_ADDR'] ?? 'local', time()+900,time()]);
    }
    public static function password(string $password): void
    {
        self::throttle(); $s=Store::settings();
        if (!$s || !Security::verifyPassword($password,$s['password'])) { self::failure(); throw new RuntimeException('The current password is incorrect.'); }
    }
    public static function credentials(string $username,string $password): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,64}$/D',$username)) throw new RuntimeException('Use 3–64 letters, numbers, dots, hyphens or underscores for your username.');
        if (strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Use a password between 12 and 72 characters.');
    }
    public static function adminPath(string $path): void
    {
        if (!preg_match('~^/[a-z][a-z0-9-]{2,48}$~D',$path) || in_array($path,['/health','/assets','/api','/login','/logout','/setup','/favicon','/robots','/site'])) throw new RuntimeException('Use a unique address such as /manage-my-app (3–49 lowercase letters, numbers or hyphens).');
        if (file_exists(BASE_PATH.'/website/public'.$path) || file_exists(BASE_PATH.'/admin/public'.$path)) throw new RuntimeException('That address already belongs to the website. Choose another.');
        foreach (glob(BASE_PATH.'/website/public/*.php') ?: [] as $file) {
            if (str_contains(file_get_contents($file),"'".$path."'") || str_contains(file_get_contents($file),'"'.$path.'"')) throw new RuntimeException('That address appears in a website route. Choose another.');
        }
    }
}
