# Stack requirements

These are the owner's requirements for this stack and every app created from it.
Both Codex and Claude Code must preserve them when implementing or updating an app.
A requirement in this file is not a claim that its feature already exists.
See README for the implemented runtime boundaries and validation evidence.

## Default visual theme

- Use a simple shadcn-style black-and-white theme for the welcome page, setup,
  sign-in, admin settings, AI chat, floating chat, and every generated starter app.
- Use white surfaces, near-black text and primary buttons, neutral gray secondary
  text/borders, system sans-serif typography, modest rounding, and visible focus.
- Keep layouts and copy concise. Avoid decorative hero sections, serif accents,
  gradients, colorful branding, and unnecessary badges or illustrations.
- Keep warnings and errors explicit with readable text, labels, and borders;
  never rely on color alone to communicate an important state.
- This is a visual default, not a requirement to install React or shadcn/ui.
  Preserve the lightweight vanilla PHP stack. Change the theme only when the
  owner explicitly requests a different design for their app.

## Local storage

- Persist all app data in local directories inside that app's checkout using
  bind mounts. Do not create Docker named or anonymous volumes for persistence.
- Include databases, uploads, sessions, AI chat history, generated files, logs,
  backups, and TLS state. Explicitly bind any image-declared persistent paths.
- Use `application/data/` for private runtime data and `application/backups/`
  for backups. Keep both outside `application/admin/public/` and excluded from Git,
  build contexts, release packages, and copies used to create a new app.
- Every app has its own data directories and identity; copies never inherit
  another app's records, administrator credentials, API keys, or encryption keys.
- Before converting existing Docker volumes, explain the impact, back up and
  migrate the contents, verify the new storage and live reads, and retain the
  original storage until the migration is proven. Never silently start an empty
  database in place of the owner's existing one.

## Browser administration and AI building

- Every new app starts with `/admin` as its administration address.
- Initial setup creates the owner's administrator account. Do not ship a default
  password or allow the first remote visitor to claim an unconfigured app.
- The administrator can add, replace, and remove their own Anthropic/Claude and
  OpenAI API credentials, select a supported provider/model, and build or modify
  the app through a conversational interface without installing either CLI.
- Offer admin-only Codex CLI and Claude Code CLI sign-in alongside API keys.
  Use official CLI login commands in a private popup with provider-hosted sign-in;
  never expose a general shell. Require admin authentication, CSRF protection and
  current-password confirmation for connection/disconnection. Keep login output
  ephemeral and scoped to the admin session that started it.
- Keep CLI credentials in app-local private bind storage, isolated from website
  code, API keys and admin data. Execute CLIs with tools disabled to return
  proposals through the same validation, review, backup and approval pipeline.
  Never silently fall back to a different billing method. CLI access depends on
  the owner’s provider account; do not promise subscription eligibility.
- Use provider APIs on the server. Keep provider naming and credential guidance
  accurate; never imply a chat subscription automatically supplies an API key.
- The chat must perform authorized project edits and report actual validation
  and results. A chatbot that only suggests code does not satisfy this requirement.
- Show progress, failures, and recoverable history. Report feature availability
  honestly when a provider is not configured or execution has not succeeded.
- Store API keys encrypted on the server with an app-specific encryption secret
  outside the public directory and Git. Never return saved keys to browsers,
  include them in prompts, or log them. Use masked status and replacement inputs.
- Authenticate and authorize every administration, chat, and editing operation.
  Use CSRF protection, login throttling, secure sessions, and password hashing.
- Give the AI bounded project tools, with path validation and protection against
  traversal and symlink escapes. Keep secrets, runtime data, and host resources
  outside its file-edit scope. Do not give public PHP unrestricted host shell
  access or mount the Docker socket to enable AI edits.
- Validate changes before applying them, retain a recovery path, and report what
  changed. Major data effects require the warning and confirmation below.

## Optional floating chat

- Add an administrator setting to enable or disable a floating chat button on
  the main app screen. Persist the setting and default it to disabled.
- When enabled, the popup uses the same protected conversation and editing
  backend as the admin page. Only authenticated administrators can see or use
  the building chat; ordinary visitors must never gain editing access.
- Hide and close the floating chat when admin authentication ends, including in
  already-open tabs. Recheck the session before opening; the enabled setting alone
  never grants visibility or access.
- Support mobile layouts, keyboard operation, clear close controls, and useful
  loading/error feedback without obstructing the app's main content.

## Administrator settings

- Allow the administrator to change their username and password and reassign
  the default `/admin` base path through settings.
- Require current-password verification for credential and admin-path changes,
  validate input, reject conflicting/reserved routes, and update navigation,
  forms, API paths, and the floating chat consistently.
- Explain and display the new address before changing it so the owner does not
  lose access. Invalidate other sessions after password changes. Changing the
  path never substitutes for authentication.

## Startup and first visit

- The application directory is named `application/`, replacing `Codex/`.
  Keep assistant-specific configuration at the repository root.
