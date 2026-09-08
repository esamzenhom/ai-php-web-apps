<?php
declare(strict_types=1);
namespace App\Admin;
use App\Security;
use RuntimeException;
use Throwable;
final class Controller
{
    public static function handle(string $path): bool
    {
        $base=Store::path();
        if ($path!==$base && !str_starts_with($path,$base.'/')) return false;
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'self'; base-uri 'none'; form-action 'self'");
        header('X-Frame-Options: SAMEORIGIN');
        Auth::start();
        $action=substr($path,strlen($base));
        if ($action==='/api') {
            header('Content-Type: application/json');
            try {
                if ($_SERVER['REQUEST_METHOD']==='GET') { Auth::requireAdmin(); self::state(); }
                elseif ($_SERVER['REQUEST_METHOD']==='POST') {
                    if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) throw new RuntimeException('This request is too large.');
                    $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
                    if (!is_array($input) || !Security::verifyCsrf($input['_csrf']??null)) { http_response_code(419); throw new RuntimeException('This form has expired. Refresh the page and try again.'); }
                    self::action($input);
                } else { http_response_code(405); echo json_encode(['error'=>'Method not allowed.']); }
            } catch (Throwable $e) {
                if (http_response_code()<400) http_response_code(400);
                $message=$e instanceof RuntimeException && !$e instanceof \PDOException ? $e->getMessage() : 'The request could not be completed. Please refresh and try again.';
                echo json_encode(['error'=>$message]);
            }
            return true;
        }
        if ($action!=='' && $action!=='/settings') { http_response_code(404); echo 'Page not found.'; return true; }
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); return true; }
        $settings=Store::settings(); $signedIn=Auth::signedIn(); $csrf=Security::csrfToken();
        require BASE_PATH.'/admin/app/Views/admin.php';
        return true;
    }
    private static function state(): void
    {
        $settings=Store::settings(); $settings['totp_enabled']=!empty($settings['totp_secret']); unset($settings['password'],$settings['totp_secret'],$settings['totp_counter']);
        $providers=Store::db()->query('SELECT name,model FROM providers')->fetchAll();
        $cli=Cli::connections();
        foreach($cli['connections'] as $name=>$connected) if($connected) $providers[]=['name'=>$name.'-cli','model'=>'default'];
        $jobs=Store::db()->query('SELECT * FROM jobs ORDER BY created_at DESC, rowid DESC LIMIT 60')->fetchAll();
        foreach ($jobs as &$job) {
            $job['security_report']=json_decode($job['security_report']??'null',true);
            $proposal=json_decode($job['proposal']??'null',true);
            $job['changes']=$proposal['files']??[]; $job['impact']=$proposal['impact']??'';
            // Include the actual before/after content for a reviewable change.
            $before=json_decode($job['base']??'{}',true);
            foreach ($job['changes'] as &$file) $file['before']=$before[$file['path']]??null;
            unset($file,$job['base'],$job['proposal']);
        }
        echo json_encode(['settings'=>$settings,'providers'=>$providers,'jobs'=>$jobs,'cli'=>$cli,'csrf'=>Security::csrfToken()]);
    }
    private static function action(array $i): void
    {
        $action=$i['action']??'';
        if ($action==='setup') {
            Auth::throttle();
            if (Store::settings()) throw new RuntimeException('Setup is already complete. Please sign in.');
            $token=trim((string)($i['token']??'')); $expected=@file_get_contents(BASE_PATH.'/data/control/setup-token');
            if (!$expected || !hash_equals($expected,$token)) { Auth::failure(); throw new RuntimeException('Enter the setup code shown by the start script.'); }
            Auth::credentials((string)($i['username']??''),(string)($i['password']??''));
            Store::db()->prepare('INSERT INTO settings(id,username,password) VALUES(1,?,?)')->execute([$i['username'],Security::hashPassword($i['password'])]);
            Auth::loginSession(Store::settings());
            echo json_encode(['redirect'=>Store::path().'/settings']); return;
        }
        if ($action==='verify_totp') {
            if(!Totp::pending())throw new RuntimeException('Sign-in expired. Enter your username and password again.');
            Totp::consume(trim((string)($i['code']??'')));
            Store::db()->prepare('DELETE FROM attempts WHERE address=?')->execute([$_SERVER['REMOTE_ADDR']??'local']);
            Auth::loginSession(Store::settings(),true);echo json_encode(['redirect'=>Store::path()]);return;
        }
        if ($action==='login') {
            Auth::throttle(); $s=Store::settings();
            if (!$s || !hash_equals($s['username'],(string)($i['username']??'')) || !Security::verifyPassword((string)($i['password']??''),$s['password'])) { Auth::failure(); throw new RuntimeException('The username or password is incorrect.'); }
            if(!empty($s['totp_secret'])) {Totp::challenge($s);echo json_encode(['redirect'=>Store::path()]);return;}
            Store::db()->prepare('DELETE FROM attempts WHERE address=?')->execute([$_SERVER['REMOTE_ADDR']??'local']);
            Auth::loginSession($s); echo json_encode(['redirect'=>Store::path()]); return;
        }
        Auth::requireAdmin();
        if($action==='totp'){echo json_encode(Totp::action($i));return;}
        if($action==='recovery_export'){
            Auth::password((string)($i['current_password']??''));
            if(!empty(Store::settings()['totp_secret']))Totp::consume(trim((string)($i['code']??'')));
            $key=file_get_contents(BASE_PATH.'/data/recovery/keypair');
            if(strlen($key)!==SODIUM_CRYPTO_BOX_KEYPAIRBYTES)throw new RuntimeException('Recovery key unavailable. Run the Start launcher.');
            echo json_encode(['recovery_key'=>base64_encode($key),'format'=>'app-studio-recovery-v1']);return;
        }
        if (in_array($action,['chat','apply','revert'],true) && is_file(BASE_PATH.'/data/control/maintenance')) throw new RuntimeException('The website is paused for an update or recovery. Wait for it to finish; if the last change failed recovery, ask your assistant to inspect the private backup.');
        if ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); echo json_encode(['redirect'=>Store::path()]); return; }
        if ($action==='cli') {
            $provider=(string)($i['provider']??''); $operation=(string)($i['operation']??'');
            if(!in_array($provider,['codex','claude'],true) || !in_array($operation,['login','logout','poll','input','cancel'],true)) throw new RuntimeException('Choose a supported CLI action.');
            if(in_array($operation,['login','logout'],true)) Auth::password((string)($i['current_password']??''));
            $data=['action'=>$operation,'provider'=>$provider];
            if(!in_array($operation,['login','logout'],true)) {
                $id=$_SESSION['cli_logins'][$provider]??null;
                if(!$id) throw new RuntimeException('Start CLI sign-in from this admin session first.');
                $data['id']=$id;
            }
            if($operation==='input') $data['input']=(string)($i['input']??'');
            $result=Cli::request($data);
            if($operation==='login') { $_SESSION['cli_logins'][$provider]=$result['id']; unset($result['id']); }
            if($operation==='cancel' || $operation==='logout') unset($_SESSION['cli_logins'][$provider]);
            echo json_encode($result);return;
        }
        if ($action==='provider') {
            Auth::password((string)($i['current_password']??''));
            $provider=$i['provider']??'';
            if (!in_array($provider,['openai','anthropic'],true)) throw new RuntimeException('Choose OpenAI or Claude.');
            if (!empty($i['remove'])) Store::db()->prepare('DELETE FROM providers WHERE name=?')->execute([$provider]);
            else {
                $model=trim((string)($i['model']??'')); $key=trim((string)($i['key']??''));
                if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/D',$model)) throw new RuntimeException('Enter the model ID from your provider account.');
                if ($key!=='' && (strlen($key)<15 || strlen($key)>512 || preg_match('/\s/',$key))) throw new RuntimeException('Enter a valid API key, without spaces.');
                if ($key==='') { $s=Store::db()->prepare('SELECT secret FROM providers WHERE name=?'); $s->execute([$provider]); $secret=$s->fetchColumn(); if (!$secret) throw new RuntimeException('Add your API key first.'); }
                else $secret=Store::secret($key);
                Store::db()->prepare('INSERT INTO providers(name,secret,model) VALUES(?,?,?) ON CONFLICT(name) DO UPDATE SET secret=excluded.secret,model=excluded.model')->execute([$provider,$secret,$model]);
            }
        } elseif ($action==='preferences') {
            Store::db()->prepare('UPDATE settings SET floating=? WHERE id=1')->execute([!empty($i['floating'])?1:0]);
        } elseif ($action==='account') {
            Auth::password((string)($i['current_password']??''));
            $s=Store::settings(); $username=trim((string)($i['username']??'')); $password=(string)($i['password']??''); $path=trim((string)($i['path']??''));
            Auth::credentials($username,$password!==''?$password:'unchanged-password'); Auth::adminPath($path);
            Store::db()->prepare('UPDATE settings SET username=?,password=?,path=?,version=version+1 WHERE id=1')->execute([$username,$password!==''?Security::hashPassword($password):$s['password'],$path]);
            Auth::loginSession(Store::settings(),true); echo json_encode(['redirect'=>$path.'/settings']); return;
        } elseif ($action==='chat') {
            $heartbeat=(int)@file_get_contents(BASE_PATH.'/data/control/heartbeat');
            if ($heartbeat<time()-15) throw new RuntimeException('Your assistant is temporarily unavailable. Wait for the current request or restart the app and try again.');
            $prompt=trim((string)($i['prompt']??'')); $request=(string)($i['request_id']??'');
            if (preg_match('~(?:sk-(?:ant-)?[A-Za-z0-9_-]{20,}|-----BEGIN .*PRIVATE KEY)~',$prompt)) throw new RuntimeException('That message appears to contain a secret. Add API keys in Settings, not in chat.');
            if (strlen($prompt)<2 || strlen($prompt)>8000) throw new RuntimeException('Describe your request in 2–8,000 characters.');
            if (!preg_match('/^[a-f0-9-]{20,64}$/D',$request)) throw new RuntimeException('Refresh the chat and try again.');
            $db=Store::db(); $db->exec('BEGIN IMMEDIATE');
            try {
                $existing=$db->prepare('SELECT id FROM jobs WHERE request_id=?'); $existing->execute([$request]);
                if (!$existing->fetchColumn()) {
                    if ($db->query("SELECT COUNT(*) FROM jobs WHERE status IN ('queued','generating','applying','apply_queued','revert_queued','reverting')")->fetchColumn()) throw new RuntimeException('A change is still running. Wait for it to finish before sending another.');
                    $p=$db->prepare('SELECT model FROM providers WHERE name=?'); $p->execute([$i['provider']??'']); $model=$p->fetchColumn();
                    if($cliName=Cli::provider((string)($i['provider']??''))) {
                        if(!(Cli::connections()['connections'][$cliName]??false)) throw new RuntimeException('Connect this CLI in Settings first.');
                        $model='default';
                    }
                    if (!$model) throw new RuntimeException('Connect a CLI or add an API key in Settings first.');
                    $db->prepare("INSERT INTO jobs(id,request_id,prompt,provider,model,status) VALUES(?,?,?,?,?,'queued')")->execute([bin2hex(random_bytes(16)),$request,$prompt,$i['provider'],$model]);
                }
                $db->exec('COMMIT');
            } catch(Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        } elseif (in_array($action,['apply','dismiss','revert'],true)) {
            $job=Store::job((string)($i['id']??''));
            if ($action!=='dismiss') {
                Auth::password((string)($i['current_password']??''));
                if (($i['confirm']??false)!==true) throw new RuntimeException('Read and confirm the change warning first.');
            }
            $db=Store::db(); $db->exec('BEGIN IMMEDIATE');
            try {
                if ($db->query("SELECT COUNT(*) FROM jobs WHERE status IN ('queued','generating','applying','apply_queued','reverting','revert_queued')")->fetchColumn()) throw new RuntimeException('Wait for the current operation to finish.');
                $from=$action==='revert'?'applied':'review'; $to=['apply'=>'apply_queued','dismiss'=>'dismissed','revert'=>'revert_queued'][$action];
                $s=$db->prepare('UPDATE jobs SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=?'); $s->execute([$to,$job['id'],$from]);
                if (!$s->rowCount()) throw new RuntimeException('This change is no longer available for that action. Refresh the chat.');
                $db->exec('COMMIT');
            } catch(Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        } else throw new RuntimeException('Unknown action.');
        echo json_encode(['ok'=>true,'csrf'=>Security::csrfToken()]);
    }
}
