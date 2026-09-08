#!/bin/sh
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set=site_password="$SITE_DB_PASSWORD" --set=migration_password="$MIGRATION_DB_PASSWORD" --set=app_database="$POSTGRES_DB" <<'SQL'
SELECT format('CREATE ROLE app_runtime LOGIN PASSWORD %L', :'site_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_runtime') \gexec
SELECT format('CREATE ROLE app_migrator LOGIN PASSWORD %L', :'migration_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_migrator') \gexec
BEGIN;
ALTER ROLE app_runtime NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
ALTER ROLE app_migrator NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
REASSIGN OWNED BY app_runtime TO app_migrator;
CREATE SCHEMA IF NOT EXISTS site AUTHORIZATION app_migrator;
ALTER SCHEMA site OWNER TO app_migrator;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE CREATE, TEMPORARY ON DATABASE :"app_database" FROM PUBLIC, app_runtime;
GRANT CONNECT ON DATABASE :"app_database" TO app_runtime, app_migrator;
GRANT CREATE, TEMPORARY ON DATABASE :"app_database" TO app_migrator;
REVOKE ALL ON SCHEMA site FROM app_runtime;
GRANT USAGE ON SCHEMA site TO app_runtime;
REVOKE ALL ON ALL TABLES IN SCHEMA site FROM app_runtime;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA site FROM app_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA site TO app_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA site TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE app_migrator IN SCHEMA site GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE app_migrator IN SCHEMA site GRANT USAGE, SELECT ON SEQUENCES TO app_runtime;
ALTER ROLE app_runtime SET search_path = site;
ALTER ROLE app_migrator SET search_path = site;
SELECT 'REVOKE ALL ON site.builder_migrations FROM app_runtime' WHERE to_regclass('site.builder_migrations') IS NOT NULL \gexec
COMMIT;
SQL
