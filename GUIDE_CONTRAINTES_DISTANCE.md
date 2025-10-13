# Guide d'utilisation - Système de Contraintes de Distance

## Vue d'ensemble

Le système de contraintes de distance permet de valider automatiquement la conformité des stations-service par rapport aux normes de distance de sécurité établies par la réglementation camerounaise.

## Normes réglementaires implémentées

### 1. Distance entre stations-service
- **Distance minimale requise** : 500 mètres (zone urbaine)
- **Distance minimale requise** : 400 mètres (zone rurale, réduction de 20%)
- Toutes les stations-service doivent être géolocalisées

### 2. Distance avec les établissements stratégiques

#### Catégorie 1 - Distance de 1000m (800m en zone rurale)
- Présidence de la République
- Services du Premier Ministre
- Assemblée Nationale
- Sénat

#### Catégorie 2 - Distance de 500m (400m en zone rurale)
- Services du Gouverneur
- Préfectures
- Sous-préfectures
- Mairies

#### Catégorie 3 - Distance de 100m (80m en zone rurale)
- Établissements d'enseignement (écoles, collèges, lycées, universités)
- Infrastructures sanitaires (hôpitaux, centres de santé, dispensaires)
- Lieux de culte (églises, mosquées, temples)
- Terrains de sport (stades, complexes sportifs)
- Places de marché
- Bâtiments administratifs

## Fonctionnalités principales

### 1. Gestion des Points d'Intérêt Stratégiques (POI)

**Accès** : Administrateur système uniquement

