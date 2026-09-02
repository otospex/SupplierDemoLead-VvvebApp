# Entrées externes requises avant ouverture publique

Le code, les contenus et la file de demandes peuvent être testés en préproduction. Les formulaires ne doivent pas être ouverts au public tant que les éléments ci-dessous ne sont pas renseignés dans la notice de confidentialité et les mentions légales.

## Blocants juridiques

- [ ] **Identité juridique** — l&rsquo;exploitant retenu est **Otospex Solutions SARL** (17 avenue Bourguiba, 4180 Houmet Souk, Tunisie ; +216 28 87 01 85 ; contact@otospex.com), renseigné dans `/mentions-legales` et `/confidentialite` (seed, 2026-09-02). Restent à saisir dans ces deux pages, aux marqueurs `[À compléter]` : capital social, RNE / matricule fiscal, nom du gérant (directeur de la publication), représentant dans l&rsquo;Union européenne (art. 27 RGPD, obligatoire pour un responsable établi hors UE).
- [ ] **Contact données personnelles** — adresse e-mail dédiée à l&rsquo;exercice des droits (marqueur dans `/confidentialite`).
- [ ] **Hébergeur** — raison sociale, adresse, pays d&rsquo;exploitation, localisation des données et sous-traitants réellement utilisés par le déploiement `independantdigital.fr`.
- [ ] **Durée de conservation** — deux durées à fixer (demandes sans suite ; demandes ayant donné lieu à une mise en relation), à reporter dans `/confidentialite`, puis à configurer sur la tâche de purge : `LEAD_RETENTION_DAYS=<jours> php scripts/purge-leads.php --apply` (dry run sans `--apply` ; single-flight par `flock`).
- [ ] **Mentions légales** — page `/mentions-legales` créée et liée depuis le pied de page ; compléter les marqueurs (capital, immatriculation, gérant, représentant UE, hébergeur). Transferts hors UE : la notice s&rsquo;appuie sur les clauses contractuelles types (décision 2021/914) pour l&rsquo;accès de l&rsquo;équipe depuis la Tunisie — les signer et les tenir à disposition.

## Configuration de production

- [ ] Confirmer que `independantdigital.fr` et `www.independantdigital.fr` pointent vers le déploiement Dokploy avec TLS actif.
- [ ] Créer l&rsquo;adresse de contact publiée et tester sa délivrabilité. Ne pas afficher une adresse tant que sa boîte et ses enregistrements DNS ne sont pas opérationnels.
- [ ] Sauvegarder la clé `storage/lead-platform-connector.key`. Sans elle, les demandes chiffrées en attente ne sont plus lisibles.
- [ ] Définir et tester la sauvegarde de la base contenant `lead_submission` ainsi que la procédure de restauration.
- [ ] Configurer le système de distribution de leads en remplissant ensemble `platform_url` et la clé API. Laisser les deux champs vides maintient la file locale; n&rsquo;en remplir qu&rsquo;un bloque les envois.
- [x] Tâche de purge : `scripts/purge-leads.php` (2026-09-02). Reste à la planifier (cron) avec la durée retenue.
- [x] Clés `site.settings.description` résolues dynamiquement (`@desc_en_id` / `@desc_fr_id`) dans `seed.dokploy.sql` (2026-09-02).
- [x] **Sitemaps et robots** (2026-09-02) — `/sitemap.xml` (index), `/sitemap-pages.xml`, `/sitemap-posts.xml`, `/sitemap-solutions.xml` générés par `app/controller/sitemap.php` et le plugin annuaire, `<loc>` absolus sur `CANONICAL_URL`, hreflang + x-default. `robots.txt` déclare le sitemap en absolu et autorise explicitement les robots IA (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot…). nginx : `nginx.dokploy.conf` (conf de l&rsquo;image + location sitemap) copié par `Dockerfile.dokploy`. À faire après mise en ligne : soumettre `https://independantdigital.fr/sitemap.xml` dans Search Console et Bing Webmaster.
- [x] **URLs sans préfixe `/page/`** (2026-09-02) — pages à `/{slug}`, articles à `/blog/{slug}`, anglais sous `/en/…` ; `/page/{slug}` et `/{lang}/page/{slug}` répondent 301. Liens réécrits dans les gabarits, le seed et la base.
- [x] **Canonicals par page** (2026-09-02) — `link[rel=canonical]`, `og:url`, `og:title`, `og:description` calculés par page (`CANONICAL_URL` + chemin) via `common.tpl` / `post.tpl` après la propagation du `<head>`. Vérifier `CANONICAL_URL=https://independantdigital.fr` dans l&rsquo;environnement Dokploy (déjà dans `docker-compose.dokploy.yaml`).
- [x] **Overlay de déploiement complet** (2026-09-02) — `Dockerfile.dokploy` copie désormais `config/app-routes.php`, `env.php`, le plugin `solutions-directory` et ses assets, le contrôleur sitemap, robots, les gabarits et `scripts/` ; `scripts/tests/deploy-overlay-test.php` échoue dès qu&rsquo;un fichier du fork n&rsquo;est pas couvert. Avant ce correctif, l&rsquo;annuaire et ses routes n&rsquo;atteignaient jamais la production.
- [ ] **Recette de cache au déploiement.** Après toute modification de routes, de PHP ou de gabarits, purger dans cet ordre puis redémarrer : `rm -f storage/compiled-templates/app_1_*` ; `rm -rf public/page-cache/* storage/cache/routes.* storage/cache/vvveb.plugins_list_*` ; `docker compose restart php`. Omettre `storage/cache/routes.*` laisse tourner l&rsquo;ancienne table de routage ; omettre `vvveb.plugins_list_*` laisse tourner l&rsquo;ancienne liste de plugins. Les entrées `public/page-cache/*/page/*` de l&rsquo;ancienne forme d&rsquo;URL doivent disparaître (le déploiement vide `page-cache` à chaque démarrage).
- [x] **Flush single-flight** (2026-09-02) — `scripts/flush-partial-leads.php` prend un `flock` non bloquant (`storage/flush-partial-leads.lock`) et sort en 0 si une exécution est en cours. La base doit tourner en UTC ; le script avertit sur STDERR si l&rsquo;horloge de la base s&rsquo;en écarte.

