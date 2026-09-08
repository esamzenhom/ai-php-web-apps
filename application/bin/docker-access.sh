#!/usr/bin/env bash
# Sourced from application/. Keep any privileged CLI wrapper private and temporary.
if ! docker info >/dev/null 2>&1 && [ "$(uname -s)" = Linux ] && [ "$(id -u)" != 0 ] && [ -z "${DOCKER_HOST:-}" ] && [ "$(docker context show 2>/dev/null)" = default ]; then
    echo 'Docker needs administrator access on this computer. You may be asked for your computer password.'
    docker_binary="$(command -v docker)"
    sudo "$docker_binary" info >/dev/null || { echo 'Cannot reach the local Docker service.'; exit 1; }
    docker_wrapper="$(mktemp -d)"
    trap 'rm -rf "$docker_wrapper"' EXIT
    printf '#!/usr/bin/env bash\nexec sudo -- %q "$@"\n' "$docker_binary" > "$docker_wrapper/docker"
    chmod 700 "$docker_wrapper/docker"
    export PATH="$docker_wrapper:$PATH"
fi
