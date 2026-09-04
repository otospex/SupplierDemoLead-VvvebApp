# Vérification de la fondation de lancement — 27 août 2026

## Résultat interne

- Suite éditoriale, contrats de contenu, politique de langue, consentement, chiffrement, mode de livraison et publisher : réussis.
- Audit éditorial : `0 blocking claims`.
- Routes publiques revues : HTTP 200. Brouillon Teams retenu : HTTP 404. Version anglaise conservée avec `noindex,follow`.
- Formulaire général : demande chiffrée en file `pending`, sans e-mail brut dans le payload d’audit.
- Panne d’insertion MySQL simulée : HTTP 503 et aucun faux accusé de réception.
- Approbation éditoriale CLI testée contre MySQL, avec relecteur et horodatage, puis brouillon remis à `editorial_ready=0`.
- Image Dokploy construite depuis un digest fixe; archive Vvveb 1.0.8.6 vérifiée par SHA-256; `pdo_mysql` présent dans l’image finale.
- Contrôle visuel représentatif effectué sur ordinateur et mobile : navigation, formulaire, hiérarchie et absence de débordement horizontal sur les routes de lancement.

## Limite du feu vert

La fondation applicative est prête pour une préproduction. L’ouverture publique des formulaires reste volontairement bloquée par les informations opérateur listées dans `docs/launch/open-items.md` : identité juridique, hébergeur et localisation réelle, contact données personnelles, durée de conservation et purge, DNS/TLS, boîte de contact, sauvegardes et clé de chiffrement.

Les trois pages d’acquisition programmées restent non publiables (`editorial_ready=0`) jusqu’à revue humaine et preuve de demande. AIFEL reste absent des recommandations nominatives tant que son dossier de preuves n’a pas été validé.
