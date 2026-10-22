#!/usr/bin/env bash
# Server MySQL 8.4 khusus Kopi & Kita (127.0.0.1:3307), terpisah dari database sistem. Tidak butuh sudo.
# Pakai: scripts/db.sh init | start | stop | status | sql [argumen mysql]
set -euo pipefail

BASE="${KK_MYSQL_BASE:-/workspace/apps/mysql-8.4.13}"
DIR="${KK_MYSQL_DIR:-/workspace/.cache/mysql-kopi}"
SOCK="$DIR/mysqld.sock"
PID="$DIR/mysqld.pid"

running() { [ -f "$PID" ] && kill -0 "$(cat "$PID")" 2>/dev/null; }

case "${1:-status}" in
    init)
        if [ -d "$DIR/data" ]; then echo "Data sudah ada di $DIR/data."; exit 0; fi
        mkdir -p "$DIR"
        "$BASE/bin/mysqld" --no-defaults --initialize-insecure --basedir="$BASE" --datadir="$DIR/data" --log-error="$DIR/error.log"
        echo "Data MySQL dibuat di $DIR/data (root lokal tanpa password, hanya lewat socket)."
        ;;
    start)
        if running; then echo "Sudah berjalan (pid $(cat "$PID"))."; exit 0; fi
        setsid "$BASE/bin/mysqld" --no-defaults --basedir="$BASE" --datadir="$DIR/data" --socket="$SOCK" \
            --pid-file="$PID" --port=3307 --bind-address=127.0.0.1 --mysqlx=OFF --log-error="$DIR/error.log" \
            > /dev/null 2>&1 < /dev/null &
        for _ in $(seq 1 40); do running && [ -S "$SOCK" ] && break; sleep 0.5; done
        if running; then echo "MySQL berjalan di 127.0.0.1:3307."; else echo "Gagal start, lihat $DIR/error.log"; exit 1; fi
        ;;
    stop)
        if running; then kill "$(cat "$PID")"; echo "MySQL dihentikan."; else echo "Tidak berjalan."; fi
        ;;
    status)
        if running; then echo "Berjalan (pid $(cat "$PID"))."; else echo "Tidak berjalan."; fi
        ;;
    sql)
        shift
        # client tarball butuh libncurses.so.6; Arch hanya punya libncursesw, jadi diarahkan lewat symlink di lib/compat
        LD_LIBRARY_PATH="$BASE/lib/compat${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}" exec "$BASE/bin/mysql" -u root --socket="$SOCK" "$@"
        ;;
    *)
        echo "Pakai: $0 init | start | stop | status | sql"; exit 1
        ;;
esac
