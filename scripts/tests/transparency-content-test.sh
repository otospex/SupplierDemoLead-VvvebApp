#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
seed="$project_root/seed.dokploy.sql"

failures=0

require_seed_text() {
    local pattern="$1"
    local message="$2"
    if ! rg -qi "$pattern" "$seed"; then
        printf 'FAIL: %s\n' "$message" >&2
        failures=$((failures + 1))
    fi
}

require_seed_text "'methode-evaluation'" 'methodology page slug is missing.'
require_seed_text "'transparence-partenariats'" 'partnership transparency page slug is missing.'
require_seed_text 'partenaire commercial non exclusif' 'non-exclusive commercial relationship is not disclosed.'
require_seed_text 'aucune recommandation automatique' 'automatic recommendations are not explicitly ruled out.'
require_seed_text 'correction factuelle' 'provider factual-correction policy is missing.'
require_seed_text 'date de dernière revue' 'content review date is missing.'
require_seed_text 'alternatives' 'alternatives policy is missing.'

if (( failures > 0 )); then
    printf 'transparency-content tests: FAIL (%d issue(s))\n' "$failures" >&2
    exit 1
fi

printf 'transparency-content tests: PASS\n'
