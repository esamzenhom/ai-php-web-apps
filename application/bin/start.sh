#!/usr/bin/env bash
set -euo pipefail
cd -P "$(dirname "$0")/.."
command -v python3 >/dev/null 2>&1 || { echo 'Please install Python 3, then run this script again.'; exit 1; }
source bin/ensure-docker.sh
if [[ " $* " != *" --rebuild "* ]] && python3 bin/open-app.py --if-running "$@"; then
    exit 0
fi
python3 bin/init-app.py
python3 bin/check-storage.py
echo 'Preparing your app. The first start may take a few minutes…'
mkdir -p data/private
startup_log=data/private/startup.log
: > "$startup_log"
run() { "$@" >> "$startup_log" 2>&1 || { echo 'Startup stopped safely. Details are in application/data/private/startup.log; ask your assistant for help.'; return 1; }; }
run docker compose build
mkdir -p data/control data/sessions data/site/sessions data/recovery data/audit backups
run docker compose run --rm --build --no-deps prepare
mkdir -p data/cli
run docker compose run --rm --no-deps --user 0:0 --entrypoint python3 cli -c 'import os; os.chown("/state", int(os.environ.get("APP_UID", "1000")), int(os.environ.get("APP_GID", "1000"))); os.chmod("/state", 0o700)'
run docker compose up -d --wait --wait-timeout 120
run docker compose exec -T postgres sh /docker-entrypoint-initdb.d/10-site.sh
run docker compose exec -T worker php bin/migrate.php
python3 bin/open-app.py "$@"
