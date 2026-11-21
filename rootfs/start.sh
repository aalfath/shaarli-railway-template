#!/bin/sh
# Start Shaarli (php-fpm + nginx) for Railway.
# - Seeds data/config.json.php from SHAARLI_USERNAME / SHAARLI_PASSWORD before anything
#   listens, so Shaarli's install page (which gives the instance to the first visitor)
#   is never reachable.
# - Supervises both processes: if either stops unexpectedly the container exits 1, so
#   Railway's ON_FAILURE restart policy restarts it. SIGTERM (redeploy) exits 0.
set -eu
log() { echo "[start] $*"; }

PORT="${PORT:-8080}"
case "$PORT" in ''|*[!0-9]*) log "PORT must be a number, got '$PORT'"; exit 1 ;; esac

APP=/var/www/shaarli
DATA="$APP/data"
mkdir -p "$DATA/thumbnails" "$APP/tmp" "$APP/pagecache"
if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ] && [ "$RAILWAY_VOLUME_MOUNT_PATH" != "$DATA" ]; then
  log "WARNING: volume mounted at $RAILWAY_VOLUME_MOUNT_PATH, but Shaarli keeps its data in $DATA"
fi
# A new Railway volume is owned by root; Shaarli runs as nginx.
if [ "$(stat -c %U "$DATA")" != nginx ]; then
  log "taking ownership of $DATA"
  chown -R nginx:nginx "$DATA"
fi
chown nginx:nginx "$DATA/thumbnails" "$APP/tmp" "$APP/pagecache"

# Seed the configuration (or reset the admin login when its variables changed).
cd "$APP"
php84 /usr/local/share/shaarli/bootstrap.php
chown -R nginx:nginx "$DATA/config.json.php" "$DATA/.railway-admin" "$DATA/sessions"

sed "s|\${PORT}|$PORT|g" /etc/railway/nginx.conf.template > /etc/nginx/nginx.conf
nginx -t -q
rm -f /var/run/nginx.pid /var/run/php-fpm.sock 2>/dev/null || true

php-fpm84 -F & FPM=$!
nginx & NGX=$!
log "Shaarli $(sed -n 's/.*\/\* \(.*\) \*\/.*/\1/p' $APP/shaarli_version.php) listening on port $PORT"

STOPPING=0
stop() {
  STOPPING=1
  log "stopping"
  kill -QUIT "$NGX" 2>/dev/null || true
  kill -QUIT "$FPM" 2>/dev/null || true
}
trap stop TERM INT

while :; do
  if ! kill -0 "$FPM" 2>/dev/null || ! kill -0 "$NGX" 2>/dev/null; then
    break
  fi
  sleep 2 & wait $! 2>/dev/null || true
done

if [ "$STOPPING" = 1 ]; then
  wait "$NGX" 2>/dev/null || true
  wait "$FPM" 2>/dev/null || true
  log "stopped"
  exit 0
fi
kill -0 "$FPM" 2>/dev/null || log "php-fpm exited unexpectedly"
kill -0 "$NGX" 2>/dev/null || log "nginx exited unexpectedly"
kill -TERM "$FPM" "$NGX" 2>/dev/null || true
wait 2>/dev/null || true
exit 1
