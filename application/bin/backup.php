<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\{Changes,Store,Backup};
$dir=BASE_PATH.'/backups/full-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));mkdir($dir,0700,true);
$temp=Backup::temporary();$failed=false;
try {
    Changes::command(['pg_dump','-h',getenv('DB_HOST')?:'postgres','-U',getenv('DB_USER')?:'app_migrator','-d',getenv('DB_NAME')?:'app','--schema=site','--no-owner','--format=custom','--lock-wait-timeout=10000'],$temp);
    Changes::command(['pg_restore','--list',$temp]);Backup::encryptFile($temp,$dir.'/database.dump.enc');
    unlink($temp);Store::db()->exec('VACUUM INTO '.Store::db()->quote($temp));chmod($temp,0600);Backup::encryptFile($temp,$dir.'/admin.sqlite.enc');
    Backup::encryptFile(BASE_PATH.'/data/control/key',$dir.'/key.enc');
    Backup::write($dir.'/files.json',json_encode(Changes::snapshot(),JSON_THROW_ON_ERROR));
    Changes::command(['tar','-cf',$temp,'-C',BASE_PATH.'/site-data','.']);Backup::encryptFile($temp,$dir.'/site-data.tar.enc');
    echo 'Verified encrypted backup: backups/'.basename($dir)."\nKeep your separately exported recovery key outside the backup folder.\n";
} catch(Throwable $e) { fwrite(STDERR,"Backup did not complete. Do not proceed with a destructive operation.\n");$failed=true; }
finally {if(is_file($temp))unlink($temp);}

if($failed)exit(1);
