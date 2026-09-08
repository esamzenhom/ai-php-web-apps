#!/usr/bin/env bash
set -euo pipefail
cd -P "$(dirname "$0")/.."
echo 'Warning: reset starts this app with an empty database, no admin account and no saved API keys or chats. The current local data and configuration will be encrypted and verified in a private recovery folder before removal. Keep the separate recovery key to restore it. Website source stays in place.'
read -r -p "Type reset to confirm: " reply
[[ "$reply" == reset ]] || { echo 'Nothing changed.'; exit 0; }
./bin/backup.sh
image=$(docker compose images -q worker | head -n 1)
[[ -n "$image" ]] || { echo "Worker image unavailable; reset stopped."; exit 1; }
docker compose down --remove-orphans
docker run --rm --network none --user 0:0 --entrypoint php -v "$PWD:/var/www/html" "$image" bin/reset-archive.php
rm .env
echo 'Run the Start launcher to set up again.'
