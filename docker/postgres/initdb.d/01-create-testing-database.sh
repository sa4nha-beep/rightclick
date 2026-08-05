#!/bin/sh
# RIGHTCLICK — basis data pengujian.
#
# Test suite berjalan di atas PostgreSQL 16, bukan SQLite. Alasannya mengikat:
# skema RIGHTCLICK bergantung pada CHECK constraint, indeks unik parsial,
# `numeric(18,2)`, `jsonb`, dan `SELECT ... FOR UPDATE` (DB Design C1–C15, R6, R7).
# Menguji di atas SQLite akan meloloskan pelanggaran yang ditolak produksi.
#
# Skrip ini hanya dijalankan sekali, saat volume data database masih kosong.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE ${POSTGRES_DB}_testing OWNER $POSTGRES_USER;
EOSQL
