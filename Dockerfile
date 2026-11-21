# Shaarli on Railway: the official image, pinned by version and digest, plus a small wrapper.
# Bump both the tag and the digest together, and add a CHANGELOG.md entry.
FROM ghcr.io/shaarli/shaarli:v0.16.10@sha256:48a1a8962c58c74c5c36d4927a7c2dcb5bf191a93b83dc480d9827f383ada903

COPY rootfs/nginx.conf.template /etc/railway/nginx.conf.template
COPY rootfs/php-fpm.conf        /etc/php84/php-fpm.conf
COPY rootfs/railway.ini         /etc/php84/conf.d/90-railway.ini
COPY rootfs/bootstrap.php       /usr/local/share/shaarli/bootstrap.php
COPY rootfs/healthz.php         /usr/local/share/shaarli/healthz.php
COPY rootfs/railway-proxy.php   /usr/local/share/shaarli/railway-proxy.php
COPY rootfs/start.sh            /usr/local/bin/start.sh

# Thumbnails go to data/thumbnails (on the volume) instead of cache/, so the picture
# wall survives a redeploy: Shaarli stores each thumbnail's path in the datastore.
RUN set -eux; \
    f=/var/www/shaarli/inc/web-thumbnailer.json; \
    sed -i 's|"cache": "cache/"|"cache": "data/thumbnails/"|' "$f"; \
    grep -q '"cache": "data/thumbnails/"' "$f"; \
    chmod 0755 /usr/local/bin/start.sh; \
    sh -n /usr/local/bin/start.sh; \
    php84 -l /usr/local/share/shaarli/bootstrap.php; \
    php84 -l /usr/local/share/shaarli/healthz.php; \
    php84 -l /usr/local/share/shaarli/railway-proxy.php; \
    php-fpm84 -t; \
    sed 's|${PORT}|8080|g' /etc/railway/nginx.conf.template > /etc/nginx/nginx.conf; \
    nginx -t; \
    grep -q '0.16.10' /var/www/shaarli/shaarli_version.php

ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/start.sh"]
