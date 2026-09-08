#!/usr/bin/env bash
# Sourced by start.sh; installation is only attempted when Docker is absent.
export PATH="/Applications/Docker.app/Contents/Resources/bin:$HOME/.docker/bin:$PATH"
platform="$(uname -s)"
if ! command -v docker >/dev/null 2>&1; then
    echo 'Docker is missing. Installing it now; your computer may ask for its administrator password.'
    case "$platform" in
        Darwin)
            case "$(uname -m)" in arm64) docker_arch=arm64 ;; x86_64) docker_arch=amd64 ;; *) echo 'This Mac architecture is not supported by the automatic installer.'; exit 1 ;; esac
            (
                set -e
                installer_dir="$(mktemp -d)"
                cleanup_installer() { hdiutil detach "$installer_dir/mount" >/dev/null 2>&1 || true; rm -rf "$installer_dir"; }
                trap cleanup_installer EXIT
                curl --fail --location --proto '=https' --tlsv1.2 --retry 3 "https://desktop.docker.com/mac/main/$docker_arch/Docker.dmg" -o "$installer_dir/Docker.dmg"
                mkdir "$installer_dir/mount"
                hdiutil attach "$installer_dir/Docker.dmg" -nobrowse -mountpoint "$installer_dir/mount"
                sudo "$installer_dir/mount/Docker.app/Contents/MacOS/install"
            )
            ;;
        Linux)
            . /etc/os-release
            case "$ID" in ubuntu|debian) ;; *) echo 'Automatic installation supports Ubuntu and Debian. Install Docker for your Linux distribution, then use the Start launcher.'; exit 1 ;; esac
            # Official signed Docker apt repository; no removal of conflicting packages.
            as_root() { if [ "$(id -u)" = 0 ]; then "$@"; else sudo "$@"; fi; }
            as_root apt-get update
            as_root apt-get install -y ca-certificates curl
            as_root install -m 0755 -d /etc/apt/keyrings
            as_root curl -fsSL "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
            as_root chmod a+r /etc/apt/keyrings/docker.asc
            printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/%s %s stable\n' "$(dpkg --print-architecture)" "$ID" "${UBUNTU_CODENAME:-$VERSION_CODENAME}" | as_root tee /etc/apt/sources.list.d/docker.list >/dev/null
            as_root apt-get update
            as_root apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
        *) echo 'Run this stack on macOS, Ubuntu, Debian, or a supported Linux environment with Docker installed.'; exit 1 ;;
    esac
    hash -r
fi
if ! docker info >/dev/null 2>&1; then
    case "$platform" in
        Darwin)
            echo 'Opening Docker Desktop. Complete any first-run prompts in its window.'
            open -a Docker
            for attempt in {1..90}; do
                docker info >/dev/null 2>&1 && break
                if (( attempt % 15 == 0 )); then echo 'Still waiting for Docker Desktop. Please check its window.'; fi
                sleep 2
            done
            ;;
        Linux)
            if [ -z "${DOCKER_HOST:-}" ] && [ "$(docker context show 2>/dev/null)" = desktop-linux ]; then
                systemctl --user start docker-desktop
            fi
            if [ -z "${DOCKER_HOST:-}" ] && [ "$(docker context show 2>/dev/null)" = default ]; then
                if command -v systemctl >/dev/null; then
                    if [ "$(id -u)" = 0 ]; then systemctl start docker; else sudo systemctl start docker; fi
                fi
            fi
            ;;
    esac
fi
# Use per-command sudo on local Linux if needed; never change socket permissions
# or permanently grant this user the root-equivalent docker group.
source bin/docker-access.sh
docker info >/dev/null 2>&1 || { echo 'Docker is not ready. Finish its setup or check the Docker service, then use the Start launcher again.'; exit 1; }
docker compose version >/dev/null 2>&1 || { echo 'Docker Compose is missing. Update Docker Desktop or install docker-compose-plugin, then use the Start launcher again.'; exit 1; }
