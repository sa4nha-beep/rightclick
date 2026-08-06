#!/bin/bash
# RIGHTCLICK — pg_dump harian, terenkripsi sebelum meninggalkan server,
# retensi 30 hari / 12 minggu / 12 bulan, sinkronisasi off-site (CLAUDE.md §14).
#
# Dijalankan oleh cron di dalam container `backup` (lihat entrypoint.sh).
# Aman dijalankan manual untuk verifikasi: `docker compose exec backup backup`.
set -euo pipefail

# Dijalankan lewat cron (busybox crond tidak menjamin pewarisan environment
# container) — variabel dimuat eksplisit dari berkas yang ditulis entrypoint.sh.
# Saat dijalankan manual (`docker compose exec backup backup`), berkas ini
# sudah ada dari proses startup container, jadi tetap konsisten.
[ -f /etc/rightclick-backup.env ] && source /etc/rightclick-backup.env

BACKUP_DIR="${BACKUP_DIR:-/backups}"
LOG_FILE="$BACKUP_DIR/backup.log"

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-rightclick}"
DB_USERNAME="${DB_USERNAME:-rightclick}"

RIGHTCLICK_BRANCH_CODE="${RIGHTCLICK_BRANCH_CODE:-UNKNOWN}"

RETENTION_DAILY="${BACKUP_RETENTION_DAILY:-30}"
RETENTION_WEEKLY="${BACKUP_RETENTION_WEEKLY:-12}"
RETENTION_MONTHLY="${BACKUP_RETENTION_MONTHLY:-12}"

log() {
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [backup] $*" | tee -a "$LOG_FILE"
}

fail() {
    log "GAGAL: $*"
    exit 1
}

