#!/usr/bin/env bash
#
# Run the full Laravel 8-13 compatibility matrix locally with Docker.
# One image is built per PHP version; every matrix row (lowest + latest deps)
# is then executed inside the matching image against the mounted package.
#
# Usage:
#   docker/test-matrix.sh            # run every row
#   docker/test-matrix.sh 13         # run only rows whose Laravel major is 13
#
set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
DOCKER_DIR="$REPO/docker"
IMG_PREFIX="laravel-vault-test"
FILTER="${1:-}"

# Mirrors .github/workflows/tests.yml.
# Format: PHP | LARAVEL | TESTBENCH | DEPS | LABEL
ROWS=(
    "8.0|8.*|6.*|--prefer-lowest|Laravel 8  lowest"
    "8.1|8.*|6.*||Laravel 8  latest"
    "8.0|9.*|7.*|--prefer-lowest|Laravel 9  lowest"
    "8.2|9.*|7.*||Laravel 9  latest"
    "8.1|10.*|8.*|--prefer-lowest|Laravel 10 lowest"
    "8.3|10.*|8.*||Laravel 10 latest"
    "8.2|11.*|9.*|--prefer-lowest|Laravel 11 lowest"
    "8.3|11.*|9.*||Laravel 11 latest"
    "8.2|12.*|10.*|--prefer-lowest|Laravel 12 lowest"
    "8.3|12.*|10.*||Laravel 12 latest"
    "8.3|13.*|11.*|--prefer-lowest|Laravel 13 lowest"
    "8.3|13.*|11.*||Laravel 13 latest"
    "8.4|13.*|11.*||Laravel 13 latest (PHP 8.4)"
    "8.5|13.*|11.*||Laravel 13 latest (PHP 8.5)"
)

command -v docker >/dev/null 2>&1 || { echo "ERROR: docker is not installed / not in PATH." >&2; exit 2; }

declare -A BUILT
results=()
overall=0

for row in "${ROWS[@]}"; do
    IFS='|' read -r PHP LARAVEL TESTBENCH DEPS LABEL <<<"$row"

    if [ -n "$FILTER" ] && [[ "$LARAVEL" != "${FILTER}.*" ]]; then
        continue
    fi

    tag="${IMG_PREFIX}:php${PHP}"
    if [ -z "${BUILT[$PHP]:-}" ]; then
        echo "### Building image for PHP ${PHP} ..."
        if docker build -t "$tag" --build-arg "PHP_VERSION=${PHP}" "$DOCKER_DIR"; then
            BUILT[$PHP]=1
        else
            results+=("BUILD-FAIL  ${LABEL} (PHP ${PHP})")
            overall=1
            continue
        fi
    fi

    echo
    echo "==================================================================="
    echo "### ${LABEL}  |  PHP ${PHP} · Laravel ${LARAVEL} · Testbench ${TESTBENCH}"
    echo "==================================================================="
    if docker run --rm \
        -v "$REPO":/src:ro \
        -e LARAVEL="$LARAVEL" \
        -e TESTBENCH="$TESTBENCH" \
        -e DEPS="$DEPS" \
        "$tag"; then
        results+=("PASS        ${LABEL} (PHP ${PHP})")
    else
        results+=("FAIL        ${LABEL} (PHP ${PHP})")
        overall=1
    fi
done

echo
echo "======================== MATRIX SUMMARY ==========================="
printf '%s\n' "${results[@]}"
echo "==================================================================="
exit $overall
