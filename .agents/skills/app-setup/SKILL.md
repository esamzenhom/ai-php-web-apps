---
name: app-setup
description: Help a non-technical owner start this stack and create their private administrator account through the browser, on a fresh checkout or when setup is requested.
---

# Browser-first setup

Read `application/docs/PRODUCT-CONTRACT.md` and follow its startup/admin requirements.

1. Run `bash application/bin/start.sh` from the repository root when startup is requested. The script
   requires Python 3, installs missing Docker on macOS/Ubuntu/Debian, opens or starts
   Docker (owner handles password/terms prompts), creates only missing configuration, generates unique
   identity and strong secrets, chooses a free local port, prepares local bind
   directories, starts services, applies schema migrations, and checks readiness.
2. The browser opens the welcome page. Its setup button carries the private setup
   code in a URL fragment (not a server URL/query log). The owner creates their
   own username and password; never set a default account on their behalf.
3. Explain Settings: either add an OpenAI/Anthropic API key and model ID, or choose
   an admin-only Codex/Claude CLI connection and complete the private login popup.
   The owner enters provider credentials on the official provider website. Then
   choose that connection in chat. Account eligibility and usage limits still apply.
4. Explain that admin address, username, password and floating chat can be changed
   in Settings. Do not require either AI CLI for ordinary building.

If setup is already complete, preserve it. If the script detects old named-volume
storage, stop before starting an empty database: follow `data-backup`, explain the
migration effect, and verify a backup/move first. Do not bypass this check.

Never print `.env`, encryption keys, API keys, passwords or setup codes in assistant
messages. The startup script may display the code privately in the owner's terminal.
Use `--no-open --quiet-code` for automated tests and read a synthetic test code only
inside the disposable test process. Keep the owner-facing URL free of secrets.

## Public starter and private website

Keep owner-created code in Git-ignored `application/website/`. Track only neutral
starter files in `application/templates/website/`; never copy custom content or
secrets into the template. Initialization copies the template only if `website/`
is absent. Public packages/clones exclude the active website; private backups and
explicit app deployments include it. Preserve this boundary for both assistants.

For an already healthy running app, start.sh only reopens its current admin page.
Use `make publish` or `start.sh --rebuild` to apply updates. Use `application/bin/stop.sh` to
stop the app and preserve storage; its separate Docker-quit prompt warns that other
apps may be interrupted and requires `yes`. Never pre-answer that prompt without
explicit authorization to stop Docker itself. Shared guidance applies to both AIs.

## Computer-specific launchers

Mac owners can double-click Start.command/Stop.command in `Start and Stop/macOS/`. Windows owners use
Start.cmd/Stop.cmd in `Start and Stop/Windows/` (or the PowerShell equivalents) inside their WSL2 Linux home
checkout. Windows start installs missing Docker Desktop, uses shared shell logic
in that exact checkout and opens the Windows browser. WSL2/Python and Docker WSL
integration are prerequisites; follow README. Never silently copy or move the app,
accept provider/software terms, or pre-confirm shutting down Docker.

After the owner creates their account, guide them to Settings to enable an
authenticator and save its one-use recovery codes. Ask them to download the separate
backup recovery key into their own safe storage, away from backups. Explain the
loss-of-key consequence. Never collect either secret in conversation or enroll on
the owner's behalf. Both steps are available in the browser.
