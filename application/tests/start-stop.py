#!/usr/bin/env python3
"""Exercise browser routing and installer branches without installing host software."""
import contextlib,io,json,os,runpy,subprocess,sys,tempfile
from pathlib import Path
from unittest.mock import patch
app=Path(__file__).resolve().parents[1]

with tempfile.TemporaryDirectory() as temporary:
    os.chdir(temporary)
    Path('.env').write_text('synthetic')
    Path('data/control').mkdir(parents=True)
    Path('data/control/setup-token').write_text('synthetic-setup-code')
    opened=[]
    configured=True
    healthy=True
    def command(args,**kwargs):
        if 'config' in args:return json.dumps({'services':{'caddy':{'ports':[{'published':12345}]},'php':{}}}).encode()
        if 'ps' in args:return json.dumps([{'Service':s,'State':'running' if healthy else 'exited','Health':''} for s in ['caddy','php']]).encode()
        if 'exec' in args:return json.dumps({'configured':configured,'path':'/my-admin'}).encode()
        raise AssertionError(args)
    def execute(extra=()):
        output=io.StringIO()
        with patch.object(sys,'argv',['open-app.py','--if-running','--quiet-code',*extra]), patch('subprocess.check_output',command), patch('urllib.request.urlopen',lambda *a,**k:contextlib.closing(io.BytesIO(b'{"status":"ok"}'))), patch('webbrowser.open',lambda url:opened.append(url)), contextlib.redirect_stdout(output):
            runpy.run_path(str(app/'bin/open-app.py'))
        return output.getvalue()
    execute();assert opened.pop()=='http://localhost:12345/my-admin'
    configured=False;execute();assert opened.pop()=='http://localhost:12345/admin#setup=synthetic-setup-code'
    payload=json.loads(execute(['--browser-target']))
    assert payload['url']=='http://localhost:12345/admin#setup=synthetic-setup-code' and not opened
    healthy=False
    try:execute();raise AssertionError('Stopped app accepted')
    except SystemExit as error:assert error.code==1
    assert not opened

# Every installer command that could change system software is replaced here.
mock=r'''
set -euo pipefail
command(){ if [[ "$*" == '-v docker' ]]; then return 1; fi; builtin command "$@"; }
uname(){ if [[ "${1:-}" == -m ]]; then echo arm64; else echo "$TEST_OS"; fi; }
docker(){ return 0; }
id(){ echo 1000; }
sudo(){ printf 'sudo %s\n' "$*"; }
curl(){ printf 'curl %s\n' "$*"; }
hdiutil(){ printf 'hdiutil %s\n' "$*"; }
apt-get(){ printf 'apt-get %s\n' "$*"; }
install(){ printf 'install %s\n' "$*"; }
chmod(){ :; }
dpkg(){ echo arm64; }
source(){ if [[ "$1" == /etc/os-release ]]; then ID=ubuntu; VERSION_CODENAME=noble; else builtin source "$@"; fi; }
builtin source bin/ensure-docker.sh
'''
# Linux uses dot to read os-release; replace that one operation in a private fixture.
with tempfile.TemporaryDirectory() as temporary:
    root=Path(temporary);(root/'bin').mkdir()
    (root/'bin/ensure-docker.sh').write_text((app/'bin/ensure-docker.sh').read_text().replace('. /etc/os-release','source /etc/os-release'))
    (root/'bin/docker-access.sh').write_text((app/'bin/docker-access.sh').read_text())
    for platform in ['Darwin','Linux']:
        result=subprocess.run(['bash','-c',mock],cwd=root,env={**os.environ,'TEST_OS':platform},capture_output=True,text=True,check=True)
        if platform=='Darwin':assert 'https://desktop.docker.com/mac/main/arm64/Docker.dmg' in result.stdout and 'MacOS/install' in result.stdout
        else:assert 'docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin' in result.stdout
print('PASS: custom admin reopening, private setup link, stopped-app rejection, mocked macOS and Ubuntu installation')

# Confirming Docker shutdown is tested with a fake daemon, never the owner's Docker.
with tempfile.TemporaryDirectory() as temporary:
    root=Path(temporary);(root/'application/bin').mkdir(parents=True)
    (root/'application/.env').write_text('synthetic')
    (root/'application/bin/docker-access.sh').write_text('true\n')
    (root/'application/bin/stop.sh').write_text((app/'bin/stop.sh').read_text())
    mock_stop=r'''
set -euo pipefail
unset DOCKER_HOST
docker(){
 case "$*" in
  info) [[ ! -f daemon-stopped ]] ;;
  'compose stop') echo app-stopped ;;
  'context show') echo desktop-linux ;;
  *) echo 'Unexpected command' >&2; return 1 ;;
 esac
}
uname(){ echo Darwin; }
osascript(){ touch daemon-stopped; }
source "$0"
'''
    for reply,expected in [('',False),('no\n',False),('yes\n',True)]:
        result=subprocess.run(['bash','-c',mock_stop,str(root/'application/bin/stop.sh')],input=reply,text=True,capture_output=True,check=True)
        assert 'app-stopped' in result.stdout
        assert (root/'application/daemon-stopped').exists()==expected
print('PASS: stop leaves Docker running unless the user explicitly confirms yes')

with tempfile.TemporaryDirectory() as temporary:
    root=Path(temporary)/'App with spaces';(root/'application/bin').mkdir(parents=True)
    for platform,extension in [('macOS','command'),('Linux','sh')]:
        (root/'Start and Stop'/platform).mkdir(parents=True)
        for action in ['start','stop']:
            relative=Path('Start and Stop')/platform/(action.title()+'.'+extension)
            wrapper=root/relative
            wrapper.write_text((app.parent/relative).read_text())
            (root/'application/bin'/(action+'.sh')).write_text('printf "%s|%s\\n" "$PWD" "$*"; exit 3\n')
            result=subprocess.run(['bash',str(wrapper),'--no-open'],input='',capture_output=True,text=True)
            assert result.returncode==3 and str(root)+'|--no-open' in result.stdout
print('PASS: macOS/Linux launchers preserve paths, arguments and exit status')
