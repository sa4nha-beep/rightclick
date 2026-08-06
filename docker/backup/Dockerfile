# syntax=docker/dockerfile:1
#
# RIGHTCLICK — image service `backup` (T1.12)
# Referensi: CLAUDE.md §14 "Backup", §18 tabel ketentuan T1.1.
#
# postgres:16-alpine dipakai (bukan image umum) agar pg_dump/pg_restore
# selalu SAMA PERSIS versinya dengan server database (16.x) — client dan
# server versi berbeda pada pg_dump/pg_restore adalah sumber kegagalan
# restore yang sulit dideteksi sampai saat dibutuhkan.
FROM postgres:16-alpine

# gnupg   → enkripsi dump sebelum meninggalkan server (S3, D-backup)
# rclone  → sinkronisasi off-site, mendukung banyak backend (S3, B2, SFTP, dst.)
#           tanpa mengunci RIGHTCLICK pada satu vendor cloud tertentu
# tzdata  → jadwal cron wajib mengikuti waktu operasional toko (Asia/Jakarta),
#           BUKAN UTC — 02:00 UTC jatuh pada jam sibuk toko di Kudus
# bash    → skrip backup.sh/restore-test.sh ditulis dengan bash, bukan ash/sh,
#           supaya idiom seperti "set -o pipefail" tersedia
RUN apk add --no-cache \
        bash \
        gnupg \
        rclone \
        tzdata

COPY docker/backup/backup.sh /usr/local/bin/backup
COPY docker/backup/restore-test.sh /usr/local/bin/restore-test
COPY docker/backup/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/backup /usr/local/bin/restore-test /usr/local/bin/entrypoint

WORKDIR /backups

ENTRYPOINT ["entrypoint"]
CMD ["crond", "-f", "-l", "2"]
