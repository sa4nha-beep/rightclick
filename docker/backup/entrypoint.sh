#!/bin/bash
# RIGHTCLICK — entrypoint container `backup`.
# Membangun crontab dari variabel environment, lalu menjalankan crond
# di foreground sebagai PID 1 (siklus hidup container).
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/backups}"
mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/monthly" /etc/crontabs

# TZ wajib mengikuti jam operasional toko (Asia/Jakarta), BUKAN UTC —
# jadwal "02:00" harus berarti dini hari waktu Kudus, saat toko tutup,
# bukan 02:00 UTC yang jatuh pada jam sibuk siang hari (CLAUDE.md §14 D3:
# "Deploy manual dan disengaja per node. Tidak boleh pada jam operasional" —
# prinsip yang sama berlaku untuk beban backup terhadap database produksi).
export TZ="${RIGHTCLICK_DISPLAY_TIMEZONE:-Asia/Jakarta}"

SCHEDULE_DAILY="${BACKUP_SCHEDULE_DAILY:-0 2 * * *}"
SCHEDULE_RESTORE_TEST="${BACKUP_SCHEDULE_RESTORE_TEST:-0 3 1 * *}"

# busybox crond TIDAK menjamin proses yang dijadwalkannya mewarisi environment
# container secara utuh (berbeda dari fork() proses anak biasa) — daripada
# bergantung pada perilaku ini, variabel environment ditulis eksplisit ke
# berkas yang di-source langsung oleh backup.sh/restore-test.sh sendiri.
# `declare -p` (bukan `env`) memastikan nilai berkuotasi benar walau berisi
# spasi atau karakter shell khusus (mis. BACKUP_ENCRYPTION_PASSPHRASE).
{
    for var_name in $(compgen -v | grep -E '^(DB_|BACKUP_|RIGHTCLICK_)'); do
        declare -p "$var_name" 2>/dev/null || true
    done
} > /etc/rightclick-backup.env

{
    echo "$SCHEDULE_DAILY /usr/local/bin/backup >> $BACKUP_DIR/cron.log 2>&1"
    echo "$SCHEDULE_RESTORE_TEST /usr/local/bin/restore-test >> $BACKUP_DIR/cron.log 2>&1"
} > /etc/crontabs/root

echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [entrypoint] Jadwal backup harian: $SCHEDULE_DAILY (TZ=$TZ)"
echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [entrypoint] Jadwal uji restore bulanan: $SCHEDULE_RESTORE_TEST (TZ=$TZ)"

if [ -z "${BACKUP_ENCRYPTION_PASSPHRASE:-}" ]; then
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [entrypoint] PERINGATAN: BACKUP_ENCRYPTION_PASSPHRASE belum diisi — backup akan gagal sampai diisi di .env" >&2
fi

if [ -z "${BACKUP_OFFSITE_REMOTE:-}" ]; then
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') [entrypoint] PERINGATAN: BACKUP_OFFSITE_REMOTE belum diisi — backup hanya tersimpan lokal, wajib diisi sebelum Fase 1 selesai" >&2
fi

exec "$@"
