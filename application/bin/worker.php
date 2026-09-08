<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\Store;
use App\Admin\Changes;
use App\Admin\Provider;
use App\Admin\Cli;
$lock=fopen(BASE_PATH.'/data/control/worker.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit(0);
$db=Store::db();
$marker=BASE_PATH.'/data/control/maintenance';
if (is_file($marker)) {
    try {
        $interrupted=json_decode(file_get_contents($marker),true,32,JSON_THROW_ON_ERROR);
        Changes::recover(Store::job($interrupted['id']),(bool)$interrupted['undo']);
        unlink($marker);
    } catch(Throwable $e) { fwrite(STDERR,"Interrupted change needs recovery. Website remains paused.\n"); }
}
$db->exec("UPDATE jobs SET status='failed',error='The worker restarted. Any interrupted website change was recovered when possible; if the website is paused, ask your assistant to check the recovery copy. No paid request was retried automatically.' WHERE status IN ('generating','applying','reverting')");

while(true) {
    file_put_contents(BASE_PATH.'/data/control/heartbeat',(string)time());
    $statement=$db->query("SELECT * FROM jobs WHERE status IN ('queued','apply_queued','revert_queued') ORDER BY created_at,rowid LIMIT 1");
    $job=$statement->fetch();
    $statement->closeCursor();
    unset($statement);
    if (!$job) { sleep(1); continue; }
    try {
        if($job['status']==='queued') {
            Store::update($job['id'],'generating'); $base=Changes::snapshot();
            $p=$db->prepare('SELECT * FROM providers WHERE name=?'); $p->execute([$job['provider']]); $provider=$p->fetch(); $p->closeCursor(); unset($p);
            if(!$provider && !Cli::provider($job['provider'])) throw new RuntimeException('The provider key was removed. Add it in Settings before sending a new request.');
            $history=$db->query("SELECT prompt,response,status,error,security_report FROM jobs WHERE status IN ('applied','answered','review','reverted','failed') ORDER BY created_at DESC,rowid DESC LIMIT 8")->fetchAll();
            $guidance=[];
            foreach(array_merge(glob(BASE_PATH.'/guidance/*.md')?:[],glob(BASE_PATH.'/guidance/skills/*/SKILL.md')?:[]) as $file) $guidance[basename(dirname($file)).'/'.basename($file)]=file_get_contents($file);
            $context=json_encode(['shared_rules'=>$guidance,'website_files'=>$base,'recent_conversation'=>array_reverse($history),'owner_request'=>$job['prompt']],JSON_THROW_ON_ERROR);
            $proposal=Provider::request($job['provider'],Cli::provider($job['provider'])?'':Store::secret($provider['secret'],true),$job['model'],$context);
            Changes::validate($proposal,$base);
            if($proposal['files']) {
                $report=\App\Admin\Audit::scan($proposal,$base);
                $db->prepare('UPDATE jobs SET security_report=? WHERE id=?')->execute([json_encode($report,JSON_THROW_ON_ERROR),$job['id']]);
                \App\Admin\Audit::enforce($report);
            }
            $db->prepare('UPDATE jobs SET response=?,proposal=?,base=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$proposal['message'],json_encode($proposal,JSON_THROW_ON_ERROR),json_encode($base,JSON_THROW_ON_ERROR),$proposal['files']?'review':'answered',$job['id']]);
        } elseif($job['status']==='apply_queued') { Store::update($job['id'],'applying'); Changes::execute($job,false); Store::update($job['id'],'applied'); }
        else { Store::update($job['id'],'reverting'); Changes::execute($job,true); Store::update($job['id'],'reverted'); }
    } catch(Throwable $e) {
        $message=$e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'The operation failed validation. Nothing further was applied. Review the website and try a smaller request.';
        Store::update($job['id'],'failed',$message);
        fwrite(STDERR,'Builder job '.$job['id'].' failed; details are in the authenticated chat.' . "\n");
    }
}
