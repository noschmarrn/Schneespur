#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-1.0.0}"
PRODUCT="schneespur"
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
SOURCE_DIR="${PROJECT_DIR}/${PRODUCT}"
BUILD_DIR="${PROJECT_DIR}/release/${PRODUCT}-${VERSION}"
ZIP_FILE="${PROJECT_DIR}/release/${PRODUCT}-${VERSION}.zip"

echo "════════════════════════════════════════════"
echo "  Building ${PRODUCT} v${VERSION}"
echo "════════════════════════════════════════════"

cd "$PROJECT_DIR"

# ── 0a. Versions-Gate ──
# VERSION im Quellbaum, das Literal app.build in config/app.php und das
# Argument müssen gleich sein. Weicht app.build ab, hält jede Installation
# ihre Laravel-Caches für veraltet und verwirft sie (StaleCompiledCache).
SRC_VERSION="$(tr -d '[:space:]' < "$SOURCE_DIR/VERSION")"
BUILD_LITERAL="$(grep -oE "'build' => '[^']+'" "$SOURCE_DIR/config/app.php" | sed -E "s/.*=> '([^']+)'/\1/")"
if [ "$SRC_VERSION" != "$VERSION" ] || [ "$BUILD_LITERAL" != "$VERSION" ]; then
    echo "  ✗ Versionen uneinig: Argument ${VERSION}, VERSION ${SRC_VERSION}, app.build ${BUILD_LITERAL}"
    exit 1
fi

# ── 0. Sicherheits-Gate ──
# 1.2.0 und 1.2.1 gingen mit 19 bekannten Advisories hinaus, weil seit August
# keine CI lief. Das Audit gehört deshalb VOR den Build, nicht danach.
echo ""
echo "▸ Auditing composer dependencies..."
(cd "$SOURCE_DIR" && composer audit --locked --no-dev) || {
    echo "  ✗ composer audit meldet Sicherheitslücken — erst beheben, dann bauen."
    exit 1
}

# ── Clean previous build ──
rm -rf "$BUILD_DIR" "$ZIP_FILE"
mkdir -p "$BUILD_DIR"

# ── 1. Frontend build ──
echo ""
echo "▸ Installing npm dependencies..."
(cd "$SOURCE_DIR" && (npm ci --silent 2>/dev/null || npm install --silent))

echo "▸ Building frontend assets..."
(cd "$SOURCE_DIR" && npm run build)

# ── 2. Composer production install ──
echo ""
echo "▸ Installing composer dependencies (production)..."
(cd "$SOURCE_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

# ── 3. Copy files ──
echo ""
echo "▸ Copying project files..."

# Core Laravel → build root (flat layout, like 1.0.0)
cp "$SOURCE_DIR/artisan" "$BUILD_DIR/"
cp "$SOURCE_DIR/composer.json" "$BUILD_DIR/"
cp "$SOURCE_DIR/composer.lock" "$BUILD_DIR/"
cp "$SOURCE_DIR/.env.example" "$BUILD_DIR/"
cp "$SOURCE_DIR/.editorconfig" "$BUILD_DIR/" 2>/dev/null || true
cp "$SOURCE_DIR/.htaccess" "$BUILD_DIR/" 2>/dev/null || true

# Application code → build root
cp -r "$SOURCE_DIR/app" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/bootstrap" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/config" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/database" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/lang" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/public" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/resources" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/routes" "$BUILD_DIR/"
cp -r "$SOURCE_DIR/vendor" "$BUILD_DIR/"

# Modules directory — example module is dev-only and excluded from releases
if [ -d "$SOURCE_DIR/modules" ]; then
    cp -r "$SOURCE_DIR/modules" "$BUILD_DIR/"
    rm -rf "$BUILD_DIR/modules/example"
fi

# The example module is dev-only: its public assets are symlinked into public/
# and get dereferenced into the build by the wholesale public/ copy above.
rm -rf "$BUILD_DIR/public/modules/example"

# Documentation and legal → build root (flat, alongside code)
cp "$PROJECT_DIR/README.md" "$BUILD_DIR/" 2>/dev/null || true
cp "$PROJECT_DIR/LICENSE" "$BUILD_DIR/" 2>/dev/null || true
cp "$PROJECT_DIR/INSTALL.de.md" "$BUILD_DIR/" 2>/dev/null || true
cp "$PROJECT_DIR/INSTALL.en.md" "$BUILD_DIR/" 2>/dev/null || true

# ── 4. Prepare storage structure (empty, writable) ──
echo "▸ Preparing storage structure..."
rm -rf "$BUILD_DIR/storage"
mkdir -p "$BUILD_DIR/storage/app/private"
mkdir -p "$BUILD_DIR/storage/app/public"
mkdir -p "$BUILD_DIR/storage/framework/cache/data"
mkdir -p "$BUILD_DIR/storage/framework/sessions"
mkdir -p "$BUILD_DIR/storage/framework/testing"
mkdir -p "$BUILD_DIR/storage/framework/views"
mkdir -p "$BUILD_DIR/storage/logs"

for dir in "$BUILD_DIR/storage/app/private" \
           "$BUILD_DIR/storage/app/public" \
           "$BUILD_DIR/storage/framework/cache/data" \
           "$BUILD_DIR/storage/framework/sessions" \
           "$BUILD_DIR/storage/framework/testing" \
           "$BUILD_DIR/storage/framework/views" \
           "$BUILD_DIR/storage/logs"; do
    touch "$dir/.gitkeep"
done

# ── 5. Remove dev/unnecessary files ──
echo "▸ Cleaning up dev files..."

# Remove installed.lock (fresh install!)
rm -f "$BUILD_DIR/storage/app/installed.lock"

# Remove public/storage symlink (installer creates it)
rm -f "$BUILD_DIR/public/storage"

# Remove bootstrap cache (regenerated on first run)
rm -f "$BUILD_DIR/bootstrap/cache/"*.php 2>/dev/null || true

# Remove test files
rm -rf "$BUILD_DIR/tests"

# Remove dev config/tooling
rm -f "$BUILD_DIR/phpunit.xml"
rm -f "$BUILD_DIR/.styleci.yml"
rm -f "$BUILD_DIR/.gitignore"
rm -f "$BUILD_DIR/.gitattributes"
rm -f "$BUILD_DIR/package.json"
rm -f "$BUILD_DIR/package-lock.json"
rm -f "$BUILD_DIR/vite.config.js"

# Remove GSD/AI/editor artifacts
rm -rf "$BUILD_DIR/.gsd"
rm -f "$BUILD_DIR/.gsd-id"
rm -f "$BUILD_DIR/CLAUDE.md"
rm -f "$BUILD_DIR/gpt.md"
rm -f "$BUILD_DIR/module.md"
rm -f "$BUILD_DIR/site.md"
rm -f "$BUILD_DIR/.mcp.json"

# Remove test/scratch files
rm -f "$BUILD_DIR/test.txt"
rm -f "$BUILD_DIR/test-file.txt"
rm -f "$BUILD_DIR/app/test.php"

# Remove database factories/seeders (not needed in production)
rm -rf "$BUILD_DIR/database/factories"
rm -rf "$BUILD_DIR/database/seeders"

# Remove any SQLite file that leaked in from local development. The repo
# gitignores database/database.sqlite, but this script copies database/
# wholesale and .gitignore does not apply to cp. Shipping it is not merely
# untidy: config/database.php defaults DB_CONNECTION to "sqlite", so a fresh
# install that has no .env yet connects to THIS file, finds a users row, and
# the installer aborts with 410 "already installed" — with no way forward for
# the operator. Found by deploying 1.2.0 to real shared hosting.
find "$BUILD_DIR/database" -maxdepth 1 -type f \( -name "*.sqlite" -o -name "*.sqlite3" -o -name "*.db" \) -delete

# ── 6. Slim vendor directory ──
echo "▸ Slimming vendor directory..."

# Remove .git directories from vendor packages (source installs)
find "$BUILD_DIR/vendor" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true

# Remove tests, docs, and other non-runtime directories
find "$BUILD_DIR/vendor" -type d \( \
    -name "tests" -o \
    -name "Tests" -o \
    -name "test" -o \
    -name "Test" -o \
    -name "test_files" -o \
    -name "docs" -o \
    -name "doc" -o \
    -name "examples" -o \
    -name "example" -o \
    -name ".github" \
\) -exec rm -rf {} + 2>/dev/null || true

# Remove non-runtime files
find "$BUILD_DIR/vendor" -type f \( \
    -name "*.md" -o \
    -name "*.markdown" -o \
    -name "CHANGELOG*" -o \
    -name "CHANGE_LOG*" -o \
    -name "CHANGES*" -o \
    -name "UPGRADING*" -o \
    -name "UPGRADE*" -o \
    -name "SECURITY*" -o \
    -name "CONTRIBUTING*" -o \
    -name "CODE_OF_CONDUCT*" -o \
    -name ".editorconfig" -o \
    -name ".gitignore" -o \
    -name ".gitattributes" -o \
    -name ".php-cs-fixer*" -o \
    -name ".php_cs*" -o \
    -name "phpunit.xml*" -o \
    -name "phpstan*" -o \
    -name ".styleci.yml" -o \
    -name ".travis.yml" -o \
    -name "Makefile" -o \
    -name "Dockerfile" -o \
    -name "docker-compose*" \
\) -delete 2>/dev/null || true

# ── 7. Write version file ──
echo "▸ Writing version file..."
cat > "$BUILD_DIR/VERSION" << EOF
${VERSION}
EOF

# ── 8. Write initial update state (prevents self-update to same version) ──
echo "▸ Writing initial update state..."
cat > "$BUILD_DIR/storage/app/schneespur_update_state.json" << EOF
{"current_version":"${VERSION}","last_counter":1}
EOF

# ── 9. Create ZIP ──
echo ""
echo "▸ Creating ZIP archive..."
rm -f "${ZIP_FILE}.filepart"
(cd "${BUILD_DIR}" && zip -r -q "${ZIP_FILE}" .)

# ── 10. Summary ──
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l)

echo ""
echo "════════════════════════════════════════════"
echo "  ✓ Build complete!"
echo ""
echo "  Version:  ${VERSION}"
echo "  Files:    ${FILE_COUNT}"
echo "  ZIP:      ${ZIP_FILE} (${ZIP_SIZE})"
echo "════════════════════════════════════════════"
echo ""
echo "  The ZIP is ready for distribution."
echo "  Users: unzip → FTP upload → open in browser → installer starts"

# ── 11. Restore dev dependencies ──
echo ""
echo "▸ Restoring dev composer dependencies..."
(cd "$SOURCE_DIR" && composer install --no-interaction --quiet)

# ── 12. Update-Gate ──
# Das neue ZIP wird über den ECHTEN Updater jeder Vorversion eingespielt. Eine
# frische Installation und die Testsuite reichen nicht: 1.2.1 bestand beides
# und endete bei jedem Nutzer, der von 1.2.0 aktualisierte, in 503.
echo ""
echo "▸ Update smoke tests..."
for FROM in ${SMOKE_FROM:-1.2.0 1.2.1 1.2.2}; do
    FROM_ZIP="${PROJECT_DIR}/release/${PRODUCT}-${FROM}.zip"
    if [ ! -f "$FROM_ZIP" ]; then
        echo "  ⚠ ${FROM_ZIP} fehlt — Smoke ${FROM} → ${VERSION} übersprungen"
        continue
    fi
    "${PROJECT_DIR}/scripts/update-smoke.sh" "$FROM_ZIP" "$ZIP_FILE" || {
        echo "  ✗ Update ${FROM} → ${VERSION} scheitert — NICHT veröffentlichen."
        exit 1
    }
done

echo "  ✓ Alle Update-Gates bestanden."
