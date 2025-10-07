# 📖 GUIDE ESPACE LECTEUR PUBLIC - SGDI

## Vue d'Ensemble

L'**Espace Lecteur** est l'interface publique du SGDI permettant à tout citoyen, entreprise ou organisation de consulter le **registre public des infrastructures pétrolières** autorisées et rejetées au Cameroun.

---

## 🎯 Objectifs

1. **Transparence administrative** - Rendre publiques les décisions ministérielles
2. **Consultation citoyenne** - Permettre aux citoyens de vérifier les autorisations
3. **Information des entreprises** - Faciliter l'accès aux données du secteur
4. **Traçabilité** - Carte interactive des infrastructures autorisées

---

## 🔐 Modes d'Accès

### 1. Compte Lecteur Classique
- **Username**: `lecteur`
- **Password**: `lecteur123`
- Accès limité en lecture seule
- Uniquement dossiers autorisés/rejetés

### 2. Connexion Google OAuth ✨ (NOUVEAU)
- Cliquer sur **"Se connecter avec Google"**
- Authentification via compte Gmail
- Création automatique d'un compte lecteur
- Avantages :
  - Pas besoin de mot de passe
  - Connexion rapide et sécurisée
  - Photo de profil Google affichée

---

## 🚀 Fonctionnalités

### 1. Dashboard Lecteur
**URL**: `/modules/lecteur/dashboard.php`

#### KPIs Affichés
- **Infrastructures Autorisées** : Nombre total d'autorisations
- **Demandes Rejetées** : Nombre total de refus
- **Autorisations ce Mois** : Nouvelles autorisations du mois en cours
- **Régions Couvertes** : Nombre de régions avec infrastructures

#### Carte Interactive (Leaflet)
- Visualisation géographique des infrastructures autorisées
- Marqueurs verts pour chaque infrastructure
- Pop-up avec détails au clic :
  - N° Dossier
  - Type d'infrastructure
  - Opérateur
  - Localisation exacte
  - Région
  - Date d'autorisation
  - Référence de la décision

#### Statistiques
- **Par Type d'Infrastructure** :
  - Implantation Station-Service
  - Reprise Station-Service
  - Implantation Point Consommateur
  - Reprise Point Consommateur
  - Implantation Dépôt GPL
  - Implantation Centre Emplisseur

- **Top 10 Régions** : Classement des régions par nombre d'infrastructures

#### Liste des Infrastructures Récentes
- Tableau des 20 dernières autorisations
- Colonnes :
  - N° Dossier
  - Type
  - Opérateur
  - Localisation
  - Région
  - Date Autorisation
  - Référence Décision

---

### 2. Recherche Avancée
**URL**: `/modules/lecteur/recherche.php`

#### Critères de Recherche
- **N° Dossier** : Recherche exacte ou partielle (ex: DPPG-2025-001)
- **Type d'Infrastructure** : Sélection parmi les 6 types
- **Région** : Liste déroulante des régions disponibles
- **Opérateur** : Nom de l'entreprise
- **Statut** : Autorisé / Rejeté
- **Date Début** : Filtrer à partir d'une date
- **Date Fin** : Filtrer jusqu'à une date

#### Résultats
- Affichage en tableau
- Limite de 100 résultats
- Badge coloré pour le statut :
  - 🟢 Vert = Autorisé
  - 🔴 Rouge = Rejeté

#### Export
- Bouton **"Exporter"** pour télécharger les résultats
- Format : CSV (compatible Excel)
- Nom du fichier : `registre_public_YYYY-MM-DD.csv`

---

### 3. Export des Données
**URL**: `/modules/lecteur/export.php`

#### Format du Fichier CSV
Colonnes exportées :
1. Statut
2. N° Dossier
3. Type Infrastructure
4. Opérateur
5. Localisation
6. Région
7. Département
8. Date Décision
9. Référence Décision
10. Latitude
11. Longitude

#### Caractéristiques
- Encodage UTF-8 avec BOM (pour Excel)
- Séparateur : point-virgule (`;`)
- Compatible Windows, Mac, Linux

---

## 🔒 Restrictions de Sécurité

### Données VISIBLES pour le Lecteur
✅ Dossiers avec statut `autorise`
✅ Dossiers avec statut `rejete`
✅ Décisions ministérielles publiées
✅ Infrastructures géolocalisées

### Données NON VISIBLES pour le Lecteur
❌ Dossiers en cours de traitement
❌ Brouillons
❌ Documents internes
❌ Rapports d'inspection
❌ Notes de frais
❌ Commentaires internes
❌ Historique des modifications

---

## 🛠️ Configuration Google OAuth

