#!/usr/bin/env python3
import json,subprocess,sys,time,urllib.request,webbrowser
from pathlib import Path
def announce(message):
    if '--browser-target' not in sys.argv: print(message)
if '--if-running' in sys.argv and not Path('.env').is_file(): sys.exit(1)
config=json.loads(subprocess.check_output(['docker','compose','config','--format','json']))
port=config['services']['caddy']['ports'][0]['published']; url='http://localhost:'+str(port)
if '--if-running' in sys.argv:
    expected=set(config['services'])
    try:
        raw=subprocess.check_output(['docker','compose','ps','--format','json'],stderr=subprocess.DEVNULL).decode().strip()
        rows=json.loads(raw) if raw.startswith('[') else [json.loads(line) for line in raw.splitlines()]
        ready={r['Service'] for r in rows if r.get('State')=='running' and r.get('Health','') in ('','healthy')}
        if not expected.issubset(ready): sys.exit(1)
    except (ValueError,subprocess.CalledProcessError): sys.exit(1)
for attempt in range(1 if '--if-running' in sys.argv else 40):
    try:
        with urllib.request.urlopen(url+'/health',timeout=2) as r:
            if json.load(r).get('status')=='ok': break
    except Exception:
        if '--if-running' in sys.argv: sys.exit(1)
        time.sleep(1)
else: sys.exit('The app did not become ready. Run make logs inside application and ask your assistant for help.')
announce(('Your app is already running: ' if '--if-running' in sys.argv else 'Your app is ready: ')+url)
state=json.loads(subprocess.check_output(['docker','compose','exec','-T','php','php','bin/status.php']))
if not state['configured']:
    code=Path('data/control/setup-token').read_text().strip()
    if '--quiet-code' not in sys.argv: announce('First setup code (keep private): '+code)
    target=url+('/admin' if '--if-running' in sys.argv or '--browser-target' in sys.argv else '/')+'#setup='+code
    announce('Create your admin account on the setup page, then add an AI provider in Settings.')
else:
    target=url+state['path']
    announce('Your admin workspace: '+target)
if '--browser-target' in sys.argv:
    print(json.dumps({'url':target}))
elif '--no-open' not in sys.argv: webbrowser.open(target)
