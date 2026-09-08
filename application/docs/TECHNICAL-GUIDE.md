# App Studio

A small, self-hosted PHP app you build through a conversation. Start it, create
your private admin account, connect Claude or Codex through CLI sign-in or an API key, and describe
what you want. Review proposed website changes before applying them.

## Start here

For everyday use, follow the [owner guide](../../README.md). Launchers are grouped
by computer under `Start and Stop/`. Paths below are relative to the repository root.

For terminal use, run `bash application/bin/start.sh`; use `--rebuild` for platform
updates, `--no-open` to skip opening a browser and `--quiet-code` for automated tests.
`bash application/bin/stop.sh` stops the app and asks separately before quitting
Docker. From `application/`, `make up`, `make down` and `make publish` remain available.

Python 3 is required. Startup installs missing Docker Desktop on macOS or Docker
Engine/Compose on Ubuntu and Debian. The owner completes OS password and Docker
terms prompts. Existing healthy apps reopen at their current admin address.

Windows uses a WSL2 Ubuntu/Debian checkout inside the Linux home directory, visible
in File Explorer at `\\wsl.localhost\Ubuntu\home\<user>\<project>`. Enable the
chosen distribution in Docker Desktop's WSL Integration settings. Windows-drive
checkouts are rejected to preserve Linux storage permissions. WSL2 and Python must
be installed first; Windows launchers can install missing signed Docker Desktop.
No workspace is silently moved/copied and no reboot is forced.

