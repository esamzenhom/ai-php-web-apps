#!/usr/bin/env python3
"""Idempotent local identity and secret setup. Never prints secrets."""
from pathlib import Path
import os,re,secrets,socket,subprocess
root=Path(__file__).resolve().parents[1]
subprocess.run(['python3', str(root/'bin/init-website.py')], check=True)
path=root/'.env'
new=not path.exists()
text=(root/'.env.example').read_text() if new else path.read_text()
def get(key):
    m=re.search(r'^'+re.escape(key)+r'=(.*)$',text,re.M)
    return m.group(1).split(' #',1)[0].strip().strip('"\'') if m else ''
def put(key,value):
    global text
    pattern=r'^'+re.escape(key)+r'=.*$'
    text=re.sub(pattern,key+'='+value,text,flags=re.M) if re.search(pattern,text,re.M) else text+'\n'+key+'='+value+'\n'
if new or not get('COMPOSE_PROJECT_NAME'):
    slug=re.sub('[^a-z0-9]+','-',root.parent.name.lower()).strip('-')[:40] or 'app'
    put('COMPOSE_PROJECT_NAME',slug+'-'+secrets.token_hex(3))
own_web=False
try:
    own_web=bool(subprocess.check_output(['docker','ps','--filter','label=com.docker.compose.project='+get('COMPOSE_PROJECT_NAME'),'--filter','label=com.docker.compose.service=caddy','--format','{{.ID}}'],stderr=subprocess.DEVNULL).strip())
except (OSError,subprocess.CalledProcessError): pass
port_busy=False
if get('HTTP_PORT') and not own_web:
    with socket.socket() as probe:
        probe.setsockopt(socket.SOL_SOCKET,socket.SO_REUSEADDR,1)
        try: probe.bind(('127.0.0.1',int(get('HTTP_PORT'))))
        except OSError: port_busy=True
if new or not get('HTTP_PORT') or port_busy:
    for port in range(8080,65535):
        with socket.socket() as s:
            s.setsockopt(socket.SOL_SOCKET,socket.SO_REUSEADDR,1)
            try: s.bind(('127.0.0.1',port)); break
            except OSError: continue
    put('HTTP_PORT',str(port))
    if port_busy: print('The previous port is in use. This app will use local port '+str(port)+'.')
for key in ['POSTGRES_PASSWORD','SITE_DB_PASSWORD','CLI_SERVICE_TOKEN','MIGRATION_DB_PASSWORD','AUDIT_SERVICE_TOKEN']:
    if not get(key) or get(key).startswith('change-me'): put(key,secrets.token_hex(32))
for key,value in [('APP_UID',os.environ.get('SUDO_UID') or os.getuid() or 1000),('APP_GID',os.environ.get('SUDO_GID') or os.getgid() or 1000)]:
    if not get(key): put(key,str(value))
if not get('HTTP_BIND'): put('HTTP_BIND','127.0.0.1')
if not path.exists() or path.read_text()!=text:
    path.write_text(text); path.chmod(0o600)
print('Local configuration is ready. Existing app settings and data were preserved.')
