#!/bin/sh
set -eu

cd /var/www/html
umask 0002

# Runtime-installed modules and their published assets live on persistent
# volumes in production. Populate a new volume from the immutable image while
# preserving every existing entry during restarts and image upgrades.
seed_missing_entries() {
    source_directory="$1"
    target_directory="$2"

    [ -d "$source_directory" ] || return 0
    mkdir -p "$target_directory"

    find "$source_directory" -mindepth 1 -maxdepth 1 -print | while IFS= read -r source_entry; do
        entry_name="$(basename "$source_entry")"
        target_entry="$target_directory/$entry_name"

        if [ ! -e "$target_entry" ] && [ ! -L "$target_entry" ]; then
            cp -a "$source_entry" "$target_entry"
        fi
    done
}

# Required plugins ship as part of the platform release. Their source must
# follow the deployed image even when /modules is a persistent volume. Overlay
# only these known core entries; runtime-installed plugins remain untouched.
sync_required_entry() {
    source_directory="$1"
    target_directory="$2"

    [ -d "$source_directory" ] || return 0
    mkdir -p "$target_directory"
    cp -a "$source_directory/." "$target_directory/"
}

mkdir -p \
    modules \
    public/platform/plugins \
    public/platform/themes \
    storage/app/private \
    storage/app/private/installation \
    storage/app/public \
    storage/app/public/settings \
    storage/app/plugin_uploads \
    storage/app/plugin_uploads/tmp \
    storage/app/plugin_uploads/extracted \
    storage/app/plugin_uploads/pending_updates \
    storage/app/platform \
    storage/app/platform/backup-checkpoints \
    storage/app/platform/plugin-install-checkpoints \
    storage/app/platform/plugin-uninstall-checkpoints \
    storage/app/platform/update-checkpoints \
    storage/app/platform/update-logs \
    storage/app/plugin_updates/backups \
    storage/app/plugin_uninstalls/removed_modules \
    storage/app/theme-overrides/core \
    storage/app/theme-overrides/namespaces/core \
    storage/app/theme-overrides/plugins \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

seed_missing_entries /opt/art-inpa/modules modules
seed_missing_entries /opt/art-inpa/public/platform public/platform
sync_required_entry /opt/art-inpa/modules/admin-theme modules/admin-theme
sync_required_entry /opt/art-inpa/modules/page-builder modules/page-builder

# Public media URLs use /storage/...; source mounts and fresh images do not
# contain Laravel's ignored public/storage symlink, so restore it at runtime.
if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    ln -s ../storage/app/public public/storage
fi

chgrp www-data \
    public/platform \
    public/platform/plugins \
    public/platform/themes \
    storage/app/private \
    storage/app/private/installation \
    storage/app/public \
    storage/app/public/settings \
    storage/app/platform \
    storage/app/platform/backup-checkpoints \
    storage/app/platform/plugin-install-checkpoints \
    storage/app/platform/plugin-uninstall-checkpoints \
    storage/app/platform/update-checkpoints \
    storage/app/platform/update-logs \
    storage/app/plugin_updates \
    storage/app/plugin_updates/backups \
    storage/app/plugin_uninstalls \
    storage/app/plugin_uninstalls/removed_modules \
    storage/app/theme-overrides \
    storage/app/theme-overrides/core \
    storage/app/theme-overrides/namespaces \
    storage/app/theme-overrides/namespaces/core \
    storage/app/theme-overrides/plugins \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod 2775 \
    public/platform \
    public/platform/plugins \
    public/platform/themes \
    storage/app/public \
    storage/app/public/settings \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod 2770 \
    storage/app/private \
    storage/app/private/installation \
    storage/app/platform \
    storage/app/platform/backup-checkpoints \
    storage/app/platform/plugin-install-checkpoints \
    storage/app/platform/plugin-uninstall-checkpoints \
    storage/app/platform/update-checkpoints \
    storage/app/platform/update-logs \
    storage/app/plugin_updates \
    storage/app/plugin_updates/backups \
    storage/app/plugin_uninstalls \
    storage/app/plugin_uninstalls/removed_modules \
    storage/app/theme-overrides \
    storage/app/theme-overrides/core \
    storage/app/theme-overrides/namespaces \
    storage/app/theme-overrides/namespaces/core \
    storage/app/theme-overrides/plugins

chgrp www-data \
    storage/app/plugin_uploads \
    storage/app/plugin_uploads/tmp \
    storage/app/plugin_uploads/extracted \
    storage/app/plugin_uploads/pending_updates

chmod 2770 \
    storage/app/plugin_uploads \
    storage/app/plugin_uploads/tmp \
    storage/app/plugin_uploads/extracted \
    storage/app/plugin_uploads/pending_updates

# Bind mounts and persistent volumes can contain files created by the host or
# an older container. Keep all runtime files writable by Apache, not only their
# parent directories.
RUNTIME_WRITABLE_PATHS="
modules
public/platform/plugins
public/platform/themes
storage/app/private
storage/app/public
storage/app/platform
storage/app/plugin_uploads
storage/app/plugin_updates
storage/app/plugin_uninstalls
storage/app/theme-overrides
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
bootstrap/cache
"

chgrp -R www-data $RUNTIME_WRITABLE_PATHS

find $RUNTIME_WRITABLE_PATHS -type d -exec chmod g+rwx {} +
find $RUNTIME_WRITABLE_PATHS -type f -exec chmod g+rw {} +

