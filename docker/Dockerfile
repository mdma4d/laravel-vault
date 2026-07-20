# Test image for a single PHP version. The Laravel / Testbench versions and the
# dependency mode (latest vs --prefer-lowest) are chosen at run time via env
# vars, so one built image per PHP version can test every matrix row.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

# Reliable extension + Composer installer.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        mbstring \
        pdo_sqlite \
        sqlite3 \
        intl \
        bcmath \
        zip \
        @composer

# Composer runs as root inside the container.
# The matrix deliberately installs end-of-life Laravel 8-11, whose releases
# carry security advisories (and pull abandoned packages such as swiftmailer).
# This is a compatibility test harness, not a deployment, so Composer's install
# blocking is disabled here (both the current and legacy env var are set to
# cover every Composer version).
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_NO_BLOCKING=1 \
    COMPOSER_NO_SECURITY_BLOCKING=1 \
    SRC=/src

WORKDIR /app

COPY run-tests.sh /usr/local/bin/run-tests
RUN chmod +x /usr/local/bin/run-tests

ENTRYPOINT ["run-tests"]
