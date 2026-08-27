# Approbation d’une publication programmée

Une page programmée reste inaccessible tant que sa revue éditoriale n’est pas enregistrée. Après validation de la checklist manuelle, exécuter dans le conteneur applicatif :

```bash
php scripts/approve-scheduled-content.php --slug=alternatives-microsoft-teams --reviewer="Prénom Nom"
```

La commande refuse les contenus qui ne sont pas au statut `scheduled` et consigne `editorial_ready`, le nom du relecteur et l’heure UTC d’approbation. Le publisher ne publie ensuite le contenu qu’à l’échéance prévue et purge les caches frontend après la transaction.
