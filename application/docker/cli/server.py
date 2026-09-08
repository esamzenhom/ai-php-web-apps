"""Private CLI adapter. No arbitrary command, terminal, path, or host access API."""
import hmac,json,os,pty,re,secrets,select,signal,subprocess,tempfile,threading,time
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
from pathlib import Path
os.umask(0o077)
ROOT=Path('/state')
TOKEN=os.environ['CLI_SERVICE_TOKEN']
if len(TOKEN)<32: raise RuntimeError('Private CLI service token is missing')
for name in ['tmp','codex','claude','work']: (ROOT/name).mkdir(exist_ok=True)
(ROOT/'codex/config.toml').write_text('cli_auth_credentials_store = "file"\n')
LOCK=threading.RLock()
SESSIONS={}
BUSY=set()
STATUS={p:False for p in ['codex','claude']}
# Never pass the private RPC credential or application environment to a CLI.
ENV={k:v for k,v in os.environ.items() if k in ['PATH','HOME','TMPDIR','CODEX_HOME','CLAUDE_CONFIG_DIR','SSL_CERT_FILE']}
ENV.update({'TERM':'dumb','NO_COLOR':'1','BROWSER':'/bin/true','CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC':'1','DISABLE_AUTOUPDATER':'1','CLAUDE_CODE_SKIP_PROMPT_HISTORY':'1'})
def command(provider,action):
    if provider not in STATUS: raise ValueError('Choose Codex CLI or Claude CLI.')
    if provider=='codex': return ['codex','login','--device-auth'] if action=='login' else ['codex','logout'] if action=='logout' else ['codex','login','status']
    return ['claude','auth',action]
def probe(provider):
    try:
        r=subprocess.run(command(provider,'status'),env=ENV,cwd=ROOT/'work',capture_output=True,timeout=15)
        valid=r.returncode==0
    except (OSError,subprocess.TimeoutExpired): valid=False
    STATUS[provider]=valid
    return valid

def clean(text):
    text=re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]','',text)
    text=re.sub(r'\x1b\][^\x07]*(?:\x07|\x1b\\)','',text)
    text=re.sub(r'(?:sk-[A-Za-z0-9_-]{20,}|eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)','[redacted]',text)
    return ''.join(c for c in text if c in '\n\t' or ord(c)>=32)[-16000:]
def stop(session):
    process=session.get('process')
    if process and process.poll() is None:
        try: os.killpg(process.pid,signal.SIGKILL)
        except ProcessLookupError: pass

def login_run(session):
    master=slave=None;final_state='failed'
    try:
        master,slave=pty.openpty()
        import termios
        attrs=termios.tcgetattr(slave);attrs[3]&=~termios.ECHO;termios.tcsetattr(slave,termios.TCSANOW,attrs)
        process=subprocess.Popen(command(session['provider'],'login'),stdin=slave,stdout=slave,stderr=slave,env=ENV,cwd=ROOT/'work',start_new_session=True)
        os.close(slave);slave=None
        with LOCK: session.update(process=process,fd=master)
        while process.poll() is None:
            if time.time()>session['expires'] or session['cancelled']: stop(session);break
            if select.select([master],[],[],.25)[0]:
                try: data=os.read(master,4096)
                except OSError: break
                with LOCK: session['output']=clean(session['output']+data.decode(errors='replace'))
        process.wait(timeout=5)
        with LOCK:
            final_state='connected' if probe(session['provider']) else ('cancelled' if session['cancelled'] else 'failed')
            if final_state=='failed':session['output']+='\nSign-in did not complete. Retry, or use an API key.'
    except Exception:
        with LOCK: session.update(output='Could not start CLI sign-in. Restart the app and try again.')
    finally:
        stop(session)
        for fd in [master,slave]:
            if fd is not None:
                try: os.close(fd)
                except OSError: pass
        with LOCK: session.pop('fd',None);BUSY.discard(session['provider']);session['state']=final_state

def login_view(session):
    # Auth instructions are ephemeral, available only to the admin who started them.
    if session['state']=='connected':return {'state':'connected','output':'Connected. You can close this window and select this CLI in chat.'}
    return {'state':session['state'],'output':session['output']}

