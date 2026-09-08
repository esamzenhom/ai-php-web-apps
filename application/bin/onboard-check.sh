#!/usr/bin/env bash
set +e
root="$(cd "$(dirname "$0")/../.." 2>/dev/null && pwd)"
if [ ! -f "$root/application/.env" ] || [ ! -f "$root/application/data/control/admin.sqlite" ]; then
  printf '%s' '{"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"FIRST-RUN SETUP: read application/docs/PRODUCT-CONTRACT.md and the shared app-setup skill. When startup is requested, run bash application/bin/start.sh from the root and guide the owner through the welcome page and browser admin setup. Keep setup codes and secrets out of assistant messages. The owner supplies their own admin credentials and AI API keys in the browser; neither AI CLI is required for building."}}'
fi
exit 0