if [ "${ART_INPA_DEVELOPMENT:-0}" = "1" ]; then
    DEVELOPMENT_UID="${ART_INPA_HOST_UID:-1000}"
    DEVELOPMENT_GID="${ART_INPA_HOST_GID:-1000}"
    chown -R "$DEVELOPMENT_UID:$DEVELOPMENT_GID" $RUNTIME_WRITABLE_PATHS
    chgrp -R www-data $RUNTIME_WRITABLE_PATHS

    # The source tree is owned by the host user while Composer runs as root
    # inside the container. Trust only the exact application mount so Composer
    # can inspect package metadata without weakening Git's global protection.
    if [ -d .git ]; then
        git config --global --add safe.directory /var/www/html
    fi
fi

if [ ! -f vendor/autoload.php ] || [ "${ART_INPA_DEVELOPMENT:-0}" = "1" ]; then
    echo ">>> Installing Composer dependencies for a source-mounted development workspace..."
    composer install --no-interaction --prefer-dist
fi

# Source bind mounts hide files baked into /var/www/html. Restore the verified
# build artifact from the immutable image when the mounted tree is empty.
if [ ! -s public/build/manifest.json ] && [ -s /opt/art-inpa/public/build/manifest.json ]; then
    echo ">>> Restoring prebuilt Vite assets from the container image..."
    mkdir -p public/build
    cp -R /opt/art-inpa/public/build/. public/build/
fi

if [ ! -s public/build/manifest.json ]; then
    echo "ERROR: Vite manifest is missing. The deployment image is incomplete." >&2
    echo "Rebuild the image from this repository; do not start Laravel without the frontend build stage." >&2
    exit 1
fi

runtime_value() {
    php -r '
        $path = "/var/www/html/storage/app/platform/installation.env";
        $key = $argv[1] ?? "";
        $content = is_readable($path) ? (string) file_get_contents($path) : "";
        if ($key !== "" && preg_match("/^".preg_quote($key, "/")."=(.*)$/m", $content, $match) === 1) {
            echo trim(trim($match[1]), "\"\x27");
        }
    ' "$1"
}

RUNTIME_INSTALLATION_FLAG=""
if [ -r storage/app/platform/installation.env ]; then
    for INSTALLATION_KEY in INSTALLATION_COMPLETE INSTAAL_IS_ACTIVE INSTAAL_IS_ATIVE; do
        INSTALLATION_VALUE="$(runtime_value "$INSTALLATION_KEY")"
        if [ "$INSTALLATION_VALUE" = "1" ]; then
            RUNTIME_INSTALLATION_FLAG="1"
            break
        fi
        if [ -n "$INSTALLATION_VALUE" ] && [ -z "$RUNTIME_INSTALLATION_FLAG" ]; then
            RUNTIME_INSTALLATION_FLAG="0"
        fi
    done
fi

if [ -f storage/app/platform/installation.complete ]; then
    RUNTIME_INSTALLATION_FLAG="1"
fi

# Persistent installer state is authoritative during image upgrades. Process
# variables remain supported for installations managed by an external secrets
# provider when no persistent flag exists. Any supported legacy flag set to 1
# keeps an existing installation active during the canonical flag migration.
ENVIRONMENT_INSTALLATION_FLAG="0"
if [ "${INSTALLATION_COMPLETE:-0}" = "1" ] || [ "${INSTAAL_IS_ACTIVE:-0}" = "1" ] || [ "${INSTAAL_IS_ATIVE:-0}" = "1" ]; then
    ENVIRONMENT_INSTALLATION_FLAG="1"
fi
INSTALLATION_FLAG="${RUNTIME_INSTALLATION_FLAG:-$ENVIRONMENT_INSTALLATION_FLAG}"

if [ -z "${APP_KEY:-}" ] && [ -r storage/app/platform/installation.env ]; then
    APP_KEY="$(runtime_value APP_KEY)"

    if [ -n "$APP_KEY" ]; then
        export APP_KEY
    fi
fi

if [ -z "${APP_KEY:-}" ] && [ "$INSTALLATION_FLAG" != "1" ]; then
    echo ">>> Generating the first-run Laravel application key..."
    APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    export APP_KEY

    php -r '
        $key = getenv("APP_KEY");
        $write = static function (string $path, bool $protect = false) use ($key): void {
            $content = is_file($path) ? (string) file_get_contents($path) : "";
            $line = "APP_KEY=\"".str_replace(["\\", "\"", "\n", "\r"], ["\\\\", "\\\"", "", ""], $key)."\"";
            $pattern = "/^APP_KEY=.*$/m";
            $content = preg_match($pattern, $content)
                ? (string) preg_replace($pattern, $line, $content)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
            file_put_contents($path, ltrim($content), LOCK_EX);
            if ($protect) {
                @chmod($path, 0660);
            }
        };

        $runtime = "/var/www/html/storage/app/platform/installation.env";
        $write($runtime, true);

        $environment = "/var/www/html/.env";
        if (is_file($environment) && is_writable($environment)) {
            $write($environment);
        }
    '
fi

# The first-run key file is created by this root entrypoint after the general
# permission pass above. Hand it to Apache explicitly so the web installer can
# persist database credentials and the completion marker in the named volume.
if [ -f storage/app/platform/installation.env ]; then
    chgrp www-data storage/app/platform/installation.env
    chmod 0660 storage/app/platform/installation.env
fi

if [ -z "${APP_KEY:-}" ] && [ "$INSTALLATION_FLAG" = "1" ]; then
    echo "ERROR: The platform is marked as installed but APP_KEY is missing." >&2
    echo "Restore the original persistent APP_KEY; generating a replacement would invalidate encrypted data." >&2
    exit 1
fi

if [ "$INSTALLATION_FLAG" = "1" ]; then
    echo ">>> Existing installation detected; applying non-destructive database migrations..."
    php artisan migrate --force --no-interaction
fi

php artisan package:discover --ansi >/dev/null

exec "$@"
