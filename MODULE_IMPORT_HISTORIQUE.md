# Module d'Import de Dossiers Historiques - Guide Complet

## 📋 Vue d'ensemble

Le **Module d'Import de Dossiers Historiques** a été créé pour intégrer dans le SGDI les **1500+ dossiers** (995 stations-service + 500 points consommateurs) autorisés **avant la mise en place du système**.

### ✨ Fonctionnalités principales

✅ Import par lots (jusqu'à 200 dossiers par fichier)
✅ Validation automatique des données
✅ Prévisualisation avant import
✅ Génération automatique des numéros de dossier
✅ Statut spécial "Dossier Historique Autorisé"
✅ Publication automatique au registre public
✅ Tableau de bord et statistiques
✅ Templates CSV prêts à l'emploi

---

## 🚀 Démarrage rapide

### Étape 1 : Installation

#### A. Exécuter la migration SQL

```bash
# Via ligne de commande
mysql -u root -p sgdi < database/migrations/add_import_historique.sql

# Ou via phpMyAdmin : importer le fichier
```

#### B. Créer le répertoire temporaire

```bash
mkdir -p uploads/temp
chmod 755 uploads/temp
```

### Étape 2 : Accès au module

**URL** : `https://votre-domaine.com/modules/import_historique/`

**Permissions** : Accessible uniquement à :
- Admin Système
- Chef de Service SDTD

### Étape 3 : Premier import (Test pilote)

1. **Télécharger le template** approprié :
   - Stations-service : `template_import_stations_service.csv`
   - Points consommateurs : `template_import_points_consommateurs.csv`

2. **Remplir 10-50 dossiers** pour tester

3. **Importer via l'interface web**

4. **Vérifier les résultats** dans le registre public

---

## 📁 Format des fichiers

### Template Stations-Service

```csv
numero_dossier;type_infrastructure;nom_demandeur;region;ville;latitude;longitude;date_autorisation;numero_decision;observations
```

**Colonnes obligatoires** :
- `type_infrastructure` : "Implantation station-service" ou "Reprise station-service"
- `nom_demandeur` : Nom de la société (ex: TOTAL CAMEROUN)
- `region` : Une des 10 régions (ex: Littoral)
- `ville` : Localité (ex: Douala)
- `date_autorisation` : Format JJ/MM/AAAA (ex: 15/03/2015)
- `numero_decision` : Numéro de la décision ministérielle

**Colonnes optionnelles** :
- `numero_dossier` : Laissez vide pour génération automatique
- `latitude` / `longitude` : Coordonnées GPS
- `observations` : Remarques

### Template Points Consommateurs

Même structure + 2 colonnes supplémentaires **obligatoires** :
- `entreprise_beneficiaire` : Nom de l'entreprise (ex: CIMENCAM)
- `activite_entreprise` : Secteur (ex: Fabrication de ciment)

---

## 🔢 Génération automatique des numéros

Si vous laissez la colonne `numero_dossier` vide, le système génère automatiquement un numéro unique :

**Format** : `HIST-[TYPE]-[REGION]-[ANNEE]-[SEQUENCE]`

### Exemples

| Type | Région | Année | Résultat |
|------|--------|-------|----------|
| Station-Service | Littoral | 2015 | `HIST-SS-LT-2015-001` |
| Point Consommateur | Centre | 2018 | `HIST-PC-CE-2018-045` |
| Dépôt GPL | Ouest | 2019 | `HIST-GPL-OU-2019-003` |

### Codes utilisés

**Types** :
- `SS` = Station-Service
- `PC` = Point Consommateur
- `GPL` = Dépôt GPL
- `CE` = Centre Emplisseur

**Régions** :
- `AD` = Adamaoua, `CE` = Centre, `ES` = Est, `EN` = Extrême-Nord
- `LT` = Littoral, `NO` = Nord, `NW` = Nord-Ouest, `OU` = Ouest
- `SU` = Sud, `SW` = Sud-Ouest

---

## ✅ Validation automatique

Le système valide automatiquement :

### 1. Champs obligatoires
- Type d'infrastructure
- Nom du demandeur
- Région
- Ville
- Date d'autorisation
- Numéro de décision
- Entreprise bénéficiaire (pour points consommateurs uniquement)

### 2. Types d'infrastructure valides
```
Implantation station-service
Reprise station-service
Implantation point consommateur
Reprise point consommateur
Implantation dépôt GPL
Implantation centre emplisseur
```

### 3. Régions valides
```
Adamaoua, Centre, Est, Extrême-Nord, Littoral
Nord, Nord-Ouest, Ouest, Sud, Sud-Ouest
```

### 4. Format des dates
- **Accepté** : `JJ/MM/AAAA` (15/03/2015)
- **Accepté** : `AAAA-MM-JJ` (2015-03-15)
- **Refusé** : `15-03-2015`, `03/15/2015`

### 5. Coordonnées GPS (si fournies)
- **Latitude** : entre -90 et 90
- **Longitude** : entre -180 et 180

---

## 🔄 Workflow d'import

```
[1] Télécharger template
        ↓
[2] Remplir données
        ↓
[3] Upload fichier CSV
        ↓
[4] Validation automatique
        ↓
[5] Prévisualisation
        ↓
[6] Confirmation
        ↓
[7] Import progressif
        ↓
[8] Résultats et rapport
```

### Détails de chaque étape

#### 1. Télécharger template
- Accéder au module : `/modules/import_historique/`
- Cliquer sur le template approprié
- Ouvrir avec Excel ou LibreOffice

#### 2. Remplir données
- **NE PAS** modifier la ligne d'en-tête
- Remplir à partir de la ligne 6 (lignes 2-5 = exemples)
- Maximum 200 lignes par fichier
- Enregistrer au format CSV (séparateur point-virgule)

#### 3. Upload
- Cliquer sur "Choisir un fichier"
- Sélectionner votre CSV
- Ajouter une description (ex: "Import stations Littoral")
- Cocher "Je confirme que mon fichier respecte le format"
- Cliquer sur "Valider et Prévisualiser"

#### 4. Validation
Le système vérifie automatiquement chaque ligne :
- ✅ Si **aucune erreur** → passage à la prévisualisation
- ❌ Si **erreurs** → rapport d'erreurs téléchargeable

#### 5. Prévisualisation
- Aperçu des 50 premières lignes
- Statistiques (types, régions, coordonnées GPS)
- Vérification visuelle des données

#### 6. Confirmation
- Cocher "Je confirme l'import de ces X dossiers"
- Cliquer sur "Confirmer l'import"

#### 7. Import progressif
- Traitement par lots de 10 dossiers simultanément
- Barre de progression en temps réel
- Log détaillé des opérations

#### 8. Résultats
- Nombre de dossiers importés avec succès
- Nombre d'erreurs
- Liens vers registre public et tableau de bord

---

## 📊 Tableau de bord

Le tableau de bord affiche :

### Statistiques globales
- Nombre total de dossiers importés
- Nombre d'utilisateurs ayant effectué des imports
- Date du premier et dernier import

### Répartition par type
- Graphique en camembert
- Liste détaillée avec pourcentages

### Répartition par région
- Graphique en barres
- Liste détaillée avec pourcentages

### Historique des imports
- Date et heure de chaque import
- Nom de l'importeur
- Nombre de dossiers importés

**URL** : `/modules/import_historique/dashboard.php`

---

## 🌍 Intégration au registre public

Les dossiers historiques importés :

✅ **Apparaissent automatiquement** dans le registre public
✅ **Badge "Historique"** pour les identifier visuellement
✅ **Inclus dans les recherches** (par type, région, société)
✅ **Inclus dans les statistiques** globales
✅ **Affichent la date réelle** d'autorisation
✅ **Coordonnées GPS** affichées sur la carte (si fournies)

### Statut spécial

Les dossiers reçoivent le statut : **"Dossier Historique Autorisé"**

Ce statut signifie :
- ❌ Pas de workflow normal (commission, paiement, visa, inspection)
- ✅ Directement publié au registre
- ✅ Date d'autorisation = date réelle historique
- ✅ Traçabilité : qui a importé, quand, depuis quel fichier

---

## 💡 Bonnes pratiques

### Stratégie d'import recommandée

#### Phase 1 : Test pilote (50 dossiers)
1. Sélectionner 50 dossiers variés (différents types et régions)
2. Créer un fichier CSV de test
3. Importer via le module
4. Vérifier dans le registre public
5. Contrôler le tableau de bord
6. ✅ Si tout est OK → passer à la phase 2

#### Phase 2 : Import par région (recommandé)
```
Semaine 1 : Littoral (250 dossiers)
Semaine 2 : Centre (300 dossiers)
Semaine 3 : Ouest (150 dossiers)
Semaine 4 : Autres régions (800 dossiers)
```

**Avantages** :
- Correction progressive des erreurs
- Validation par lot
- Meilleure traçabilité

#### Phase 3 : Consolidation
1. Vérifier les statistiques globales
2. Contrôler les doublons
3. Valider le registre public complet
4. Générer un rapport final

### Organisation des données

#### Qualité des données
- ✅ Uniformiser les noms de sociétés (ex: "TOTAL" → "TOTAL CAMEROUN")
- ✅ Vérifier l'orthographe des villes
- ✅ Valider les numéros de décision
- ✅ S'assurer de la cohérence des dates

#### Données manquantes
Si certaines informations manquent :
- **Coordonnées GPS** : Laisser vide (ajout ultérieur possible)
- **Date exacte inconnue** : Utiliser 01/01 de l'année
- **Observations** : Laisser vide si rien à noter

#### Fichiers de travail
- Conserver les fichiers CSV sources
- Nommer clairement : `import_stations_littoral_2025-01-20.csv`
- Archiver après import réussi
- Documenter les corrections effectuées

---

## 🔧 Dépannage

### Erreurs courantes

#### "Type d'infrastructure invalide"
**Cause** : Faute de frappe ou nom incorrect
**Solution** : Copier-coller depuis la liste des types valides

#### "Région invalide"
**Cause** : Orthographe incorrecte (ex: "Littorral" au lieu de "Littoral")
**Solution** : Copier-coller depuis la liste des régions valides

#### "Format de date invalide"
**Cause** : Format incorrect (ex: AAAA-MM-JJ ou 15-03-2015)
**Solution** : Utiliser JJ/MM/AAAA (15/03/2015)

#### "Entreprise bénéficiaire obligatoire"
**Cause** : Champ vide pour un point consommateur
**Solution** : Remplir le nom de l'entreprise

#### "Coordonnées GPS invalides"
**Cause** : Valeurs hors limites
**Solution** : Vérifier sur Google Maps et corriger

#### "Session expirée"
**Cause** : Plus de 30 minutes entre upload et confirmation
**Solution** : Réimporter le fichier

#### "Fichier trop volumineux"
**Cause** : Plus de 200 lignes
**Solution** : Diviser en plusieurs fichiers

### Support technique

En cas de problème :
1. Consulter la documentation (`INSTRUCTIONS_IMPORT.md`)
2. Télécharger le rapport d'erreurs
3. Vérifier les exemples dans les templates
4. Contacter l'administrateur système

---

## 📈 Statistiques attendues

### Volume total estimé
- **995** Stations-service
- **500** Points consommateurs
- **Total** : ~1500 dossiers

### Répartition estimée par région
- Littoral : ~25% (375 dossiers)
- Centre : ~20% (300 dossiers)
- Ouest : ~15% (225 dossiers)
- Autres : ~40% (600 dossiers)

### Durée estimée
- Test pilote (50 dossiers) : 1 heure
- Import par lots de 200 : 30 minutes/lot
- Total (1500 dossiers) : 2-4 semaines en mode progressif

---

## 🔒 Sécurité et traçabilité

### Traçabilité complète
Chaque import est tracé :
- 👤 **Qui** : Utilisateur ayant effectué l'import
- 📅 **Quand** : Date et heure exactes
- 📁 **Quoi** : Nombre de dossiers, source/description
- ✅ **Résultat** : Succès et erreurs

### Logs
Tout est enregistré dans :
- `logs_import_historique` : Historique des imports
- `historique_dossier` : Traçabilité par dossier
- Logs système : Actions utilisateur

### Sauvegardes recommandées
Avant import massif :
```bash
# Sauvegarder la base de données
mysqldump -u root -p sgdi > sgdi_backup_avant_import.sql
```

---

## 📚 Fichiers et documentation

### Structure créée

```
modules/import_historique/
├── index.php                      # Interface principale
├── preview.php                    # Prévisualisation
├── process.php                    # Traitement import
├── ajax_import_single.php         # Import AJAX
├── result.php                     # Résultats
├── dashboard.php                  # Tableau de bord
├── download_template.php          # Téléchargement templates
├── export_errors.php              # Export erreurs
├── functions.php                  # Fonctions métier
├── README.md                      # Documentation technique
└── templates/
    ├── template_import_stations_service.csv
    ├── template_import_points_consommateurs.csv
    └── INSTRUCTIONS_IMPORT.md

database/migrations/
└── add_import_historique.sql      # Migration SQL
```

### Documentation disponible

1. **INSTRUCTIONS_IMPORT.md** : Guide utilisateur détaillé
2. **README.md** : Documentation technique
3. **MODULE_IMPORT_HISTORIQUE.md** : Ce document (guide complet)

---

## ✅ Checklist de mise en production

Avant de commencer l'import massif :

### Préparation
- [ ] Migration SQL exécutée
- [ ] Répertoire `uploads/temp` créé avec permissions
- [ ] Sauvegarde de la base de données effectuée
- [ ] Templates téléchargés et testés

### Test pilote
- [ ] 50 dossiers de test importés avec succès
- [ ] Dossiers visibles dans le registre public
- [ ] Badge "Historique" affiché correctement
- [ ] Statistiques cohérentes dans le tableau de bord
- [ ] Aucune erreur détectée

### Organisation
- [ ] Stratégie d'import définie (par région/type)
- [ ] Fichiers CSV préparés et validés
- [ ] Responsable de l'import désigné
- [ ] Planning établi

### Après import
- [ ] Vérification des totaux (1500 dossiers)
- [ ] Contrôle du registre public
- [ ] Validation des statistiques
- [ ] Archivage des fichiers sources

---

## 🎯 Objectif final

À l'issue de l'import complet, le SGDI doit contenir :

✅ **~1500 dossiers historiques** importés
✅ **Tous visibles** dans le registre public
✅ **Statistiques complètes** par type et région
✅ **Traçabilité totale** des imports
✅ **Registre public** représentatif de la réalité terrain

Le système sera alors **opérationnel et complet**, intégrant à la fois :
- Les dossiers historiques (avant SGDI)
- Les nouveaux dossiers (via workflow complet)

---

**SGDI - Système de Gestion des Dossiers d'Implantation**
**MINEE/DPPG - Ministère de l'Eau et de l'Energie**
**Version 1.0 - Janvier 2025**
