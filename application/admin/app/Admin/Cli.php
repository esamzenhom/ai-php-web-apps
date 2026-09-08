<?php
declare(strict_types=1);
namespace App\Admin;
use RuntimeException;
final class Cli
{
    public static function request(array $data, int $timeout=25): array
    {
        $token=getenv('CLI_SERVICE_TOKEN');
        if (!$token) throw new RuntimeException('CLI connections are not ready. Run the start script to install the CLI service.');
        $ch=curl_init('http://cli:8090/'); $raw='';
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_WRITEFUNCTION=>static function($ch,string $part)use(&$raw):int{if(strlen($raw)+strlen($part)>2000000)return 0;$raw.=$part;return strlen($part);}]);
        $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($ok===false)throw new RuntimeException('The CLI service is unavailable or timed out. Restart the app or use an API key. Nothing was applied.');
        $result=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if($status!==200)throw new RuntimeException($result['error']??'The CLI request failed.');
        return $result;
    }
    public static function connections(): array
    {
        try{return ['available'=>true]+self::request(['action'=>'status'],4);}
        catch(\Throwable $e){return ['available'=>false,'connections'=>['codex'=>false,'claude'=>false]];}
    }
    public static function provider(string $name): ?string
    {
        return ['codex-cli'=>'codex','claude-cli'=>'claude'][$name]??null;
    }
}
