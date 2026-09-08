#!/usr/bin/env python3
"""Offline disposable maintenance checks; run after starting this stack."""
import os,subprocess,tempfile
from pathlib import Path
app=Path(__file__).resolve().parents[1]
image=subprocess.check_output(['docker','compose','images','-q','worker'],cwd=app,text=True).splitlines()[0]
with tempfile.TemporaryDirectory(prefix='backup-maintenance-') as name:
    fixture=Path(name).resolve();data=fixture/'data';backups=fixture/'backups';data.mkdir();backups.mkdir()
    command=['docker','run','--rm','--network','none','--entrypoint','php','-e','DB_HOST=127.0.0.1','-e','APP_UID='+str(os.getuid()),'-e','APP_GID='+str(os.getgid()),'-v',str(app/'admin/app')+':/var/www/html/admin/app:ro','-v',str(app/'bin')+':/var/www/html/bin:ro','-v',str(data)+':/var/www/html/data','-v',str(backups)+':/var/www/html/backups']
    def php(*args,check=True):
        return subprocess.run([*command,*([] if args[0]=='bin/prepare.php' else ['--user',str(os.getuid())+':'+str(os.getgid())]),image,*args],capture_output=True,text=True,check=check)
    php('bin/prepare.php')
    failed=php('bin/backup.php',check=False)
    assert failed.returncode!=0 and 'Backup did not complete' in failed.stderr
    assert not list((data/'control/backup-work').glob('private-*'))
    legacy=backups/'legacy';legacy.mkdir();(legacy/'safe-link').symlink_to('/outside/not-to-be-followed')
    assert 'encrypted 1' in php('bin/encrypt-backups.php').stdout
    assert not (legacy/'safe-link').is_symlink() and (legacy/'safe-link.symlink.enc').is_file()
    php('bin/recovery.php','decrypt-folder','backups/legacy')
    recovered=data/'control/recovered/legacy/safe-link.symlink'
    assert not recovered.is_symlink() and recovered.read_text()=='/outside/not-to-be-followed'
    assert 'encrypted 0' in php('bin/encrypt-backups.php').stdout
print('PASS: failed backups remove private temporary files; legacy links encrypt/recover as metadata without following targets')
