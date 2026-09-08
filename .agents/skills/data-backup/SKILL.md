---
name: data-backup
description: Protect this app's local database, files, admin account, API keys and chat history before backups, restores, migrations or operations with a major data effect.
---

# Data protection

Follow `application/docs/PRODUCT-CONTRACT.md` for storage and data-impact warnings. All persistence
uses local bind directories under `application/data/`; Docker named/anonymous
volumes are prohibited. Backups live in ignored `application/backups/`.

Before a request deletes, overwrites, resets, moves, exposes or materially changes
records, explain exactly what information is affected, downtime/access effects,
reversibility and the recovery plan in plain language. Get confirmation for that
specific effect. A general update request is not consent to data loss; don't repeat
an already explicit confirmation. Stop if the required backup fails.

From `application/`, run `make backup`. It verifies a custom PostgreSQL dump and
saves admin SQLite, the API encryption key, website source and private website files inside authenticated encrypted .enc payloads. The separate recovery private key stays in data/recovery and must never be included in backups.
These recovery folders are sensitive; never commit, publish or send them to an AI.
Keep the corresponding `.env` safely outside Git too; it is not in this backup.

Chat apply creates verified database and source backups before changes. Chat undo
restores the earlier website database and source, which removes newer records; it
requires the displayed warning, explicit confirmation and the current password,
and creates another recovery copy first. It does not restore uploads or admin data.

`make restore FILE=backups/<folder>/database.dump.enc` restores website database records
only, after warning, confirmation and a fresh verified backup. Full admin recovery
requires stopping the app and restoring the matching SQLite/key pair together;
never replace one without the other. Don't overwrite the only current copy.

`make reset` warns, confirms, requires a successful backup, stops this app, and
encrypts and verifies existing data/config in a private recovery folder before removing active state. It keeps data/recovery outside the archive. It preserves source.

For legacy Docker-volume migrations, discover exact project labels and mounts,
back up and migrate before switching mounts, verify records and live reads, and
retain the old volume until recovery is proven. Never use `docker compose down -v`
or remove another app's storage. Ordinary stops preserve local directories.

Local backups do not protect against loss of the machine. Discuss an encrypted
off-device copy when real data matters; only send it to an owner-approved location.

See `application/docs/RECOVERY.md` for separate key export/import, verified legacy
conversion and decrypt-only recovery. Never delete plaintext legacy files until
ciphertext round-trip verification succeeds. Never rotate the recovery key merely
because the app is reset. Warn the owner to keep an off-device key separately from
backups; losing every copy makes the encrypted backups unrecoverable.
