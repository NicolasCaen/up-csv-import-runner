# UP CSV Import Runner

## Description
Lire les fichiers XML générés par UP CSV Importer et exécuter les imports CSV depuis l’administration WordPress.

## Installation
1. Déposer le dossier dans `wp-content/plugins/`.
2. Activer le plugin dans l’administration WordPress.

## Changelog
- 2025-11-11 · v0.1.2.0 · Affichage d’un résumé post-import (lignes importées + erreurs) sur la page d’exécution. Bump version.
- 2025-11-11 · v0.1.1.0 · Scan prioritaire de `plugins/up-csv-import-runner/config/`, puis dossier personnalisé RELATIF à `wp-content/`, puis `plugins/up-csv-importer/config-settings/`. Réglage de dossier ajouté dans la page principale.
- 2025-11-11 · v0.1.0 · Création du plugin et structure initiale (includes, menus dynamiques).
