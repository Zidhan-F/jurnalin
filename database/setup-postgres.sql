-- Jalankan sekali sebagai superuser PostgreSQL. Password aplikasi dikirim lewat variabel,
-- jadi tidak pernah tersimpan di repo. Samakan nilainya dengan DB_PASSWORD di .env:
--   psql -U postgres -h 127.0.0.1 -v app_password=ISI_PASSWORD -f database/setup-postgres.sql
-- Membuat role aplikasi non-superuser tanpa BYPASSRLS, plus database dev dan test.
\set ON_ERROR_STOP on

SELECT format('CREATE ROLE jurnalin_app LOGIN PASSWORD %L NOSUPERUSER NOBYPASSRLS NOCREATEROLE NOCREATEDB', :'app_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'jurnalin_app')\gexec

SELECT format('ALTER ROLE jurnalin_app LOGIN PASSWORD %L NOSUPERUSER NOBYPASSRLS', :'app_password')\gexec

SELECT 'CREATE DATABASE jurnalin OWNER jurnalin_app ENCODING ''UTF8'''
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'jurnalin')\gexec

SELECT 'CREATE DATABASE jurnalin_test OWNER jurnalin_app ENCODING ''UTF8'''
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'jurnalin_test')\gexec

\c jurnalin
ALTER SCHEMA public OWNER TO jurnalin_app;
\c jurnalin_test
ALTER SCHEMA public OWNER TO jurnalin_app;
