<?php
declare(strict_types=1);
namespace App\Admin;
use RuntimeException;
final class Backup
{
    private const MAGIC="STBACK01";
    public static function temporary(): string
    {
        $dir=BASE_PATH.'/data/control/backup-work';
        if (!is_dir($dir)) mkdir($dir,0700,true);
        $file=tempnam($dir,'private-'); if (!$file) throw new RuntimeException('Recovery workspace unavailable.');
        chmod($file,0600); return $file;
    }
    public static function encryptFile(string $input,string $output): void
    {
        $public=file_get_contents(BASE_PATH.'/data/recovery/public.key');
        if (strlen($public)!==SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) throw new RuntimeException('Backup recovery key is unavailable.');
        $key=sodium_crypto_secretstream_xchacha20poly1305_keygen();
        [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $temp=$output.'.partial-'.bin2hex(random_bytes(6)); $in=fopen($input,'rb');$out=fopen($temp,'xb');chmod($temp,0600);
        try {
            self::put($out,self::MAGIC.sodium_crypto_box_seal($key,$public).$header);
            while (!feof($in)) {
                $plain=fread($in,65536);if ($plain===false) throw new RuntimeException('Backup read failed.');
                if ($plain==='') break;
                $cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$plain);
                self::put($out,pack('N',strlen($cipher)).$cipher);
            }
            $final=sodium_crypto_secretstream_xchacha20poly1305_push($state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            self::put($out,pack('N',strlen($final)).$final);fclose($out);$out=null;
            $check=self::temporary();
            try { self::decryptFile($temp,$check);if (!hash_equals(hash_file('sha256',$input),hash_file('sha256',$check))) throw new RuntimeException('Encrypted backup verification failed.'); }
            finally { unlink($check); }
            if (!rename($temp,$output)) throw new RuntimeException('Could not finalize encrypted backup.');
        } finally { fclose($in);if(is_resource($out))fclose($out);if(is_file($temp))unlink($temp);sodium_memzero($key); }
    }
    private static function put($stream,string $bytes): void
    {
        while ($bytes!=='') { $n=fwrite($stream,$bytes);if (!$n) throw new RuntimeException('Backup storage is full or unavailable.');$bytes=substr($bytes,$n); }
    }
    private static function take($stream,int $size): string
    {
        $value='';while(strlen($value)<$size){$part=fread($stream,$size-strlen($value));if($part===false||$part==='')throw new RuntimeException('Encrypted backup is incomplete.');$value.=$part;}return $value;
    }
    public static function decryptFile(string $input,string $output): void
    {
        $pair=file_get_contents(BASE_PATH.'/data/recovery/keypair');
        $in=fopen($input,'rb');$out=fopen($output,'wb');chmod($output,0600);
        try {
            if(self::take($in,8)!==self::MAGIC)throw new RuntimeException('Unsupported encrypted backup.');
            $key=sodium_crypto_box_seal_open(self::take($in,80),$pair);
            if($key===false)throw new RuntimeException('This recovery key does not match the backup.');
            $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::take($in,24),$key);sodium_memzero($key);
            while(true){
                $length=unpack('N',self::take($in,4))[1];
                if($length<17||$length>65553)throw new RuntimeException('Invalid encrypted backup block.');
                $block=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::take($in,$length));
                if($block===false)throw new RuntimeException('Backup authentication failed; no restore was performed.');
                [$plain,$tag]=$block;self::put($out,$plain);
                if($tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL){if(fread($in,1)!=='')throw new RuntimeException('Unexpected data after backup.');break;}
            }
        } finally { fclose($in);fclose($out);sodium_memzero($pair); }
    }
    public static function exists(string $path): bool { return is_file($path.'.enc')||is_file($path); }
    public static function withFile(string $path,callable $callback): mixed
    {
        $encrypted=str_ends_with($path,'.enc')?$path:$path.'.enc';
        if(!is_file($encrypted))return $callback($path); // Legacy backups remain recoverable during conversion.
        $temp=self::temporary();try{self::decryptFile($encrypted,$temp);return $callback($temp);}finally{unlink($temp);}
    }
    public static function read(string $path): string { return self::withFile($path,fn($file)=>file_get_contents($file)); }
    public static function write(string $path,string $content): void
    {
        $temp=self::temporary();try{file_put_contents($temp,$content);self::encryptFile($temp,$path.'.enc');}finally{unlink($temp);}
    }
}
