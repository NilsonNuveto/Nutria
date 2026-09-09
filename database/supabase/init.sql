-- Execute uma vez no SQL Editor do Supabase antes das migrations do Laravel.
-- O schema dedicado evita expor as tabelas internas da aplicação pela Data API do schema public.
CREATE SCHEMA IF NOT EXISTS nutria;

REVOKE ALL ON SCHEMA nutria FROM anon;
REVOKE ALL ON SCHEMA nutria FROM authenticated;