## Validation commerciale et éditoriale

- [ ] AIFEL fournit les preuves demandées dans `docs/providers/aifel/evidence-request.md`; la revue passe explicitement à publiable avant toute fiche ou formulaire nominatif.
- [ ] Les pages d&rsquo;alternatives restent avec `editorial_ready=0` jusqu&rsquo;à saisie d&rsquo;une source de demande française et validation de la checklist manuelle.
- [ ] Tout outil d&rsquo;analyse d&rsquo;audience ou de publicité est documenté avant activation; le bandeau et la politique de cookies sont adaptés aux traceurs réellement déployés.
- [ ] `/blog` figure dans la navigation et reste vide tant que le publieur programmé n&rsquo;a pas tourné. Soit lancer la publication programmée avant l&rsquo;ouverture, soit retirer l&rsquo;entrée de la navigation : une rubrique annoncée puis vide est un défaut visible dès la première visite.
- [ ] La meta description de la page d&rsquo;accueil anglaise dans `seed.dokploy.sql` (~ligne 2198) affirme un cloud souverain « SecNumCloud-certified » : à réécrire avant toute exposition publique de `/en/`, cette certification n&rsquo;étant pas établie côté commercial/éditorial.

## Décision d&rsquo;ouverture

Le feu vert est consigné avec la date, le nom du responsable et les URL des notices finales. Une case cochée doit renvoyer vers une preuve ou une configuration vérifiée; elle ne doit pas reposer sur une hypothèse.

- [x] **Balises canonical/og inertes** — réglé par le calcul par page dans la propagation (voir ci-dessus). Les balises restées dans `generated/*.fr.html` sont sans effet et peuvent être nettoyées à l&rsquo;occasion.
- [ ] **Pages de démonstration anglaises publiées** — `/en/marketplace`, `/en/pricing`, `/en/portfolio`, `/en/shipping-delivery`, `/en/subscription-policy` (restes Vvveb) sont `publish` et figurent donc dans `/sitemap-pages.xml`. Les passer en brouillon ou les supprimer avant soumission du sitemap.
- [ ] **Annuaire : six fiches déclarées** (Tixeo, Oodrive Work, BlueMind, OUTSCALE, Olvid, Wimi) seedées le 2026-09-02 en `verification_status=declare`. Avant tout passage en « vérifié » : revoir chaque fiche avec la checklist, confirmer les dates de validité au catalogue ANSSI (CSPN Tixeo expirée le 24/01/2026, CSPN Olvid « non maintenu », SecNumCloud Outscale à renouveler le 30/11/2026) et l&rsquo;hébergeur d&rsquo;Olvid.
- [ ] **Audit SEO** — voir `docs/launch/seo-audit-2026-09-02.md` pour les points restants côté contenu.
