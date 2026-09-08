<?php
declare(strict_types=1);
namespace App\Admin;
use RuntimeException;
final class Audit
{
    public static function scan(array $proposal,array $base): array
    {
        foreach($proposal['files'] as $file)$base[$file['path']]=$file['content'];
        $ch=curl_init('http://audit:8091/');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['files'=>$base],JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.getenv('AUDIT_SERVICE_TOKEN')],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>100]);
        $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($status!==200||!is_string($body))throw new RuntimeException('Automatic security scan is unavailable. No change was approved; try again when the scanner is ready.');
        $report=json_decode($body,true,32,JSON_THROW_ON_ERROR);
        if(!array_key_exists('passed',$report))throw new RuntimeException('Security scanner returned an invalid report.');
        return $report;
    }
    public static function enforce(array $report): void
    {
        if(($report['passed']??false)!==true){
            $items=array_slice($report['findings']??[],0,8);
            throw new RuntimeException('Security check blocked this change: '.implode('; ',array_map(fn($f)=>$f['file'].':'.$f['line'].' '.$f['message'],$items)).' Ask your assistant to correct it.');
        }
    }
}
