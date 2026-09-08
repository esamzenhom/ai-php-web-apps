<?php
declare(strict_types=1);
namespace App\Admin;
use PDO;
use RuntimeException;
use Throwable;
final class Changes
{
    public static function execute(array $job, bool $undo): void
    {
        $lock=fopen(BASE_PATH.'/data/control/site.lock','c');
        $deadline=time()+30;
        while (!flock($lock,LOCK_EX|LOCK_NB)) {
            if (time()>$deadline) throw new RuntimeException('A website request is still running. No change was applied; try again shortly.');
            usleep(100000);
        }
        $marker=BASE_PATH.'/data/control/maintenance';
        file_put_contents($marker,json_encode(['id'=>$job['id'],'undo'=>$undo]));
        try {
            if ($undo) self::revert($job); else self::apply($job);
            unlink($marker);
        } catch (Throwable $error) {
            try { self::recover($job,$undo); unlink($marker); }
            catch (Throwable $recoveryError) {
                throw new RuntimeException('Recovery needs attention. The website is paused to protect records. Keep the recovery folder and ask your assistant to restore it. Admin and chat remain accessible.');
            }
            throw $error;
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public static function recover(array $job, bool $undo): void
    {
        $dir=BASE_PATH.'/backups/change-'.$job['id'].($undo?'/before-undo':'');
        if (!Backup::exists($dir.'/files.json')) return; // No mutation starts before a verified backup.
        $files=json_decode(Backup::read($dir.'/files.json'),true,512,JSON_THROW_ON_ERROR);
        self::restoreDatabase($dir.'/database.dump');
        $proposal=json_decode($job['proposal'],true,32,JSON_THROW_ON_ERROR);
        foreach($proposal['files'] as $file) {
            if (array_key_exists($file['path'],$files)) self::write($file['path'],$files[$file['path']]);
            elseif (is_file(self::path($file['path']))) unlink(self::path($file['path']));
        }
    }
    public static function path(string $path): string
    {
        if (!preg_match('~^(public|app|migrations)/[a-zA-Z0-9_/-]+\.(php|html|css|js|json|svg|sql)$~D',$path) || str_contains($path,'..') || str_contains($path,'//')) throw new RuntimeException('The proposal includes a file outside the editable website.');
        if (str_starts_with($path,'app/') && !str_ends_with($path,'.php')) throw new RuntimeException('App files must be PHP.');
        if (str_ends_with($path,'.sql') !== str_starts_with($path,'migrations/')) throw new RuntimeException('SQL belongs only in schema migrations.');
        if (str_starts_with($path,'migrations/') && !preg_match('~^migrations/[0-9]{3,}_[a-z0-9_]+\.sql$~D',$path)) throw new RuntimeException('Use a numbered schema migration filename.');
        $root=BASE_PATH.'/website'; $current=$root;
        foreach(explode('/',$path) as $segment) { $current.='/'.$segment; if(is_link($current)) throw new RuntimeException('Symbolic links are not editable.'); }
        if (is_file($current) && stat($current)['nlink']>1) throw new RuntimeException('Hard-linked files are not editable.');
        return $current;
    }
    public static function snapshot(): array
    {
        $files=[]; $root=BASE_PATH.'/website/'; $size=0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative=substr($file->getPathname(),strlen($root));
            self::path($relative); if (!$file->isFile()) continue;
            $size+=$file->getSize();
            if ($file->getSize()>120000 || $size>500000 || count($files)>=150) throw new RuntimeException('The website exceeds this builder’s review limit (150 files / 500 KB). Split the project before continuing.');
            $content=file_get_contents($file->getPathname());
            if (preg_match('~(?:sk-(?:ant-)?[A-Za-z0-9_-]{20,}|-----BEGIN .*PRIVATE KEY)~',$content)) throw new RuntimeException('A possible hardcoded secret was found in the website. Remove it before sending source to an AI provider.');
            $files[$relative]=$content;
        }
        ksort($files); return $files;
    }
    public static function validate(array $proposal,array $base): void
    {
        if (count($proposal['files'])>12) throw new RuntimeException('Ask for a smaller change: at most 12 files can be reviewed at once.');
        $seen=[];
        foreach ($proposal['files'] as $file) {
            if (!is_array($file) || !is_string($file['path']??null) || !is_string($file['content']??null)) throw new RuntimeException('The proposal has an invalid file.');
            $path=$file['path']; self::path($path);
            if (isset($seen[$path])) throw new RuntimeException('The proposal repeats a file.'); $seen[$path]=true;
            if (strlen($file['content'])>120000 || str_contains($file['content'],"\0")) throw new RuntimeException('The proposal contains an oversized or binary file.');
            if (preg_match('~(?:sk-(?:ant-)?[A-Za-z0-9_-]{20,}|-----BEGIN .*PRIVATE KEY)~',$file['content'])) throw new RuntimeException('A possible secret was found in the proposed code. Remove it before continuing.');
            if (str_ends_with($path,'.php')) {
                $tmp=tempnam(BASE_PATH.'/data/control','lint-');
                try { file_put_contents($tmp,$file['content']); self::command([PHP_BINARY,'-l',$tmp]); } finally { unlink($tmp); }
            }
            if (str_starts_with($path,'migrations/')) {
                if (array_key_exists($path,$base)) throw new RuntimeException('Existing migrations cannot be rewritten. Ask for a new migration.');
                $existing=array_filter(array_keys($base),fn($p)=>str_starts_with($p,'migrations/'));
                if ($existing && strcmp($path,max($existing))<=0) throw new RuntimeException('Use the next numbered migration.');
                self::sql($file['content']);
            }
        }
    }
    public static function sql(string $sql): array
    {
        // Deliberately restricted DDL. No functions, procedures, DML or psql commands.
        $sql=preg_replace('~/\*.*?\*/|--[^\n]*~s','',$sql);
        if (str_contains($sql,'\\') || str_contains($sql,'$') || preg_match('/\b(?:COPY|PROGRAM|DO|FUNCTION|PROCEDURE|TRIGGER|INSERT|UPDATE|DELETE|TRUNCATE|GRANT|REVOKE|ROLE|OWNER|DATABASE|TABLESPACE|FOREIGN|SERVER|EXTENSION)\b/i',$sql)) throw new RuntimeException('This migration exceeds the supported schema-only operations. Ask for plain table/index changes.');
        $clean=preg_replace('~/\*.*?\*/|--[^\n]*~s','',$sql);
        $statements=array_filter(array_map('trim',explode(';',$clean)));
        foreach($statements as $statement) if (!preg_match('/^(?:CREATE\s+(?:TABLE|(?:UNIQUE\s+)?INDEX|TYPE)|ALTER\s+TABLE|DROP\s+(?:TABLE|INDEX|TYPE))\s/i',$statement)) throw new RuntimeException('Only table, index and type schema changes are supported.');
        if (!$statements) throw new RuntimeException('The migration is empty.'); return $statements;
    }
    public static function command(array $args,?string $output=null): string
    {
        $descriptors=[0=>['file','/dev/null','r'],1=>$output?['file',$output,'w']:['pipe','w'],2=>['pipe','w']];
        $env=['PATH'=>getenv('PATH'),'PGPASSWORD'=>getenv('DB_PASSWORD')?:''];
        $process=proc_open($args,$descriptors,$pipes,null,$env);
        if (!is_resource($process)) throw new RuntimeException('The validation or backup process could not start.');
        $stdout=$output?'':stream_get_contents($pipes[1]); if (!$output) fclose($pipes[1]);
        $stderr=stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('Validation or backup failed. No new change was applied. '.(str_contains($args[0],'php')?trim($stdout):'Check database availability.'));
        return $stdout;
    }
    private static function db(): PDO { return \App\Database::pdo(); }
    private static function backup(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Could not create a recovery folder.');
        $temp=Backup::temporary();
        try {
            self::command(['pg_dump','-h',getenv('DB_HOST')?:'postgres','-U',getenv('DB_USER')?:'app_migrator','-d',getenv('DB_NAME')?:'app','--schema=site','--no-owner','--format=custom','--lock-wait-timeout=10000'],$temp);
            $list=self::command(['pg_restore','--list',$temp]);
            if (!str_contains($list,'SCHEMA')) throw new RuntimeException('Database backup verification failed.');
            Backup::encryptFile($temp,$dir.'/database.dump.enc');
        } finally {unlink($temp);}

    }
    public static function apply(array $job): void
    {
        $proposal=json_decode($job['proposal'],true,32,JSON_THROW_ON_ERROR); $base=json_decode($job['base'],true,512,JSON_THROW_ON_ERROR);
        if (self::snapshot()!==$base) throw new RuntimeException('The website changed after this proposal was prepared. Ask for a fresh proposal so newer work is preserved.');
        self::validate($proposal,$base);
        Audit::enforce(Audit::scan($proposal,$base));
        $dir=BASE_PATH.'/backups/change-'.$job['id']; self::backup($dir);
        Backup::write($dir.'/files.json',json_encode($base,JSON_THROW_ON_ERROR));
        $pdo=self::db(); $pdo->beginTransaction();
        $written=[];
        try {
            $pdo->exec("SET LOCAL statement_timeout='15s'; SET LOCAL lock_timeout='5s'");
            $pdo->exec('CREATE TABLE IF NOT EXISTS site.builder_migrations (filename text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
            $migrations=array_filter($proposal['files'],fn($f)=>str_starts_with($f['path'],'migrations/'));
            usort($migrations,fn($a,$b)=>strcmp($a['path'],$b['path']));
            foreach($migrations as $file) {
                foreach(self::sql($file['content']) as $statement) $pdo->exec($statement);
                $pdo->prepare('INSERT INTO site.builder_migrations(filename) VALUES(?)')->execute([$file['path']]);
            }
            foreach($proposal['files'] as $file) { self::write($file['path'],$file['content']); $written[]=$file['path']; }
            \App\Database::restrictRuntime();
            $pdo->commit();
        } catch(Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach($written as $path) { if (array_key_exists($path,$base)) self::write($path,$base[$path]); else unlink(self::path($path)); }
            throw new RuntimeException('The change failed and was rolled back. The previous files and database schema were preserved. Ask for a corrected proposal.');
        }
    }
    public static function revert(array $job): void
    {
        $base=json_decode($job['base'],true,512,JSON_THROW_ON_ERROR); $proposal=json_decode($job['proposal'],true,32,JSON_THROW_ON_ERROR);
        $expected=$base; foreach($proposal['files'] as $file) $expected[$file['path']]=$file['content']; ksort($expected);
        if (self::snapshot()!==$expected) throw new RuntimeException('Later edits exist. Automatic undo would overwrite them; ask for a new change instead.');
        $dir=BASE_PATH.'/backups/change-'.$job['id'];
        self::backup($dir.'/before-undo');
        Backup::write($dir.'/before-undo/files.json',json_encode($expected,JSON_THROW_ON_ERROR));
        // Undo schema and rows together only after explicit data-loss warning/approval.
        self::restoreDatabase($dir.'/database.dump');
        foreach($proposal['files'] as $file) { $p=$file['path']; if (array_key_exists($p,$base)) self::write($p,$base[$p]); else unlink(self::path($p)); }
    }
    public static function restoreDatabase(string $dump): void
    {
        Backup::withFile($dump,static function($dump): void {
        $sql=tempnam(BASE_PATH.'/data/control','restore-');
        try {
            self::command(['pg_restore','--no-owner','--no-privileges','--file=-',$dump],$sql);
            self::command(['psql','-h',getenv('DB_HOST')?:'postgres','-U',getenv('DB_USER')?:'app_migrator','-d',getenv('DB_NAME')?:'app','--no-psqlrc','--single-transaction','-v','ON_ERROR_STOP=1','-c','DROP SCHEMA site CASCADE','-f',$sql]);
            \App\Database::restrictRuntime();
        } finally { if (is_file($sql)) unlink($sql); }
        });
    }
    private static function write(string $path,string $content): void
    {
        $full=self::path($path); $parent=dirname($full);
        if (!is_dir($parent) && !mkdir($parent,0755,true)) throw new RuntimeException('Could not create the website folder.');
        $tmp=tempnam($parent,'.change-');
        if (!$tmp || file_put_contents($tmp,$content)===false) throw new RuntimeException('Could not save the changed file.');
        chmod($tmp,0644);
        if (!rename($tmp,$full)) { unlink($tmp); throw new RuntimeException('Could not publish the changed file.'); }
    }
}
