# Qualification d'une opportunité AIFEL

Une opportunité n'est transmise que si le besoin correspond au périmètre étudié et si la personne a accepté une introduction nominative.

## Conditions minimales

- La visioconférence ou la collaboration d'équipe est le besoin central.
- Une exploitation française ou la réduction de dépendances étrangères compte réellement dans la décision.
- L'organisation, son secteur et un ordre de grandeur du nombre d'utilisateurs sont connus.
- Le contact est DSI, RSSI, CTO, dirigeant, acheteur ou mandaté par l'un de ces rôles.
- L'échéance, le niveau de maturité et l'outil actuel sont renseignés.
- Les intégrations indispensables, les contraintes d'accessibilité et les exigences de sécurité sont indiquées.
- Le contact a coché le consentement nominatif AIFEL présenté dans le formulaire.

## Informations transmises

- Coordonnées fournies dans le formulaire.
- Organisation, rôle, taille approximative et secteur.
- Cas d'usage, utilisateurs, outil actuel, échéance et contraintes déclarées.
- Source de la demande et paramètres de campagne pertinents.
- Version et horodatage du consentement nominatif.

## Informations qui ne valent pas consentement

- Inscription à une lettre d'information.
- Téléchargement d'un guide.
- Diagnostic général.
- Visite d'une page AIFEL ou clic sur un lien.
- Sélection d'une catégorie de besoin sans case nominative.

## États de routage

1. `diagnostic_only` : réponse utile sans transmission à un fournisseur.
2. `needs_review` : intérêt possible mais informations ou preuves insuffisantes.
3. `qualified_introduction` : conditions minimales et consentement présents.
4. `not_a_fit` : besoin hors périmètre ou contrainte bloquante connue.

## Contrôle avant envoi

Le responsable vérifie la cohérence du besoin, la version du consentement, la date et l'absence de champ ajouté par inférence. Le fournisseur reçoit uniquement les opportunités `qualified_introduction`.
