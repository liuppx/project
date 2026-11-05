#!/usr/bin/env bash
set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/package-common.sh"
package_common_init project "$@"
for command_name in npm composer; do command -v "$command_name" >/dev/null 2>&1 || package_fail "$command_name is required"; done
[[ -f "$PACKAGE_ROOT_DIR/package-lock.json" && -f "$PACKAGE_ROOT_DIR/composer.lock" ]] || package_fail 'package-lock.json and composer.lock are required for deterministic installs'
(cd "$PACKAGE_ROOT_DIR" && HUSKY=0 npm ci --no-audit --no-fund && npx vite build -- fromcmd)
(cd "$PACKAGE_ROOT_DIR" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress)
[[ -f "$PACKAGE_ROOT_DIR/public/manifest.json" && -d "$PACKAGE_ROOT_DIR/public/js/build" ]] || package_fail 'frontend build manifest or assets are missing'
package_make_root project
package_archive_source "$PACKAGE_DIR"
rm -rf "$PACKAGE_DIR/node_modules" "$PACKAGE_DIR/vendor" "$PACKAGE_DIR/output"
cp -R "$PACKAGE_ROOT_DIR/vendor" "$PACKAGE_DIR/vendor"
mkdir -p "$PACKAGE_DIR/public/js"
cp -R "$PACKAGE_ROOT_DIR/public/js/build" "$PACKAGE_DIR/public/js/build"
cp "$PACKAGE_ROOT_DIR/public/manifest.json" "$PACKAGE_DIR/public/manifest.json"
package_copy_scripts "$PACKAGE_DIR/scripts"
package_strip_build_files "$PACKAGE_DIR"
package_finish_artifact service-with-assets
package_finish
