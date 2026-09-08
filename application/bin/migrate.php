<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\Changes;
$pdo=App\Database::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS site.builder_migrations (filename text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
$files=glob(BASE_PATH.'/website/migrations/*.sql')?:[];sort($files);
foreach($files as $file){
    $name='migrations/'.basename($file);$s=$pdo->prepare('SELECT 1 FROM site.builder_migrations WHERE filename=?');$s->execute([$name]);if($s->fetchColumn())continue;
    $pdo->beginTransaction();
    try{foreach(Changes::sql(file_get_contents($file)) as $sql)$pdo->exec($sql);$pdo->prepare('INSERT INTO site.builder_migrations(filename) VALUES(?)')->execute([$name]);$pdo->commit();echo 'Applied '.basename($file)."\n";}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,'Migration failed; transaction rolled back.' . "\n");exit(1);}
}
\App\Database::restrictRuntime();
echo "Website schema is up to date.\n";