def generate(data):
    provider=data['provider']
    if provider not in STATUS:raise ValueError('Unsupported CLI.')
    with LOCK:
        if provider in BUSY:raise ValueError('This CLI is busy. Finish sign-in or wait for the current request.')
        BUSY.add(provider)
    try:
        if not probe(provider):raise ValueError('This CLI is signed out. Connect it in admin Settings.')
        with tempfile.TemporaryDirectory(dir=ROOT/'tmp') as tmp:
            folder=Path(tmp);schema=folder/'schema.json';out=folder/'answer.json'
            schema.write_text(json.dumps(data['schema']))
            prompt=data['system']+'\n\n'+data['context']
            if provider=='codex':
                argv=['codex','exec','--skip-git-repo-check','--ephemeral','--ignore-user-config','--ignore-rules','--sandbox','read-only','--disable','shell_tool','--disable','unified_exec','--disable','apps','--disable','plugins','--disable','hooks','--disable','browser_use','--disable','computer_use','--disable','image_generation','--disable','view_image','--disable','code_mode_host','--disable','skill_search','--disable','skill_mcp_dependency_install','--output-schema',str(schema),'--output-last-message',str(out),'--color','never','-']
            else:
                argv=['claude','-p','--restricted','--tools','','--disallowedTools','mcp__*','--strict-mcp-config','--mcp-config','{"mcpServers":{}}','--setting-sources','','--no-session-persistence','--output-format','json','--json-schema',json.dumps(data['schema'])]
            model=data.get('model','')
            if model and model!='default':
                if not re.fullmatch(r'[A-Za-z0-9._:-]{1,100}',model):raise ValueError('Invalid CLI model.')
                argv+=['--model',model]
            # Bounded output in memory; kill the entire process group on timeout/overflow.
            process=subprocess.Popen(argv,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,cwd=folder,env=ENV,start_new_session=True)
            def feed():
                try:process.stdin.write(prompt.encode());process.stdin.close()
                except (BrokenPipeError,OSError):pass
            threading.Thread(target=feed,daemon=True).start()
            output=bytearray();size=0;deadline=time.time()+240
            streams=[process.stdout,process.stderr]
            try:
                while streams:
                    if time.time()>deadline:raise ValueError('CLI request timed out. Nothing was applied; your account may have used credits.')
                    ready,_,_=select.select(streams,[],[],.2)
                    for stream in ready:
                        chunk=os.read(stream.fileno(),8192)
                        if not chunk:streams.remove(stream);continue
                        size+=len(chunk)
                        if size>3000000:raise ValueError('CLI output exceeded the size limit. Try a smaller request.')
                        if stream is process.stdout:output.extend(chunk)
                if process.wait(timeout=5)!=0:raise ValueError('The CLI could not complete this request. Check your account access or reconnect in Settings. Nothing was applied.')
                if provider=='codex':
                    if not out.exists() or out.stat().st_size>2000000:raise ValueError('Codex did not return a complete proposal.')
                    result=json.loads(out.read_text())
                else:
                    envelope=json.loads(output)
                    if envelope.get('is_error'):raise ValueError('Claude could not complete the request. Check account usage and try again.')
                    result=envelope.get('structured_output')
                    if result is None:result=json.loads(envelope.get('result',''))
                if not isinstance(result,dict):raise ValueError('CLI returned an invalid proposal.')
                return {'proposal':result}
            finally:
                if process.poll() is None:os.killpg(process.pid,signal.SIGKILL)
                process.wait()
    finally:
        with LOCK:BUSY.discard(provider)

def dispatch(data):
    action=data.get('action')
    if action=='status':
        with LOCK:return {'connections':dict(STATUS)}
    if action=='generate':return generate(data)
    provider=data.get('provider')
    if provider not in STATUS:raise ValueError('Choose a supported CLI.')
    with LOCK:
        if action=='login':
            if provider in BUSY:raise ValueError('This CLI is busy. Close its existing login or wait for your request.')
            if len(SESSIONS)>100:
                for key in list(SESSIONS):
                    if SESSIONS[key]['expires']<time.time():SESSIONS.pop(key)
            sid=secrets.token_hex(24)
            session={'provider':provider,'output':'Starting secure CLI sign-in…\n','state':'waiting','expires':time.time()+600,'cancelled':False}
            SESSIONS[sid]=session;BUSY.add(provider)
            threading.Thread(target=login_run,args=(session,),daemon=True).start()
            return {'id':sid,**login_view(session)}
        if action=='logout':
            if provider in BUSY:raise ValueError('Finish the current request or close the login window first.')
            subprocess.run(command(provider,'logout'),env=ENV,cwd=ROOT/'work',capture_output=True,timeout=20,check=True)
            probe(provider)
            return {'connected':STATUS[provider]}
        session=SESSIONS.get(data.get('id'))
        if not session or session['provider']!=provider:raise ValueError('Login session expired. Start sign-in again.')
        if action=='poll':return login_view(session)
        if action=='cancel':session['cancelled']=True;stop(session);return {'ok':True}
        if action=='input':
            value=data.get('input','')
            if provider!='claude' or session['state']!='waiting' or 'fd' not in session:raise ValueError('This sign-in is not waiting for an authentication code.')
            if not re.fullmatch(r'[A-Za-z0-9_.~:#/+=-]{1,512}',value):raise ValueError('Paste only the one-time code from the provider login page.')
            os.write(session['fd'],(value+'\n').encode());return {'ok':True}
    raise ValueError('Unsupported action.')

class Handler(BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def do_POST(self):
        if self.path!='/' or not hmac.compare_digest(self.headers.get('Authorization',''),'Bearer '+TOKEN):
            self.send_response(403);self.end_headers();return
        try:
            size=int(self.headers.get('Content-Length','0'))
            if not 0<size<=1500000:raise ValueError('Request size is invalid.')
            result=dispatch(json.loads(self.rfile.read(size)))
            status=200
        except ValueError as error:status=400;result={'error':str(error)}
        except Exception:status=400;result={'error':'CLI operation failed. Reconnect in Settings or use an API key.'}
        payload=json.dumps(result).encode();self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Cache-Control','no-store');self.send_header('Content-Length',str(len(payload)));self.end_headers();self.wfile.write(payload)
if __name__=='__main__':
    for provider in STATUS:probe(provider)
    ThreadingHTTPServer(('0.0.0.0',8090),Handler).serve_forever()
