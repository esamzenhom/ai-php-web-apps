#!/usr/bin/env python3
"""Isolated end-to-end tests. Provider output is a synthetic fixture, not a paid API call.
Run: python3 application/tests/integration.py
Uses a new temporary checkout, local bind mounts and unique containers; removes
only that synthetic test stack after completion. Set KEEP_TEST_APP=1 to inspect.
"""
import http.cookiejar,json,os,re,subprocess,tempfile,time,urllib.request,urllib.error,uuid
from pathlib import Path
repo=Path(__file__).resolve().parents[2]
temp=Path(tempfile.mkdtemp(prefix='app-studio-tests-'));root=temp/'repo'
subprocess.run(['python3',str(repo/'application/bin/package.py'),'--clone',str(root)],check=True)
app=root/'application'
def dc(*args,input=None,check=True):
    return subprocess.run(['docker','compose',*args],cwd=app,input=input,capture_output=True,text=True,check=check).stdout
# Replace the transport only in this disposable test checkout. Decode, worker,
# proposal validation, auth, settings, backup, apply, and undo stay real.
p=app/'admin/app/Admin/Provider.php';source=p.read_text();marker="        $system='You are the build partner"
source=source.replace(marker,"        if (is_file(BASE_PATH.'/data/control/test-response.json')) return self::decode($provider,json_decode(file_get_contents(BASE_PATH.'/data/control/test-response.json'),true));\n"+marker);p.write_text(source)
log=open(temp/'startup.log','w')
try:
    try:
        subprocess.run([str(root/'application/bin/start.sh'),'--no-open','--quiet-code'],stdout=log,stderr=subprocess.STDOUT,check=True)
    except subprocess.CalledProcessError:
        startup=app/'data/private/startup.log'
        if startup.exists(): print('Startup diagnostics:\n'+'\n'.join(startup.read_text().splitlines()[-30:]),flush=True)
        raise
    config=json.loads(dc('config','--format','json'));url='http://localhost:'+str(config['services']['caddy']['ports'][0]['published'])
    running_before=dc('ps','-q')
    reopen=subprocess.run([str(root/'application/bin/start.sh'),'--no-open','--quiet-code'],capture_output=True,text=True,check=True)
    assert 'already running' in reopen.stdout and dc('ps','-q')==running_before
    print('PASS: running start reopens without recreating containers',flush=True)
    print('Test URL:',url,flush=True)
    assert (app/'admin/public/index.php').is_file() and (app/'website/public/index.php').is_file()
    assert not any((app/name).exists() for name in ['app','public','site','migrations'])
    print('PASS: generated app and admin have separate source folders',flush=True)
    class Browser:
        def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.path='/admin'
        def get(self,path):
            try:
                r=self.opener.open(url+path);return r.status,r.read().decode(),r.headers
            except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers
        def page(self):
            code,body,_=self.get(self.path);assert code==200
            self.csrf=re.search(r'name="csrf-token" content="([a-f0-9]+)"',body).group(1)
            return body
        def post(self,data,csrf=None):
            req=urllib.request.Request(url+self.path+'/api',data=json.dumps({**data,'_csrf':self.csrf if csrf is None else csrf}).encode(),headers={'Content-Type':'application/json'})
            try:r=self.opener.open(req);code=r.status;body=json.load(r)
            except urllib.error.HTTPError as e:code=e.code;body=json.load(e)
            if body.get('csrf'):self.csrf=body['csrf']
            return code,body
        def state(self):
            code,body,_=self.get(self.path+'/api');assert code==200,(code,body);d=json.loads(body);self.csrf=d['csrf'];return d
    owner=Browser();visitor=Browser();owner.page()
    assert visitor.get('/admin/api')[0]==401
    assert owner.post({'action':'setup'},csrf='bad')[0]==419
    pw='synthetic-test-password-2026'
    setup={'action':'setup','username':'test_owner','password':pw,'token':'wrong'}
    assert owner.post(setup)[0]==400
    setup['token']=(app/'data/control/setup-token').read_text().strip()
    assert owner.post(setup)[0]==200;owner.page();owner.state()
    assert visitor.get('/admin')[0]==200
    assert owner.post(setup)[0]==400
    print('PASS: first setup, single claim, private token, authentication and CSRF',flush=True)
    assert owner.state()['cli']['available'] is True
    assert owner.state()['cli']['connections']=={'codex':False,'claude':False}
    assert 'id="cli-form"' in owner.get('/admin/settings')[1]
    assert visitor.post({'action':'cli','operation':'login','provider':'codex'},csrf=owner.csrf)[0] in (401,419)
    assert owner.post({'action':'cli','operation':'login','provider':'codex','current_password':'wrong'})[0]==400
    assert owner.post({'action':'cli','operation':'poll','provider':'codex'})[0]==400
    assert owner.post({'action':'cli','operation':'login','provider':'bash','current_password':pw})[0]==400
    assert owner.post({'action':'chat','provider':'codex-cli','prompt':'Create a page','request_id':str(uuid.uuid4())})[0]==400
    print('PASS: CLI admin boundary, password check, session binding, command allowlist and signed-out chat rejection',flush=True)

    key='sk-synthetic-key-not-valid-for-provider'
    assert owner.post({'action':'provider','provider':'openai','model':'fixture-model','key':key,'current_password':pw})[0]==200
    assert key not in json.dumps(owner.state())
    encrypted=dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Store::db()->query('SELECT secret FROM providers')->fetchColumn();")
    assert key not in encrypted
    assert owner.post({'action':'preferences','floating':True})[0]==200
    assert 'id="open-chat"' in owner.get('/')[1]
    assert 'id="open-chat"' not in visitor.get('/')[1]
    assert json.loads(visitor.get('/_chat-status')[1])=={'enabled':False}
    assert json.loads(owner.get('/_chat-status')[1])=={'enabled':True}
    assert owner.post({'action':'preferences','floating':False})[0]==200
    assert json.loads(owner.get('/_chat-status')[1])=={'enabled':False}
    assert 'id="open-chat"' not in owner.get('/')[1]
    assert owner.post({'action':'preferences','floating':True})[0]==200
    signed_out=Browser();signed_out.page()
    assert signed_out.post({'action':'login','username':'test_owner','password':pw})[0]==200
    signed_out.page()
    assert json.loads(signed_out.get('/_chat-status')[1])=={'enabled':True}
    assert signed_out.post({'action':'logout'})[0]==200
    assert json.loads(signed_out.get('/_chat-status')[1])=={'enabled':False}
    assert 'id="open-chat"' not in signed_out.get('/')[1]
    assert 'sandbox allow-scripts' in owner.get('/_site/')[2]['Content-Security-Policy']
    print('PASS: encrypted key, masked state, floating chat permissions and page isolation',flush=True)
    second=Browser();second.page();assert second.post({'action':'login','username':'test_owner','password':pw})[0]==200
    assert owner.post({'action':'account','username':'new_owner','password':pw+'x','path':'/manage-test','current_password':pw})[0]==200
    pw+='x';owner.path='/manage-test';owner.page();second.path='/manage-test'
    assert second.get('/manage-test/api')[0]==401
    assert visitor.get('/admin')[0]==404
    assert owner.state()['settings']['username']=='new_owner'
    print('PASS: username/password/path changes, old path removal and other-session invalidation',flush=True)
    # Real queue + worker receives synthetic structured output from both adapters.
    original=(app/'website/public/index.php').read_text()
    proposal={'message':'I prepared a synthetic test page and a notes table.','impact':'Creates a test table and replaces the welcome heading.','files':[{'path':'public/index.php','content':'<?php echo "<!doctype html><html><head><title>Test</title></head><body>Fixture website applied</body></html>";'}, {'path':'migrations/002_test_notes.sql','content':'CREATE TABLE test_notes (id integer PRIMARY KEY, note text NOT NULL);'}]}
    response={'status':'completed','output':[{'type':'message','content':[{'type':'output_text','text':json.dumps(proposal)}]}]}
    (app/'data/control/test-response.json').write_text(json.dumps(response))
    request=str(uuid.uuid4());data={'action':'chat','provider':'openai','prompt':'Build a synthetic notes page','request_id':request}
    assert owner.post(data)[0]==200;assert owner.post(data)[0]==200
    def wait(status):
        for _ in range(90):
            state=owner.state();job=state['jobs'][0]
            if job['status']==status:return job
            if job['status']=='failed':raise AssertionError(job['error'])
            time.sleep(1)
        raise AssertionError('Timed out: '+job['status'])
    job=wait('review');assert len(owner.state()['jobs'])==1
    assert (app/'website/public/index.php').read_text()==original
    assert owner.post({'action':'apply','id':job['id'],'confirm':False,'current_password':pw})[0]==400
    assert owner.post({'action':'apply','id':job['id'],'confirm':True,'current_password':'bad'})[0]==400
    assert owner.post({'action':'apply','id':job['id'],'confirm':True,'current_password':pw})[0]==200
    wait('applied')
    for _ in range(10):
        if 'Fixture website applied' in visitor.get('/_site/')[1]: break
        time.sleep(1)
    else: raise AssertionError('The applied page was not visible through the public gateway.')
    assert (app/'backups'/('change-'+job['id'])/'database.dump.enc').stat().st_size>100
    assert owner.post({'action':'revert','id':job['id'],'confirm':True,'current_password':pw})[0]==200
    wait('reverted');assert (app/'website/public/index.php').read_text()==original
    assert not (app/'website/migrations/002_test_notes.sql').exists()
    print('PASS: queued provider fixture, duplicate prevention, explicit approval, live code + DDL apply, verified backup and undo',flush=True)
    # Test stale proposals preserve independent edits.
    request=str(uuid.uuid4());data['request_id']=request;assert owner.post(data)[0]==200
    job=wait('review');(app/'website/public/index.php').write_text(original+'\n<!-- independent edit -->')
    assert owner.post({'action':'apply','id':job['id'],'confirm':True,'current_password':pw})[0]==200
    for _ in range(30):
        if owner.state()['jobs'][0]['status']=='failed':break
        time.sleep(1)
    assert owner.state()['jobs'][0]['status']=='failed'
    assert 'independent edit' in (app/'website/public/index.php').read_text()
    (app/'website/public/index.php').write_text(original)
    print('PASS: stale proposals cannot overwrite newer work',flush=True)
    # Claude decoder and a conversational answer, without files.
    assert owner.post({'action':'provider','provider':'anthropic','model':'fixture-claude','key':'sk-ant-synthetic-test-key','current_password':pw})[0]==200
    answer={'message':'What would you like your app to do?','impact':'No changes.','files':[]}
    (app/'data/control/test-response.json').write_text(json.dumps({'stop_reason':'end_turn','content':[{'type':'text','text':json.dumps(answer)}]}))
    assert owner.post({'action':'chat','provider':'anthropic','prompt':'Help me plan','request_id':str(uuid.uuid4())})[0]==200
    wait('answered')
    assert owner.post({'action':'provider','provider':'anthropic','remove':True,'current_password':pw})[0]==200
    assert len(owner.state()['providers'])==1
    print('PASS: Claude structured response, chat-only answer and key removal',flush=True)
    # Simulate a crash after a backed-up proposal partially wrote a file.
    recover_job=next(j for j in owner.state()['jobs'] if j['status']=='reverted')
    (app/'website/public/index.php').write_text('<?php echo "Interrupted synthetic write";')
    (app/'data/control/maintenance').write_text(json.dumps({'id':recover_job['id'],'undo':False}))
    assert visitor.get('/_site/')[0]==503
    dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; App\\Admin\\Store::db()->prepare(\"UPDATE jobs SET status='applying' WHERE id=?\")->execute(["+json.dumps(recover_job['id'])+"]);")
    dc('restart','worker')
    for _ in range(30):
        if not (app/'data/control/maintenance').exists():break
        time.sleep(1)
    assert not (app/'data/control/maintenance').exists()
    assert (app/'website/public/index.php').read_text()==original
    print('PASS: interrupted change recovery restores files/database and removes the website pause',flush=True)
    # Runtime role cannot read admin filesystem, connect to core FPM, or create DB roles.
    out=dc('exec','-T','site','php','-r',"echo file_exists('/var/www/html/data/control/key') ? 'LEAK' : 'isolated';")
    assert out=='isolated'
    mounts=json.loads(subprocess.check_output(['docker','inspect',*dc('ps','-q').split()],text=True))
    assert all(m['Type']=='bind' for c in mounts for m in c['Mounts'])
    assert not subprocess.check_output(['docker','volume','ls','--filter','label=com.docker.compose.project='+config['name'],'-q']).strip()
    print('PASS: generated runtime has no admin key mount; every container mount is local; no Docker volumes',flush=True)
    print(dc('exec','-T','worker','php',input=(app/'tests/security.php').read_text()),flush=True)
    print(dc('exec','-T','worker','php','bin/backup.php'),flush=True)
    exec(compile((app/'tests/hardening.py').read_text(),str(app/'tests/hardening.py'),'exec'))
    visitor.path=owner.path;visitor.page()
    limited=False
    for _ in range(12):
        code,_=visitor.post({'action':'login','username':'new_owner','password':'incorrect-password'})
        if code==429:limited=True;break
    assert limited
    print('PASS: repeated failed sign-ins are rate limited',flush=True)
    # Restart preserves username, path, provider and conversation.
    dc('restart','php','worker');time.sleep(3);assert owner.state()['settings']['path']=='/manage-test'
    print('PASS: persistence across service restarts',flush=True)
    (temp/'result.json').write_text(json.dumps({'url':url,'root':str(root),'path':owner.path,'username':'new_owner','password':pw}))
    stopped=subprocess.run([str(root/'application/bin/stop.sh')],input='no\n',capture_output=True,text=True,check=True)
    assert 'Docker was left running' in stopped.stdout
    assert not dc('ps','--status','running','-q').strip()
    subprocess.run([str(root/'application/bin/start.sh'),'--no-open','--quiet-code'],stdout=log,stderr=subprocess.STDOUT,check=True)
    restarted_config=json.loads(dc('config','--format','json'))
    assert str(restarted_config['services']['caddy']['ports'][0]['published'])==url.rsplit(':',1)[1], 'Restart changed the local port'
    assert owner.state()['settings']['path']=='/manage-test'
    print('PASS: stop preserves data and Docker, restart preserves the admin account',flush=True)
    recovery_hash=__import__('hashlib').sha256((app/'data/recovery/keypair').read_bytes()).hexdigest()
    reset=subprocess.run([str(app/'bin/reset.sh')],input='reset\n',capture_output=True,text=True)
    assert reset.returncode==0,reset.stdout+reset.stderr
    assert __import__('hashlib').sha256((app/'data/recovery/keypair').read_bytes()).hexdigest()==recovery_hash
    archive=sorted((app/'backups').glob('reset-*'))[-1]
    assert {p.name for p in archive.iterdir()}=={'data.tar.enc','environment.enc'}
    assert not (app/'.env').exists() and not (app/'data/control/admin.sqlite').exists()
    assert {p.name for p in (app/'data').iterdir()}=={'recovery'}, 'Reset left active state behind'
    assert (app/'website/public/index.php').read_text()==original
    try:
        subprocess.run([str(app/'bin/start.sh'),'--no-open','--quiet-code'],stdout=log,stderr=subprocess.STDOUT,check=True)
    except subprocess.CalledProcessError:
        print('Reset/start diagnostics:\n'+'\n'.join((app/'data/private/startup.log').read_text().splitlines()[-30:]),flush=True)
        raise
    reset_listing=dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Backup::withFile(BASE_PATH.'/backups/"+archive.name+"/data.tar.enc',fn($p)=>App\\Admin\\Changes::command(['tar','-tf',$p]));")
    assert './recovery' not in reset_listing and './control/key' in reset_listing and './cli/' in reset_listing
    assert dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Store::settings()===null?'unconfigured':'configured';")=='unconfigured'
    print('PASS: reset archives are encrypted, exclude recovery key, preserve source and restart unconfigured',flush=True)
    print('All integration tests passed. Real paid provider requests were not made.',flush=True)
finally:
    log.close()
    if os.getenv('KEEP_TEST_APP')=='1': print('Synthetic test checkout retained at '+str(root),flush=True)
    else:
        dc('down','--remove-orphans',check=False)
        import shutil
        shutil.rmtree(temp)
