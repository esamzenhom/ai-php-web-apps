"""Private offline scanner. Never executes submitted code or downloads rules."""
import hashlib,hmac,json,os,re,subprocess,tempfile,threading
from pathlib import Path
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
TOKEN=os.environ['AUDIT_SERVICE_TOKEN']
LOCK=threading.Lock()
RULES='/opt/audit/rules.yml'
class Handler(BaseHTTPRequestHandler):
 def log_message(self,*args):pass
 def do_POST(self):
  if not hmac.compare_digest(self.headers.get('Authorization',''),'Bearer '+TOKEN):self.send_error(403);return
  try:
   size=int(self.headers.get('Content-Length','0'))
   if size<2 or size>1200000:raise ValueError('Invalid scan size')
   data=json.loads(self.rfile.read(size))
   if data.get('action')=='health':result={'ok':True}
   else:
    files=data['files']
    if not isinstance(files,dict) or len(files)>150 or sum(len(v.encode()) for v in files.values())>500000:raise ValueError('Invalid scan input')
    if not LOCK.acquire(blocking=False):raise ValueError('Security scanner is busy. Try again.')
    try:
     with tempfile.TemporaryDirectory(prefix='audit-') as folder:
      for name,content in files.items():
       if not re.fullmatch(r'(public|app|migrations)/[a-zA-Z0-9_/-]+\.(php|html|css|js|json|svg|sql)',name) or '..' in name or '//' in name or len(content.encode())>120000:raise ValueError('Invalid source path')
       p=Path(folder)/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(content)
      run=subprocess.run(['semgrep','scan','--config',RULES,'--json','--jobs','1','--metrics=off','--disable-version-check','--disable-nosem','--no-git-ignore','--timeout','10','--max-target-bytes','120000',folder],capture_output=True,timeout=90,cwd=folder,env={k:v for k,v in os.environ.items() if k not in ('SEMGREP_IN_DOCKER','AUDIT_SERVICE_TOKEN')})
      if run.returncode!=0:raise ValueError('Security scan could not complete. Nothing can be applied.')
      report=json.loads(run.stdout)
      if report.get('errors'):raise ValueError('Security scan could not parse all files. Correct the source before applying.')
      findings=[{'rule':r['check_id'].split('.')[-1],'file':str(Path(r['path']).relative_to(folder)),'line':r['start']['line'],'message':r['extra']['message']} for r in report.get('results',[])]
      result={'engine':'Semgrep 1.176.0','rules':hashlib.sha256(Path(RULES).read_bytes()).hexdigest(),'findings':findings,'passed':not findings,'files':len(files)}
    finally:LOCK.release()
   body=json.dumps(result).encode();self.send_response(200);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
  except Exception:
   body=b'{"error":"Security scan failed or is unavailable. Nothing was approved."}';self.send_response(503);self.end_headers();self.wfile.write(body)
ThreadingHTTPServer(('0.0.0.0',8091),Handler).serve_forever()
