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

show_available_tests() {
    local root="$1"

    printf '[SWAFI TEST] Pruebas disponibles en este despliegue:\n' >&2

    if [[ -d "$root/tests" ]]; then
        find "$root/tests" -type f -name '*Test.php' -print \
            | sed "s#^$root/##" \
            | LC_ALL=C sort \
            | sed 's/^/[SWAFI TEST]   - /' >&2
    else
        printf '[SWAFI TEST]   - La carpeta tests no existe en este despliegue.\n' >&2
    fi
}

normalize_test_path() {
    local candidate="$1"

    candidate="${candidate#./}"
    printf '%s' "$candidate"
}

validate_requested_test_files() {
    local root="$1"
    shift

    local argument
    local normalized

    for argument in "$@"; do
        # PHPUnit acepta opciones y valores adicionales. Solo se consideran
        # rutas explícitas los argumentos que terminan en .php.
        [[ "$argument" == *.php ]] || continue

        normalized="$(normalize_test_path "$argument")"

        if [[ "$normalized" == /* || "$normalized" == ../* || "$normalized" == *'/../'* ]]; then
            fail "La ruta de prueba no es segura: $argument"
        fi

        if [[ ! -f "$root/$normalized" ]]; then
            printf '[SWAFI TEST] ERROR: No existe el archivo de prueba solicitado: %s\n' "$normalized" >&2
            printf '[SWAFI TEST] El archivo debe estar agregado al repositorio y presente en el despliegue activo de Laravel Cloud.\n' >&2
            show_available_tests "$root"
            exit 2
        fi
    done
}

verify_requested_tests_in_copy() {
    local root="$1"
    shift

    local argument
    local normalized

    for argument in "$@"; do
        [[ "$argument" == *.php ]] || continue

        normalized="$(normalize_test_path "$argument")"

        if [[ ! -f "$root/$normalized" ]]; then
            fail "La copia temporal no contiene el archivo de prueba solicitado: $normalized"
        fi
    done
}

# La existencia de una prueba explícita se valida antes de exigir Composer o
# instalar dependencias. Esto produce un diagnóstico inmediato incluso en un
# ambiente donde todavía no se han preparado las herramientas de desarrollo.
command -v find >/dev/null 2>&1 || fail 'find no está disponible en el ambiente.'
command -v sed >/dev/null 2>&1 || fail 'sed no está disponible en el ambiente.'
command -v sort >/dev/null 2>&1 || fail 'sort no está disponible en el ambiente.'
validate_requested_test_files "$PROJECT_ROOT" "$@"

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

verify_requested_tests_in_copy "$TEST_ROOT" "$@"

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
