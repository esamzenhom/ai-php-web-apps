"""Executed inside integration.py's disposable fixture, never against owner data."""
import base64,hashlib,hmac,struct
# RFC 6238 SHA1 vector, six-digit variant.
assert dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',1);")=='287082'
code,result=owner.post({'action':'totp','operation':'begin','current_password':pw});assert code==200,result
secret=result['secret'];counter=int(time.time())//30
raw=hmac.new(base64.b32decode(secret),struct.pack('>Q',counter),hashlib.sha1).digest();offset=raw[-1]&15
otp=str((struct.unpack('>I',raw[offset:offset+4])[0]&0x7fffffff)%1000000).zfill(6)
code,result=owner.post({'action':'totp','operation':'enable','current_password':pw,'code':otp});assert code==200,result
codes=result['recovery_codes'];assert len(set(codes))==10
assert owner.state()['settings']['totp_enabled'] is True
assert secret not in json.dumps(owner.state())
assert owner.post({'action':'recovery_export','current_password':'wrong'})[0]==400
challenger=Browser();challenger.path=owner.path;challenger.page()
assert challenger.post({'action':'login','username':'new_owner','password':pw})[0]==200
assert challenger.get(owner.path+'/api')[0]==401
assert json.loads(challenger.get('/_chat-status')[1])=={'enabled':False}
assert 'verify_totp' in challenger.page()
assert challenger.post({'action':'verify_totp','code':otp})[0]==400 # consumed enrollment code
assert challenger.post({'action':'verify_totp','code':codes[0]})[0]==200
challenger.page();assert challenger.state()['settings']['totp_enabled']
retry=Browser();retry.path=owner.path;retry.page()
assert retry.post({'action':'login','username':'new_owner','password':pw})[0]==200;retry.page()
assert retry.post({'action':'verify_totp','code':codes[0]})[0]==400
assert retry.post({'action':'verify_totp','code':codes[1]})[0]==200
code,result=owner.post({'action':'recovery_export','current_password':pw,'code':codes[2]});assert code==200,result
assert len(base64.b64decode(result['recovery_key']))==64
recovery_pair=(app/'data/recovery/keypair').read_bytes()
(app/'data/control/recovery-import.json').write_text(json.dumps(result))
assert 'imported' in dc('run','--rm','--no-deps','prepare','php','bin/recovery.php','import')
assert not (app/'data/control/recovery-import.json').exists()
assert (app/'data/recovery/keypair').read_bytes()==recovery_pair
full_file=sorted((app/'backups').glob('full-*/files.json.enc'))[-1]
assert 'Verified private recovery file' in dc('run','--rm','--no-deps','prepare','php','bin/recovery.php','decrypt',str(full_file.relative_to(app)))
dc('run','--rm','--no-deps','prepare')
assert json.loads((app/'data/control/recovered/files.json').read_text())['public/index.php']==original
assert 'already exists' in subprocess.run(['docker','compose','run','--rm','--no-deps','prepare','php','bin/recovery.php','decrypt',str(full_file.relative_to(app))],cwd=app,capture_output=True,text=True).stderr
print('PASS: matching recovery-key import and authenticated decrypt-only recovery without overwriting files',flush=True)

