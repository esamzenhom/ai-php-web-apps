"""Run in a disposable CLI container with a temporary /state bind mount.
Real executables are checked, then fake CLI processes exercise the adapter only.
Never supplies credentials or makes model requests.
"""
import importlib.util,json,os,subprocess,time
from pathlib import Path
spec=importlib.util.spec_from_file_location('runner','/opt/runner/server.py')
r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)
for executable,args in [('codex',['exec','--help']),('claude',['--help'])]:
    result=subprocess.run([executable,*args],env=r.ENV,capture_output=True,text=True,check=True)
    for flag in (['--ephemeral','--output-schema','--sandbox'] if executable=='codex' else ['--restricted','--tools','--json-schema','--no-session-persistence']):assert flag in result.stdout,flag
assert r.probe('codex') is False and r.probe('claude') is False
print('PASS: installed CLI flags and signed-out account status',flush=True)
bin_dir=Path('/state/fake-bin');bin_dir.mkdir()
script='''#!/usr/bin/python3
import json,sys,time
from pathlib import Path
args=sys.argv[1:];provider=Path(sys.argv[0]).name
marker=Path('/state')/(provider+'-connected')
if ('status' in args):
 print(json.dumps({'loggedIn':marker.exists()}));sys.exit(0 if marker.exists() else 1)
if ('logout' in args):marker.unlink(missing_ok=True);sys.exit(0)
if ('login' in args):
 print('Open https://auth.openai.com/device or https://claude.ai/oauth/authorize and enter code TEST-ONLY',flush=True)
 if provider=='claude':
  code=input()
  if code!='test-code':sys.exit(1)
 else:time.sleep(.5)
 marker.write_text('synthetic');sys.exit(0)
text=sys.stdin.read()
assert 'CLI_SERVICE_TOKEN' not in __import__('os').environ
proposal={'message':'Synthetic CLI answer','impact':'None','files':[]}
if '--output-last-message' in args:Path(args[args.index('--output-last-message')+1]).write_text(json.dumps(proposal))
else:print(json.dumps({'is_error':False,'structured_output':proposal}))
'''
for name in ['codex','claude']:
    p=bin_dir/name;p.write_text(script);p.chmod(0o700)
r.ENV['PATH']=str(bin_dir)+':'+r.ENV['PATH']
for provider in ['codex','claude']:
    login=r.dispatch({'action':'login','provider':provider});sid=login['id']
    if provider=='claude':
        for _ in range(100):
            view=r.dispatch({'action':'poll','provider':provider,'id':sid})
            if 'TEST-ONLY' in view['output']:break
            time.sleep(.05)
        r.dispatch({'action':'input','provider':provider,'id':sid,'input':'test-code'})
    for _ in range(100):
        result=r.dispatch({'action':'poll','provider':provider,'id':sid})
        if result['state']!='waiting':break
        time.sleep(.05)
    assert result['state']=='connected',result
    assert r.STATUS[provider]
    answer=r.generate({'provider':provider,'schema':{'type':'object'},'system':'Return JSON','context':'Test','model':'default'})
    assert answer['proposal']['message']=='Synthetic CLI answer'
    r.dispatch({'action':'logout','provider':provider})
    assert not r.STATUS[provider]
print('PASS: login output, Claude code input, connected status, both generation adapters and logout')
for action in [{'action':'login','provider':'sh'},{'action':'input','provider':'claude','id':'unknown','input':'bad'}]:
    try:r.dispatch(action)
    except ValueError:pass
    else:raise AssertionError('Unsafe action accepted')
assert '[redacted]' in r.clean('sk-'+('a'*40))
login=r.dispatch({'action':'login','provider':'claude'})
try:
    r.dispatch({'action':'login','provider':'claude'})
    raise AssertionError('Duplicate login accepted')
except ValueError:pass
r.dispatch({'action':'cancel','provider':'claude','id':login['id']})
for _ in range(100):
    if 'claude' not in r.BUSY:break
    time.sleep(.05)
assert 'claude' not in r.BUSY
print('PASS: command allowlist, session binding, output redaction, duplicate login and cancellation')