Official installation references: [macOS](https://docs.docker.com/desktop/setup/install/mac-install/),
[Ubuntu](https://docs.docker.com/engine/install/ubuntu/),
[Windows](https://docs.docker.com/desktop/setup/install/windows-install/).

## What you can do

- Build PHP pages, HTML/CSS/JavaScript, forms and schema-backed features in chat.
- Review complete before/after files and a plain-language impact explanation.
- Apply a change after confirmation and password verification. The worker validates
  paths/PHP/schema, saves recovery copies and applies the actual files.
- Undo a change with an explicit warning: this restores the earlier database,
  removing newer website records. A fresh recovery copy is saved first.
- Change the admin username, password and default `/admin` address in Settings.
- Enable an administrator-only floating building chat on the main app screen.
- Switch between saved OpenAI and Anthropic credentials; keys are encrypted on the
  server and never returned to the browser.

API access is separate from chat subscriptions. Enter a model ID available to your
API account that supports structured output. See the provider documentation for
[OpenAI structured output](https://developers.openai.com/api/docs/guides/structured-outputs)
and [Claude structured output](https://platform.claude.com/docs/en/build-with-claude/structured-outputs).

## Boundaries you can rely on

The browser builder edits `application/website/`; it cannot edit the admin system,
provider secrets, host configuration, Docker files, or shared assistant rules.
It is an app builder with bounded file/schema tools, not an unrestricted terminal.
Infrastructure changes remain available through repository edits with Codex/Claude.

The website runs in a separate container with a restricted database role, and its
pages are sandboxed in the browser. It cannot read the admin session or API keys.
Website URLs are served beneath `/_site/`; use relative URLs in generated pages.
The browser sandbox limits cross-page scripting, browser storage, and cookie-based
login flows. Do not remove it to enable a feature: separate website/admin origins
are needed before supporting those advanced integrations safely.

Changes are limited to 12 files per proposal, 120 KB per file and a 500 KB / 150-file
website context. Schema tools support ordinary table/index/type DDL, not arbitrary
SQL, triggers, shell commands, or direct editing of customer records. PHP syntax
and transactional schema checks are automatic. An offline Semgrep service scans the
complete proposed source before review and again before apply; findings or scanner
errors block changes. Its bundled PHP/JavaScript rules catch common execution, SQL
injection, XSS and unsafe-path patterns, but cannot prove all code safe. Functional
tests and human security review still matter.
Real provider calls require your credentials; automated tests use synthetic output.

## Files and storage

```text
README.md                short owner guide
Start and Stop/          macOS, Windows and Linux Start/Stop launchers
AGENTS.md / CLAUDE.md     assistant entrypoints
.agents/skills/          canonical shared skills
.claude/skills/          relative links to those skills
application/
  docs/                  technical guide, assistant guide and product requirements
  templates/website/     public neutral starter
  website/               private created app (Git-ignored)
  admin/                 protected platform
  bin/                   startup, maintenance and PowerShell helpers
  docker/ edge/          service configuration
  data/                  private local runtime storage (ignored)
  backups/               private recovery copies (ignored)
  releases/              generated public archives (ignored)
  tests/                 isolated checks
```

All service persistence, including Postgres and proxy state, uses local directories;
no Docker named or anonymous volumes are created. Do not delete `data/` to fix a
startup problem. Existing older Docker-volume installations must be backed up,
migrated and verified before changing storage; startup stops if it detects them.

From `application/`:

```sh
make down                       # stop; keep all data
make backup                     # verified private database/admin/source/file backup
make migrate                    # apply pending schema-only site migrations
make clone DEST=../../my-new-app # new repo, without data/keys or old app identity
python3 bin/package.py           # application/releases/app-studio.tar.gz: public source only
```

Backups encrypt all payloads with authenticated encryption and verify recovery
before success. The separate recovery private key stays in `data/recovery/`, never
in backup/reset archives. Download it through admin Settings and keep it separately
from off-device backups. See [recovery instructions](RECOVERY.md). The `.env` also
needs a separate private backup. No automated retention deletes recovery copies.

## Developing the stack with Codex or Claude Code

Start `codex -C .` or `claude` at the root. Both read the same instructions and
skills, update the shared journal, and follow the same data-impact warning rules.
Edit `.agents/skills/` once; the Claude links reflect updates automatically. New,
renamed or removed skills must update those links in the same change.

The main services are Caddy, admin PHP-FPM, the job worker, a private CLI runner, an offline security scanner, an internal site gateway,
an isolated PHP website runtime, and Postgres. The generated site cannot reach
admin PHP-FPM directly: the gateway separates their networks. Only Caddy publishes
a local host port; database and worker ports stay private.

The website runtime uses PHP-FPM behind its own internal Caddy gateway. Before
public production use, verify HTTPS, backups, availability and load for the intended
workload. The optional `edge/` overlay is source configuration; live TLS/domain
routing is not exercised by the local tests.

## Validation and public release

```sh
python3 application/tests/integration.py
python3 application/tests/backup-maintenance.py # after starting this checkout
```

This creates a disposable checkout with its own local data and services, then tests
setup, authentication, settings, encryption, both provider-response decoders, queued
changes, approval, backups, apply, undo and stale-edit protection. It does not make
paid provider calls. It also exercises TOTP/recovery-code replay rejection, encrypted
backup tampering, legacy role upgrades, runtime permission denials and scanner
fail-closed behavior. A failed or timed-out generation is not retried automatically.

The release command copies only reusable source and examples, preserving skill
links and excluding local data, keys, logs, backups and private artifacts. It does
not push to GitHub, deploy a site or sanitize old Git history. Review the archive
before publishing it; private material from older commits may still exist in history.

### Admin CLI connections

In **Admin → Settings → Use your CLI account**, choose Codex CLI or Claude Code
CLI, enter your current admin password, and open the login popup. Follow its
provider link and complete sign-in yourself. Codex displays a device code; Claude
may ask you to paste the one-time code returned by its login page. Close the
popup after it reports connected, then select that CLI in the chat assistant menu.
You can retain API keys as separate options; there is no automatic fallback.

The account must support the chosen CLI and its usage limits/billing apply.
[Codex device sign-in](https://learn.chatgpt.com/docs/auth) may need enabling in
account/workspace security settings. [Claude authentication commands](https://code.claude.com/docs/en/cli-reference)
use the official CLI. The popup is for login only, not a shell.

The stack installs pinned official CLIs in a private container. Credentials live
only in `application/data/cli/` (private local bind storage), never in the generated
website container or public packages. Its internal endpoint has no host port and
requires an app-specific secret. Both CLI modes return proposals with tools
restricted; review, warnings, validation, backups and undo still apply. Disconnect
in Settings to sign this app out. Other copies have independent login storage.

CLI login caches are **not** included in the standard application backup: sign in
again after restoring to a new machine. Do not manually publish or share that
folder. No live account sign-in or paid model generation is performed by tests;
the account owner must complete those steps.

### What stays private

`application/website/` contains your custom app and is excluded from platform Git,
public archives and new-app clones. Startup creates it from the tracked neutral
`application/templates/website/` only when missing; restarting never replaces your
existing website. Keep custom code in private backups or a separate private repository.

Credentials, databases, uploads and CLI sign-ins remain in ignored `.env`, `data/`
and `backups/`. Shared admin source, scripts, example configuration and Codex/Claude
skills remain public. Never put credentials or client content in starter templates.
Ignore rules do not remove previously committed files or erase Git history.

`make clone` and the public archive create a fresh platform, not a copy of your
custom app. Private backups and an explicitly requested `make deploy` still include
your website so recovery and deployment work. Review the destination before deploying.

## Admin and database protection

Enable **Two-factor sign-in** in admin Settings with an authenticator app. The
owner must verify a code before activation and save the one-use recovery codes.
TOTP secrets are encrypted; login challenges expire after five minutes, codes
cannot be replayed, and failed attempts are rate limited. Changing 2FA invalidates
other sessions. Password confirmation protects setup/removal and recovery-key export;
export also requires a code when 2FA is enabled.

The website uses `app_runtime`, with row read/write permissions only. The isolated
worker uses `app_migrator` for approved schema migrations. Runtime cannot own/alter
schema, truncate tables or edit migration records. Ordinary website CRUD still
requires application-level authorization; this role split does not provide row-level
access control or prevent a vulnerable site from misusing permitted data access.
Startup transfers legacy runtime-owned objects without recreating tables or rows.

The scanner has no public ports, uses only an internal network, runs as an
unprivileged user with a read-only root filesystem, and has CPU/memory/process limits.
Rules are versioned in `docker/audit/rules.yml`; update and test them alongside code.
Neither API output nor CLI output bypasses the same approval and scan pipeline.
