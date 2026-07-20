#!/usr/bin/env bash
#
# In-container test runner. Copies the (read-only mounted) package into a
# writable work dir, selects the requested Laravel / Testbench versions,
# installs dependencies and runs composer validate + PHPUnit.
#
# Env vars:
#   LARAVEL    Laravel framework constraint, e.g. "11.*"  (optional)
#   TESTBENCH  Orchestra Testbench constraint, e.g. "9.*" (required with LARAVEL)
#   DEPS       "" for latest, "--prefer-lowest" for lowest deps
#   SRC        mount point of the package source (default /src)
#
set -euo pipefail

SRC="${SRC:-/src}"
APP="/app"
LARAVEL="${LARAVEL:-}"
TESTBENCH="${TESTBENCH:-}"
DEPS="${DEPS:-}"

echo ">> PHP $(php -r 'echo PHP_VERSION;')"
echo ">> Laravel='${LARAVEL:-<from composer.json>}'  Testbench='${TESTBENCH:-<auto>}'  deps='${DEPS:-latest}'"

# Copy only what we need so the host repo (and its vendor/) is never touched.
mkdir -p "$APP"
for item in composer.json phpunit.xml.dist src tests; do
    if [ -e "$SRC/$item" ]; then
        cp -a "$SRC/$item" "$APP/"
    fi
done
cd "$APP"
rm -rf vendor composer.lock

composer validate --strict

if [ -n "$LARAVEL" ]; then
    composer require \
        "laravel/framework:${LARAVEL}" \
        "orchestra/testbench:${TESTBENCH}" \
        --dev --no-update --no-interaction
fi

# shellcheck disable=SC2086
composer update $DEPS \
    --prefer-dist --no-interaction --no-progress --with-all-dependencies

composer show laravel/framework orchestra/testbench phpunit/phpunit || true

vendor/bin/phpunit
