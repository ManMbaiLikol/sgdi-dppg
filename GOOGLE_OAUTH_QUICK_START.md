# 🚀 GOOGLE OAUTH - DÉMARRAGE RAPIDE

## ⚡ Configuration en 5 Minutes

### ✅ Prérequis
- [ ] Compte Google (Gmail)
- [ ] SGDI installé et fonctionnel

---

## 📝 ÉTAPE 1 : Google Cloud Console (5 min)

### 1.1 Créer le Projet
1. Aller sur : **https://console.cloud.google.com/**
2. Cliquer sur **"Sélectionner un projet"** → **"NOUVEAU PROJET"**
3. Nom : **`SGDI-MINEE`**
4. Cliquer **"CRÉER"**

### 1.2 Activer l'API
1. Menu → **APIs et services** → **Bibliothèque**
2. Rechercher : **"Google+ API"**
3. Cliquer **"ACTIVER"**

### 1.3 Configurer l'Écran de Consentement
1. Menu → **APIs et services** → **Écran de consentement OAuth**
2. Type : **"Externe"** → **"CRÉER"**
3. Remplir :
   ```
   Nom application : SGDI - Registre Public
   Email support   : votre-email@gmail.com
   ```
4. **"ENREGISTRER ET CONTINUER"** (3 fois)
5. **"RETOUR AU TABLEAU DE BORD"**

### 1.4 Créer les Identifiants OAuth
1. Menu → **APIs et services** → **Identifiants**
2. **"+ CRÉER DES IDENTIFIANTS"** → **"ID client OAuth"**
3. Type : **"Application Web"**
4. Nom : **`SGDI Lecteur`**
5. **URI de redirection autorisés** → **"+ AJOUTER UN URI"** :
   ```
   http://localhost/dppg-implantation/auth/google_callback.php
   ```
   ⚠️ **Copier EXACTEMENT cette URL !**
6. Cliquer **"CRÉER"**

### 1.5 Copier les Identifiants
Une fenêtre s'affiche avec :
```
ID client        : 123456789-abc123.apps.googleusercontent.com
Secret du client : GOCSPX-abc123def456
```
📋 **COPIER CES DEUX VALEURS** !

---

## ⚙️ ÉTAPE 2 : Configurer le Code SGDI (1 min)

### 2.1 Ouvrir le Fichier
Ouvrir : **`config/google_oauth.php`**

### 2.2 Remplacer les Valeurs
Lignes 17-18, remplacer :
```php
define('GOOGLE_CLIENT_ID', 'VOTRE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET');
```

Par vos vraies valeurs :
```php
define('GOOGLE_CLIENT_ID', '123456789-abc123.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-abc123def456');
```

### 2.3 Sauvegarder
Enregistrer le fichier `config/google_oauth.php`

---

## ✅ ÉTAPE 3 : Vérifier (1 min)

### 3.1 Script de Vérification
Exécuter dans le terminal :
```bash
php verifier_google_oauth.php
```

Vous devriez voir :
```
✅ Configuration Google OAuth COMPLÈTE
```

### 3.2 Tester la Connexion
1. Aller sur : **http://localhost/dppg-implantation/**
2. Vous devriez voir le bouton **"Se connecter avec Google"**
3. Cliquer dessus
4. Autoriser l'application
5. Vous êtes redirigé vers le dashboard lecteur !

---

## 🎉 C'EST TERMINÉ !

Vos utilisateurs peuvent maintenant se connecter avec leur compte Google !

---

## 🐛 Problème ?

### Erreur "redirect_uri_mismatch" ?
L'URI dans Google Cloud ne correspond pas.

**Solution** :
1. Retourner dans Google Cloud Console
2. Identifiants → Votre ID client
3. Vérifier l'URI de redirection :
   ```
   http://localhost/dppg-implantation/auth/google_callback.php
   ```
4. Doit être EXACTEMENT comme ci-dessus

### Erreur "access_denied" ?
Vous n'êtes pas dans les utilisateurs de test.

**Solution** :
1. Google Cloud Console → Écran de consentement OAuth
2. Utilisateurs de test → **"+ AJOUTER DES UTILISATEURS"**
3. Ajouter votre Gmail
4. Réessayer

### Autre problème ?
Consulter : **`GOOGLE_OAUTH_SETUP.md`** (guide complet)

---

## 📊 Vérification Rapide

- [ ] Projet Google Cloud créé
- [ ] Google+ API activée
- [ ] Écran de consentement configuré
- [ ] ID client OAuth créé
- [ ] URI de redirection ajouté
- [ ] Client ID copié dans le code
- [ ] Client Secret copié dans le code
- [ ] Script de vérification = ✅
- [ ] Test de connexion réussi

---

## 🚀 Production

Pour mettre en production avec un vrai domaine :

1. **Ajouter l'URI de production** dans Google Cloud :
   ```
   https://votre-domaine.com/auth/google_callback.php
   ```

2. **Publier l'application** :
   - Google Cloud Console → Écran de consentement OAuth
   - Cliquer **"PUBLIER L'APPLICATION"**

3. C'est tout ! 🎉

---

**Besoin d'aide ?** Voir `GOOGLE_OAUTH_SETUP.md` pour le guide détaillé
