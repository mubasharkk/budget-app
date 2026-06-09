#!/usr/bin/env bash
# =============================================================================
# Budget App — VPS Deployment Script
# Usage: ./deploy.sh [--branch master] [--skip-migrations]
#
# Assumes MySQL/database credentials are already configured in .env on the server.
# Each step (except git pull) prompts [y/N] — default is skip (Enter = skip).
#
# Post-deploy: ensure a queue worker is running for receipt OCR & product matching:
#   php artisan queue:work --tries=3 --timeout=300
# =============================================================================
set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
BLUE='\033[0;34m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${BLUE}[deploy]${NC} $*"; }
success() { echo -e "${GREEN}[deploy]${NC} ✓ $*"; }
warn()    { echo -e "${YELLOW}[deploy]${NC} ⚠ $*"; }
error()   { echo -e "${RED}[deploy]${NC} ✗ $*"; }

# ── Job tracker ───────────────────────────────────────────────────────────────
declare -A JOB_STATUS
record() { JOB_STATUS["$1"]="$2"; }

print_summary() {
    echo ""
    echo -e "${BLUE}══════════════════════════════════${NC}"
    echo -e "${BLUE}  Deploy Summary${NC}"
    echo -e "${BLUE}══════════════════════════════════${NC}"
    for job in "Code pull" "PHP dependencies" "Frontend build" "Migrations" "Seeders" "Caches" "Storage link" "Queue restart" "Permissions"; do
        status="${JOB_STATUS[$job]:-— not reached}"
        echo -e "  ${job}: ${status}"
    done
    echo -e "${BLUE}══════════════════════════════════${NC}"
    echo ""
}

# ── Prompt helper — default NO (Enter = skip) ────────────────────────────────
prompt_step() {
    local label="$1"
    echo ""
    echo -e "${YELLOW}──────────────────────────────────${NC}"
    echo -e "  Step: ${BLUE}${label}${NC}"
    echo -e "${YELLOW}──────────────────────────────────${NC}"
    read -r -p "  Run this step? [y/N] " REPLY
    [[ "${REPLY,,}" == "y" ]]
}

# ── Config (override via env or flags) ───────────────────────────────────────
BRANCH="${DEPLOY_BRANCH:-master}"
APP_DIR="${DEPLOY_APP_DIR:-/var/www/budget-app}"
PHP="${DEPLOY_PHP:-php}"
COMPOSER="${DEPLOY_COMPOSER:-composer}"
SKIP_MIGRATIONS=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --branch)          BRANCH="$2"; shift 2 ;;
        --app-dir)         APP_DIR="$2"; shift 2 ;;
        --skip-migrations) SKIP_MIGRATIONS=true; shift ;;
        *) echo "Unknown option: $1" >&2; exit 1 ;;
    esac
done

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    warn "Configure .env (APP_URL, DB_*, OPENAI_API_KEY, queue driver) before running migrations."
fi

# ── Git: pull latest code (always runs) ──────────────────────────────────────
info "Deploying '${BRANCH}' to ${APP_DIR} ($(git rev-parse --short HEAD))"
info "Fetching latest code..."
if git fetch origin && git checkout "$BRANCH" && git reset --hard "origin/${BRANCH}"; then
    success "Code updated to $(git rev-parse --short HEAD)"
    record "Code pull" "✓ done ($(git rev-parse --short HEAD))"
else
    error "Code pull failed"
    record "Code pull" "✗ failed"
    print_summary
    exit 1
fi

# Composer's post-autoload-dump runs `php artisan package:discover`, which boots Laravel.
# Without a real APP_KEY that step exits non-zero. Fresh clones often ship APP_KEY empty.
if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
    info "Generating APP_KEY (required before Composer scripts run)..."
    APP_KEY_VALUE="$($PHP -r "echo 'base64:'.base64_encode(random_bytes(32));")"
    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env
    else
        printf '\nAPP_KEY=%s\n' "${APP_KEY_VALUE}" >> .env
    fi
fi

# Composer disables plugins when UID=0 unless explicitly allowed.
if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
    export COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"
    warn "Running as root — COMPOSER_ALLOW_SUPERUSER=1 set. Prefer a dedicated deploy user."
fi

# ── PHP dependencies ──────────────────────────────────────────────────────────
if prompt_step "PHP dependencies (composer install --no-dev)"; then
    info "Installing PHP dependencies..."
    if $COMPOSER install \
        --no-dev \
        --no-interaction \
        --optimize-autoloader \
        --prefer-dist \
        --no-progress; then
        success "PHP dependencies installed"
        record "PHP dependencies" "✓ done"
    else
        error "Composer install failed"
        record "PHP dependencies" "✗ failed"
        print_summary
        exit 1
    fi
