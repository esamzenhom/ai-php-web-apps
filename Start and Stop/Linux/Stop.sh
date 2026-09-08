#!/usr/bin/env bash
cd "$(dirname "$0")/../.." || exit 1
exec bash ./application/bin/stop.sh "$@"
