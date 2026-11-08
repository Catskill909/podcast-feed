# PodFeed Studio: nginx + PHP-FPM on the official PHP image.
# Replaces the Nixpacks build, which installed PHP 8.3.15 from Nix and could
# not be upgraded past it (PHP2Shell, CVE-2026-109618 / CVE-2026-109619).
FROM php:8.3.36-fpm-bookworm

# Refuse to build on a PHP without the PHP2Shell fix. Guards against the tag
# above being changed to something older, or a stale cached base image.
RUN php -r 'if (version_compare(PHP_VERSION, "8.3.36", "<")) { fwrite(STDERR, "PHP " . PHP_VERSION . " is missing the PHP2Shell fix (needs >= 8.3.36)\n"); exit(1); }'

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx \
 && rm -rf /var/lib/apt/lists/* \
 && rm -f /etc/nginx/sites-enabled/default \
 && docker-php-ext-install opcache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

# Same path as under Nixpacks: Coolify volumes and the scheduled task
# (php /app/cron/auto-scan-feeds.php) both point here.
WORKDIR /app
COPY . /app

EXPOSE 3000
CMD ["start-app"]
