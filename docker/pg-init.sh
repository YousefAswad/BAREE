#!/bin/sh
# Local docker only: creates the non-owner app role before any table exists.
# Table grants and the append-only revokes run after migrations (scripts/install.sh → scripts/pg-init.sql).
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -v app_password="${APP_DB_PASSWORD:-app}" <<'SQL'
DO $$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'baree_app') THEN CREATE ROLE baree_app LOGIN; END IF;
END $$;
ALTER ROLE baree_app WITH LOGIN PASSWORD :'app_password' NOSUPERUSER NOBYPASSRLS;
CREATE EXTENSION IF NOT EXISTS vector;
GRANT USAGE ON SCHEMA public TO baree_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO baree_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO baree_app;
SQL
