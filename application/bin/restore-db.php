<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\Changes;
$file=realpath(BASE_PATH.'/'.($argv[1]??''));
if (!$file || !str_starts_with($file,BASE_PATH.'/backups/') || !in_array(basename($file),['database.dump','database.dump.enc'],true)) exit(1);
Changes::restoreDatabase($file);
echo "Website database restored.\n";
