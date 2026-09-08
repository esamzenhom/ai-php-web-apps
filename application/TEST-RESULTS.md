# Verification

Last full disposable Docker integration run: 2026-09-08. Tests use synthetic
accounts and provider responses, not private owner data. A passing test run is
not a security certification.

## Coverage

- First-run setup, single account claim, login, CSRF, throttling and session invalidation.
- Authenticator TOTP enrollment, replay prevention, one-use recovery codes and disabling.
- Encrypted and masked provider keys, account/password/admin-path changes and admin-only floating chat.
- API and CLI proposal adapters, queue idempotency, review, approval, file/schema apply, undo and interrupted recovery.
- Offline security scan acceptance/rejection, suppression resistance and failure-closed scanner outages.
- Restricted website database role, actual CRUD access and denied DDL, truncation, temporary tables and migration metadata access.
- Authenticated backup encryption, wrong-key/tamper/truncation rejection, legacy conversion and full-folder recovery.
- Reset archives excluding the recovery key, source preservation and unconfigured restart.
- Local bind storage, runtime isolation, persistence, repeated start and stop/restart.
- Source clone/archive privacy, launcher packaging and shared assistant skill links.

Browser checks covered desktop, tablet and phone layouts, settings, authenticator
controls, login popups, review/apply and floating chat. PHP/JavaScript/Python/shell
syntax and production Compose configuration were checked.

## Reproduce

From the repository root:

```sh
python3 application/tests/packaging.py
python3 application/tests/start-stop.py
node application/tests/home-chat.cjs
python3 application/tests/integration.py
```

The integration suite requires Docker and starts an isolated disposable stack.
Run `python3 application/tests/run-cli-tests.py` for isolated CLI adapter checks.
After starting the current checkout, `python3 application/tests/backup-maintenance.py`
uses its worker image with disposable storage for maintenance regression checks.
GitHub source checks run the lightweight packaging, launcher and chat tests only.

## Unverified boundaries

No real paid provider requests were made. Actual CLI account completion and model
availability depend on the owner's provider account. Fresh-machine Docker installs,
native Windows/WSL installation and Finder double-click were not exercised;
launcher logic was checked with mocks. Remote production deployment and automatic
public TLS were not tested.

The website browser sandbox limits cookie-based authentication and browser storage.
Applications needing those features require separate-origin design; see the
[technical guide](docs/TECHNICAL-GUIDE.md). Generated code still needs application-specific
review even when automated checks pass.