- Provide clear computer-specific Start launchers backed by `application/bin/start.sh` as the owner's first entry point. It checks
  prerequisites in plain language, creates missing local configuration safely,
  starts this app, applies pending schema migrations, checks readiness, and
  prints or opens the welcome page. Re-running it preserves data and identity.
- Install missing Docker on supported macOS and Ubuntu/Debian hosts, leaving OS
  password and Docker Desktop terms prompts to the owner. Start a stopped daemon.
- For a healthy running app, reopen its current admin address without rebuilding
  or applying migrations. Preserve an explicit rebuild/update command.
- Provide `application/bin/stop.sh` to stop this app while preserving all data. Offer to quit
  Docker afterward only with explicit confirmation and a warning about other apps;
  default to leaving Docker running. Never uninstall Docker or delete storage.
- Include macOS double-click `.command` launchers and Windows `.cmd`/PowerShell
  launchers. Windows uses the exact WSL2 Linux checkout and Windows browser; it
  installs missing Docker Desktop but never silently moves or copies app data.
  Preserve the same explicit Docker-shutdown confirmation on every platform.
- The opening home page welcomes the owner and asks them to set up administration
  before building. Its setup action leads to the initial `/admin` setup page.
- After setup, use the configured admin address, prevent setup from running
  again, and allow the owner to start building through the browser chat.

## Source folder separation

- Keep the created app entirely under `application/website/` (`public/`, `app/`,
  `migrations/`). Its AI-editable scope must never include the platform.
- Keep the protected platform and `/admin` UI under `application/admin/` (`app/`,
  `public/`, `migrations/`). Both folders are siblings, not mixed source trees.
- Shared startup/maintenance commands and Docker infrastructure stay at
  `application/bin/` and `application/docker/`. Runtime storage stays in the
  existing local `data/` and `backups/` directories. Folder changes must preserve
  account data, credentials, saved proposals and migration identities.

## Public repository

- Keep the active `application/website/` out of platform Git and public packages.
  Track only the neutral `application/templates/website/` starter. Startup copies
  it only when the active website is absent; existing custom source is preserved.
  Private backups and explicit app deployments must still include custom source.

- Keep the root owner-friendly: a short README, `Start and Stop/` grouped by
  computer, `application/`, and small AGENTS.md/CLAUDE.md assistant entrypoints.
  Put detailed technical/assistant/product documents in `application/docs/` and
  generated public archives in ignored `application/releases/`.
- Publish reusable source, schema-only migrations, example configuration, and
  accurate documentation. Exclude secrets, personal settings, real app data,
  backups, local activity/chat logs, client materials, and internal artifacts.
- Use neutral example content. Review tracked files and release contents before
  declaring the repository ready for public publication. Do not publish or
  rewrite Git history merely to reorganize local files.
- Update imports, scripts, mounts, hooks, instructions, skills, documentation,
  and clone/deploy exclusions together when moving folders. Verify both assistants
  still use the same canonical skills and progress journal.

## Explain major effects on data before acting

Treat the owner as non-technical. Before an operation that may delete, overwrite,
reset, expose, move, or materially transform their data:

1. Give a prominent plain-language warning naming the affected information,
   what will happen, any downtime/access change, and whether it is reversible.
2. Explain the backup/recovery plan and a safer alternative when available.
3. Get confirmation for that specific effect before performing it. A general
   request such as “update the app” is not consent to lose data. Existing explicit
   confirmation for the same explained effect does not need to be repeated.
4. Verify the backup before the risky operation; stop if a required backup fails.
5. Afterward, verify the outcome and tell the owner what was preserved or changed.

Example: “Warning: this reset removes all customer records and saved chats from
this app. I will first save and verify a backup so they can be restored. Your
app's source files will remain. Do you want to reset those records?”

Routine edits without a major data effect do not need this warning or an extra
confirmation. Apply the same rule in CLI assistance and the browser building chat.

## Security hardening

- Scan the complete proposed website locally before review and again before apply.
  Reject findings, parser failures and scanner outages. Keep scanner rules outside
  AI-editable source, disable suppression comments and network rule downloads.
  A passing automated scan is limited evidence, not a full security audit.
- Offer authenticator-app TOTP in admin Settings, with verified enrollment,
  encrypted secrets, short-lived login challenges, rate limits, replay prevention
  and one-use hashed recovery codes. Password-only sessions cannot administer
  an account with 2FA enabled. Enabling/disabling invalidates other sessions.
- Encrypt backup payloads with authenticated encryption. Keep the recovery private
  key in separate private local storage, excluded from every backup and reset
  archive. Offer password-confirmed recovery-key download (plus OTP when enabled).
  Warn that losing both the local and separately saved key makes recovery impossible.
- Website database credentials may read/write app rows but must not own tables,
  alter schema, truncate tables, manage roles or change migration bookkeeping.
  Only the isolated worker gets the schema-owner credential. Preserve grants after
  new migrations and restores, including upgrades of existing installations.
