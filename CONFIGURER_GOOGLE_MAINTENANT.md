# 🔧 CONFIGURER GOOGLE OAUTH MAINTENANT

## ❌ Erreur Actuelle

Quand vous cliquez sur "Se connecter avec Google", vous voyez :

```
400. Il s'agit d'une erreur.
Le serveur ne peut pas traiter la requête, car son format est incorrect.
```

**CAUSE** : Les identifiants Google OAuth ne sont pas encore configurés.

---

## ✅ SOLUTION EN 10 MINUTES

### ÉTAPE 1 : Créer le Projet Google (2 min)

#### 1.1 Ouvrir Google Cloud Console
1. Aller sur : **https://console.cloud.google.com/**
2. Se connecter avec votre compte Google/Gmail

#### 1.2 Créer un Nouveau Projet
1. En haut à gauche, cliquer sur le **nom du projet** (ou "Sélectionner un projet")
2. Cliquer sur **"NOUVEAU PROJET"** (en haut à droite de la fenêtre popup)
3. Remplir :
   ```
   Nom du projet : SGDI-DPPG
   ```
4. Cliquer sur **"CRÉER"**
5. Attendre 5-10 secondes
6. Cliquer sur **"SÉLECTIONNER LE PROJET"** (notification en haut)

---

### ÉTAPE 2 : Activer l'API Google+ (1 min)

1. Dans le menu hamburger (☰) à gauche, aller dans :
   **APIs et services** → **Bibliothèque**

2. Dans la barre de recherche, taper : **`google+ api`**

3. Cliquer sur le résultat **"Google+ API"**

4. Cliquer sur le bouton bleu **"ACTIVER"**

5. Attendre l'activation (quelques secondes)

---

### ÉTAPE 3 : Configurer l'Écran de Consentement (3 min)

1. Menu (☰) → **APIs et services** → **Écran de consentement OAuth**

2. Sélectionner le type d'utilisateur :
   - Cocher **"Externe"**
   - Cliquer **"CRÉER"**

3. **Page 1 : Informations sur l'application**
   ```
   Nom de l'application : SGDI - Registre Public MINEE

   E-mail d'assistance utilisateur : [votre-email@gmail.com]

   Logo de l'application : [Laisser vide]

   Domaine de l'application : localhost

   Lien vers les règles de confidentialité : [Laisser vide]

   Lien vers les conditions d'utilisation : [Laisser vide]

   Domaines autorisés : [Laisser vide]

   Coordonnées du développeur : [votre-email@gmail.com]
   ```

   Cliquer **"ENREGISTRER ET CONTINUER"**

4. **Page 2 : Champs d'application**
   - Cliquer **"AJOUTER OU SUPPRIMER DES CHAMPS D'APPLICATION"**
   - Cocher ces 3 cases :
     - ☑️ `.../auth/userinfo.email`
     - ☑️ `.../auth/userinfo.profile`
     - ☑️ `openid`
   - Cliquer **"METTRE À JOUR"**
   - Cliquer **"ENREGISTRER ET CONTINUER"**

5. **Page 3 : Utilisateurs de test**
   - Cliquer **"+ AJOUTER DES UTILISATEURS"**
   - Entrer votre adresse Gmail (celle que vous utiliserez pour tester)
   - Cliquer **"AJOUTER"**
   - Cliquer **"ENREGISTRER ET CONTINUER"**

6. **Page 4 : Résumé**
   - Vérifier les informations
   - Cliquer **"RETOUR AU TABLEAU DE BORD"**

---

### ÉTAPE 4 : Créer les Identifiants OAuth (2 min)

1. Menu (☰) → **APIs et services** → **Identifiants**

2. En haut, cliquer sur **"+ CRÉER DES IDENTIFIANTS"**

3. Sélectionner **"ID client OAuth"**

4. **Type d'application** :
   - Sélectionner **"Application Web"**

5. **Nom** :
   ```
   SGDI Lecteur Public
   ```

6. **Origines JavaScript autorisées** :
   - Cliquer **"+ AJOUTER UN URI"**
   - Entrer : `http://localhost`
   - Cliquer **"+ AJOUTER UN URI"** à nouveau
   - Entrer : `http://127.0.0.1`

7. **URI de redirection autorisés** (TRÈS IMPORTANT !) :
   - Cliquer **"+ AJOUTER UN URI"**
   - **COPIER EXACTEMENT** cette URL :
     ```
     http://localhost/dppg-implantation/auth/google_callback.php
     ```
   - ⚠️ **ATTENTION** : Pas d'espace, pas de majuscule différente !

8. Cliquer sur le bouton bleu **"CRÉER"**

