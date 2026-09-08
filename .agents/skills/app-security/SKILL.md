---
name: app-security
description: >
  Security rules the assistant MUST apply to THIS app (vanilla PHP 8.3 + PDO/Postgres
  + Caddy + Docker). Use this skill automatically whenever adding or editing any
  feature that touches user input, forms, database queries, authentication,
  sessions, file uploads, output/HTML, routes, env/secrets, or Docker/Caddy
  config. The owner is non-technical and relies on the assistant to keep the app safe
  from common web attacks (SQL injection, XSS, CSRF, secret leaks, etc.).
---

# App Security — non-negotiable rules

Read `application/docs/PRODUCT-CONTRACT.md` first. Its browser admin, isolated AI building, local
bind storage, public packaging, and plain-language data-warning rules apply to
every app made from this stack. Before major data effects, explain the specific
records affected and recovery plan, obtain confirmation, and verify a backup;
stop if that backup fails. Routine safe edits need no additional approval.


> Part of your broader role as the owner's build partner — see the
> [`app-builder`](../app-builder/SKILL.md) skill for how to collaborate, advise,
> and design. This file is the concrete security ruleset.

The owner of this app is **not a security expert**. You (Codex or Claude Code) are responsible
for making every change secure by default. Apply this whole checklist on any
change that touches input, output, queries, auth, sessions, uploads, config, or
secrets. When in doubt, choose the safer option and briefly tell the owner what
you protected against in plain language.

Admin helpers live in `application/admin/app/Security.php` (`App\Security`). The
isolated website cannot load admin files: put equivalent site-specific security
helpers in `application/website/app/` when adding forms/auth. Never mount the admin
code, keys, sessions or Docker socket into the generated website runtime.

## 1. SQL injection — always parameterize
- NEVER build SQL by concatenating or interpolating variables.
- ALWAYS use prepared statements via `App\Database::pdo()`.

```php
// ✅ correct
$stmt = \App\Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);

// ❌ never
$pdo->query("SELECT * FROM users WHERE email = '$email'");
```
- Table/column names can't be parameterized — if one must be dynamic, validate
  it against a hardcoded allow-list, never pass user input through.

## 2. XSS — escape everything you print
- Any value that ends up in HTML MUST go through `Security::esc()`.

```php
echo '<p>Hello, ' . \App\Security::esc($name) . '</p>';
```
- Never `echo` raw request data (`$_GET`, `$_POST`, DB text written by users)
  into a page. JSON responses via `json_encode` are safe; raw HTML is not.

## 3. CSRF — protect every state-changing form
- Every `POST`/`PUT`/`DELETE` form must include a CSRF token and verify it.

```php
// In the form:
echo '<form method="post" action="/save">';
echo \App\Security::csrfField();          // hidden token input
// ...
echo '</form>';

// In the handler (first thing):
if (!\App\Security::verifyCsrf($_POST['_csrf'] ?? null)) {
    http_response_code(419);
    exit('Invalid request');
}
```

## 4. Passwords & auth — never store plaintext
- Hash with `Security::hashPassword()`, check with `Security::verifyPassword()`.
- Never log passwords, tokens, or secrets. Never put them in URLs.
- Use `Security::startSession()` for sessions (httponly + SameSite cookies).
- Regenerate the session id on login: `session_regenerate_id(true)`.

## 5. Input validation
- Treat all `$_GET`/`$_POST`/headers/uploads as hostile until validated.
- Validate type, length, and format (e.g. `filter_var($e, FILTER_VALIDATE_EMAIL)`).
- Reject unexpected input rather than trying to "clean" it.

## 6. File uploads (if added)
- Store uploads OUTSIDE `public/` (never web-executable).
- Validate real MIME type and extension against an allow-list; cap file size.
- Generate a new random filename; never trust the client-provided name.

## 7. Secrets & config
- `.env` holds secrets and is git-ignored — never commit it, never print it,
  never echo env vars to a page.
- Use a long random `POSTGRES_PASSWORD`. Don't hardcode credentials in code.
- Keep `display_errors` off outside local (already handled in `bootstrap.php`).

## 8. Output & headers
- Caddy already sends baseline security headers (and HSTS in production) and
  denies dotfiles — see `docker/caddy/`.
- Only expose `admin/public/` for the platform and `website/public/` for the
  created app. Keep each side’s app code and migrations outside its public directory.

## 9. Docker / infra
- Use local bind mounts under `application/data/` for every persistent path;
  never introduce named or anonymous volumes, including proxy image defaults.
- Don't publish container ports to the host unless needed (DB stays internal).
- Keep base images patched (`postgres:16-alpine`, `php:8.3-fpm-alpine`, `caddy:2-alpine`).
- Run `docker compose pull` periodically to pick up security updates.

## CLI connections
- Keep the CLI service off public host ports and out of website networks. Authenticate
  internal requests with the per-app token; never inherit that token in CLI processes.
- Only allow fixed login/status/code-input/cancel/logout/generate operations. Require
  admin sessions and CSRF, with current-password checks to connect/disconnect.
- Keep login output ephemeral and tied to the initiating admin session. Render it
  as text; link only to official provider hosts. Never expose a general terminal.
- CLI credentials use private local storage, never shared with website code, API
  keys or admin database mounts. Restrict tools and validate every returned proposal.

## 10. Dangerous functions — avoid
- No `eval`, `exec`, `shell_exec`, `system`, `passthru` on user input.
- No `unserialize()` of user data; use `json_decode` instead.
- No `include`/`require` of a user-controlled path.

---

### When you finish a change, self-check:
1. Did any user input reach SQL without a prepared statement? → fix.
2. Did any user input reach HTML without `esc()`? → fix.
3. Does every state-changing form verify CSRF? → fix.
4. Are passwords hashed and secrets kept out of code/logs/URLs? → fix.

Then tell the owner, in one plain sentence, what was protected.

## Public starter and private website

Keep owner-created code in Git-ignored `application/website/`. Track only neutral
starter files in `application/templates/website/`; never copy custom content or
secrets into the template. Initialization copies the template only if `website/`
is absent. Public packages/clones exclude the active website; private backups and
explicit app deployments include it. Preserve this boundary for both assistants.

## Automated checks, 2FA and database roles

Both assistants must preserve the offline security gate before proposal review and
apply. Keep rules in `application/docker/audit/`, fail closed on errors, and never
use a passing scan to claim code is fully audited. Test representative unsafe and
safe code when changing the gate.

Preserve TOTP challenge/session boundaries, single-use counters and recovery codes,
rate limits and password confirmation. Never enroll an authenticator for the owner
or print their secret/recovery codes. The owner enables it in Settings.

`app_runtime` is a CRUD-only website role; `app_migrator` owns schema and is available
only to the worker. Never give the website migration credentials. Reapply runtime
grants and protect `builder_migrations` after migrations and restores.
