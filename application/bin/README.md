# Operational scripts

Run from `application/`, or start with `bash application/bin/start.sh` from the root.

- `ensure-docker.sh`: install/start Docker on supported hosts; keep OS/terms prompts visible.
- `docker-access.sh`: temporary per-command sudo access for a local Linux daemon.
- `open-app.py`: check running services and open the current admin/setup address.
- `init-app.py`: idempotent local identity, free port and strong-secret setup.
- `prepare.php`: initialize private local directories and setup/encryption keys.
- `worker.php`: persistent provider/proposal/apply/undo queue.
- `migrate.php`: new schema-only migrations in `website/migrations/`.
- `backup.sh`: verified private database/admin/source/site-data backup.
- `restore.sh`: warned and confirmed website-database restore with safety backup.
- `reset.sh`: warned and confirmed reset; archives data/config instead of erasing.
- `package.py`: clean independent clone or public-source tar archive.
- `deploy.sh`: confirmed remote source/schema update; stops if live backup fails.
- `onboard-check.sh`, `log-activity.py`: shared Codex and Claude hooks.

Do not publish private recovery folders, keys, actual environment files or logs.