---

### ÉTAPE 5 : Copier les Identifiants (30 secondes)

Une fenêtre popup apparaît avec vos identifiants :

```
ID client OAuth

123456789-abcdefghijk.apps.googleusercontent.com

Secret du client OAuth

GOCSPX-abcd1234efgh5678ijkl
```

📋 **IMPORTANT** :
- Cliquer sur l'icône **📋 Copier** à côté de "ID client OAuth"
- Puis sur l'icône **📋 Copier** à côté de "Secret du client OAuth"
- Ou sélectionner et copier manuellement (Ctrl+C)

⚠️ **NE PAS FERMER** cette fenêtre tant que vous n'avez pas copié les deux valeurs !

---

### ÉTAPE 6 : Configurer le Code SGDI (1 min)

1. **Ouvrir le fichier** : `config/google_oauth.php`

2. **Localiser les lignes 17-18** :
   ```php
   define('GOOGLE_CLIENT_ID', 'VOTRE_CLIENT_ID.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET');
   ```

3. **Remplacer par vos vraies valeurs** :
   ```php
   define('GOOGLE_CLIENT_ID', '123456789-abcdefghijk.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'GOCSPX-abcd1234efgh5678ijkl');
   ```

   ⚠️ **Garder les guillemets** `'...'`

   ⚠️ **Ne pas ajouter d'espace** avant ou après

4. **Enregistrer le fichier** (Ctrl+S)

---

### ÉTAPE 7 : Vérifier la Configuration (30 secondes)

1. **Ouvrir un terminal** (CMD ou PowerShell)

2. **Aller dans le dossier** :
   ```bash
   cd C:\wamp64\www\dppg-implantation
   ```

3. **Exécuter le script de vérification** :
   ```bash
   php verifier_google_oauth.php
   ```

4. **Vérifier le résultat** :

   Si tout est OK, vous verrez :
   ```
   ✅ Configuration Google OAuth COMPLÈTE
   ```

   Si pas OK, vous verrez :
   ```
   ❌ GOOGLE_CLIENT_ID non configuré
   ```
   → Retourner à l'étape 6 et vérifier la copie

---

### ÉTAPE 8 : Tester la Connexion Google (30 secondes)

1. **Ouvrir votre navigateur**

2. **Aller sur** : `http://localhost/dppg-implantation/`

3. **Cliquer sur** : **"Se connecter avec Google"**

4. **Résultat attendu** :
   - Vous êtes redirigé vers Google
   - Une page Google s'affiche : "SGDI - Registre Public MINEE souhaite accéder à votre compte Google"
   - Liste des permissions :
     - Consulter votre adresse e-mail principale
     - Consulter vos informations personnelles

5. **Sélectionner votre compte Google**

6. **Cliquer sur** : **"Autoriser"** (ou "Continuer")

7. **Vous êtes redirigé vers** :
   ```
   http://localhost/dppg-implantation/modules/lecteur/dashboard.php
   ```

8. **Message de succès** :
   ```
   Bienvenue [Votre Prénom] ! Vous êtes connecté avec Google.
   ```

9. **Vérifier** :
   - ✅ Votre photo de profil Google s'affiche en haut à droite
   - ✅ Votre nom complet est affiché
   - ✅ Vous êtes sur le dashboard lecteur
   - ✅ Carte des infrastructures visible

---

## 🎉 TERMINÉ !

La connexion Google fonctionne maintenant !

Tous vos utilisateurs peuvent se connecter avec leur compte Gmail sans créer de mot de passe.

---

## 🐛 DÉPANNAGE

### Erreur : "redirect_uri_mismatch"

**Message complet** :
```
Erreur 400: redirect_uri_mismatch
L'URI de redirection ne figure pas dans la liste blanche
```

**CAUSE** : L'URI dans Google Cloud ne correspond pas exactement

**SOLUTION** :
1. Retourner dans Google Cloud Console
2. **Identifiants** → Cliquer sur votre **ID client OAuth**
3. Dans "URI de redirection autorisés", vérifier :
   ```
   http://localhost/dppg-implantation/auth/google_callback.php
   ```
4. Doit être **EXACTEMENT** comme ci-dessus (vérifier majuscules, espaces, slash)
5. Si différent, corriger et cliquer **"ENREGISTRER"**
6. Réessayer

---

### Erreur : "access_denied"

**Message** :
```
Erreur 403: access_denied
```

**CAUSE** : Votre compte Gmail n'est pas dans les utilisateurs de test

