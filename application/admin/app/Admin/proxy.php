<?php
declare(strict_types=1);
$siteLock=fopen(BASE_PATH.'/data/control/site.lock','c');
if (!$siteLock || is_file(BASE_PATH.'/data/control/maintenance') || !flock($siteLock,LOCK_SH|LOCK_NB)) {
    http_response_code(503); header('Retry-After: 5'); echo 'Your website is being updated. Please try again shortly.'; return;
}
// Generated pages always have an opaque browser origin, including direct visits.
header("Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups; default-src 'self' data: https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$uri=substr($_SERVER['REQUEST_URI'],strlen('/_site')) ?: '/';
if (!str_starts_with($uri,'/') || preg_match('~[\r\n\\\\]~',$uri)) { http_response_code(400); exit; }
$ch=curl_init('http://gateway'.$uri);
$headers=[];
foreach (['CONTENT_TYPE'=>'Content-Type','HTTP_ACCEPT'=>'Accept'] as $name=>$label) if(isset($_SERVER[$name])) $headers[]=$label.': '.$_SERVER[$name];
$cookiePrefix='studio_site_'.substr(hash('sha256',getenv('APP_ID')?:'app'),0,12).'_';
$cookies=[];
foreach ($_COOKIE as $name=>$value) if (str_starts_with($name,$cookiePrefix) && is_string($value)) $cookies[]=rawurlencode(substr($name,strlen($cookiePrefix))).'='.rawurlencode($value);
if($cookies) $headers[]='Cookie: '.implode('; ',$cookies);
$headers[]='X-Forwarded-Prefix: /_site';
$responseHeaders=[];
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$_SERVER['REQUEST_METHOD'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>file_get_contents('php://input'),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$responseHeaders) { $responseHeaders[]=$line; return strlen($line); }]);
$body=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
if($body===false || !$status) { http_response_code(502); echo 'Your website is restarting. Please refresh in a moment.'; exit; }
http_response_code($status); $html=false;
foreach($responseHeaders as $line) {
    if(stripos($line,'Content-Type:')===0) { header(trim($line)); $html=stripos($line,'text/html')!==false; }
    if(preg_match('/^Set-Cookie:\s*([A-Za-z0-9_-]+)=/i',$line,$match)) {
        $line=preg_replace('/^Set-Cookie:\s*([A-Za-z0-9_-]+)=/i','Set-Cookie: '.$cookiePrefix.'$1=',$line,1);
        $cookie=preg_replace('/;\s*(?:Domain|Path)=[^;]*/i','',trim($line)); header($cookie.'; Path=/_site; SameSite=Lax',false);
    }
    if(stripos($line,'Location:')===0) {
        $location=trim(substr($line,9));
        if(str_starts_with($location,'/') && !str_starts_with($location,'//')) header('Location: /_site'.$location);
    }
}
if($html) $body=preg_replace('/<head\b[^>]*>/i','$0<base href="/_site/">',$body,1);
echo $body;
