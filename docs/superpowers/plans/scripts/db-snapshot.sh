#!/usr/bin/env bash
# Dump the rows we are about to modify so the change is reversible.
set -euo pipefail
OUT="docs/superpowers/plans/scripts/db-backup-$(date +%Y%m%d-%H%M%S).sql"
docker exec db mysqldump -uvvveb -pvvveb vvveb \
  post --where="post_id IN (1,2,3,4,5,6,7,11,12)" > "$OUT.post" 2>/dev/null
docker exec db mysqldump -uvvveb -pvvveb vvveb \
  post_content --where="post_id IN (7,11,12)" > "$OUT.post_content" 2>/dev/null
echo "Backup written: $OUT.post  /  $OUT.post_content"
