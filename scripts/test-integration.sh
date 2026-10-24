#!/usr/bin/env bash
set -euo pipefail
# This script installs WordPress into a disposable directory/database. Never point it at a live site.
: "${KTICKETS_TESTING:?Set KTICKETS_TESTING=1 only for a disposable database}"
: "${KTICKETS_WP_PATH:?Set KTICKETS_WP_PATH to a disposable WordPress directory}"
: "${KTICKETS_DB_NAME:?Set KTICKETS_DB_NAME}"
: "${KTICKETS_DB_USER:?Set KTICKETS_DB_USER}"
: "${KTICKETS_DB_HOST:=127.0.0.1:3306}"
: "${KTICKETS_DB_PASSWORD:=}"
: "${WP_CLI:=wp}"
repo_dir=$(cd "$(dirname "$0")/.." && pwd)
mkdir -p "$KTICKETS_WP_PATH"
"$WP_CLI" core download --path="$KTICKETS_WP_PATH" --version="${WP_VERSION:-7.1.3}"
"$WP_CLI" config create --path="$KTICKETS_WP_PATH" --dbname="$KTICKETS_DB_NAME" --dbuser="$KTICKETS_DB_USER" --dbpass="$KTICKETS_DB_PASSWORD" --dbhost="$KTICKETS_DB_HOST" --dbprefix=sample_ --dbcharset=utf8mb4
"$WP_CLI" core install --path="$KTICKETS_WP_PATH" --url=https://example.test/konstantinos --title='Synthetic ticket test' --admin_user=fixture_owner --admin_password='fixture-password-only' --admin_email=fixture@example.test --skip-email
ln -s "$repo_dir" "$KTICKETS_WP_PATH/wp-content/plugins/kourentzes-tickets"
"$WP_CLI" plugin activate kourentzes-tickets --path="$KTICKETS_WP_PATH"
"$WP_CLI" eval-file "$repo_dir/tests/integration.php" --path="$KTICKETS_WP_PATH"
