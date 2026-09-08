<?php
declare(strict_types=1);
namespace App\Admin;
use App\Security;
use RuntimeException;
use Throwable;
final class Totp
{
    public static function generate(): string
    {
        $bits='';foreach(str_split(random_bytes(20)) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$out='';foreach(str_split($bits,5) as $part)$out.=$alphabet[bindec($part)];return $out;
    }
    public static function code(string $secret,int $counter): string
    {
        $bits='';$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        foreach(str_split($secret) as $c){$n=strpos($alphabet,$c);if($n===false)throw new RuntimeException('Invalid authenticator setup key.');$bits.=str_pad(decbin($n),5,'0',STR_PAD_LEFT);}
        $key='';foreach(str_split($bits,8) as $byte)if(strlen($byte)===8)$key.=chr(bindec($byte));
        $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),$key,true);
        $offset=ord($hash[19])&15;$value=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
        return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
    }
    public static function match(string $secret,string $code,int $last=-1): ?int
    {
        if(!preg_match('/^[0-9]{6}$/D',$code))return null;
        $now=intdiv(time(),30);
        foreach([$now,$now-1,$now+1] as $counter)if($counter>$last && hash_equals(self::code($secret,$counter),$code))return $counter;
        return null;
    }
    public static function pending(): bool
    {
        $s=Store::settings();return $s && !empty($s['totp_secret']) && ($_SESSION['totp_until']??0)>time() && ($_SESSION['totp_version']??0)===(int)$s['version'];
    }
    public static function challenge(array $settings): void
    {
        Auth::start();session_regenerate_id(true);$_SESSION=['totp_until'=>time()+300,'totp_version'=>(int)$settings['version']];
    }
    public static function consume(string $code): void
    {
        Auth::throttle();$db=Store::db();$db->exec('BEGIN IMMEDIATE');$valid=false;
        try {
            $settings=Store::settings();
            if(!empty($settings['totp_secret'])){
                $counter=self::match(Store::secret($settings['totp_secret'],true),$code,(int)$settings['totp_counter']);
                if($counter!==null){$db->prepare('UPDATE settings SET totp_counter=? WHERE id=1')->execute([$counter]);$valid=true;}
                elseif(preg_match('/^[a-f0-9]{16}$/D',$code)){$q=$db->prepare('DELETE FROM recovery_codes WHERE hash=?');$q->execute([hash('sha256',$code)]);$valid=$q->rowCount()===1;}
            }
            $db->exec('COMMIT');
        }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        if(!$valid){Auth::failure();throw new RuntimeException('The code is invalid, expired or already used. Wait for a new code, or use an unused recovery code.');}
    }
    public static function action(array $input): array
    {
        Auth::password((string)($input['current_password']??''));$operation=$input['operation']??'';
        if($operation==='begin'){
            if(!empty(Store::settings()['totp_secret']))self::consume(trim((string)($input['code']??'')));
            $_SESSION['enroll_secret']=self::generate();$_SESSION['enroll_until']=time()+600;
            return ['secret'=>$_SESSION['enroll_secret'],'uri'=>'otpauth://totp/'.rawurlencode('App Studio:'.Store::settings()['username']).'?secret='.$_SESSION['enroll_secret'].'&issuer=App%20Studio&algorithm=SHA1&digits=6&period=30'];
        }
        if($operation==='enable'){
            $secret=$_SESSION['enroll_secret']??'';
            if(($_SESSION['enroll_until']??0)<time()||!$secret)throw new RuntimeException('Authenticator setup expired. Start again.');
            $counter=self::match($secret,trim((string)($input['code']??'')));
            if($counter===null){Auth::failure();throw new RuntimeException('Enter the current six-digit code from your authenticator.');}
            $db=Store::db();$db->exec('BEGIN IMMEDIATE');$codes=[];
            try{
                $db->prepare('UPDATE settings SET totp_secret=?,totp_counter=?,version=version+1 WHERE id=1')->execute([Store::secret($secret),$counter]);
                $db->exec('DELETE FROM recovery_codes');$q=$db->prepare('INSERT INTO recovery_codes(hash) VALUES(?)');
                for($i=0;$i<10;$i++){$code=bin2hex(random_bytes(8));$codes[]=$code;$q->execute([hash('sha256',$code)]);}
                $db->exec('COMMIT');
            }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
            Auth::loginSession(Store::settings(),true);
            return ['enabled'=>true,'recovery_codes'=>$codes,'csrf'=>Security::csrfToken()];
        }
        if($operation==='disable'){
            if(($input['confirm']??false)!==true)throw new RuntimeException('Confirm that future sign-ins will use only your password.');
            self::consume(trim((string)($input['code']??'')));
            $db=Store::db();$db->exec('BEGIN IMMEDIATE');
            try{$db->exec('UPDATE settings SET totp_secret=NULL,totp_counter=-1,version=version+1 WHERE id=1');$db->exec('DELETE FROM recovery_codes');$db->exec('COMMIT');}catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
            Auth::loginSession(Store::settings());return ['enabled'=>false,'csrf'=>Security::csrfToken()];
        }
        throw new RuntimeException('Unknown authenticator operation.');
    }
}
