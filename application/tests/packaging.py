#!/usr/bin/env python3
"""Verify public copies exclude private source and startup preserves custom work."""
from pathlib import Path
import subprocess
import tarfile
import tempfile

repo = Path(__file__).resolve().parents[2]

def run(script, *args):
    subprocess.run(['python3', str(script), *map(str, args)], check=True,
                   cwd=script.parent.parent, stdout=subprocess.DEVNULL)

with tempfile.TemporaryDirectory(prefix='app-public-copy-') as tmp:
    first = Path(tmp) / 'first'
    run(repo / 'application/bin/package.py', '--clone', first)
    for name in ['application/bin/start.sh','application/bin/stop.sh','Start and Stop/macOS/Start.command','Start and Stop/macOS/Stop.command','Start and Stop/Linux/Start.sh','Start and Stop/Linux/Stop.sh']:
        assert (first / name).is_file() and (first / name).stat().st_mode & 0o111
    for name in ['Start and Stop/Windows/Start.cmd','Start and Stop/Windows/Stop.cmd','application/bin/start-windows.ps1','application/bin/stop-windows.ps1']:
        assert (first / name).is_file()
    assert {p.name for p in first.iterdir() if not p.name.startswith('.')} == {'README.md','AGENTS.md','CLAUDE.md','Start and Stop','application'}
    for name in ['.github/CONTRIBUTING.md', '.github/SECURITY.md', '.github/workflows/source-checks.yml']:
        assert (first / name).is_file()
    app = first / 'application'
    assert not (app / 'website').exists()
    assert not (app / 'data').exists() and not (app / '.env').exists()
    (app / '.env').write_text('DEPLOY_HOST=example.invalid\nDEPLOY_USER=test\nDEPLOY_PATH=/test\n')
    blocked = subprocess.run(['bash', 'bin/deploy.sh'], cwd=app, text=True, capture_output=True)
    assert blocked.returncode == 1 and 'No local website is present' in blocked.stdout
    (app / '.env').unlink()
    run(app / 'bin/init-website.py')
    assert (app / 'website/public/index.php').read_bytes() == (app / 'templates/website/public/index.php').read_bytes()
    custom = app / 'website/public/index.php'
    custom.write_text('PRIVATE_CUSTOM_WEBSITE_FIXTURE')
    (app / 'website/migrations/private.sql').write_text('-- PRIVATE_CUSTOM_SCHEMA_FIXTURE')
    run(app / 'bin/init-website.py')
    assert custom.read_text() == 'PRIVATE_CUSTOM_WEBSITE_FIXTURE'
    for name in ['data/control/key', 'backups/private', 'site/legacy', '.env', '../CLAUDE.local.md']:
        target = app / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('PRIVATE_STORAGE_FIXTURE')
    second = Path(tmp) / 'second'
    run(app / 'bin/package.py', '--clone', second)
    assert not (second / 'application/website').exists()
    run(app / 'bin/package.py')
    with tarfile.open(first / 'application/releases/app-studio.tar.gz') as archive:
        names = archive.getnames()
        assert 'app-studio/application/templates/website/public/index.php' in names
        for item in archive.getmembers():
            if item.isfile():
                contents = archive.extractfile(item).read()
                # Test source itself contains fixture strings; exclude it from this check.
                if '/tests/' not in item.name:
                    assert b'PRIVATE_CUSTOM_' not in contents and b'PRIVATE_STORAGE_FIXTURE' not in contents
        assert not any(n.startswith('app-studio/application/' + d + '/') for n in names
                       for d in ['website', 'site', 'data', 'backups'])
    assert (second / '.claude/skills/app-builder').resolve() == (second / '.agents/skills/app-builder').resolve()
print('PASS: clean clone/archive, private source and credentials excluded, startup preserves existing website, shared skills intact')
