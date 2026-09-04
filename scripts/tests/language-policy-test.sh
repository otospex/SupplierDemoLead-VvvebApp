#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
theme="$project_root/public/themes/souverainete-digitale"

english=(
    "$theme/index.html"
    "$theme/content/index.html"
    "$theme/content/page.html"
    "$theme/content/post.html"
    "$theme/content/contact.html"
)

french=(
    "$theme/index.fr.html"
    "$theme/content/index.fr.html"
    "$theme/content/page.fr.html"
    "$theme/content/post.fr.html"
    "$theme/content/contact.fr.html"
)

failures=0

for template in "${english[@]}"; do
    if ! rg -q '<meta name="robots" content="noindex,follow">' "$template"; then
        printf 'FAIL: %s must be parked with noindex,follow.\n' "${template#$project_root/}" >&2
        failures=$((failures + 1))
    fi
done

for template in "${french[@]}"; do
    if rg -q '<meta name="robots" content="noindex,follow">' "$template"; then
        printf 'FAIL: %s must remain indexable.\n' "${template#$project_root/}" >&2
        failures=$((failures + 1))
    fi
    if rg -q 'data-v-component-language' "$template"; then
        printf 'FAIL: %s must not expose the parked language in navigation.\n' "${template#$project_root/}" >&2
        failures=$((failures + 1))
    fi
done

if (( failures > 0 )); then
    printf 'language-policy tests: FAIL (%d issue(s))\n' "$failures" >&2
    exit 1
fi

printf 'language-policy tests: PASS\n'