# Retensi berbasis JUMLAH berkas terbaru, bukan umur berkas (mtime) — dua
# terminologi ini gampang tertukar. "30 hari" berarti 30 dump terbaru, sama
# artinya karena dump dibuat tepat satu kali per hari, tetapi bila cron pernah
# terlewat sehari, retensi berbasis mtime akan salah membuang dump yang masih
# di dalam jendela 30 hari kalender.
prune_by_count() {
    local dir="$1"
    local keep="$2"

    [ -d "$dir" ] || return 0

    # Nama berkas berawalan timestamp (YYYYMMDD_HHMMSS) → urutan nama =
    # urutan waktu. `ls` tanpa opsi lain sudah cukup, tidak perlu `sort`.
    local files
    files=$(ls -1 "$dir"/*.dump.gpg 2>/dev/null | sort) || true
    local count
    count=$(echo "$files" | grep -c . || true)

    if [ "$count" -gt "$keep" ]; then
        echo "$files" | head -n "$((count - keep))" | while IFS= read -r old_file; do
            [ -n "$old_file" ] && rm -f "$old_file" && log "Retensi: menghapus $old_file"
        done
    fi
}

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/monthly"

timestamp=$(date -u '+%Y%m%d_%H%M%S')
filename="rightclick_${RIGHTCLICK_BRANCH_CODE}_${timestamp}.dump"
plain_path="/tmp/$filename"
encrypted_name="${filename}.gpg"

log "Memulai pg_dump — database=$DB_DATABASE host=$DB_HOST cabang=$RIGHTCLICK_BRANCH_CODE"

export PGPASSWORD="${DB_PASSWORD:-}"

if ! pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -Fc -f "$plain_path" "$DB_DATABASE"; then
    rm -f "$plain_path"
    fail "pg_dump gagal"
fi

dump_size=$(du -h "$plain_path" | cut -f1)
log "pg_dump selesai ($dump_size), mengenkripsi..."

if [ -z "${BACKUP_ENCRYPTION_PASSPHRASE:-}" ]; then
    rm -f "$plain_path"
    fail "BACKUP_ENCRYPTION_PASSPHRASE belum diisi — dump TIDAK BOLEH tersimpan tanpa enkripsi"
fi

# Enkripsi simetris (AES256) dengan passphrase, BUKAN keypair GPG asimetris.
# Trade-off yang disengaja: sebuah node cabang tak berawak menjalankan uji
# restore bulanan secara otomatis (lihat restore-test.sh) dan karenanya perlu
# mampu mendekripsi dump-nya sendiri tanpa campur tangan manusia. Skema
# asimetris (kunci publik saja di server, kunci privat off-site) akan
# mencegah verifikasi otomatis ini. Passphrase disimpan HANYA di environment
# container `backup` (S3) — tidak dibagikan dengan container `app`/`web`, dan
# harus berbeda dari kredensial database.
if ! gpg --batch --yes --passphrase "$BACKUP_ENCRYPTION_PASSPHRASE" \
        --symmetric --cipher-algo AES256 \
        --output "$BACKUP_DIR/daily/$encrypted_name" \
        "$plain_path"; then
    rm -f "$plain_path"
    fail "Enkripsi gpg gagal"
fi

# Dump mentah dihapus SEGERA setelah terenkripsi — tidak pernah ada jeda
# waktu di mana dump tak terenkripsi bertahan di disk lebih lama dari
# yang dibutuhkan proses enkripsi itu sendiri.
rm -f "$plain_path"
log "Enkripsi selesai: daily/$encrypted_name"

# Salinan mingguan (Minggu) dan bulanan (tanggal 1) — bukan dump terpisah,
# hanya salinan hard-link dari dump harian yang sama untuk menghemat ruang
# dan menghindari beban pg_dump berulang pada hari yang sama.
day_of_week=$(date -u '+%u') # 7 = Minggu
day_of_month=$(date -u '+%d')

if [ "$day_of_week" = "7" ]; then
    cp "$BACKUP_DIR/daily/$encrypted_name" "$BACKUP_DIR/weekly/$encrypted_name"
    log "Disalin ke weekly/ (hari Minggu)"
fi

if [ "$day_of_month" = "01" ]; then
    cp "$BACKUP_DIR/daily/$encrypted_name" "$BACKUP_DIR/monthly/$encrypted_name"
    log "Disalin ke monthly/ (tanggal 1)"
fi

prune_by_count "$BACKUP_DIR/daily" "$RETENTION_DAILY"
prune_by_count "$BACKUP_DIR/weekly" "$RETENTION_WEEKLY"
prune_by_count "$BACKUP_DIR/monthly" "$RETENTION_MONTHLY"

if [ -n "${BACKUP_OFFSITE_REMOTE:-}" ]; then
    log "Sinkronisasi off-site ke $BACKUP_OFFSITE_REMOTE..."
    # `sync` (bukan `copy`): remote menjadi cermin retensi lokal, termasuk
    # penghapusan dump yang sudah melewati retensi (D — off-site retensi
    # sama dengan lokal, bukan akumulasi tanpa batas).
    if rclone sync "$BACKUP_DIR" "$BACKUP_OFFSITE_REMOTE" \
            --exclude "backup.log" \
            --exclude "restore-test.log"; then
        log "Sinkronisasi off-site berhasil"
    else
        # Kegagalan sinkronisasi off-site TIDAK menggagalkan keseluruhan
        # backup — dump terenkripsi sudah aman tersimpan lokal. Ini dicatat
        # sebagai kegagalan agar terlihat di log, tetapi exit code proses
        # tetap 0 (backup lokal berhasil).
        log "PERINGATAN: sinkronisasi off-site gagal — dump lokal tetap tersimpan, coba lagi jadwal berikutnya"
    fi
else
    log "PERINGATAN: BACKUP_OFFSITE_REMOTE belum diisi — dump HANYA tersimpan lokal. Wajib diisi sebelum Fase 1 dinyatakan selesai (CLAUDE.md §14)."
fi

log "Backup selesai: daily/$encrypted_name ($dump_size terkompresi sebelum enkripsi)"
