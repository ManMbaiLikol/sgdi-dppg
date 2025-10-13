# Configuration Google Places API pour l'import automatique des POI

## Présentation

Le module d'import Google Places permet de détecter et importer automatiquement les points d'intérêt stratégiques du Cameroun depuis Google Places API.

**Fonctionnalités :**
- Interface admin avec sélection des régions/villes du Cameroun
- Recherche automatique des POI par catégorie (écoles, hôpitaux, mosquées, mairies, etc.)
- Aperçu et validation avant import
- Import par lots avec détection de doublons
- Mapping automatique des types Google vers les catégories SGDI

## Coûts

Google Places API est payant mais offre :
- **$200 de crédits gratuits par mois** (environ 28$ après le crédit de 200$ appliqué à Places API)
- **$0.032 par recherche** (Nearby Search)
- **~875 recherches gratuites par mois** avec les crédits

## Configuration de l'API Google Places

### Étape 1 : Créer un projet Google Cloud

1. Accédez à [Google Cloud Console](https://console.cloud.google.com)
2. Connectez-vous avec votre compte Google
3. Cliquez sur "Sélectionner un projet" → "Nouveau projet"
4. Nommez votre projet (ex: "SGDI-POI-Import")
5. Cliquez sur "Créer"

### Étape 2 : Activer l'API Places

1. Dans le menu, allez à **"API et services" → "Bibliothèque"**
2. Recherchez **"Places API"**
3. Cliquez sur **"Places API"**
4. Cliquez sur **"Activer"**

### Étape 3 : Activer l'API Geocoding (requis)

1. Dans la bibliothèque, recherchez **"Geocoding API"**
2. Cliquez sur **"Geocoding API"**
3. Cliquez sur **"Activer"**

### Étape 4 : Créer une clé API

1. Allez à **"API et services" → "Identifiants"**
2. Cliquez sur **"+ Créer des identifiants"**
3. Sélectionnez **"Clé API"**
4. Une clé sera générée (ex: `AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`)

### Étape 5 : Sécuriser la clé API (recommandé)

1. Cliquez sur **"Modifier la clé API"**
2. Sous **"Restrictions d'API"** :
   - Sélectionnez "Limiter la clé"
   - Cochez uniquement :
     - ✅ Places API
     - ✅ Geocoding API
3. Sous **"Restrictions relatives à l'application"** (optionnel) :
   - Sélectionnez "Adresses IP" si vous connaissez l'IP de votre serveur
   - Ajoutez les IPs autorisées
4. Cliquez sur **"Enregistrer"**

### Étape 6 : Configurer la clé dans SGDI

#### Pour un environnement local (WAMP/XAMPP)

Créez un fichier `.env` à la racine du projet :

```env
GOOGLE_PLACES_API_KEY=AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**OU** modifiez `config/app.php` et ajoutez :

```php
// Configuration Google Places API
define('GOOGLE_PLACES_API_KEY', 'AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
```

#### Pour Railway (déploiement)

1. Dans le dashboard Railway, sélectionnez votre projet
2. Allez dans l'onglet **"Variables"**
3. Cliquez sur **"+ New Variable"**
4. Ajoutez :
   - **Variable name:** `GOOGLE_PLACES_API_KEY`
   - **Value:** Votre clé API
5. Cliquez sur **"Add"**

## Utilisation du module

### Accès au module

1. Connectez-vous en tant qu'**Admin** ou **Chef de Service**
2. Allez dans **"Points d'intérêt" → "Import Google Places"**
3. Ou accédez directement : `/modules/poi/import_google.php`

### Processus d'import

1. **Sélectionner une région** (ex: Littoral, Centre, etc.)
2. **Sélectionner une ville** (ex: Douala, Yaoundé, etc.)
3. **Choisir le rayon de recherche** (5 à 20 km)
4. **Sélectionner les catégories** de POI à rechercher
5. Cliquer sur **"Rechercher sur Google Places"**
6. **Prévisualiser les résultats** dans le tableau
7. **Sélectionner** les POI à importer (cocher/décocher)
8. Cliquer sur **"Importer les POI sélectionnés"**

### Catégories détectées automatiquement

Le système mappe automatiquement les types Google vers les catégories SGDI :

| Catégorie SGDI | Types Google détectés | Distance min |
|----------------|----------------------|--------------|
| Établissements d'enseignement | school, university, primary_school, secondary_school | 100m |
| Infrastructures sanitaires | hospital, doctor, dentist, pharmacy, health | 100m |
| Lieux de culte | church, mosque, synagogue, temple | 100m |
| Terrains de sport | stadium | 100m |
| Places de marché | supermarket | 100m |
| Bâtiments administratifs | local_government_office, courthouse, embassy | 100m |
| Mairies | city_hall, town_hall | 500m |

### Détection de doublons

Le système détecte automatiquement les doublons en vérifiant :
- Le nom du POI
- Les coordonnées GPS (±0.001° de différence)

Les doublons ne seront pas importés.

## Estimation des coûts par recherche

### Exemple 1 : Import pour Douala (10 km, toutes catégories)

- 7 types de catégories
- ~10-15 recherches Google
- Coût : **~$0.32 - $0.48**
- POI trouvés : ~100-300

### Exemple 2 : Import complet pour les 10 régions

- 10 régions × 3 villes par région = 30 villes
- ~15 recherches par ville
- Total : ~450 recherches
- Coût : **~$14.40**
- POI trouvés : ~1000-3000

### Limites du crédit gratuit

Avec $28 de crédits gratuits par mois :
- ~875 recherches possibles
- Suffisant pour ~58 villes complètes
- Import complet du Cameroun possible en 1 mois

## Limitations et recommandations

### Limitations de Google Places

- **Bâtiments gouvernementaux** : Peu référencés (Présidence, Préfectures, etc.)
  → Ces POI doivent être ajoutés manuellement
- **Zones rurales** : Moins de données disponibles
  → Vérifier les résultats avant import
- **Précision variable** : Certains POI peuvent avoir des coordonnées approximatives
  → Valider sur carte avant import

### Recommandations

1. **Commencer par les grandes villes** (Yaoundé, Douala, Garoua, Bamenda)
2. **Vérifier l'aperçu** avant d'importer massivement
3. **Importer par lots** (une ville à la fois)
4. **Compléter manuellement** les POI stratégiques manquants
5. **Surveiller les coûts** dans Google Cloud Console

## Surveillance des coûts

### Consulter votre consommation

1. Allez dans [Google Cloud Console](https://console.cloud.google.com)
2. Sélectionnez votre projet
3. Menu → **"Facturation"**
4. Consultez **"Rapports"** pour voir l'utilisation

### Configurer des alertes budgétaires

1. Menu → **"Facturation" → "Budgets et alertes"**
2. Cliquez sur **"Créer un budget"**
3. Définissez un montant (ex: $20/mois)
4. Configurez des alertes à 50%, 90%, 100%

## Résolution des problèmes

### Erreur : "Clé API non configurée"

**Solution :** Vérifiez que `GOOGLE_PLACES_API_KEY` est bien définie dans `.env` ou `config/app.php`

### Erreur : "API key not valid"

**Solutions :**
1. Vérifiez que Places API et Geocoding API sont activées
2. Vérifiez les restrictions de la clé API
3. Attendez quelques minutes après la création de la clé

### Erreur : "REQUEST_DENIED"

**Solutions :**
1. Vérifiez que vous avez activé la facturation sur Google Cloud
2. Vérifiez les restrictions d'IP si configurées
3. Vérifiez que la clé n'est pas expirée

### Aucun POI trouvé pour une ville

**Solutions :**
1. Augmentez le rayon de recherche (15-20 km)
2. Vérifiez l'orthographe de la ville
3. Essayez une ville voisine plus grande

## Support

Pour toute question ou problème :
- Documentation officielle : [Google Places API Docs](https://developers.google.com/maps/documentation/places/web-service)
- Console de test : [Places API Playground](https://developers.google.com/maps/documentation/places/web-service/search-nearby)

---

**Version :** 1.0
**Dernière mise à jour :** Octobre 2025
