#!/bin/sh
# RIGHTCLICK — entrypoint container aplikasi.
# Menyiapkan direktori runtime Laravel sebelum menjalankan perintah apa pun.
# Tidak menjalankan migration otomatis: G3/D5 melarang perubahan skema tak disengaja,
# migration dijalankan eksplisit sebagai bagian deploy (HS-ARCH bagian 9.3).
set -e

APP_DIR=/var/www/html

if [ -d "$APP_DIR" ]; then
    mkdir -p \
        "$APP_DIR/storage/app/private" \
        "$APP_DIR/storage/app/public" \
        "$APP_DIR/storage/framework/cache/data" \
        "$APP_DIR/storage/framework/sessions" \
        "$APP_DIR/storage/framework/testing" \
        "$APP_DIR/storage/framework/views" \
        "$APP_DIR/storage/logs" \
        "$APP_DIR/bootstrap/cache"

    # Bind mount Windows mengabaikan chown; kegagalan di sini tidak boleh
    # menghentikan container.
    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
fi

exec "$@"