**Chemin** : Carte des infrastructures > Bouton "Gérer les POI" (visible uniquement pour l'admin)

**Fonctionnalités** :
- Ajouter un nouveau POI avec sa localisation GPS
- Modifier un POI existant
- Désactiver un POI (ne sera plus pris en compte dans les validations)
- Catégoriser les POI selon leur type
- Visualiser tous les POI sur une carte

**Pour ajouter un POI** :
1. Aller dans "Gérer les POI"
2. Cliquer sur "Ajouter un POI"
3. Sélectionner la catégorie (détermine automatiquement la distance minimale)
4. Cliquer sur la carte pour placer le POI
5. Remplir les informations (nom, description, ville, région, zone)
6. Enregistrer

### 2. Validation Géospatiale d'un Dossier

**Accès** : Chef de Service, Admin, Cadre DPPG, Sous-directeur, Directeur

**Chemin** : Dossier > Menu Actions > "Validation géospatiale"

**Prérequis** :
- Le dossier doit avoir des coordonnées GPS
- Le dossier doit être une station-service

**Processus de validation** :
1. Accéder à la page de validation géospatiale du dossier
2. Sélectionner le type de zone (urbaine ou rurale)
3. Cliquer sur "Valider maintenant"
4. Le système vérifie automatiquement :
   - Les distances avec les autres stations-service
   - Les distances avec tous les POI actifs
5. Affichage du résultat :
   - **CONFORME** : Aucune violation détectée
   - **NON CONFORME** : Violations détectées avec détails

**Interprétation des résultats** :
- Chaque violation est classée par sévérité :
  - **Critique** : Écart > 50% de la distance requise
  - **Majeure** : Écart entre 25% et 50%
  - **Mineure** : Écart < 25%

### 3. Visualisation sur la Carte

**Accès** : Tous les utilisateurs authentifiés

**Chemin** : Menu principal > "Carte des infrastructures"

**Fonctionnalités** :
- Visualiser toutes les infrastructures géolocalisées
- Afficher/masquer les Points d'Intérêt Stratégiques (bouton "Afficher les POI")
- Afficher/masquer les zones de contrainte (bouton "Afficher les zones de contrainte")
- Filtrer par type d'infrastructure, statut et région
- Cliquer sur un marqueur pour voir les détails

**Zones affichées** :
- Cercles rouges en pointillés : Zones de 500m autour des stations-service
- Cercles de couleur : Zones de contrainte autour des POI (couleur selon la catégorie)

### 4. Indicateurs de Conformité

**Dans la fiche d'un dossier** :
- Badge "CONFORME" ou "NON CONFORME" dans les informations générales
- Lien direct vers les détails de la validation
- Badge dans le menu Actions

## Workflow d'utilisation recommandé

### Étape 1 : Configuration initiale (Admin uniquement)
1. Ajouter tous les POI stratégiques de votre zone
2. Vérifier la catégorisation des POI
3. Tester la validation sur un dossier pilote

**Important** : Seul l'administrateur système peut ajouter, modifier ou supprimer des POI pour garantir l'intégrité des données réglementaires.

### Étape 2 : Lors de la création d'un dossier (Chef Service)
1. Créer le dossier avec les informations habituelles
2. Ajouter les coordonnées GPS via "Localisation GPS"
3. Spécifier le type de zone (urbaine/rurale)

### Étape 3 : Validation géospatiale (Chef Service/Cadre DPPG)
1. Accéder à la validation géospatiale du dossier
2. Lancer la validation
3. Si NON CONFORME :
   - Analyser les violations
   - Décider si une dérogation est possible
   - Documenter la décision

### Étape 4 : Suivi (Tous les rôles)
1. Consulter la carte pour visualiser les implantations
2. Activer l'affichage des zones de contrainte
3. Identifier les zones saturées ou problématiques

## Base de données

### Tables créées
- `categories_poi` : Catégories de POI avec distances minimales
- `points_interet` : Points d'intérêt stratégiques
- `validations_geospatiales` : Historique des validations
- `violations_contraintes` : Détails des violations détectées
- `audit_poi` : Journal des modifications des POI

### Colonnes ajoutées aux dossiers
- `zone_type` : Type de zone (urbaine/rurale)
- `validation_geospatiale_faite` : Si validation effectuée
- `conformite_geospatiale` : Résultat (conforme/non_conforme/en_attente)

## Migration de la base de données

Pour activer le système, exécuter le script SQL :
```bash
mysql -u root -p sgdi_mvp < database/add_contraintes_distance.sql
```

Ce script :
1. Crée toutes les tables nécessaires
2. Insère les catégories de POI prédéfinies
3. Ajoute les colonnes aux tables existantes
4. Crée les vues et index pour les performances

## API et fonctions disponibles

### Fonctions PHP principales
- `validerConformiteGeospatiale($lat, $lng, $dossier_id, $zone_type)` : Valide un point
- `verifierDistanceStations($lat, $lng, $exclus, $zone_type)` : Vérifie distances stations
- `verifierDistancePOIs($lat, $lng, $zone_type)` : Vérifie distances POI
- `enregistrerValidationGeospatiale(...)` : Enregistre une validation
- `creerPOI($data, $user_id)` : Crée un nouveau POI
- `getAllPOIsForMap($filters)` : Récupère tous les POI pour la carte

### Fichiers créés
- `database/add_contraintes_distance.sql` : Script de migration
- `includes/contraintes_distance_functions.php` : Fonctions métier
- `modules/dossiers/validation_geospatiale.php` : Interface de validation
- `modules/poi/index.php` : Liste des POI
- `modules/poi/create.php` : Création de POI
- `modules/poi/delete.php` : Désactivation de POI

## Maintenance

### Mise à jour des POI
- Les POI peuvent être modifiés ou désactivés sans supprimer les validations passées
- Toutes les modifications sont tracées dans la table `audit_poi`
- Désactiver un POI le retire des futures validations mais conserve l'historique

### Mise à jour des distances réglementaires
Si les normes changent, modifier la table `categories_poi` :
```sql
UPDATE categories_poi
SET distance_min_metres = 600, distance_min_rural_metres = 480
WHERE code = 'etablissement_enseignement';
```

### Performance
- Index créés sur les coordonnées GPS et les colonnes de recherche
- Clustering des marqueurs sur la carte pour gérer un grand nombre de points
- Vues matérialisées pour les statistiques

## Sécurité

- **Gestion des POI** : Seul l'administrateur système peut ajouter, modifier ou supprimer des POI
- **Validation géospatiale** : Chef Service, Admin, Cadres DPPG, Sous-directeur, Directeur
- **Visualisation carte** : Tous les utilisateurs authentifiés
- **Audit complet** : Les validations sont auditées avec l'identité de l'utilisateur
- **Traçabilité** : Les modifications de POI sont tracées dans la table d'audit
- **Sécurité des formulaires** : Token CSRF sur tous les formulaires
- **Validation des données** : Validation côté serveur des coordonnées GPS

## Support et dépannage

### Problème : Validation ne fonctionne pas
- Vérifier que les coordonnées GPS sont au format correct (décimal)
- Vérifier que le dossier est bien une station-service
- S'assurer que les POI sont actifs (`actif = 1`)

### Problème : POI ne s'affichent pas sur la carte
- Vérifier que les POI ont des coordonnées valides
- S'assurer que la colonne `actif` est à 1
- Vider le cache du navigateur

### Problème : Zones de contrainte incorrectes
- Vérifier le type de zone (urbaine/rurale)
- Contrôler les distances dans la table `categories_poi`
- Recalculer avec le bon type de zone

## Évolutions futures possibles

1. **Export des rapports de conformité**
   - Générer un PDF avec la carte et les violations
   - Export Excel des statistiques de conformité

2. **Notifications automatiques**
   - Alerter quand un nouveau dossier est proche d'un POI critique
   - Notification lors de l'ajout d'un nouveau POI affectant des dossiers existants

3. **Analyse de zones**
   - Identifier les zones saturées
   - Recommandations d'implantation optimales
   - Heatmap des infrastructures

4. **Import de POI en masse**
   - Import CSV de POI
   - Géocodage automatique d'adresses

5. **Intégration avec d'autres systèmes**
   - API REST pour les validations
   - Webhook lors de violations critiques

## Contact

Pour toute question ou suggestion d'amélioration, contacter l'équipe de développement ou le Chef de Service SDTD.

---
*Document généré le 2025-10-13*