### Prérequis
1. Compte Google Cloud Platform
2. Projet créé
3. API Google+ activée

### Étapes d'Installation

#### 1. Créer les Identifiants OAuth
```bash
# Accéder à Google Cloud Console
https://console.cloud.google.com/

# Navigation :
APIs & Services → Credentials → Create Credentials → OAuth Client ID
```

#### 2. Configuration
- **Type d'application** : Application Web
- **Nom** : SGDI Lecteur Public
- **URIs de redirection autorisés** :
  ```
  http://localhost/dppg-implantation/auth/google_callback.php
  http://votre-domaine.com/auth/google_callback.php
  ```

#### 3. Récupérer les Clés
- **Client ID** : `123456789-abcdef.apps.googleusercontent.com`
- **Client Secret** : `GOCSPX-xxxxxxxxxxxx`

#### 4. Configuration du Fichier
**Fichier** : `config/google_oauth.php`

```php
// Remplacer ces valeurs
define('GOOGLE_CLIENT_ID', 'VOTRE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET');
```

#### 5. Test
1. Aller sur la page de connexion
2. Cliquer sur **"Se connecter avec Google"**
3. Autoriser l'application
4. Vérifier la redirection vers `/modules/lecteur/dashboard.php`

---

## 📊 Base de Données

### Table : `users`
Nouvelles colonnes pour Google OAuth :

```sql
google_id VARCHAR(100) NULL UNIQUE    -- ID Google de l'utilisateur
photo_url VARCHAR(500) NULL           -- URL de la photo de profil
```

### Création Automatique de Compte
Lors de la première connexion Google :
- Username auto-généré : `prenom_nom_1234`
- Email : Email Google
- Rôle : `lecteur` (automatique)
- Password : Aléatoire (non utilisé)
- Photo : URL Google

---

## 🗺️ Carte des Infrastructures

### Technologie
- **Bibliothèque** : Leaflet.js 1.9.4
- **Tuiles** : OpenStreetMap
- **Centrage** : Cameroun (lat: 6.5, lon: 12.5)
- **Zoom initial** : 6

