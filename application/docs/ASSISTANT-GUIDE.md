# Working on App Studio

This repository is a PHP app stack for a non-technical owner. Codex and Claude
Code are build partners: implement the request, verify it, and explain the result
in plain language. Read [PRODUCT-CONTRACT.md](PRODUCT-CONTRACT.md) before changing
behavior; it is the shared product requirement authority.

## Start and resume

Before new work, read `application/PROGRESS.md` and the tail of
`application/ACTIVITY-LOG.md` if present. Follow the shared `work-continuity` skill.
Keep the journal current through multi-step work and mark it IDLE when finished.

For a new checkout, use `bash application/bin/start.sh` from the root. It handles local configuration,
prerequisites, free ports, private bind storage, startup and migrations, then opens
the welcome page. Follow `app-setup` to help the owner create their admin account
in the browser. Never ask them to edit configuration or run an AI CLI to build.
Do not print or paste setup codes, passwords, API keys or private config into chat.

If the whole user message is `/start`, inspect progress and stack status read-only,
explain the state, and offer at most three useful next actions. Recommend resuming
unfinished work. Start/stop/change the stack only when the owner chooses that
operation; an explicit request to start or implement already supplies authorization.

## Layout and commands

Run `docker compose`, `make` and `bin/*` commands from `application/`.
The `application/bin/start.sh` handles its own directory. Preserve app isolation: never change
another checkout's containers, ports, local data, or configuration.

- `application/templates/website/`: tracked neutral starter; never copy owner content here.
- `application/website/`: Git-ignored private editable website (`public/`, `app/`, `migrations/`).
- `application/admin/`: protected platform (`app/`, `public/`, `migrations/`).
- `application/bin/`: shared startup, worker and maintenance commands.
- `application/data/`: local private runtime data (Postgres, admin SQLite, keys,
  sessions, uploads, proxy state). `application/backups/`: private recovery copies.
- `application/docker*`, `edge/`: isolated services and optional shared TLS proxy.
- `bash application/bin/start.sh`: start locally; `make down`: stop; `make backup`: verified private
  backup; `make migrate`: schema migrations; `make clone DEST=../../new-app`: copy
  public source to a new repo; `python3 bin/package.py`: public source archive.

`start.sh` installs missing Docker on macOS and Ubuntu/Debian, starts its service,
and reopens healthy apps without rebuilding. Use `make publish` or `--rebuild` for
updates. `stop.sh` stops only this app, then offers to quit Docker with an explicit
warning that other apps can be interrupted. Only `yes` authorizes quitting Docker.
Never accept Docker terms for the owner or remove stored data to stop the app.

macOS double-click launchers are `Start and Stop/macOS/Start.command` / `Stop.command`; Windows uses
`Start and Stop/Windows/Start.cmd` / `Stop.cmd` (PowerShell helpers under `application/bin/`). Windows dispatches
to the same shell lifecycle in the exact WSL2 Linux checkout, opens its browser
on Windows, and handles Docker Desktop quit confirmation there. Do not silently
copy the app to another workspace or use Windows-drive database storage. Keep all
launchers packaged, executable where needed, and preserve the LF shell line endings.

## Shared skills and future updates

`AGENTS.md` is the shared rulebook; `CLAUDE.md` imports it. Canonical skills live
in `.agents/skills/<name>/`. `.claude/skills/<name>` is a relative symlink to that
same directory, never an independent copy. Edit canonical skills once for both.
For a new skill, create its link from the root:
`ln -s ../../.agents/skills/<name> .claude/skills/<name>`.
Update links when skills are renamed or removed. Verify inventories and targets.

Keep tool-specific hooks in `.codex/hooks.json` and `.claude/settings.json`, both
calling shared `application/bin/` scripts. Change and verify both configurations
together when hook behavior changes. Keep documentation and supported commands
accurate for both assistants. The browser worker reads the same shared guidance.

## Implementation rules

- Default every interface and generated app to the simple shadcn-style
  black-and-white theme defined in PRODUCT-CONTRACT.md, unless the owner asks
  for another design. Preserve clear warnings and keyboard focus.

- Apply `app-builder` for product work, `app-security` for input, auth, code
  execution, secrets, output, routes or config, and `data-backup` for data changes.
- All persistence is in local bind-mounted directories; no named or anonymous
  Docker volumes. Explicitly bind image-declared persistence paths too.
- Never switch an existing database to empty bind storage. Back up, migrate,
  verify records/live reads, then retain the previous storage for recovery.
- Every app has an initial `/admin` setup, editable admin username/password/path,
  provider-key settings, browser AI building chat, and an admin-only floating chat
  controlled by a persistent setting. Preserve these through website changes.
- AI-generated website code runs separately from the admin. Keep admin keys,
  sessions, queue, settings, host shell and Docker socket out of its runtime.
  Keep the browser sandbox boundary; do not add `allow-same-origin` casually.
- Admins can use API keys or official Claude/Codex CLI sign-in. Preserve the
  private login popup, per-session login access, isolated CLI service and local
  credentials. Never expose a general terminal or let the CLI apply changes
  outside the reviewed proposal pipeline.
- Generated site code and SQL are proposals. Approval is tied to the persisted
  proposal; validate syntax/path/schema, reject stale changes, verify backups,
  and report actual results. Do not call a provider acceptance an applied change.
- Schema is code; data is not. Add new numbered DDL files under
  `application/website/migrations/` (website Postgres) or `application/admin/migrations/`
  (protected admin SQLite); never rewrite applied migrations or include
  real INSERT/UPDATE data. Use prepared SQL and output escaping.
- Startup creates `website/` from `templates/website/` only when absent; never overwrite
  an existing website. Public clones/packages exclude the active website (including
  its migrations). Explicit website deployment and private backups still include it.
- Public packages must exclude runtime data, keys, backups, private logs, client
  materials, personal config, and stale internal artifacts. Preserve symlinks.
  Generated public archives belong in ignored `application/releases/`, never at the root.
  Public-ready source does not authorize pushing, deploying, or rewriting history.

## Warn before major effects on the owner's data

Before deleting, overwriting, resetting, moving, exposing, or substantially
transforming data, give a clear warning naming the affected records/files, what
will happen, downtime/access effects, reversibility and the backup/recovery plan.
Offer a safer alternative when useful. Get confirmation for that specific effect
before executing it; general “update this” wording is not consent to data loss.
Do not repeat an existing explicit confirmation for the same explained effect.
Stop if a required backup fails. Verify the result and explain what was preserved.
Routine changes without a major data effect need no extra warning or permission.
Apply this rule equally in CLI work and the browser chat.

## Verification

Use a disposable source clone for account/provider/data tests, never the owner's
real records. `python3 application/tests/integration.py` covers the HTTP/worker
flow with synthetic provider outputs; it does not prove live API access. Run PHP
lint and JS syntax checks for changed files; verify browser layouts and interactions
on desktop and mobile. Inspect mount types and confirm no app Docker volumes.
Report unavailable provider credentials and other unverified boundaries plainly.
