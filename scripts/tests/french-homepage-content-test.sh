#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
homepage="$project_root/public/themes/souverainete-digitale/index.fr.html"
seed="$project_root/seed.dokploy.sql"

failures=0

require_text() {
    local pattern="$1"
    local message="$2"
    if ! rg -q "$pattern" "$homepage"; then
        printf 'FAIL: %s\n' "$message" >&2
        failures=$((failures + 1))
    fi
}

forbid_text() {
    local pattern="$1"
    local message="$2"
    if rg -q "$pattern" "$homepage"; then
        printf 'FAIL: %s\n' "$message" >&2
        failures=$((failures + 1))
    fi
}

require_text 'Indépendant Digital' 'French homepage must use the Indépendant Digital brand.'
require_text 'independantdigital\.fr' 'French homepage must use the final domain.'
require_text 'Vers la souveraineté numérique' 'French homepage must use the approved souveraineté numérique H1 pivot.'
require_text 'href="(#process|/page/methode-evaluation)"' 'French homepage must link to the evaluation methodology.'
if ! rg -q "Indépendant Digital — Souveraineté numérique par étapes" "$seed"; then
    printf 'FAIL: deployment seed must use the approved French homepage title.\n' >&2
    failures=$((failures + 1))
fi

forbid_text 'Digital\.Sovereignty' 'Legacy demo brand is still visible.'
forbid_text 'souverainete-digitale\.fr' 'Legacy domain is still present.'
forbid_text 'ACMECORP|CUBIX|NEXUS|DELTA' 'Demo customer brands are still present.'
forbid_text '250\+?[[:space:]]+organisations|plus de 250 organisations' 'Unsupported customer count is still present.'
forbid_text 'Conformité vérifiée' 'Unsupported verified-compliance label is still present.'
forbid_text 'certifié SecNumCloud' 'Indépendant Digital must not present itself as SecNumCloud-certified.'
if rg -q "Souveraineté Numérique — Cloud souverain & protection des données|Cloud souverain certifié SecNumCloud, à l''abri du CLOUD Act" "$seed"; then
    printf 'FAIL: deployment seed still contains the legacy French homepage metadata.\n' >&2
    failures=$((failures + 1))
fi

if (( failures > 0 )); then
    printf 'french-homepage-content tests: FAIL (%d issue(s))\n' "$failures" >&2
    exit 1
fi

printf 'french-homepage-content tests: PASS\n'
