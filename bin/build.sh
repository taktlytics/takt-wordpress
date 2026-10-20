#!/usr/bin/env bash
# Builds a distributable, dependency-scoped plugin ZIP under dist/.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SLUG="takt-analytics"
BUILD="$ROOT/build"
DIST="$ROOT/dist"
SCOPER="$ROOT/bin/php-scoper.phar"
SCOPER_VERSION="0.18.20"
SCOPER_SHA256="5e35e4a89ea53b2c70a19bfa8a856e4f9080aa12eb13a5bce650bb69d08eb122"
SCOPER_URL="https://github.com/humbug/php-scoper/releases/download/${SCOPER_VERSION}/php-scoper.phar"

rm -rf "$BUILD" "$DIST"
mkdir -p "$DIST"

echo "==> Staging WordPress symbol excludes for php-scoper"
composer install --no-interaction --quiet
mkdir -p "$ROOT/.scoper-excludes"
cp vendor/sniccowp/php-scoper-wordpress-excludes/generated/*.json "$ROOT/.scoper-excludes/"

echo "==> Installing production dependencies"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

verify_scoper() {
  echo "$SCOPER_SHA256  $SCOPER" | sha256sum --check --status
}

if [ ! -f "$SCOPER" ] || ! verify_scoper; then
  echo "==> Downloading php-scoper $SCOPER_VERSION"
  rm -f "$SCOPER"
  curl -fsSL "$SCOPER_URL" -o "$SCOPER"
fi

if ! verify_scoper; then
  rm -f "$SCOPER"
  echo "php-scoper $SCOPER_VERSION checksum mismatch, refusing to run it" >&2
  exit 1
fi

echo "==> Scoping dependencies into build/ (prefix Takt\\WP\\Vendor)"
php "$SCOPER" add-prefix --output-dir="$BUILD" --force --no-interaction --quiet

echo "==> Regenerating the scoped autoloader"
# php-scoper patches installed.json with the prefixed namespaces but leaves
# Composer's own autoload_real.php inconsistent; a fresh dump rebuilds the
# internals against the already-prefixed package maps.
cp composer.json "$BUILD/composer.json"
composer dump-autoload --working-dir="$BUILD" --classmap-authoritative --no-dev --no-interaction

echo "==> Smoke-testing the scoped autoloader"
BUILD="$BUILD" php -r '
require getenv("BUILD")."/vendor/autoload.php";
$checks = [
    "scoped core-php"  => class_exists("Takt\\WP\\Vendor\\Vskstudio\\Takt\\Takt"),
    "scoped psr7"      => class_exists("Takt\\WP\\Vendor\\Nyholm\\Psr7\\Factory\\Psr17Factory"),
    "plugin namespace" => class_exists("Vskstudio\\Takt\\WordPress\\Plugin"),
    "bundled tracker"  => is_readable(getenv("BUILD")."/vendor/vskstudio/takt-core-php/resources/takt.auto.js"),
];
$ok = true;
foreach ($checks as $name => $pass) {
    fwrite(STDERR, sprintf("    [%s] %s\n", $pass ? "ok" : "FAIL", $name));
    $ok = $ok && $pass;
}
exit($ok ? 0 : 1);
'

echo "==> Packaging dist/$SLUG.zip"
STAGE="$(mktemp -d)"
mkdir -p "$STAGE/$SLUG"
cp -R "$BUILD/." "$STAGE/$SLUG/"
rm -rf "$STAGE/$SLUG/tests" "$STAGE/$SLUG"/.php-cs-fixer* "$STAGE/$SLUG"/phpstan.neon \
       "$STAGE/$SLUG"/phpunit.xml* "$STAGE/$SLUG"/scoper.inc.php "$STAGE/$SLUG"/composer.* 2>/dev/null || true
rm -rf "$STAGE/$SLUG/vendor/vskstudio/takt-core-php/bin"
find "$STAGE/$SLUG/vendor" -type f -name composer.lock -delete
( cd "$STAGE" && zip -rqX "$DIST/$SLUG.zip" "$SLUG" )
rm -rf "$STAGE"

echo "==> Restoring dev dependencies"
composer install --no-interaction --quiet

echo "Built $DIST/$SLUG.zip"
