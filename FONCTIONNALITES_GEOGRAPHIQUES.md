# Fonctionnalités Géographiques - SGDI

## 🗺️ Vue d'ensemble

Le système SGDI intègre désormais des fonctionnalités géographiques complètes pour la gestion et la visualisation des infrastructures pétrolières au Cameroun.

## ✨ Fonctionnalités implémentées

### 1. **Carte interactive des infrastructures** (`modules/carte/index.php`)
- Visualisation géographique de toutes les infrastructures
- Clustering automatique des marqueurs pour meilleure lisibilité
- Filtres par type, statut et région
- Statistiques en temps réel
- Accès réservé aux utilisateurs authentifiés

**Accès:** Menu Dossiers → Carte des infrastructures

### 2. **Gestion de la localisation GPS** (`modules/dossiers/localisation.php`)
- Interface intuitive avec carte cliquable
- Placement du marqueur par clic ou drag & drop
- Saisie manuelle des coordonnées possible
- Validation automatique des coordonnées
- Détection automatique des infrastructures à proximité (rayon 5 km)
- Calcul des distances entre infrastructures
- Support de multiples formats de coordonnées

**Formats GPS supportés:**
- Décimal: `3.8667, 11.5167`
- DMS: `3°52'0"N 11°31'0"E`
- Mixte: `N 3.8667 E 11.5167`

### 3. **Carte publique** (`public_map.php`)
- Accessible sans authentification
- Affiche uniquement les infrastructures **autorisées**
- Filtres par type et région
- Statistiques publiques
- Design professionnel et responsive
- Conformité avec la transparence publique

**URL publique:** `http://localhost/dppg-implantation/public_map.php`

### 4. **Fonctions géographiques** (`includes/map_functions.php`)

#### Validation des coordonnées
```php
validateGPSCoordinates($latitude, $longitude);
// Vérifie que les coordonnées sont valides et au Cameroun
```

#### Parsing de coordonnées
```php
parseGPSCoordinates($input);
// Convertit différents formats en latitude/longitude
```

#### Calcul de distance
```php
calculateDistance($lat1, $lon1, $lat2, $lon2);
// Formule de Haversine, résultat en kilomètres
```

#### Recherche de proximité
```php
findNearbyInfrastructures($latitude, $longitude, $radius_km = 5);
// Trouve toutes les infrastructures dans un rayon donné
```

#### Formatage des coordonnées
```php
formatGPSCoordinates($lat, $lng, 'decimal'); // 3.866700, 11.516700
formatGPSCoordinates($lat, $lng, 'dms');     // 3°52'0.00"N 11°31'0.00"E
```

## 🎨 Technologies utilisées

- **Leaflet.js** - Bibliothèque de cartographie interactive
- **OpenStreetMap** - Fonds de carte gratuits
- **Leaflet.markercluster** - Regroupement intelligent des marqueurs
- **Font Awesome** - Icônes pour les types d'infrastructures
- **Bootstrap 5** - Interface responsive

## 📊 Base de données

### Modifications requises

Exécutez le script SQL suivant:
```bash
database/add_gps_features.sql
```

Ce script ajoute:
- Index sur `coordonnees_gps` pour performances
- Champ `adresse_precise` pour descriptions détaillées
- Table `zones_restreintes` (future utilisation)
- Table `historique_localisation` (audit trail)
- Vues optimisées pour les requêtes géographiques

### Vues SQL créées

#### `infrastructures_geolocalisees`
Toutes les infrastructures avec coordonnées valides

#### `infrastructures_publiques`
Uniquement les infrastructures autorisées (pour la carte publique)

## 🎯 Cas d'usage

### 1. Ajouter une localisation à un dossier
1. Ouvrir le dossier
2. Cliquer sur "Localisation GPS"
3. Cliquer sur la carte ou saisir les coordonnées
4. Le système affiche automatiquement les infrastructures à proximité
5. Enregistrer

### 2. Visualiser toutes les infrastructures
1. Menu Dossiers → Carte des infrastructures
2. Utiliser les filtres pour affiner
3. Cliquer sur un marqueur pour voir les détails
4. Option "Google Maps" pour navigation

### 3. Consultation publique
1. Accéder à `public_map.php` (pas d'authentification requise)
2. Filtrer par type ou région
3. Cliquer sur une infrastructure pour voir les informations publiques

## 🔒 Sécurité

- **Validation stricte** des coordonnées GPS
- **Vérification** que les coordonnées sont au Cameroun (1.5°-13.5°N, 7.5°-16.5°E)
- **Carte publique** limitée aux infrastructures autorisées
- **Pas d'informations sensibles** sur la carte publique
- **Protection CSRF** sur tous les formulaires

## 🌍 Zones géographiques du Cameroun

Le système inclut les centres approximatifs des principales villes:
- Yaoundé: 3.8667°N, 11.5167°E
- Douala: 4.0511°N, 9.7679°E
- Garoua: 9.3014°N, 13.3964°E
- Bafoussam: 5.4781°N, 10.4179°E
- Bamenda: 5.9597°N, 10.1453°E
- Maroua: 10.5910°N, 14.3163°E
- Ngaoundéré: 7.3167°N, 13.5833°E

## 📈 Statistiques disponibles

### Carte interne (authentifiée)
- Nombre total d'infrastructures géolocalisées
- Répartition par type
- Répartition par statut
- Répartition par région

### Carte publique
- Total des infrastructures autorisées
- Nombre par type d'infrastructure
- Toutes les infrastructures sont vérifiées et conformes

## 🚀 Améliorations futures possibles

1. **Import de fichiers GPS** (GPX, KML)
2. **Calcul d'itinéraires** entre infrastructures
3. **Heatmap** de densité des infrastructures
4. **Zones de restriction automatiques** (parcs nationaux, zones militaires)
5. **Export de données géographiques** (GeoJSON, KML)
6. **API REST** pour intégration externe
7. **Mode hors-ligne** pour inspections terrain
8. **Photos géolocalisées** des infrastructures
9. **Historique des déplacements** d'une infrastructure
10. **Alertes de proximité** lors de nouvelles demandes

## 📱 Responsive Design

Toutes les cartes sont **entièrement responsive** et fonctionnent sur:
- Desktop (Chrome, Firefox, Safari, Edge)
- Tablettes
- Smartphones

## 🎓 Formation utilisateurs

### Pour les agents DPPG
1. Comment ajouter une localisation GPS
2. Comment vérifier les infrastructures à proximité
3. Comment interpréter les alertes de proximité

### Pour le public
1. Comment consulter la carte publique
2. Comment vérifier qu'une infrastructure est autorisée
3. Comment filtrer par région ou type

## 📞 Support technique

En cas de problème avec les fonctionnalités géographiques:
1. Vérifier que JavaScript est activé
2. Vérifier la connexion Internet (pour charger les cartes)
3. Vérifier les permissions de géolocalisation du navigateur
4. Contacter l'administrateur système

## 📄 Licence des cartes

- **OpenStreetMap**: © OpenStreetMap contributors (ODbL)
- **Leaflet**: BSD-2-Clause License
- Utilisation gratuite pour usage gouvernemental

---

**Développé pour MINEE/DPPG - Système de Gestion des Dossiers d'Implantation**