### Marqueurs
- **Icône** : Marqueur vert (infrastructures autorisées)
- **Source** : [Leaflet Color Markers](https://github.com/pointhi/leaflet-color-markers)

### Pop-up Informations
```javascript
{
  numero: "DPPG-2025-001",
  type: "Implantation Station-Service",
  operateur: "BOCOM PETROLEUM SA",
  localisation: "Ebolowa, Route de Kribi",
  region: "Sud",
  date_decision: "16/09/2025",
  reference: "DECISION_N°2025/001/MINEE/DPPG"
}
```

### Ajustement Automatique
- Si plusieurs marqueurs : zoom pour tous les afficher
- Padding de 10% pour éviter les marqueurs sur les bords

---

## 🔍 Requêtes SQL Utilisées

### Infrastructures Autorisées
```sql
SELECT d.*,
       DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format,
       dec.reference_decision
FROM dossiers d
INNER JOIN decisions dec ON d.id = dec.dossier_id
WHERE d.statut = 'autorise'
ORDER BY dec.date_decision DESC
LIMIT 20
```

### Statistiques par Type
```sql
SELECT type_infrastructure, COUNT(*) as count
FROM dossiers
WHERE statut = 'autorise'
GROUP BY type_infrastructure
ORDER BY count DESC
```

### Top Régions
```sql
SELECT region, COUNT(*) as count
FROM dossiers
WHERE statut = 'autorise'
  AND region IS NOT NULL
  AND region != ''
GROUP BY region
ORDER BY count DESC
LIMIT 10
```

---

## 📱 Interface Responsive

### Desktop (> 992px)
- Carte pleine largeur (100%)
- Statistiques sur 2 colonnes
- Tableau avec toutes les colonnes

### Tablet (768px - 991px)
- KPIs sur 2 colonnes
- Carte adaptée
- Tableau scrollable

### Mobile (< 768px)
- KPIs en colonne unique
- Carte réduite (300px de hauteur)
- Tableau en mode scroll horizontal

---

## 🚨 Gestion des Erreurs

### Pas d'Infrastructures
```html
<div class="alert alert-info">
  Aucune infrastructure autorisée pour le moment
</div>
```

### Erreur de Carte
- Fallback : Afficher uniquement la liste
- Log : Enregistrer l'erreur JavaScript

### Erreur Google OAuth
- Message : "Erreur d'authentification Google"
- Redirection : Page de connexion
- Log : Enregistrer dans logs_activite

---

## 📈 Métriques et Analytics

### À Suivre
- Nombre de connexions Google vs classiques
- Recherches les plus fréquentes
- Régions les plus consultées
- Exports de données

### Logs
Toutes les actions sont enregistrées dans `logs_activite` :
- Connexion (classique ou Google)
- Recherches effectuées
- Exports de données
- Consultations de détails

---

## 🎨 Personnalisation

### Couleurs
```css
- Succès (Autorisé) : #28a745 (Vert)
- Danger (Rejeté) : #dc3545 (Rouge)
- Info : #17a2b8 (Bleu clair)
- Primary : #007bff (Bleu)
```

### Icônes
- Dashboard : `fa-globe`
- Recherche : `fa-search`
- Export : `fa-download`
- Carte : `fa-map-marker-alt`
- Google : `fab fa-google`

---

## 🔐 Sécurité

### Mesures Implémentées
1. **Restriction de données** : Uniquement autorisés/rejetés
2. **Pas de modification** : Lecture seule
3. **CSRF Protection** : Non applicable (lecture seule)
4. **SQL Injection** : Prepared statements
5. **XSS Prevention** : `sanitize()` sur toutes les sorties
6. **OAuth Secure** : HTTPS recommandé en production

### Recommandations Production
- Activer HTTPS obligatoire
- Limiter le taux de requêtes (rate limiting)
- Ajouter CAPTCHA sur export massif
- Monitorer les accès suspects

---

## 📚 Fichiers Créés

### Modules Lecteur
```
modules/lecteur/
├── dashboard.php         # Dashboard principal avec carte
├── recherche.php         # Recherche avancée multi-critères
└── export.php            # Export CSV des résultats
```

### Authentification Google
```
auth/
└── google_callback.php   # Callback OAuth Google

config/
└── google_oauth.php      # Configuration OAuth
```

### Base de Données
```
database/
└── add_google_oauth.sql  # Ajout colonnes google_id et photo_url

install_google_oauth.php  # Script d'installation
```

---

## 🧪 Tests à Effectuer

### Connexion
- [ ] Connexion avec compte lecteur classique
- [ ] Connexion avec Google (première fois)
- [ ] Connexion avec Google (utilisateur existant)
- [ ] Redirection vers dashboard lecteur

### Dashboard
- [ ] Affichage des KPIs corrects
- [ ] Chargement de la carte Leaflet
- [ ] Marqueurs avec bonnes coordonnées
- [ ] Pop-ups avec informations complètes
- [ ] Statistiques par type
- [ ] Top 10 régions
- [ ] Liste des 20 dernières autorisations

### Recherche
- [ ] Recherche par N° dossier
- [ ] Filtre par type d'infrastructure
- [ ] Filtre par région
- [ ] Filtre par opérateur
- [ ] Filtre par statut (autorisé/rejeté)
- [ ] Filtre par plage de dates
- [ ] Combinaison de plusieurs filtres
- [ ] Affichage des résultats
- [ ] Message si aucun résultat

### Export
- [ ] Bouton export visible si résultats
- [ ] Téléchargement du fichier CSV
- [ ] Encodage UTF-8 correct
- [ ] Toutes les colonnes présentes
- [ ] Données correspondant aux filtres

### Sécurité
- [ ] Impossible d'accéder aux dossiers en cours
- [ ] Impossible de modifier des données
- [ ] SQL injection bloquée
- [ ] XSS prevention active

---

## 💡 Améliorations Futures

### Court Terme
- [ ] Filtres sauvegardés (cookies)
- [ ] Export PDF avec mise en page
- [ ] Impression optimisée de la carte
- [ ] Partage de résultats par lien

### Moyen Terme
- [ ] API publique REST
- [ ] Widget carte intégrable
- [ ] Notifications email sur nouvelles autorisations
- [ ] Statistiques avancées (graphiques)

### Long Terme
- [ ] Application mobile dédiée
- [ ] Authentification 2FA
- [ ] Multi-langues (FR/EN)
- [ ] Accessibilité WCAG 2.1

---

## 📞 Support

### Pour les Utilisateurs
- Email : support@sgdi.minee.cm
- Téléphone : +237 XXX XXX XXX
- Horaires : Lun-Ven 8h-17h

### Pour les Développeurs
- Documentation technique : `/docs`
- Code source : Repository Git
- Issues : GitHub Issues

---

## 📝 Changelog

### Version 1.0 (2025-01-05)
- ✅ Création de l'espace lecteur
- ✅ Dashboard avec carte Leaflet
- ✅ Recherche avancée multi-critères
- ✅ Export CSV
- ✅ Authentification Google OAuth
- ✅ Restriction aux dossiers autorisés/rejetés
- ✅ Interface responsive

---

**Auteur** : Équipe Développement SGDI
**Date** : 5 Janvier 2025
**Version** : 1.0
