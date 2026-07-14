#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

log() {
    printf '[SWAFI TEST] %s\n' "$1"
}

fail() {
    printf '[SWAFI TEST] ERROR: %s\n' "$1" >&2
    exit 1
}

command -v php >/dev/null 2>&1 || fail 'PHP no está disponible en el ambiente.'
command -v composer >/dev/null 2>&1 || fail 'Composer no está disponible en el ambiente.'
command -v tar >/dev/null 2>&1 || fail 'tar no está disponible en el ambiente.'
command -v mktemp >/dev/null 2>&1 || fail 'mktemp no está disponible en el ambiente.'

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/swafi-tests.XXXXXX")"
TEST_ROOT="$TEMP_ROOT/project"

cleanup() {
    rm -rf "$TEMP_ROOT"
}

trap cleanup EXIT INT TERM

mkdir -p "$TEST_ROOT"

log 'Creando una copia temporal aislada del proyecto...'

tar \
    --exclude='./.git' \
    --exclude='./vendor' \
    --exclude='./node_modules' \
    --exclude='./public/build' \
    --exclude='./public/hot' \
    --exclude='./bootstrap/cache/*.php' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/testing/*' \
    --exclude='./storage/framework/views/*' \
    -cf - . | tar -xf - -C "$TEST_ROOT"

# Reutiliza las dependencias productivas ya instaladas para reducir descargas.
# Composer agregará únicamente las dependencias de desarrollo que falten.
if [[ -d "$PROJECT_ROOT/vendor" ]]; then
    log 'Copiando dependencias productivas existentes...'
    cp -a "$PROJECT_ROOT/vendor" "$TEST_ROOT/vendor"
fi

cd "$TEST_ROOT"

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

# Evita heredar una configuración de Composer que suprima require-dev.
unset COMPOSER_NO_DEV || true
export COMPOSER_NO_INTERACTION=1
export APP_ENV=testing
export APP_DEBUG=false

# Las pruebas funcionales necesitan una llave, pero no deben reutilizar una
# llave productiva cuando el script se ejecute fuera de Laravel Cloud.
if [[ -z "${APP_KEY:-}" ]]; then
    APP_KEY_VALUE="$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')"
    export APP_KEY="$APP_KEY_VALUE"
fi

log 'Instalando dependencias de desarrollo en la copia temporal...'
composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

[[ -f vendor/bin/phpunit ]] || fail 'PHPUnit no fue instalado. Revisa composer.lock y la conectividad de Composer.'

log 'Ejecutando PHPUnit directamente...'
php vendor/bin/phpunit --configuration phpunit.xml "$@"
