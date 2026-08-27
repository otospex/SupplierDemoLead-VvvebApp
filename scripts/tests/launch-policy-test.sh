#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

failures=0
fail() {
  printf 'FAIL: %s\n' "$1" >&2
  failures=$((failures + 1))
}

grep -q 'https://independantdigital.fr/' public/themes/souverainete-digitale/index.fr.html || fail 'French homepage does not declare the launch domain.'
grep -q '<meta name="robots" content="noindex,follow">' public/themes/souverainete-digitale/index.html || fail 'Parked English homepage is indexable.'
grep -q "type='post' AND status='publish'" seed.dokploy.sql || fail 'Unreviewed legacy posts are not retired at launch.'
grep -q "slug NOT IN ('contact','a-propos','methode-evaluation'" seed.dokploy.sql || fail 'The reviewed-page allowlist is missing from the launch seed.'

if rg -n 'souverainete-digitale\.fr|Digital\.Sovereignty|independance-otospex-dev|admin@admin\.com|contact@admin\.com' \
  public/themes/souverainete-digitale seed.dokploy.sql >/dev/null; then
  fail 'Old domains, demo identity or development endpoints remain in publishable assets.'
fi

if rg -n 'href="https://(?:www\.)?(?:linkedin\.com|x\.com|github\.com|youtube\.com)/"' \
  public/themes/souverainete-digitale --glob '*.fr.html' >/dev/null; then
  fail 'French templates still expose placeholder social links.'
fi

for template in \
  public/themes/souverainete-digitale/index.fr.html \
  public/themes/souverainete-digitale/content/index.fr.html \
  public/themes/souverainete-digitale/content/page.fr.html \
  public/themes/souverainete-digitale/content/post.fr.html \
  public/themes/souverainete-digitale/content/contact.fr.html; do
  grep -q '/page/independance-numerique' "$template" || fail "$template does not expose the reviewed independence hub."
  grep -q '/page/methode-evaluation' "$template" || fail "$template does not expose the public evaluation method."
  grep -q '/page/transparence-partenariats' "$template" || fail "$template does not expose the partnership disclosure."
  if grep -q 'Nouveau livre blanc\|/page/solutions' "$template"; then
    fail "$template still advertises an unreviewed legacy route or product."
  fi
  if grep -qi 'Supplier Demo\|Powerful and easy to use drag and drop\|Digital Sovereignty - B2B Solutions' "$template"; then
    fail "$template still contains starter-theme metadata."
  fi
done

if [[ ! -f docs/launch/open-items.md ]]; then
  fail 'External launch inputs are not recorded.'
else
  grep -q 'Identité juridique' docs/launch/open-items.md || fail 'Legal identity launch gate is missing.'
  grep -q 'Hébergeur' docs/launch/open-items.md || fail 'Hosting disclosure launch gate is missing.'
  grep -q 'Durée de conservation' docs/launch/open-items.md || fail 'Lead retention launch gate is missing.'
fi

if (( failures > 0 )); then
  printf 'launch-policy tests: FAIL (%d issue(s))\n' "$failures" >&2
  exit 1
fi

printf 'launch-policy tests: PASS\n'
