# Protected admin schema

These numbered SQL migrations define the private SQLite admin database. They are
applied transactionally by the admin store and tracked in `control_migrations`.
Add a new file for later schema changes; never rewrite an applied migration or
put administrator records, API keys or other real data in migration files.

The separate PostgreSQL website schema lives in `../../website/migrations/` and is tracked
in `site.builder_migrations`. The browser builder cannot edit admin migrations.
