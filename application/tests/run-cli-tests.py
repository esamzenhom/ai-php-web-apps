#!/usr/bin/env python3
"""Run CLI adapter tests with private disposable storage, never the owner's login."""
import json,subprocess,tempfile
from pathlib import Path
app=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='cli-runner-tests-') as temporary:
    folder=Path(temporary);state=folder/'state';state.mkdir()
    override=folder/'override.json'
    override.write_text(json.dumps({'services':{'cli':{'volumes':[
        {'type':'bind','source':str(state),'target':'/state'},
        {'type':'bind','source':str(app/'tests'),'target':'/tests','read_only':True}]}}}))
    prefix=['docker','compose','-f','docker-compose.yml','-f',str(override)]
    config=json.loads(subprocess.check_output(prefix+['config','--format','json'],cwd=app))
    mounts=config['services']['cli']['volumes']
    assert next(v for v in mounts if v['target']=='/state')['source']==str(state)
    subprocess.run(prefix+['run','--rm','--no-deps','--entrypoint','python3','cli','/tests/cli_runner.py'],cwd=app,check=True)
