#!/usr/bin/env python3
"""Create the private website from the public starter only when absent."""
from pathlib import Path
import shutil

root = Path(__file__).resolve().parents[1]
website = root / 'website'
if not website.exists() and not website.is_symlink():
    shutil.copytree(root / 'templates/website', website)
    print('Your private website was created from the starter template.')
