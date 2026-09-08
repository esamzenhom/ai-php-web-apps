<?php declare(strict_types=1); $name=htmlspecialchars(getenv('APP_NAME')?:'My App',ENT_QUOTES,'UTF-8'); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$name?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#fff;color:#09090b;font:14px/1.6 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:640px;margin:12vh auto;padding:24px}section{border:1px solid #e4e4e7;border-radius:12px;padding:32px}h1{margin:0 0 12px;font-size:28px;font-weight:600;line-height:1.2;letter-spacing:-.035em}p{margin:0;color:#71717a}
</style></head><body><main><section><h1><?=$name?></h1><p>Your app is ready. Open your admin chat and describe what you want to build.</p></section></main></body></html>
