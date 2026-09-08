#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
get() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' || true; }
deploy_host="$(get DEPLOY_HOST)"; deploy_user="$(get DEPLOY_USER)"
deploy_path="$(get DEPLOY_PATH)"; deploy_port="$(get DEPLOY_SSH_PORT)"; deploy_port="${deploy_port:-22}"
[[ "$deploy_host" =~ ^[a-zA-Z0-9.-]+$ && "$deploy_user" =~ ^[a-zA-Z0-9_-]+$ && "$deploy_path" =~ ^/[a-zA-Z0-9_./-]+$ && "$deploy_port" =~ ^[0-9]+$ ]] || { echo 'Set a valid deploy host, user, absolute repository path and SSH port first.'; exit 1; }
# A public checkout intentionally has no custom website. Never let rsync --delete
# remove a live website when deploying from an uninitialized checkout.
[[ -f website/public/index.php ]] || { echo 'No local website is present. Restore the intended website from a private backup before deploying.'; exit 1; }
remote="$deploy_user@$deploy_host"
echo 'Warning: deployment replaces live source and applies pending schema migrations. This can change website records and visitor access. A verified live backup is required first.'
read -r -p "Type deploy to confirm: " reply
[[ "$reply" == deploy ]] || { echo 'Nothing changed.'; exit 0; }
ssh -p "$deploy_port" "$remote" "test -f '$deploy_path/application/.env'" || { echo 'Initialize the remote repository with start.sh before deploying updates.'; exit 1; }
ssh -p "$deploy_port" "$remote" "cd '$deploy_path/application' && make backup"
rsync -az --delete -e "ssh -p $deploy_port" --exclude '.git' --include '.env.example' --exclude '.env*' --exclude data --exclude backups --exclude dist --exclude '*.log' --exclude '*.bak' --exclude ACTIVITY-LOG.md --exclude settings.local.json --exclude __pycache__ ../ "$remote:$deploy_path/"
ssh -p "$deploy_port" "$remote" "cd '$deploy_path/application' && make prod-up && make prod-migrate"
echo 'Deployment finished. Check the live website and its admin page before declaring it healthy.'
