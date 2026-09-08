#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
file="${1:-}"
[[ "$file" =~ ^backups/[a-zA-Z0-9_/-]+/database\.dump(\.enc)?$ ]] && [[ -f "$file" ]] || { echo 'Choose an existing backups/.../database.dump.enc file.'; exit 1; }
echo 'Warning: restoring replaces website records with the selected backup. Newer customer records and submissions will be removed. Admin settings and keys stay unchanged.'
echo 'A fresh private backup will be verified before restoring.'
read -r -p "Type restore to confirm: " reply
[[ "$reply" == restore ]] || { echo 'Nothing changed.'; exit 0; }
./bin/backup.sh
# A fixed command passes the selected validated file as an argument, not shell code.
docker compose exec -T worker php bin/restore-db.php "$file"
