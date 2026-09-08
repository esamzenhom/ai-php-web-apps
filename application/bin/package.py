#!/usr/bin/env python3
"""Copy only public source; never carry app identity or customer data into a new app."""
import argparse,shutil
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('--clone');args=parser.parse_args()
root=Path(__file__).resolve().parents[2]
allowed=['AGENTS.md','CLAUDE.md','README.md','Start and Stop','.gitignore','.gitattributes','.github','.agents','.codex','.claude','application']
blocked={'node_modules','.git','__pycache__','ACTIVITY-LOG.md','.DS_Store','settings.local.json','CLAUDE.local.md','worktrees','.env','vendor','.idea','.vscode','Thumbs.db'}
def ignore(directory,names):
    private={'data','backups','website','site','releases'} if Path(directory)==root/'application' else set()
    return [n for n in names if n in blocked or n in private or (n.startswith('.env.') and n!='.env.example') or n.endswith(('.bak','.log','.pyc','.pyo','.pyd','.swp','.swo','~'))]
if args.clone:
    dest=Path(args.clone).resolve()
    if dest.exists(): parser.error('Choose a new, empty destination folder; existing folders are not overwritten.')
    if root==dest or root in dest.parents: parser.error('Choose a destination outside this repository.')
    dest.mkdir(parents=True)
    for name in allowed:
        source=root/name
        if source.is_dir():shutil.copytree(source,dest/name,symlinks=True,ignore=ignore)
        elif source.exists():shutil.copy2(source,dest/name)
    (dest/'application/PROGRESS.md').write_text('# Work in progress\n\nStatus: IDLE\n\n## Goal\nFresh app; no work in progress.\n\n## Next action\nOpen README.md and choose your launcher in Start and Stop.\n')
    print('New independent app created. Start with the guide: '+str(dest/'README.md'))
else:
    import tempfile
    with tempfile.TemporaryDirectory() as tmp:
        dest=Path(tmp)/'app-studio'
        import subprocess
        subprocess.run(['python3',__file__,'--clone',str(dest)],check=True,stdout=subprocess.DEVNULL)
        output=root/'application/releases';output.mkdir(exist_ok=True)
        # Tar preserves relative skill symlinks, file modes and empty directories.
        import tarfile
        archive=output/'app-studio.tar.gz'
        with tarfile.open(archive,'w:gz',dereference=False) as tar:
            tar.add(dest,arcname='app-studio')
        print('Public source package: '+str(archive))
