<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\Backup;
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
try {
 $operation=$argv[1]??'';$input=$argv[2]??'';
 if($operation==='import') {
  // The assistant stages the owner's downloaded JSON here, never in backups/public.
  $path=BASE_PATH.'/data/control/recovery-import.json';
  if($input!=='')throw new RuntimeException('Import uses only data/control/recovery-import.json.');
  $data=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
  $pair=base64_decode($data['recovery_key']??'',true);
  if(($data['format']??'')!=='app-studio-recovery-v1'||$pair===false||strlen($pair)!==SODIUM_CRYPTO_BOX_KEYPAIRBYTES)throw new RuntimeException('Invalid recovery key file.');
  $public=sodium_crypto_box_publickey($pair);
  if(!hash_equals(sodium_crypto_scalarmult_base(sodium_crypto_box_secretkey($pair)),$public))throw new RuntimeException('Invalid recovery key pair.');
  $dir=BASE_PATH.'/data/recovery';if(!is_dir($dir))mkdir($dir,0700,true);
  foreach(['keypair'=>$pair,'public.key'=>$public] as $name=>$value)if(is_file($dir.'/'.$name)&&!hash_equals(file_get_contents($dir.'/'.$name),$value))throw new RuntimeException('A different recovery key exists. Keep both keys separate; ask your assistant to recover into a fresh folder.');
  foreach(['keypair'=>$pair,'public.key'=>$public] as $name=>$value){file_put_contents($dir.'/'.$name,$value);chmod($dir.'/'.$name,0600);}
  unlink($path);sodium_memzero($pair);echo "Recovery key imported. No app records were changed.\n";
 } elseif($operation==='decrypt-folder') {
  $path=realpath(BASE_PATH.'/'.$input);$root=realpath(BASE_PATH.'/backups');
  if(!$path||!$root||!str_starts_with($path,$root.'/')||!is_dir($path))throw new RuntimeException('Choose a folder under backups.');
  $dir=BASE_PATH.'/data/control/recovered';if(!is_dir($dir))mkdir($dir,0700,true);
  $out=$dir.'/'.basename($path);if(file_exists($out))throw new RuntimeException('A recovered folder already exists. Preserve it before continuing.');
  $stage=$dir.'/.pending-'.bin2hex(random_bytes(8));mkdir($stage,0700);$count=0;
  try {
   foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->isLink())throw new RuntimeException('Unexpected link in backup.');
    if(!$file->isFile())continue;
    if(!str_ends_with($file->getFilename(),'.enc'))throw new RuntimeException('Convert remaining legacy files before folder recovery.');
    $relative=substr($file->getPathname(),strlen($path)+1,-4);
    foreach(explode('/',$relative) as $segment)if(in_array($segment,['','.','..'],true))throw new RuntimeException('Invalid backup filename.');
    $target=$stage.'/'.$relative;
    if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);
    Backup::decryptFile($file->getPathname(),$target);$count++;
   }
   if(!$count)throw new RuntimeException('No encrypted files found.');
   if(!rename($stage,$out))throw new RuntimeException('Could not finalize recovered folder.');
   echo 'Verified private recovery folder: data/control/recovered/'.basename($out)."\nNo records were overwritten.\n";
  } finally {
   if(is_dir($stage)){
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($stage);
   }
  }
 } elseif($operation==='decrypt') {
  $path=realpath(BASE_PATH.'/'.$input);$root=realpath(BASE_PATH.'/backups');
  if(!$path||!$root||!str_starts_with($path,$root.'/')||!str_ends_with($path,'.enc')||!is_file($path))throw new RuntimeException('Choose an encrypted file under backups.');
  $dir=BASE_PATH.'/data/control/recovered';if(!is_dir($dir))mkdir($dir,0700,true);
  $out=$dir.'/'.basename($path,'.enc');if(file_exists($out))throw new RuntimeException('A recovered file already exists. Preserve it before continuing.');
  $temp=Backup::temporary();try{Backup::decryptFile($path,$temp);if(!link($temp,$out))throw new RuntimeException('Could not save recovery file.');}finally{unlink($temp);}
  echo 'Verified private recovery file: data/control/recovered/'.basename($out)."\nNo records were overwritten.\n";
 } else throw new RuntimeException('Use recovery.php import or recovery.php decrypt[-folder] backups/<folder>[/<file>.enc]');
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