assert owner.post({'action':'totp','operation':'disable','current_password':pw,'code':codes[3],'confirm':False})[0]==400
assert owner.post({'action':'totp','operation':'disable','current_password':pw,'code':codes[3],'confirm':True})[0]==200
assert not owner.state()['settings']['totp_enabled']
assert challenger.get(owner.path+'/api')[0]==401
print('PASS: TOTP vector, enrollment, password-only denial, replay prevention, one-use recovery codes, key export and session invalidation',flush=True)
# Upgrade a legacy runtime-owned table without replacing its rows or identity.
dc('exec','-T','postgres','sh','-c','psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"',input="CREATE TABLE site.legacy_fixture(id integer PRIMARY KEY,value text); INSERT INTO site.legacy_fixture VALUES(7,'keep'); ALTER TABLE site.legacy_fixture OWNER TO app_runtime;")
legacy_oid=dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Database::pdo()->query(\"SELECT 'site.legacy_fixture'::regclass::oid\")->fetchColumn();")
dc('exec','-T','postgres','sh','/docker-entrypoint-initdb.d/10-site.sh')
assert legacy_oid==dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Database::pdo()->query(\"SELECT 'site.legacy_fixture'::regclass::oid\")->fetchColumn();")
assert dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Database::pdo()->query('SELECT value FROM site.legacy_fixture WHERE id=7')->fetchColumn();")=='keep'
dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; App\\Database::pdo()->exec('DROP TABLE site.legacy_fixture');")
print('PASS: legacy role upgrade preserves table identity and existing rows',flush=True)
# Actual site PDO privileges, including ordinary CRUD and denied schema/role operations.
program=r'''<?php
$p=new PDO('pgsql:host=postgres;dbname='.getenv('DB_NAME'),getenv('DB_USER'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec("INSERT INTO site.security_fixture VALUES (1,'preserved')");
if($p->query('SELECT value FROM site.security_fixture WHERE id=1')->fetchColumn()!=='preserved')throw new Exception('CRUD failed');
foreach(['CREATE TABLE site.forbidden(id int)','ALTER TABLE site.security_fixture ADD bad int','DROP TABLE site.security_fixture','TRUNCATE site.security_fixture','CREATE SCHEMA forbidden','CREATE TEMP TABLE forbidden_temp(id int)','CREATE ROLE forbidden','SET ROLE app_migrator','DELETE FROM site.builder_migrations'] as $sql){
 try{$p->exec($sql);}catch(PDOException $e){continue;}throw new Exception('Privilege escape: '.$sql);
}
$p->exec("UPDATE site.security_fixture SET value='updated' WHERE id=1");$p->exec('DELETE FROM site.security_fixture WHERE id=1');echo 'PASS: runtime CRUD works; DDL, truncate, role escalation and migration metadata writes denied';
'''
dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; App\\Database::pdo()->exec('CREATE TABLE security_fixture(id int PRIMARY KEY,value text)');")
print(dc('exec','-T','site','php',input=program),flush=True)
dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; App\\Database::pdo()->exec('DROP TABLE security_fixture');")
# Authenticated scanner, suppression bypass and representative PHP/JS hazards.
scan=r'''<?php
require 'admin/app/bootstrap.php';
use App\Admin\Audit;
$bad=[
 'public/test.php'=>"<?php eval(\$_GET['x']); // nosemgrep\n",
 'app/query.php'=>"<?php \$pdo->query('SELECT * FROM users WHERE id='.\$_GET['id']);",
 'app/xss.php'=>"<?php echo \$_POST['x'];",
 'app/path.php'=>"<?php include \$_GET['path'];",
 'public/test.js'=>'document.body.innerHTML = location.hash;',
 'public/test.html'=>'<svg/onload=alert(1)></svg>',
];
foreach($bad as $path=>$source){$r=Audit::scan(['files'=>[['path'=>$path,'content'=>$source]]],[]);if($r['passed'])throw new Exception('Scanner missed '.$path);}
$r=Audit::scan(['files'=>[['path'=>'public/safe.php','content'=>"<?php \$only=true; echo htmlspecialchars(\$_GET['x'],ENT_QUOTES,'UTF-8'); \$s=\$pdo->prepare('SELECT * FROM users WHERE id=?'); \$s->execute([\$_GET['id']]);"]]],[]);
if(!$r['passed'])throw new Exception('Safe code incorrectly blocked');echo 'PASS: offline audit detects execution, SQL injection, XSS and unsafe paths; ignores suppression comments; accepts escaped/prepared code';
'''
print(dc('exec','-T','worker','php',input=scan),flush=True)
# A scanner outage must block apply BEFORE backups, source edits or SQL.
dc('stop','audit')
try:
    guard=r'''<?php
require 'admin/app/bootstrap.php';use App\Admin\Changes;
$base=Changes::snapshot();$job=['id'=>'scanner-outage','base'=>json_encode($base),'proposal'=>json_encode(['files'=>[['path'=>'public/index.php','content'=>'<?php echo "must not apply";']]])];
try{Changes::apply($job);}catch(RuntimeException $e){if(Changes::snapshot()!==$base)throw new Exception('Changed while scanner down');echo 'PASS: scanner outage blocks apply without modifying source';exit;}
throw new Exception('Scanner outage did not block');
'''
    print(dc('exec','-T','worker','php',input=guard),flush=True)
