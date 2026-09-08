#!/usr/bin/env bash
cd "$(dirname "$0")/../.." || exit 1
bash ./application/bin/start.sh "$@"
result=$?
if [ -t 0 ]; then read -r -p 'Press Enter to close this window.' _reply; fi
exit "$result"
