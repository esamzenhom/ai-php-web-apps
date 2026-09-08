#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export PATH="/Applications/Docker.app/Contents/Resources/bin:$HOME/.docker/bin:$PATH"
if ! command -v docker >/dev/null 2>&1; then
    echo 'Docker is not installed. Nothing needs to be stopped.'
    exit 0
fi
source bin/docker-access.sh
if ! docker info >/dev/null 2>&1; then
    echo 'Docker is not running or cannot be reached. No app files or data were changed.'
    exit 0
fi
if [ -f .env ]; then
    docker compose stop
    echo 'This app is stopped. Your website, account, keys and data are kept. Use the Start launcher in Start and Stop to reopen it.'
else
    echo 'This app has not been configured; there are no app services to stop.'
fi

if [[ " $* " == *" --keep-docker "* ]]; then
    echo 'Docker was left running.'
    exit 0
fi

echo 'Quit Docker too? This can interrupt ALL other apps and containers using Docker on this computer. Their saved data will not be deleted.'
if ! read -r -p 'Type yes to quit Docker, or press Enter to keep it running: ' stop_docker_reply; then
    echo 'Docker was left running.'
    exit 0
fi
if [[ "$stop_docker_reply" != yes ]]; then
    echo 'Docker was left running.'
    exit 0
fi
if [[ -n "${DOCKER_HOST:-}" ]]; then
    echo 'Docker uses a custom connection. Quit that Docker service directly; no shared service was changed.'
    exit 0
fi
docker_context="$(docker context show)"
case "$(uname -s):$docker_context" in
    Darwin:desktop-linux|Darwin:default)
        osascript -e 'tell application "Docker" to quit'
        ;;
    Linux:desktop-linux)
        docker desktop stop --timeout 60
        ;;
    Linux:default)
        if [[ "$(id -u)" = 0 ]]; then
            systemctl stop docker.service docker.socket
        else
            sudo systemctl stop docker.service docker.socket
        fi
        ;;
    *) echo 'This Docker connection cannot be stopped automatically. Quit it directly; no shared service was changed.'; exit 0 ;;
esac
for attempt in {1..30}; do
    if ! docker info >/dev/null 2>&1; then echo 'Docker is stopped. Use the Start launcher in Start and Stop when you want to start again.'; exit 0; fi
    sleep 1
done
echo 'The quit request was sent, but Docker is still responding. Check its window or service status.'
exit 1
