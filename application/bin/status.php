<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
$s=App\Admin\Store::settings();
echo json_encode(['configured'=>$s!==null,'path'=>$s['path']??'/admin']);
