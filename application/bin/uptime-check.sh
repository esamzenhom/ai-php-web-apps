#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
#  Check the site is up; alert if it's down.
#  Hits UPTIME_URL (the /health endpoint) and, if it's not HTTP 200,
#  sends an alert by email (via app/Mail.php) and/or a webhook.
#
#  Set in .env:  UPTIME_URL, and ALERT_EMAIL and/or ALERT_WEBHOOK.
#  Run:  ./bin/uptime-check.sh   (or: make uptime)
#  Schedule it with cron for continuous monitoring (see README).
# ─────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."

# Read a value from .env; returns empty (not an error) if the var is absent.
get() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' || true; }
URL="$(get UPTIME_URL)"; EMAIL="$(get ALERT_EMAIL)"; HOOK="$(get ALERT_WEBHOOK)"
[ -n "$URL" ] || { echo "✗ Set UPTIME_URL in .env." >&2; exit 1; }

CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$URL" 2>/dev/null || true)"
CODE="${CODE:-000}"
if [ "$CODE" = "200" ]; then
  echo "OK ($CODE): $URL"
  exit 0
fi

MSG="ALERT: ${URL} is DOWN (HTTP ${CODE}) at $(date)"
echo "$MSG" >&2

# Webhook alert (Slack/Telegram/Discord/etc.)
if [ -n "$HOOK" ]; then
  curl -s -m 15 -H 'Content-Type: application/json' \
    -d "{\"text\":$(printf '%s' "$MSG" | sed 's/"/\\"/g; s/^/"/; s/$/"/')}" \
    "$HOOK" >/dev/null 2>&1 || true
fi

# Email alert via the app's Mailer (runs inside the php container).
if [ -n "$EMAIL" ]; then
  docker compose exec -T php php -r \
    'require "/var/www/html/admin/app/bootstrap.php"; \App\Mail::send($argv[1], "Your site is DOWN", $argv[2]);' \
    "$EMAIL" "$MSG" 2>/dev/null || echo "  (could not send email alert — check MAIL_* settings)"
fi

exit 1
