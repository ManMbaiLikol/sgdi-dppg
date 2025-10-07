# 🔐 GUIDE DE CONFIGURATION GOOGLE OAUTH - SGDI

## 📋 Vue d'ensemble

Ce guide vous accompagne **étape par étape** pour configurer l'authentification Google OAuth dans le SGDI.

**Durée estimée** : 10-15 minutes

---

## ✅ Prérequis

- [ ] Un compte Google (Gmail)
- [ ] Accès à Internet
- [ ] Le SGDI installé et fonctionnel

---

## 🚀 ÉTAPE 1 : Accéder à Google Cloud Console

### 1.1 Ouvrir la console
1. Aller sur : **https://console.cloud.google.com/**
2. Se connecter avec votre compte Google

![Google Cloud Console](https://i.imgur.com/console-home.png)

### 1.2 Vérifier/Créer une organisation (Optionnel)
- Si vous avez déjà une organisation : Passer à l'étape 2
- Sinon : Suivre les instructions de Google pour créer une organisation

---

## 🏗️ ÉTAPE 2 : Créer un Nouveau Projet

### 2.1 Cliquer sur le sélecteur de projet
En haut à gauche, à côté de "Google Cloud Platform", cliquer sur le **sélecteur de projet**

### 2.2 Créer un nouveau projet
1. Cliquer sur **"NOUVEAU PROJET"** (en haut à droite)
2. Remplir les informations :
   ```
   Nom du projet    : SGDI-MINEE
   Organisation     : (Votre organisation ou laisser vide)
   Emplacement      : (Laisser par défaut)
   ```
3. Cliquer sur **"CRÉER"**
4. Attendre la création (quelques secondes)
5. Sélectionner le projet nouvellement créé

![Créer projet](https://i.imgur.com/create-project.png)

---

## 🔌 ÉTAPE 3 : Activer les APIs Nécessaires

### 3.1 Accéder à la bibliothèque d'API
1. Dans le menu de gauche, cliquer sur **"APIs et services"**
2. Cliquer sur **"Bibliothèque"**

### 3.2 Activer Google+ API
1. Dans la barre de recherche, taper : **"Google+ API"**
2. Cliquer sur **"Google+ API"** dans les résultats
3. Cliquer sur **"ACTIVER"**
4. Attendre l'activation (quelques secondes)

### 3.3 Activer People API (recommandé)
1. Retourner à la bibliothèque
2. Rechercher : **"Google People API"**
3. Cliquer sur **"Google People API"**
4. Cliquer sur **"ACTIVER"**

![Activer API](https://i.imgur.com/enable-api.png)

---

## 🔑 ÉTAPE 4 : Créer les Identifiants OAuth 2.0

### 4.1 Accéder aux identifiants
1. Dans le menu de gauche : **"APIs et services"** → **"Identifiants"**
2. Cliquer sur **"+ CRÉER DES IDENTIFIANTS"** (en haut)
3. Sélectionner **"ID client OAuth"**

### 4.2 Configurer l'écran de consentement OAuth (si demandé)

#### Si c'est votre première fois :
1. Cliquer sur **"CONFIGURER L'ÉCRAN DE CONSENTEMENT"**

#### Type d'utilisateur :
- Sélectionner **"Externe"** (pour permettre tout compte Gmail)
- Cliquer sur **"CRÉER"**

#### Informations sur l'application :
```
Nom de l'application          : SGDI - Registre Public
E-mail d'assistance utilisateur : votre-email@gmail.com
Logo de l'application          : (Optionnel)
```

#### Domaine de l'application :
```
Domaine de l'application      : localhost (ou votre domaine)
Lien vers les règles de confidentialité : (Optionnel)
Lien vers les conditions d'utilisation  : (Optionnel)
```

#### Coordonnées du développeur :
```
Adresses e-mail : votre-email@gmail.com
```

3. Cliquer sur **"ENREGISTRER ET CONTINUER"**

#### Champs d'application (Scopes) :
1. Cliquer sur **"AJOUTER OU SUPPRIMER DES CHAMPS D'APPLICATION"**
2. Cocher :
   - ☑️ `.../auth/userinfo.email`
   - ☑️ `.../auth/userinfo.profile`
   - ☑️ `openid`
3. Cliquer sur **"METTRE À JOUR"**
4. Cliquer sur **"ENREGISTRER ET CONTINUER"**

#### Utilisateurs de test (Mode développement) :
1. Cliquer sur **"+ AJOUTER DES UTILISATEURS"**
2. Ajouter votre adresse Gmail pour les tests
3. Cliquer sur **"AJOUTER"**
4. Cliquer sur **"ENREGISTRER ET CONTINUER"**

5. Vérifier le résumé et cliquer sur **"RETOUR AU TABLEAU DE BORD"**

### 4.3 Créer l'ID client OAuth

1. Retourner dans **"Identifiants"**
2. Cliquer sur **"+ CRÉER DES IDENTIFIANTS"**
3. Sélectionner **"ID client OAuth"**

#### Type d'application :
- Sélectionner **"Application Web"**

#### Nom :
```
Nom : SGDI Lecteur Public
```

#### Origines JavaScript autorisées :
Cliquer sur **"+ AJOUTER UN URI"** et ajouter :
```
http://localhost
http://127.0.0.1
```

Si vous avez un domaine de production :
```
https://votre-domaine.com
```

#### URI de redirection autorisés :
Cliquer sur **"+ AJOUTER UN URI"** et ajouter :

**Pour développement local** :
```
http://localhost/dppg-implantation/auth/google_callback.php
http://127.0.0.1/dppg-implantation/auth/google_callback.php
```

**Pour WAMP (si port différent)** :
```
http://localhost:8080/dppg-implantation/auth/google_callback.php
```

**Pour production** :
```
https://votre-domaine.com/auth/google_callback.php
```

⚠️ **IMPORTANT** : L'URI doit être **EXACTEMENT** le même que celui configuré dans votre code !

4. Cliquer sur **"CRÉER"**

### 4.4 Récupérer les identifiants

Une fenêtre s'affiche avec :
```
ID client OAuth     : 123456789-abc123def456.apps.googleusercontent.com
Secret du client    : GOCSPX-abc123def456ghi789
```

📋 **COPIER CES DEUX VALEURS** - Vous en aurez besoin à l'étape suivante !

Vous pouvez aussi les retrouver en cliquant sur l'ID client créé dans la liste.

![Identifiants OAuth](https://i.imgur.com/oauth-credentials.png)

---

## ⚙️ ÉTAPE 5 : Configurer le Code SGDI

### 5.1 Ouvrir le fichier de configuration
Ouvrir le fichier : **`config/google_oauth.php`**

### 5.2 Remplacer les valeurs
Localiser ces lignes (lignes 11-12) :
```php
define('GOOGLE_CLIENT_ID', 'VOTRE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET');
```

Remplacer par vos vraies valeurs :
```php
define('GOOGLE_CLIENT_ID', '123456789-abc123def456.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-abc123def456ghi789');
```

### 5.3 Vérifier l'URI de redirection
Localiser la ligne 13 :
```php
define('GOOGLE_REDIRECT_URI', BASE_URL . '/auth/google_callback.php');
```

S'assurer que `BASE_URL` est correctement défini dans `config/config.php` :
```php
// Exemple pour WAMP en local
define('BASE_URL', 'http://localhost/dppg-implantation');

// Exemple pour production
define('BASE_URL', 'https://votre-domaine.com');
```

### 5.4 Sauvegarder le fichier
Enregistrer les modifications dans `config/google_oauth.php`

---

## 🧪 ÉTAPE 6 : Tester la Connexion

### 6.1 Accéder à la page de connexion
1. Ouvrir un navigateur
2. Aller sur : **http://localhost/dppg-implantation/**
3. Vous devriez voir le bouton **"Se connecter avec Google"**

### 6.2 Tester l'authentification
1. Cliquer sur **"Se connecter avec Google"**
2. Sélectionner votre compte Google
3. Autoriser l'application SGDI à accéder à :
   - Votre adresse e-mail
   - Vos informations de profil
4. Cliquer sur **"Autoriser"**

### 6.3 Vérifier la redirection
Vous devriez être redirigé vers :
```
http://localhost/dppg-implantation/modules/lecteur/dashboard.php
```

Avec le message : **"Bienvenue [Prénom] ! Vous êtes connecté avec Google."**

### 6.4 Vérifier le compte créé
1. Se déconnecter
2. Se reconnecter avec le compte admin (`admin` / `admin123`)
3. Aller dans **Gestion des utilisateurs**
4. Vérifier qu'un nouveau compte lecteur a été créé avec :
   - Username : `prenom_nom_xxxx`
   - Email : Votre Gmail
   - Rôle : lecteur
   - Photo : Photo de profil Google

---

## 🐛 DÉPANNAGE

### Problème 1 : "Erreur 400: redirect_uri_mismatch"

**Cause** : L'URI de redirection ne correspond pas

**Solution** :
1. Vérifier dans Google Cloud Console → Identifiants → Votre ID client
2. Comparer l'URI de redirection configurée avec celle dans le code
3. S'assurer qu'elles sont **EXACTEMENT** identiques (majuscules/minuscules, slash final, etc.)

**Exemple d'erreur** :
```
Configuré dans Google : http://localhost/dppg-implantation/auth/google_callback.php
Dans le code           : http://localhost/dppg-implantation/auth/google_callback.php/
                                                                                      ^ Slash en trop !
```

### Problème 2 : "Erreur 403: access_denied"

**Cause** : L'utilisateur n'est pas dans la liste des testeurs (mode développement)

**Solution** :
1. Aller dans Google Cloud Console
2. APIs et services → Écran de consentement OAuth
3. Section "Utilisateurs de test"
4. Ajouter l'adresse Gmail de test
5. Réessayer

### Problème 3 : "Erreur d'authentification Google: Aucun code reçu"

**Cause** : L'utilisateur a refusé l'autorisation

**Solution** :
1. Réessayer la connexion
2. Cliquer sur **"Autoriser"** quand Google demande les permissions

### Problème 4 : "Impossible d'obtenir le token"

**Cause** : Client ID ou Client Secret incorrect

**Solution** :
1. Vérifier les valeurs dans `config/google_oauth.php`
2. Comparer avec Google Cloud Console → Identifiants
3. Copier-coller à nouveau (attention aux espaces)

### Problème 5 : "cURL error" ou "SSL certificate problem"

**Cause** : Problème de certificat SSL (WAMP/Windows)

**Solution temporaire** (développement uniquement) :
Dans `config/google_oauth.php`, localiser :
```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```
Cette ligne désactive la vérification SSL (déjà présente)

**Solution définitive** (production) :
1. Télécharger le certificat CA : https://curl.se/ca/cacert.pem
2. Sauvegarder dans `C:\wamp64\bin\php\php8.x\extras\ssl\`
3. Modifier `php.ini` :
   ```ini
   curl.cainfo = "C:\wamp64\bin\php\php8.x\extras\ssl\cacert.pem"
   ```
4. Redémarrer Apache

### Problème 6 : Page blanche après autorisation

**Cause** : Erreur PHP non affichée

**Solution** :
1. Activer l'affichage des erreurs dans `php.ini` :
   ```ini
   display_errors = On
   error_reporting = E_ALL
   ```
2. Redémarrer Apache
3. Consulter les logs PHP : `C:\wamp64\logs\php_error.log`
4. Vérifier les logs Apache : `C:\wamp64\logs\apache_error.log`

### Problème 7 : "Ce compte n'est pas un compte lecteur"

**Cause** : L'email Google correspond à un utilisateur existant avec un autre rôle

**Solution** :
1. Utiliser une autre adresse Gmail
2. OU demander à l'admin de supprimer/modifier l'utilisateur existant
3. OU utiliser le mot de passe classique pour ce compte

---

## 📊 VÉRIFICATION DE L'INSTALLATION

### Checklist finale

- [ ] Projet créé dans Google Cloud Console
- [ ] Google+ API activée
- [ ] Écran de consentement configuré
- [ ] ID client OAuth créé
- [ ] URI de redirection ajouté
- [ ] Client ID copié dans `config/google_oauth.php`
- [ ] Client Secret copié dans `config/google_oauth.php`
- [ ] BASE_URL correctement défini
- [ ] Bouton Google visible sur page de connexion
- [ ] Test de connexion réussi
- [ ] Compte lecteur créé automatiquement
- [ ] Redirection vers dashboard lecteur OK

---

## 🔒 SÉCURITÉ

### Pour le développement (localhost)
- ✅ HTTP accepté
- ✅ SSL verification disabled (dans le code)
- ✅ Mode "Externe" pour tout compte Gmail

### Pour la production
- ⚠️ **OBLIGATOIRE** : Utiliser HTTPS uniquement
- ⚠️ Activer SSL verification :
  ```php
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  ```
- ⚠️ Publier l'application (sortir du mode test)
- ⚠️ Ajouter vraie politique de confidentialité
- ⚠️ Configurer le domaine vérifié

---

## 📱 PASSAGE EN PRODUCTION

### 1. Publier l'application
1. Google Cloud Console → APIs et services → Écran de consentement OAuth
2. Cliquer sur **"PUBLIER L'APPLICATION"**
3. Google peut demander une vérification (si > 100 utilisateurs)

### 2. Mettre à jour les URIs
1. Ajouter les URIs de production :
   ```
   https://votre-domaine.com
   https://votre-domaine.com/auth/google_callback.php
   ```
2. Retirer les URIs localhost si souhaité

### 3. Mettre à jour le code
1. Modifier `config/config.php` :
   ```php
   define('BASE_URL', 'https://votre-domaine.com');
   ```
2. Activer SSL verification dans `config/google_oauth.php` :
   ```php
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
   ```

---

## 📚 RESSOURCES

### Documentation officielle
- **Google OAuth 2.0** : https://developers.google.com/identity/protocols/oauth2
- **Google Cloud Console** : https://console.cloud.google.com/
- **People API** : https://developers.google.com/people

### Support
- **Documentation SGDI** : `/docs`
- **Email** : support@sgdi.minee.cm

---

## ✅ RÉSUMÉ

Vous avez maintenant configuré avec succès l'authentification Google OAuth !

**Les utilisateurs peuvent** :
- ✅ Se connecter avec leur compte Gmail en 1 clic
- ✅ Accéder au registre public sans créer de mot de passe
- ✅ Voir leur photo de profil Google

**Le système** :
- ✅ Crée automatiquement un compte lecteur
- ✅ Enregistre l'email et le nom de l'utilisateur
- ✅ Redirige vers le dashboard lecteur
- ✅ Log toutes les connexions

---

**Dernière mise à jour** : 5 Janvier 2025
**Version** : 1.0
**Statut** : ✅ Prêt pour production
