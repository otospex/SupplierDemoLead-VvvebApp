#!/bin/sh
# Replacement init.sh for the Dokploy build.
# - Mirrors upstream vvveb/vvvebcms init.sh logic (download Vvveb on first start)
# - Then overlays our plugin / theme on top, every run, so updates always land
# - Hands off to supervisord, identical to upstream

set -e

# Use a real Vvveb file as the existence check, not just /public/.
# Volume mounts auto-create parent directories, so /public/ may exist as an
# empty directory even on a fresh install — checking for index.php is reliable.
if [ ! -f /var/www/html/public/index.php ]; then
    export DIR_VVVEB='/var/www/html'
    export DIR_CONFIG=${DIR_VVVEB}'/config'
    export DIR_PUBLIC=${DIR_VVVEB}'/public'
    export DIR_PLUGINS=${DIR_VVVEB}'/plugins'
    export DIR_STORAGE=${DIR_VVVEB}'/storage'
    export DIR_CACHE=${DIR_STORAGE}'/cache'
    export DIR_ADMIN=${DIR_VVVEB}'/admin'
    export DIR_DIGITAL_ASSETS=${DIR_STORAGE}'/digital_assets'
    export DIR_IMAGE_CACHE=${DIR_PUBLIC}'/image-cache'
    export DOWNLOAD_URL=${DOWNLOAD_URL:='https://www.vvveb.com/download.php'}

    echo "[init] Bootstrapping Vvveb from ${DOWNLOAD_URL}…"
    curl -Lo /tmp/vvveb.zip ${DOWNLOAD_URL}
    unzip -o /tmp/vvveb.zip -d ${DIR_VVVEB}
    rm -rf /tmp/vvveb.zip

    mkdir -p ${DIR_STORAGE}/logs
    touch ${DIR_STORAGE}/logs/error_log
    chown -R www-data:www-data ${DIR_VVVEB}
    chmod -R 744 ${DIR_VVVEB}
    chmod -R 733 ${DIR_STORAGE}
    chmod -R 733 ${DIR_PUBLIC}
    chmod -R 744 ${DIR_PUBLIC}/index.php
    chmod -R 744 ${DIR_PUBLIC}/admin/index.php
    chmod -R 744 ${DIR_PUBLIC}/vadmin/index.php
    chmod -R 733 ${DIR_CONFIG}

    # Upstream init.sh leaves these unwritable — Vvveb installer bails with
    # "plugins is not writable" / "public/themes is not writable" without these.
    chmod -R 755 ${DIR_PLUGINS}
    chmod -R 755 ${DIR_PUBLIC}/plugins
    chmod -R 755 ${DIR_PUBLIC}/themes
fi

# Overlay our plugin + theme on top of the upstream-bootstrapped tree.
# Runs every container start so plugin updates baked into the image always land.
if [ -d /opt/lpc-overlay ]; then
    echo "[init] Applying lead-platform-connector overlay…"
    cp -a /opt/lpc-overlay/. /var/www/html/
    chown -R www-data:www-data \
        /var/www/html/plugins \
        /var/www/html/public/plugins \
        /var/www/html/public/themes 2>/dev/null || true
    chmod -R u+rwX,go+rX /var/www/html/plugins /var/www/html/public/plugins /var/www/html/public/themes 2>/dev/null || true

    # Ensure every theme has a writable backup/ folder. Vvveb's visual editor
    # copies the current page into backup/ before overwriting on save; without
    # the directory the save bails with "<theme>/backup folder not writable!".
    for theme_dir in /var/www/html/public/themes/*/; do
        [ -d "$theme_dir" ] || continue
        mkdir -p "${theme_dir}backup"
        chown www-data:www-data "${theme_dir}backup"
        chmod 775 "${theme_dir}backup"
    done

    # Cache invalidation: themes/plugins lists are scanned from disk and cached.
    # Volume persists across redeploys, so a freshly-overlaid theme stays
    # invisible in the admin until this cache is busted.
    rm -f /var/www/html/storage/cache/vvveb.themes_list_* \
          /var/www/html/storage/cache/vvveb.plugins_list_* 2>/dev/null || true
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
