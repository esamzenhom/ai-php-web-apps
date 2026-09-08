#!/usr/bin/env python3
"""Refuse a silent switch from this project's old Docker volumes to empty folders."""
import json,subprocess,sys
config=json.loads(subprocess.check_output(['docker','compose','config','--format','json']))
project=config['name']
volumes=subprocess.check_output(['docker','volume','ls','--filter','label=com.docker.compose.project='+project,'--format','{{.Name}}']).decode().strip()
legacy=subprocess.run(['docker','volume','inspect',project+'_pgdata'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0
containers=subprocess.check_output(['docker','ps','-aq','--filter','label=com.docker.compose.project='+project]).decode().split()
if containers:
    legacy=legacy or any(m['Type']=='volume' for c in json.loads(subprocess.check_output(['docker','inspect',*containers])) for m in c['Mounts'])
if volumes or legacy:
    sys.exit('Warning: this app has older Docker-volume storage. Starting with local folders could hide existing records. Ask your assistant to back up, migrate, and verify those records first. No containers were changed.')
