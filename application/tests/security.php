<?php
declare(strict_types=1);
require '/var/www/html/admin/app/bootstrap.php';
use App\Admin\Changes;
use App\Admin\Provider;
use App\Admin\Auth;
$checks=0;
function rejects(callable $fn):void { global $checks; try{$fn();}catch(Throwable $e){$checks++;return;}throw new RuntimeException('Unsafe input was accepted.'); }
foreach(['../app/Admin/Store.php','public/../../data/control/key','public/.env','public/a//b.php','migrations/bad.sql','app/foo.sql','public/a.php/../b.php'] as $path) rejects(fn()=>Changes::path($path));
foreach(['DELETE FROM users;','CREATE TABLE x(id int); COPY x TO PROGRAM \'id\';','DO $$ BEGIN END $$;','ALTER TABLE users OWNER TO app;'] as $sql) rejects(fn()=>Changes::sql($sql));
rejects(fn()=>Changes::validate(['files'=>[['path'=>'public/bad.php','content'=>'<?php invalid & syntax @@@']]],[]));
rejects(fn()=>Changes::validate(['files'=>[['path'=>'migrations/001_old.sql','content'=>'CREATE TABLE x (id int);']]],['migrations/001_old.sql'=>'CREATE TABLE old (id int);']));
rejects(fn()=>Provider::decode('openai',['status'=>'incomplete','output'=>[]]));
rejects(fn()=>Provider::decode('anthropic',['stop_reason'=>'max_tokens','content'=>[]]));
rejects(fn()=>Auth::adminPath('/health'));
rejects(fn()=>Auth::adminPath('//evil.example'));
$root=BASE_PATH.'/website/public/security-link.php';
if (file_exists($root)||is_link($root)) throw new RuntimeException('Test path already exists.');
symlink(BASE_PATH.'/data/control/key',$root);
try{rejects(fn()=>Changes::path('public/security-link.php'));}finally{unlink($root);}
$statement=Changes::sql('-- descriptive comment'."\n".'CREATE TABLE test_safe (id integer PRIMARY KEY);');
if(count($statement)!==1) throw new RuntimeException('Valid DDL rejected.');
echo "PASS: $checks security rejection checks plus valid DDL.\n";
