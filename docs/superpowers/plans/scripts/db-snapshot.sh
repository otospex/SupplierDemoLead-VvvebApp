#!/usr/bin/env bash
# Dump the rows we are about to modify so the change is reversible.
#
# IMPORTANT: target the SAME MySQL instance the app uses. The PHP container
# connects to host "db", which resolves (via the app's docker network) to the
# container "supplierdemolead-vvvebapp-db-1" — NOT the standalone container that
# happens to be named "db". Editing the wrong instance silently has no effect on
# the running site. Override with DB_CONTAINER=... if your stack differs.
set -euo pipefail
DB_CONTAINER="${DB_CONTAINER:-supplierdemolead-vvvebapp-db-1}"
OUT="docs/superpowers/plans/scripts/db-backup-$(date +%Y%m%d-%H%M%S).sql"
docker exec "$DB_CONTAINER" mysqldump -uvvveb -pvvveb vvveb \
  post --where="post_id IN (1,2,3,4,5,6,7,11,12)" > "$OUT.post" 2>/dev/null
docker exec "$DB_CONTAINER" mysqldump -uvvveb -pvvveb vvveb \
  post_content --where="post_id IN (7,11,12)" > "$OUT.post_content" 2>/dev/null
echo "Backup written ($DB_CONTAINER): $OUT.post  /  $OUT.post_content"
