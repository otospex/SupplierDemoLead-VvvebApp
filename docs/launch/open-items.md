# Entrées externes requises avant ouverture publique

Le code, les contenus et la file de demandes peuvent être testés en préproduction. Les formulaires ne doivent pas être ouverts au public tant que les éléments ci-dessous ne sont pas renseignés dans la notice de confidentialité et les mentions légales.

## Blocants juridiques

- [ ] **Identité juridique** — nom ou raison sociale de l&rsquo;exploitant, forme juridique, capital le cas échéant, SIREN/SIRET ou autre identifiant applicable, adresse postale et directeur de publication.
- [ ] **Contact données personnelles** — adresse e-mail ou adresse postale dédiée à l&rsquo;exercice des droits.
- [ ] **Hébergeur** — raison sociale, adresse, pays d&rsquo;exploitation, localisation des données et sous-traitants réellement utilisés par le déploiement `independantdigital.fr`.
- [ ] **Durée de conservation** — durée opérationnelle des demandes non qualifiées, des demandes qualifiées et des journaux de sécurité, puis tâche de purge correspondante.
- [ ] **Mentions légales** — informations imposées par le statut réel de l&rsquo;exploitant et coordonnées de l&rsquo;hébergeur.

## Configuration de production

- [ ] Confirmer que `independantdigital.fr` et `www.independantdigital.fr` pointent vers le déploiement Dokploy avec TLS actif.
- [ ] Créer l&rsquo;adresse de contact publiée et tester sa délivrabilité. Ne pas afficher une adresse tant que sa boîte et ses enregistrements DNS ne sont pas opérationnels.
- [ ] Sauvegarder la clé `storage/lead-platform-connector.key`. Sans elle, les demandes chiffrées en attente ne sont plus lisibles.
- [ ] Définir et tester la sauvegarde de la base contenant `lead_submission` ainsi que la procédure de restauration.
- [ ] Configurer le système de distribution de leads en remplissant ensemble `platform_url` et la clé API. Laisser les deux champs vides maintient la file locale; n&rsquo;en remplir qu&rsquo;un bloque les envois.
- [ ] Ajouter une tâche de purge alignée sur la durée de conservation retenue.
- [ ] `seed.dokploy.sql` (~ligne 2195) écrit le titre/meta FR de la page d&rsquo;accueil sous une clé `site.settings.description` codée en dur `'2'`. Sur un déploiement propre où le français n&rsquo;obtient pas `language_id = 2`, la page d&rsquo;accueil FR perd son `<title>`/meta. Calculer la clé dynamiquement (comme le bloc v9 le fait déjà pour `@fr_id`/`site.settings.language_id`) avant tout nouveau déploiement.

## Validation commerciale et éditoriale

- [ ] AIFEL fournit les preuves demandées dans `docs/providers/aifel/evidence-request.md`; la revue passe explicitement à publiable avant toute fiche ou formulaire nominatif.
- [ ] Les pages d&rsquo;alternatives restent avec `editorial_ready=0` jusqu&rsquo;à saisie d&rsquo;une source de demande française et validation de la checklist manuelle.
- [ ] Tout outil d&rsquo;analyse d&rsquo;audience ou de publicité est documenté avant activation; le bandeau et la politique de cookies sont adaptés aux traceurs réellement déployés.
- [ ] La meta description de la page d&rsquo;accueil anglaise dans `seed.dokploy.sql` (~ligne 2198) affirme un cloud souverain « SecNumCloud-certified » : à réécrire avant toute exposition publique de `/en/`, cette certification n&rsquo;étant pas établie côté commercial/éditorial.

## Décision d&rsquo;ouverture

Le feu vert est consigné avec la date, le nom du responsable et les URL des notices finales. Une case cochée doit renvoyer vers une preuve ou une configuration vérifiée; elle ne doit pas reposer sur une hypothèse.
