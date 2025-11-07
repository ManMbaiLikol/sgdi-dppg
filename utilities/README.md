# Utilities / Scripts Utilitaires

Ce dossier contient les scripts utilitaires, de migration, d'analyse et de debug utilisés pour la maintenance et l'import de données dans le système SGDI.

## 📋 Organisation des Scripts

### 🔍 Scripts d'Analyse (`analyze_*.php`)

Scripts d'analyse de données pour l'import MINEE et la validation GPS:

- `analyze_500m_violations.php` - Détection des stations trop proches (< 500m)
- `analyze_gps_duplicates.php` - Identification des doublons GPS
- `analyze_minee_data.php` - Analyse des données MINEE avant import
- `analyze_no_match.php` - Analyse des stations sans correspondance
- `analyze_xlsm.php` - Analyse des fichiers Excel MINEE

### 🧹 Scripts de Nettoyage (`clean_*.php`)

Scripts de nettoyage et préparation des données:

- `clean_and_merge_data.php` - Nettoyage et fusion des données
- `clean_historical_data.php` - Nettoyage des données historiques
- `clean_minee_data.php` - Nettoyage spécifique données MINEE

### ✅ Scripts de Vérification (`verify_*.php`)

Scripts de validation et vérification:

- `verify_circle_scale.php` - Vérification échelle cercles sur carte
- `verify_cleanup_need.php` - Vérification besoin de nettoyage
- `verify_close_pairs.php` - Vérification paires de stations proches
- `verify_geographic_coherence.php` - Vérification cohérence géographique

### 🔎 Scripts de Détection (`detect_*.php`)

- `detect_gps_collisions.php` - Détection des collisions GPS

### 📊 Scripts de Comparaison

- `compare_strategies.php` - Comparaison des stratégies d'import

### 🔄 Scripts d'Import (`import_*.php`)

Scripts d'import de données depuis la base MINEE:

- `import_fusion_auto.php` - Import automatique avec fusion
- Autres scripts d'import spécialisés

### ⚙️ Scripts d'Exécution (`execute_*.php`)

Scripts d'exécution de tâches spécifiques:

- `execute_merge.php` - Exécution de fusion de données
- `execute_strategy_2.php` - Exécution stratégie 2

### 🚂 Scripts Railway (`railway_*.php`)

Scripts spécifiques au déploiement Railway.app (voir git history pour liste complète)

### 🔧 Scripts de Fusion

- `batch_merge_duplicates.php` - Fusion par lot des doublons

### 📦 Scripts de Migration

- `run_migration.php` - Exécution des migrations

### 👁️ Scripts de Visualisation

- `view_import_samples.php` - Visualisation d'échantillons d'import

## 🎯 Utilisation

### Précautions

⚠️ **ATTENTION**: Ces scripts sont destinés à un usage administratif et de maintenance uniquement.

- Ne PAS exécuter en production sans sauvegarde
- Vérifier la configuration de base de données avant exécution
- Consulter les logs après chaque exécution
- Certains scripts modifient directement les données

### Environnement

Ces scripts doivent être exécutés depuis la ligne de commande PHP:

```bash
php utilities/nom_du_script.php
```

Ou via navigateur (selon le script):

```
http://localhost/dppg-implantation/utilities/nom_du_script.php
```

### Configuration

La plupart des scripts utilisent la configuration de base de données située dans:
- `config/database.php`

Assurez-vous que la connexion à la base de données est correctement configurée.

## 📝 Import MINEE

### Contexte

Ces scripts ont été développés pour l'import initial des données historiques depuis la base de données MINEE (Ministère des Mines, de l'Eau et de l'Énergie).

### Processus d'Import Typique

1. **Analyse** (`analyze_minee_data.php`)
2. **Nettoyage** (`clean_minee_data.php`)
3. **Vérification** (`verify_geographic_coherence.php`)
4. **Import** (`import_fusion_auto.php`)
5. **Validation** (`view_import_samples.php`)

### Données OSM

Certains scripts intègrent des données OpenStreetMap pour enrichir la géolocalisation:
- Extraction OSM dans `modules/osm_extraction/`
- Matching MINEE-OSM pour améliorer les coordonnées GPS

## 🗺️ Gestion GPS

### Contraintes de Distance

Le système applique des contraintes de distance entre stations (500m minimum selon réglementation).

Scripts concernés:
- `analyze_500m_violations.php`
- `detect_gps_collisions.php`
- `verify_close_pairs.php`

## 📊 Rapports Générés

Certains scripts génèrent des rapports HTML dans le répertoire racine:
- `rapport_*.html`
- `matching_result_*.html`
- `import_result_*.html`

**Note**: Ces fichiers de rapport ne sont pas versionnés (exclus par .gitignore).

## 🔐 Sécurité

- Ces scripts ne doivent PAS être accessibles en production
- Limiter l'accès au répertoire `utilities/` via configuration Apache/Nginx
- Supprimer ou déplacer ce dossier lors du déploiement en production

### Protection Apache

Ajouter dans `.htaccess` du dossier `utilities/`:

```apache
Order Deny,Allow
Deny from all
Allow from 127.0.0.1
```

## 📚 Documentation Complémentaire

Pour plus d'informations sur:
- **Import MINEE**: Voir documentation dans `docs/`
- **Migrations**: Voir `database/migrations/`
- **OSM**: Voir `modules/osm_extraction/README.md` (si disponible)

## 🛠️ Maintenance

Ces scripts peuvent être conservés pour:
- Maintenance future
- Imports additionnels
- Debugging
- Tests de performance

Cependant, ils ne sont **pas nécessaires** au fonctionnement quotidien de l'application SGDI.

---

**Date de création**: Novembre 2025
**Dernière mise à jour**: 2025-11-07
