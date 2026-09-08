<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\Backup;
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
// Explicit maintenance command; never called silently by startup.
$count=0;
foreach(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH.'/backups',FilesystemIterator::SKIP_DOTS))) as $file){
    if($file->isLink()) {
        $path=$file->getPathname();
        if(file_exists($path.'.symlink')||is_link($path.'.symlink'))throw new RuntimeException('Link metadata filename conflicts with an existing backup; stopped safely.');
        $temp=Backup::temporary();
        try{file_put_contents($temp,readlink($path));Backup::encryptFile($temp,$path.'.symlink.enc');}finally{unlink($temp);}
        if(!unlink($path))throw new RuntimeException('Link metadata verified but its original could not be removed.');
        $count++;continue;
    }
    if(!$file->isFile()||str_ends_with($file->getFilename(),'.enc'))continue;
    $path=$file->getPathname();Backup::encryptFile($path,$path.'.enc');
    if(!unlink($path))throw new RuntimeException('Encrypted copy verified, but the plaintext original could not be removed.');
    $count++;
}
echo "Verified and encrypted $count legacy backup files. Recovery key stays outside backups.\n";
