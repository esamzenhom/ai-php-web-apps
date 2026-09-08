<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
use App\Admin\Auth;
use App\Admin\Store;
use App\Admin\Controller;
use App\Security;
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/','/')?:'/';
try {
    if ($path==='/health') {
        header('Content-Type: application/json'); Store::db()->query('SELECT 1');
        echo json_encode(['status'=>'ok']); exit;
    }
    if ($path==='/_chat-status') {
        header('Cache-Control: no-store');
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
        $enabled=Auth::signedIn() && (int)(Store::settings()['floating']??0)===1;
        echo json_encode(['enabled'=>$enabled]); exit;
    }
    if (Controller::handle($path)) exit;
    if ($path==='/') {
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        $settings=Store::settings(); $adminPath=Store::path(); $signedIn=Auth::signedIn();
        require BASE_PATH.'/admin/app/Views/home.php'; exit;
    }
    if ($path==='/_site' || str_starts_with($path,'/_site/')) {
        if (!Store::settings()) { header('Location: /'); exit; }
        require BASE_PATH.'/admin/app/Admin/proxy.php'; exit;
    }
    http_response_code(404); echo 'Page not found.';
} catch(Throwable $e) {
    http_response_code(503); header('Content-Type: text/plain'); echo 'The app is starting or needs attention. Run the start script and try again.';
}