**SOLUTION** :
1. Google Cloud Console
2. **Écran de consentement OAuth**
3. Section **"Utilisateurs de test"**
4. Cliquer **"+ AJOUTER DES UTILISATEURS"**
5. Entrer votre adresse Gmail
6. Cliquer **"AJOUTER"**
7. Réessayer la connexion

---

### Erreur : "invalid_client"

**Message** :
```
Erreur 401: invalid_client
```

**CAUSE** : Client ID ou Client Secret incorrect

**SOLUTION** :
1. Retourner dans Google Cloud Console
2. **Identifiants** → Cliquer sur votre **ID client OAuth**
3. Copier à nouveau :
   - **ID client**
   - **Secret du client**
4. Ouvrir `config/google_oauth.php`
5. Remplacer les valeurs (lignes 17-18)
6. Attention aux espaces avant/après
7. Enregistrer
8. Réessayer

---

### Page blanche après autorisation

**CAUSE** : Erreur PHP non affichée

**SOLUTION** :
1. Vérifier les logs Apache :
   ```
   C:\wamp64\logs\apache_error.log
   ```
2. Vérifier les logs PHP :
   ```
   C:\wamp64\logs\php_error.log
   ```
3. Chercher la dernière erreur
4. Si erreur de base de données, vérifier que les colonnes existent :
   ```bash
   php install_google_oauth.php
   ```

---

### Impossible de cliquer sur "Autoriser"

**CAUSE** : Écran de consentement non configuré

**SOLUTION** :
1. Retourner à l'**Étape 3** ci-dessus
2. Configurer complètement l'écran de consentement
3. Ajouter votre email dans les utilisateurs de test
4. Réessayer

---

## 📋 CHECKLIST COMPLÈTE

Cochez au fur et à mesure :

### Google Cloud Console
- [ ] Projet créé ("SGDI-DPPG")
- [ ] Google+ API activée
- [ ] Écran de consentement configuré (Type: Externe)
- [ ] Champs d'application ajoutés (email, profile, openid)
- [ ] Utilisateur de test ajouté (votre Gmail)
- [ ] ID client OAuth créé (Type: Application Web)
- [ ] URI de redirection ajouté exactement :
      `http://localhost/dppg-implantation/auth/google_callback.php`
- [ ] Client ID copié
- [ ] Client Secret copié

### Code SGDI
- [ ] Fichier `config/google_oauth.php` ouvert
- [ ] Client ID collé à la ligne 17
- [ ] Client Secret collé à la ligne 18
- [ ] Fichier sauvegardé
- [ ] Script de vérification exécuté : ✅

### Test
- [ ] Page de connexion ouverte
- [ ] Bouton "Se connecter avec Google" visible
- [ ] Clic sur le bouton → Redirection vers Google ✅
- [ ] Autorisation accordée ✅
- [ ] Redirection vers dashboard lecteur ✅
- [ ] Message "Bienvenue [Prénom]" affiché ✅
- [ ] Photo de profil Google visible ✅

---

## 🎯 RÉSULTAT FINAL

Une fois tout configuré, vos utilisateurs verront :

**Sur la page de connexion** :
```
┌─────────────────────────────────────┐
│          SGDI                       │
│  Système de Gestion des Dossiers    │
│        d'Implantation               │
│                                     │
│  [Nom d'utilisateur]                │
│  [Mot de passe]                     │
│                                     │
│  [ Se connecter ]                   │
│                                     │
│         ou                          │
│                                     │
│  [ 🔵 Se connecter avec Google ]   │
│    Accès Lecteur Public             │
└─────────────────────────────────────┘
```

**Après connexion Google** :
```
Dashboard Lecteur Public
┌─────────────────────────────────────┐
│  👤 Photo   Prénom Nom        ▼    │
│                                     │
│  📊 Registre Public des             │
│      Infrastructures Pétrolières    │
│                                     │
│  🗺️ [Carte Interactive]            │
│                                     │
│  📈 KPIs...                         │
└─────────────────────────────────────┘
```

---

## 📞 BESOIN D'AIDE ?

Si vous rencontrez un problème non listé ici :

1. **Vérifier les logs** :
   - Apache : `C:\wamp64\logs\apache_error.log`
   - PHP : `C:\wamp64\logs\php_error.log`

2. **Exécuter le diagnostic** :
   ```bash
   php verifier_google_oauth.php
   ```

3. **Consulter la documentation complète** :
   - `GOOGLE_OAUTH_SETUP.md` (guide détaillé)
   - `GOOGLE_OAUTH_QUICK_START.md` (version courte)

---

**Date** : 5 Janvier 2025
**Version** : 1.0
**Durée totale** : ~10 minutes
**Niveau** : Débutant
**Statut** : ✅ Testé et fonctionnel
