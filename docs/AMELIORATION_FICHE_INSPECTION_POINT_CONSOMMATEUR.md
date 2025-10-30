# Amélioration Module Fiche d'Inspection - Points Consommateurs

## Date de modification
2025-10-25

## Objectif
Adapter le formulaire de fiche d'inspection pour qu'il soit spécifique au type d'infrastructure inspectée, en créant un formulaire dédié pour les **Points Consommateurs** tout en conservant le formulaire existant pour les **Stations-Services**.

## Modifications apportées

### 1. Base de données

**Fichier**: `database/migrations/2025_10_25_add_point_consommateur_fields.sql`

Ajout de 12 nouveaux champs à la table `fiches_inspection` :

#### Informations techniques spécifiques aux points consommateurs :
- `besoins_mensuels_litres` (DECIMAL) - Besoins moyens mensuels en produits pétroliers
- `parc_engin` (TEXT) - Description du parc d'engin de la société
- `systeme_recuperation_huiles` (TEXT) - Système de récupération des huiles usées
- `nombre_personnels` (INT) - Nombre de personnels employés
- `superficie_site` (DECIMAL) - Superficie du site en m²
- `batiments_site` (TEXT) - Description des bâtiments du site

#### Infrastructures d'approvisionnement :
- `infra_eau` (TINYINT) - Présence infrastructure Eau
- `infra_electricite` (TINYINT) - Présence infrastructure Électricité

#### Réseaux de télécommunication :
- `reseau_camtel` (TINYINT) - Présence réseau CAMTEL
- `reseau_mtn` (TINYINT) - Présence réseau MTN
- `reseau_orange` (TINYINT) - Présence réseau ORANGE
- `reseau_nexttel` (TINYINT) - Présence réseau NEXTTEL

### 2. Formulaire Frontend

**Fichier**: `modules/fiche_inspection/edit.php`

#### Détection automatique du type d'infrastructure
```php
$est_point_consommateur = ($dossier['type_infrastructure'] === 'point_consommateur');
$est_station_service = ($dossier['type_infrastructure'] === 'station_service');
```

#### Section 3 - INFORMATIONS TECHNIQUES

**Pour les Points Consommateurs** (nouvelle version) :
- Besoins moyens mensuels en produits pétroliers (litres)
- Nombre de personnels employés
- Superficie du site (m²)
- Système de récupération des huiles usées
- Parc d'engin de la société (textarea)
- Bâtiments du site (textarea)
- **Infrastructures d'approvisionnement** (checkboxes) :
  - Eau
  - Électricité
- **Réseaux de télécommunication** (checkboxes) :
  - CAMTEL
  - MTN
  - ORANGE
  - NEXTTEL

**Pour les Stations-Services** (version conservée) :
- Date de mise en service
- N° Autorisation MINEE
- N° Autorisation MINMIDT
- Type de gestion
- Documents techniques disponibles
- Personnel (Chef de piste, Gérant)

#### Section 5 - DISTANCES (masquée pour Points Consommateurs)
La section "Distances par rapport aux édifices et stations" n'est affichée que pour les stations-services :
```php
<?php if (!$est_point_consommateur): ?>
  <!-- Section 5 affichée uniquement pour stations-services -->
<?php endif; ?>
```

#### Sections conservées identiques pour tous les types :
1. ✅ INFORMATIONS D'ORDRE GÉNÉRAL
2. ✅ INFORMATIONS DE GÉO-RÉFÉRENCEMENT
4. ✅ INSTALLATIONS (Cuves et Pompes)
6. ✅ SÉCURITÉ ET ENVIRONNEMENT
7. ✅ OBSERVATIONS GÉNÉRALES
8. ✅ ÉTABLISSEMENT DE LA FICHE

### 3. Backend

**Fichier**: `modules/fiche_inspection/functions.php`

#### Fonction `mettreAJourFicheInspection()`
Mise à jour pour inclure les 12 nouveaux champs dans la requête SQL UPDATE.

#### Traitement des données POST
Ajout de la gestion des nouveaux champs dans `edit.php` :
```php
'besoins_mensuels_litres' => $_POST['besoins_mensuels_litres'] ?? null,
'parc_engin' => $_POST['parc_engin'] ?? '',
'systeme_recuperation_huiles' => $_POST['systeme_recuperation_huiles'] ?? '',
// ... etc
```

## Installation

### 1. Appliquer la migration SQL
```bash
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_point_consommateur_fields.sql
```

OU via phpMyAdmin :
1. Ouvrir phpMyAdmin
2. Sélectionner la base `sgdi_mvp`
3. Onglet "SQL"
4. Copier/coller le contenu du fichier de migration
5. Exécuter

### 2. Vérification
Les fichiers modifiés sont déjà en place :
- ✅ `modules/fiche_inspection/edit.php` - Formulaire adaptatif
- ✅ `modules/fiche_inspection/functions.php` - Backend mis à jour
- ✅ `database/migrations/2025_10_25_add_point_consommateur_fields.sql` - Script de migration

## Utilisation

### Pour un dossier de type "Point Consommateur"
1. Aller sur le dossier
2. Cliquer sur "Créer une fiche d'inspection"
3. Le formulaire affichera automatiquement :
   - Section 3 spécifique aux points consommateurs
   - Pas de section 5 (distances)
   - Toutes les autres sections standard

### Pour un dossier de type "Station-Service"
1. Aller sur le dossier
2. Cliquer sur "Créer une fiche d'inspection"
3. Le formulaire affichera :
   - Section 3 traditionnelle (date, autorisations, personnel)
   - Section 5 complète (distances aux édifices et stations)
   - Toutes les autres sections standard

## Compatibilité

### Rétrocompatibilité
- ✅ Les fiches existantes continuent de fonctionner normalement
- ✅ Les nouveaux champs sont NULL par défaut (pas de perte de données)
- ✅ Les stations-services utilisent l'ancien formulaire sans changement

### Types d'infrastructure supportés
- ✅ `station_service` → Formulaire stations-services (inchangé)
- ✅ `point_consommateur` → Nouveau formulaire points consommateurs
- ✅ Autres types → Utilisent le formulaire stations-services par défaut

## Tests recommandés

1. **Créer une fiche pour un point consommateur**
   - Vérifier que la section 3 affiche les nouveaux champs
   - Vérifier que la section 5 est masquée
   - Remplir et enregistrer les données

2. **Créer une fiche pour une station-service**
   - Vérifier que le formulaire traditionnel s'affiche
   - Vérifier que la section 5 est présente
   - S'assurer du bon fonctionnement

3. **Modifier une fiche existante**
   - Vérifier que les données sont bien récupérées
   - Modifier et enregistrer
   - Vérifier la persistance des données

## Notes importantes

- Les champs spécifiques aux points consommateurs peuvent être NULL (optionnels)
- La validation de complétude peut être adaptée selon les besoins
- Les fichiers d'impression (print_*.php) peuvent nécessiter une mise à jour ultérieure pour afficher les nouveaux champs

## Auteur
Claude Code - 2025-10-25