else
    warn "Skipping PHP dependencies"
    record "PHP dependencies" "⚠ skipped"
fi

# ── Frontend assets ───────────────────────────────────────────────────────────
if prompt_step "Frontend build (npm ci + npm run build)"; then
    info "Installing Node dependencies..."
    if npm ci --prefer-offline --no-audit --no-fund; then
        info "Building frontend assets (Inertia + React)..."
        if npm run build; then
            success "Frontend assets built"
            record "Frontend build" "✓ done"
        else
            error "npm run build failed"
            record "Frontend build" "✗ failed"
            print_summary
            exit 1
        fi
    else
        error "npm ci failed"
        record "Frontend build" "✗ failed (npm ci)"
        print_summary
        exit 1
    fi
else
    warn "Skipping frontend build"
    record "Frontend build" "⚠ skipped"
fi

# ── Migrations ────────────────────────────────────────────────────────────────
if [[ "$SKIP_MIGRATIONS" == false ]]; then
    if prompt_step "Database migrations (php artisan migrate)"; then
        info "Running database migrations..."
        if $PHP artisan migrate --force --no-interaction; then
            success "Migrations complete"
            record "Migrations" "✓ done"
        else
            error "Migrations failed — check DB_* settings in .env"
            record "Migrations" "✗ failed"
            print_summary
            exit 1
        fi
    else
        warn "Skipping migrations"
        record "Migrations" "⚠ skipped"
    fi
else
    warn "Skipping migrations (--skip-migrations flag set)"
    record "Migrations" "⚠ skipped"
fi

# ── Seeders (optional) ────────────────────────────────────────────────────────
if prompt_step "Seed budgeting categories (CategorySeeder)"; then
    info "Seeding category catalog..."
    if $PHP artisan db:seed --class=CategorySeeder --no-interaction --force; then
        success "CategorySeeder complete"
        record "Seeders" "✓ CategorySeeder"
    else
        error "CategorySeeder failed"
        record "Seeders" "✗ failed"
    fi
else
    warn "Skipping seeders"
    record "Seeders" "⚠ skipped"
fi

# ── Caches ────────────────────────────────────────────────────────────────────
if prompt_step "Rebuild caches (config / route / view / event)"; then
    info "Rebuilding caches..."
    if $PHP artisan config:cache && \
       $PHP artisan route:cache  && \
       $PHP artisan view:cache   && \
       $PHP artisan event:cache; then
        success "Caches rebuilt"
        record "Caches" "✓ done"
    else
        error "Cache rebuild failed"
        record "Caches" "✗ failed"
        print_summary
        exit 1
    fi
else
    warn "Skipping cache rebuild"
    record "Caches" "⚠ skipped"
fi

# ── Storage link ──────────────────────────────────────────────────────────────
if [[ ! -L public/storage ]]; then
    if prompt_step "Create storage symlink (php artisan storage:link)"; then
        if $PHP artisan storage:link; then
            success "Storage link created"
            record "Storage link" "✓ done"
        else
            error "Storage link failed"
            record "Storage link" "✗ failed"
        fi
    else
        warn "Skipping storage link"
        record "Storage link" "⚠ skipped"
    fi
else
    record "Storage link" "⚠ skipped (already exists)"
fi

# ── Queue workers ─────────────────────────────────────────────────────────────
if prompt_step "Restart queue workers (php artisan queue:restart)"; then
    info "Signalling queue workers to restart..."
    if $PHP artisan queue:restart; then
        success "Queue restart signalled — workers will pick up new code"
        record "Queue restart" "✓ done"
    else
        warn "queue:restart failed (is a worker supervisor configured?)"
        record "Queue restart" "⚠ failed"
    fi
else
    warn "Skipping queue restart"
    record "Queue restart" "⚠ skipped"
fi

# ── Permissions ───────────────────────────────────────────────────────────────
if [[ ! -w storage || ! -w bootstrap/cache ]]; then
    if prompt_step "Fix permissions (chmod 775 storage + bootstrap/cache)"; then
        info "Fixing permissions..."
        chmod -R 775 storage bootstrap/cache
        chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
        chown -R "$USER":"$USER"   storage bootstrap/cache
        record "Permissions" "✓ fixed"
    else
        warn "Skipping permissions fix"
        record "Permissions" "⚠ skipped"
    fi
else
    record "Permissions" "⚠ skipped (already writable)"
fi

success "Done — deployed ${BRANCH} @ $(git rev-parse --short HEAD)"
warn "Receipt processing requires a running queue worker (QUEUE_CONNECTION in .env)."

print_summary
