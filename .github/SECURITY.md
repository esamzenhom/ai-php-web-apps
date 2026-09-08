# Security

## Report a vulnerability

Do not post credentials, backups, exploit details or private website code in a
public issue. If GitHub shows **Security → Report a vulnerability**, use that
private reporting form. If private reporting is unavailable, open an issue asking
for a private reporting channel without including vulnerability details.
Never include live secrets in a report; use a minimal synthetic example.

## Security boundaries

The stack separates generated website code from administration services and uses
restricted website database permissions. AI proposals pass offline security checks
before review and application. These checks cover common patterns; they are not
a complete security audit or a guarantee that generated code is safe.

Enable authenticator two-factor sign-in in admin Settings, keep recovery codes
private, and store the backup recovery key separately from encrypted backups.
Review proposed changes before approving them. CLI sign-in is administrator-only.

Private files are excluded from Git and source packages. Ignore rules do not remove
files from older commits or protect files that were deliberately force-added.
Review the complete Git history before sharing an existing customized repository.
If a secret was exposed, revoke it; deleting the file alone is insufficient.

Only the latest main branch is maintained; there is no stated response-time
commitment. Internet-facing deployments and generated apps require their own
security review. Live provider access and fresh-machine installers have additional
verification limits documented in [test results](../application/TEST-RESULTS.md).
