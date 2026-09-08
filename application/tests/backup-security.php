<?php
declare(strict_types=1);
require 'admin/app/bootstrap.php';
use App\Admin\Backup;
$source=Backup::temporary();$enc=$source.'.enc';$out=$source.'.out';
try {
 file_put_contents($source,random_bytes(200000));Backup::encryptFile($source,$enc);Backup::decryptFile($enc,$out);
 if(hash_file('sha256',$source)!==hash_file('sha256',$out))throw new Exception('Round-trip failed');
 $good=file_get_contents($enc);
 foreach([substr($good,0,-1),$good.'x',substr_replace($good,chr(ord($good[150])^1),150,1),substr_replace($good,str_repeat('x',80),8,80)] as $bad){
  file_put_contents($enc,$bad);$rejected=false;try{Backup::decryptFile($enc,$out);}catch(Throwable $e){$rejected=true;}if(!$rejected)throw new Exception('Invalid encrypted backup accepted');
 }
 echo "PASS: multi-block backup round-trip; truncation, tampering, trailing bytes and mismatched sealed key rejected\n";
}finally{foreach([$source,$enc,$out] as $p)if(is_file($p))unlink($p);}
