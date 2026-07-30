#!/usr/bin/env bash
# =============================================================================
# SIFOBI — Deploy Script
# Cara pakai: bash deploy.sh
# =============================================================================

set -e

# ── Warna output ─────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
BOLD='\033[1m'
RESET='\033[0m'

ok()   { echo -e "  ${GREEN}✓${RESET}  $1"; }
info() { echo -e "  ${CYAN}→${RESET}  $1"; }
warn() { echo -e "  ${YELLOW}!${RESET}  $1"; }
fail() { echo -e "\n  ${RED}✗  GAGAL: $1${RESET}\n"; exit 1; }
step() { echo -e "\n${BOLD}${CYAN}[$1]${RESET} $2"; }

# ── Deteksi PHP ───────────────────────────────────────────────────────────────
detect_php() {
    # AAPanel paths (prioritas utama)
    for candidate in \
        /www/server/php/84/bin/php \
        /www/server/php/83/bin/php \
        /www/server/php/82/bin/php \
        /www/server/php/81/bin/php \
        php8.4 php8.3 php8.2 php8.1 php; do
        if command -v "$candidate" &>/dev/null 2>&1 || [ -x "$candidate" ]; then
            echo "$candidate"
            return
        fi
    done
    fail "PHP tidak ditemukan. Pastikan PHP sudah terinstall."
}

PHP=$(detect_php)
ARTISAN="$PHP artisan"

# ── Header ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║       SIFOBI — Deploy Script             ║${RESET}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${RESET}"
echo ""
info "PHP      : $PHP  ($($PHP -r 'echo PHP_VERSION;'))"
info "Direktori: $(pwd)"
info "Waktu    : $(date '+%Y-%m-%d %H:%M:%S')"

# ── 1. Git Pull ───────────────────────────────────────────────────────────────
step "1/6" "Git Pull"

# Simpan hash sebelum pull untuk bisa lihat diff
BEFORE=$(git rev-parse HEAD 2>/dev/null || echo "unknown")

# Cek apakah ada perubahan lokal yang tidak di-commit
if ! git diff --quiet 2>/dev/null || ! git diff --cached --quiet 2>/dev/null; then
    warn "Ada perubahan lokal yang belum di-commit:"
    git status --short
    echo ""
    read -r -p "  Lanjut dan simpan perubahan lokal ke stash dulu? [y/N] " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        git stash push -m "auto-stash sebelum deploy $(date '+%Y%m%d-%H%M%S')"
        ok "Perubahan lokal di-stash."
    else
        fail "Deploy dibatalkan. Selesaikan dulu perubahan lokal."
    fi
fi

git pull origin main

AFTER=$(git rev-parse HEAD 2>/dev/null || echo "unknown")

if [ "$BEFORE" = "$AFTER" ]; then
    ok "Sudah up-to-date — tidak ada commit baru."
else
    ok "Pull berhasil."
    echo ""
    echo -e "  ${CYAN}Perubahan baru:${RESET}"
    git log --oneline "${BEFORE}..${AFTER}" 2>/dev/null | while read -r line; do
        echo -e "    ${CYAN}•${RESET} $line"
    done
fi

# ── 2. Composer ───────────────────────────────────────────────────────────────
step "2/6" "Composer"

COMPOSER_CHANGED=$(git diff --name-only "${BEFORE}..${AFTER}" 2>/dev/null | grep -c "composer" || true)

if [ "$COMPOSER_CHANGED" -gt 0 ] || [ "$BEFORE" = "unknown" ]; then
    info "composer.json/lock berubah — jalankan install..."
    composer install --no-interaction --no-dev --optimize-autoloader
    ok "Composer selesai."
else
    ok "composer.lock tidak berubah — skip."
fi

# ── 3. Migration ─────────────────────────────────────────────────────────────
step "3/6" "Database Migration"

MIGRATION_OUT=$($ARTISAN migrate --force 2>&1)

if echo "$MIGRATION_OUT" | grep -q "Nothing to migrate"; then
    ok "Tidak ada migration baru."
else
    echo "$MIGRATION_OUT" | grep -E "Running|DONE|ERROR" | while read -r line; do
        echo "    $line"
    done
    ok "Migration selesai."
fi

# ── 4. Clear Cache ────────────────────────────────────────────────────────────
step "4/6" "Clear Cache"

$ARTISAN config:clear && ok "Config cleared."
$ARTISAN route:clear  && ok "Route cleared."
$ARTISAN view:clear   && ok "View cleared."
$ARTISAN cache:clear  && ok "App cache cleared."

# ── 5. Rebuild Cache ─────────────────────────────────────────────────────────
step "5/6" "Rebuild Cache"

$ARTISAN config:cache && ok "Config cached."
$ARTISAN route:cache  && ok "Route cached."
$ARTISAN view:cache   && ok "View cached."

# ── 6. Permission & Storage ───────────────────────────────────────────────────
step "6/6" "Permission & Storage Link"

chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache 2>/dev/null || \
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
ok "Permission storage OK."

$ARTISAN storage:link --force 2>/dev/null || true
ok "Storage link OK."

# ── Selesai ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}${GREEN}║  ✓  Deploy selesai! $(date '+%H:%M:%S')              ║${RESET}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════╝${RESET}"
echo ""
$ARTISAN --version
echo ""