finally:dc('start','audit')
print(dc('exec','-T','worker','php',input=(app/'tests/backup-security.php').read_text()),flush=True)

full=sorted((app/'backups').glob('full-*'))[-1]
assert len(list(full.iterdir()))==5 and all(p.suffix=='.enc' for p in full.iterdir())
legacy=app/'backups'/'legacy-fixture';legacy.mkdir();(legacy/'key').write_text('synthetic-legacy-secret');(legacy/'old-cli-link').symlink_to('/not-a-real-target')
print(dc('exec','-T','worker','php','bin/encrypt-backups.php'),flush=True)
assert not (legacy/'old-cli-link').is_symlink() and (legacy/'old-cli-link.symlink.enc').exists()
assert dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Backup::read(BASE_PATH.'/backups/legacy-fixture/old-cli-link.symlink');")=='/not-a-real-target'
assert not (legacy/'key').exists() and (legacy/'key.enc').read_bytes()[:8]==b'STBACK01'
assert 'encrypted 0' in dc('exec','-T','worker','php','bin/encrypt-backups.php')
assert dc('exec','-T','worker','php','-r',"require 'admin/app/bootstrap.php'; echo App\\Admin\\Backup::read(BASE_PATH.'/backups/legacy-fixture/key');")=='synthetic-legacy-secret'
print('PASS: full backup payloads encrypted; legacy conversion verified and idempotent',flush=True)
unsafe={'message':'Unsafe synthetic test','impact':'Must be blocked','files':[{'path':'public/index.php','content':'<?php eval($_GET["x"]);'}]}
(app/'data/control/test-response.json').write_text(json.dumps({'status':'completed','output':[{'type':'message','content':[{'type':'output_text','text':json.dumps(unsafe)}]}]}))
assert owner.post({'action':'chat','provider':'openai','prompt':'Synthetic unsafe proposal','request_id':str(uuid.uuid4())})[0]==200
for _ in range(90):
    blocked=owner.state()['jobs'][0]
    if blocked['status']=='failed':break
    time.sleep(1)
assert blocked['status']=='failed' and blocked['security_report']['passed'] is False
assert (app/'website/public/index.php').read_text()==original
print('PASS: unsafe provider output blocked before review, report saved and website unchanged',flush=True)
# Missing local key must not silently rotate and strand existing encrypted backups.
recovery_dir=app/'data/recovery'
for name in ['keypair','public.key']:(recovery_dir/name).rename(recovery_dir/(name+'.saved'))
try:
    missing=subprocess.run(['docker','compose','run','--rm','--no-deps','prepare'],cwd=app,capture_output=True,text=True)
    assert missing.returncode!=0 and 'Import their recovery key' in missing.stderr
finally:
    for name in ['keypair','public.key']:(recovery_dir/(name+'.saved')).rename(recovery_dir/name)
assert 'Verified private recovery folder' in dc('run','--rm','--no-deps','prepare','php','bin/recovery.php','decrypt-folder',str(full.relative_to(app)))
dc('run','--rm','--no-deps','prepare')
recovered=app/'data/control/recovered'/full.name
assert {p.name for p in recovered.iterdir()}=={'database.dump','admin.sqlite','key','files.json','site-data.tar'}
assert json.loads((recovered/'files.json').read_text())['public/index.php']==original
print('PASS: missing key stops startup; complete backup folder decrypts without overwriting live state',flush=True)
