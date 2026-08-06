#!/bin/bash
# RIGHTCLICK — uji restore bulanan (CLAUDE.md §14: "uji restore bulanan").
#
# Backup yang tidak pernah diuji restore-nya bukan backup, hanya asumsi.
# Skrip ini mendekripsi dump terbaru, memulihkannya ke database sementara,
# menjalankan pemeriksaan sanity, lalu membuang database sementara tersebut.
#
# Dijalankan oleh cron (lihat entrypoint.sh). Verifikasi manual:
# `docker compose exec backup restore-test`.
set -euo pipefail

[ -f /etc/rightclick-backup.env ] && source /etc/rightclick-backup.env

BACKUP_DIR="${BACKUP_DIR:-/backups}"
LOG_FILE="$BACKUP_DIR/restore-test.log"

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-rightclick}"

RESTORE_TEST_DB="rightclick_restore_test"

log() {
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [restore-test] $*" | tee -a "$LOG_FILE"
}

fail() {
    log "GAGAL: $*"
    exit 1
}

cleanup() {
    rm -f /tmp/restore_test.dump
    PGPASSWORD="${DB_PASSWORD:-}" dropdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" --if-exists "$RESTORE_TEST_DB" 2>/dev/null || true
}
trap cleanup EXIT

latest_dump=$(ls -1 "$BACKUP_DIR/daily"/*.dump.gpg 2>/dev/null | sort | tail -n 1) || true

if [ -z "$latest_dump" ]; then
    fail "Tidak ada dump di $BACKUP_DIR/daily — belum pernah backup berhasil, tidak ada yang bisa diuji"
fi

log "Menguji restore dari: $latest_dump"

if [ -z "${BACKUP_ENCRYPTION_PASSPHRASE:-}" ]; then
    fail "BACKUP_ENCRYPTION_PASSPHRASE belum diisi — tidak dapat mendekripsi dump untuk diuji"
fi

if ! gpg --batch --yes --passphrase "$BACKUP_ENCRYPTION_PASSPHRASE" \
        --decrypt --output /tmp/restore_test.dump \
        "$latest_dump"; then
    fail "Dekripsi gagal — dump mungkin rusak atau passphrase salah. INI TEMUAN SERIUS: dump tersimpan tidak dapat dipulihkan."
fi

log "Dekripsi berhasil, membuat database sementara: $RESTORE_TEST_DB"

export PGPASSWORD="${DB_PASSWORD:-}"

dropdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" --if-exists "$RESTORE_TEST_DB" 2>/dev/null || true

if ! createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$RESTORE_TEST_DB"; then
    fail "Gagal membuat database sementara $RESTORE_TEST_DB"
fi

log "Memulihkan dump ke $RESTORE_TEST_DB..."

if ! pg_restore -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
        --no-owner --role="$DB_USERNAME" \
        -d "$RESTORE_TEST_DB" /tmp/restore_test.dump; then
    fail "pg_restore gagal — dump TIDAK DAPAT DIPULIHKAN. Ini temuan kritis, tindak lanjuti segera."
fi

log "pg_restore selesai, menjalankan pemeriksaan sanity..."

# Sanity check: tabel inti harus ada dan dapat di-query. Ini BUKAN
# pemeriksaan integritas data lengkap (checksum, row count terhadap
# sumber) — hanya memastikan dump benar-benar dapat dipulihkan menjadi
# database yang berfungsi, bukan berkas kosong atau rusak.
core_tables="branches users audit_logs document_sequences settings approvals"
missing_tables=""

for table in $core_tables; do
    exists=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$RESTORE_TEST_DB" -tAc \
        "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = '$table');")
    if [ "$exists" != "t" ]; then
        missing_tables="$missing_tables $table"
    fi
done

if [ -n "$missing_tables" ]; then
    fail "Tabel inti hilang setelah restore:$missing_tables — dump tidak lengkap"
fi

log "Semua tabel inti ditemukan setelah restore: $core_tables"
log "UJI RESTORE BERHASIL — dump $latest_dump terverifikasi dapat dipulihkan"
