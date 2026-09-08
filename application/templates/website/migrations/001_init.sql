-- ─────────────────────────────────────────────────────────────
--  Migration 001 — initial schema.
--
--  RULES (so schema stays in sync across servers, data never moves):
--    • Only DDL here: CREATE / ALTER / DROP TABLE, indexes, etc.
--    • NEVER put INSERT/UPDATE of real data here.
--    • Migrations are append-only: to change the schema, add a NEW
--      file (002_..., 003_...). Don't edit an applied one.
--    • Each file runs exactly once, in numeric order, tracked in the
--      schema_migrations table by bin/migrate.php.
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email       TEXT NOT NULL UNIQUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
