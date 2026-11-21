#!/usr/bin/env bash
# Update-Smoke-Test: spielt ein neues Release-ZIP über den ECHTEN Updater der
# Vorversion ein, so wie der Update-Knopf es tut.
#
#   scripts/update-smoke.sh <von.zip> <nach.zip>
#
# Warum es das gibt: 1.2.1 lief durch 945 Tests und eine frische Installation
# und endete trotzdem bei jedem Nutzer, der von 1.2.0 aktualisierte, in 503 —
# der Updater migriert mit den alten, schon geladenen Klassen. Das sieht nur
# ein Lauf, der wirklich von der Vorversion aus aktualisiert.
#
# Exit 0 = Update sauber. Alles andere = nicht veröffentlichen.
set -euo pipefail

FROM_ZIP="$(realpath "$1")"
TO_ZIP="$(realpath "$2")"
HERE="$(cd "$(dirname "$0")" && pwd)"
RUNNER="$HERE/update-smoke-runner.php"

WORK="$(mktemp -d "${TMPDIR:-/tmp}/schneespur-smoke.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT
APP="$WORK/app"

TO_VERSION="$(unzip -p "$TO_ZIP" VERSION | tr -d '[:space:]')"
FROM_VERSION="$(unzip -p "$FROM_ZIP" VERSION | tr -d '[:space:]')"
echo "▸ Smoke: ${FROM_VERSION} → ${TO_VERSION}"

fail() { echo "  ✗ $*"; exit 1; }

# ── Vorversion so aufsetzen, wie der Installer sie hinterläßt ──
unzip -q "$FROM_ZIP" -d "$APP"
touch "$APP/database/smoke.sqlite"
cat > "$APP/.env" <<EOF
APP_NAME=Schneespur
APP_ENV=production
APP_KEY=base64:$(head -c32 /dev/urandom | base64)
APP_DEBUG=false
APP_URL=http://localhost
APP_LOCALE=de
DB_CONNECTION=sqlite
DB_DATABASE=$APP/database/smoke.sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=single
EOF
(cd "$APP" && php artisan migrate --force --no-interaction >/dev/null) || fail "Vorversion ließ sich nicht migrieren"
touch "$APP/storage/app/installed.lock"

# ── Update über den alten Updater ──
OUT="$(php "$RUNNER" update "$APP" "$TO_ZIP" "$TO_VERSION" 2>&1 || true)"
echo "  $(echo "$OUT" | tail -1)"
case "$OUT" in
    *STAGED*)
        OUT2="$(php "$RUNNER" finalize "$APP" 2>&1 || true)"
        echo "  $(echo "$OUT2" | tail -1)"
        [[ "$OUT2" == *"FINALIZE OK"* ]] || fail "Stufe 2 gescheitert"
        ;;
    *INSTALLED*) ;;
    *) fail "Update gescheitert" ;;
esac

# ── Ergebnis prüfen ──
[[ ! -e "$APP/storage/framework/down" ]] || fail "Wartungsmodus aktiv (jede Seite 503)"

PENDING="$(cd "$APP" && php artisan migrate:status --no-interaction 2>&1 | grep -c Pending || true)"
[[ "$PENDING" == "0" ]] || fail "$PENDING Migration(en) offen"

[[ "$(tr -d '[:space:]' < "$APP/VERSION")" == "$TO_VERSION" ]] || fail "VERSION ist nicht $TO_VERSION"
grep -q "\"current_version\": *\"$TO_VERSION\"" "$APP/storage/app/schneespur_update_state.json" \
    || fail "update_state steht nicht auf $TO_VERSION"

STATUS="$(php "$RUNNER" http "$APP" /login 2>&1 | tail -1)"
[[ "$STATUS" == "200" ]] || fail "GET /login liefert $STATUS"

echo "  ✓ ${FROM_VERSION} → ${TO_VERSION} sauber (kein down, 0 offene Migrationen, /login 200)"
