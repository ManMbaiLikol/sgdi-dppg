# Guide de Déploiement sur Railway.app

## Prérequis

- Compte GitHub (gratuit)
- Compte Railway.app (gratuit, 5$/mois de crédits)
- Git installé sur votre ordinateur

## Étape 1 : Préparer le Dépôt GitHub

### 1.1 Créer un compte GitHub (si vous n'en avez pas)
1. Allez sur https://github.com
2. Cliquez sur "Sign up"
3. Créez votre compte gratuit

### 1.2 Créer un nouveau dépôt
1. Connectez-vous à GitHub
2. Cliquez sur le bouton "+" en haut à droite → "New repository"
3. Remplissez les informations :
   - **Repository name** : `sgdi-dppg` (ou le nom de votre choix)
   - **Description** : "Système de Gestion des Dossiers d'Implantation - MINEE/DPPG"
   - **Visibility** : Choisissez "Private" (recommandé) ou "Public"
   - **NE PAS** cocher "Initialize with README" (le projet a déjà des fichiers)
4. Cliquez sur "Create repository"

### 1.3 Initialiser Git dans votre projet local

Ouvrez Git Bash dans le dossier du projet (`C:\wamp64\www\dppg-implantation`) et exécutez :

```bash
# Initialiser Git
git init

# Ajouter tous les fichiers
git add .

# Créer le premier commit
git commit -m "Initial commit - SGDI Application"

# Ajouter le dépôt distant (remplacez YOUR_USERNAME par votre nom d'utilisateur GitHub)
git remote add origin https://github.com/YOUR_USERNAME/sgdi-dppg.git

# Pousser le code vers GitHub
git branch -M main
git push -u origin main
```

**Note** : Si Git vous demande vos identifiants, entrez votre nom d'utilisateur GitHub et un **Personal Access Token** (pas votre mot de passe).

Pour créer un token :
1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token → Cochez "repo" → Generate token
3. Copiez le token et utilisez-le comme mot de passe

## Étape 2 : Créer un Compte Railway.app

1. Allez sur https://railway.app
2. Cliquez sur "Login" ou "Start a New Project"
3. **Connectez-vous avec GitHub** (recommandé pour faciliter le déploiement)
4. Autorisez Railway à accéder à vos dépôts GitHub

## Étape 3 : Créer le Projet sur Railway

### 3.1 Créer un nouveau projet
1. Dans le dashboard Railway, cliquez sur "New Project"
2. Sélectionnez "Deploy from GitHub repo"
3. Choisissez votre dépôt `sgdi-dppg`
4. Railway va automatiquement détecter le Dockerfile et commencer le déploiement

### 3.2 Ajouter la base de données MySQL
1. Dans votre projet Railway, cliquez sur "+ New"
2. Sélectionnez "Database" → "Add MySQL"
3. Railway va créer automatiquement une base de données MySQL

## Étape 4 : Configuration des Variables d'Environnement

Railway configure automatiquement les variables MySQL. Vérifiez-les :

1. Cliquez sur le service MySQL
2. Allez dans l'onglet "Variables"
3. Vous devriez voir :
   - `MYSQL_HOST`
   - `MYSQL_DATABASE`
   - `MYSQL_USER`
   - `MYSQL_PASSWORD`
   - `MYSQL_PORT`

Ces variables seront automatiquement utilisées par votre application.

## Étape 5 : Importer la Base de Données

### 5.1 Exporter votre base de données locale
Dans WAMP/phpMyAdmin :
1. Ouvrez phpMyAdmin
2. Sélectionnez la base de données `sgdi_mvp`
3. Cliquez sur "Exporter"
4. Sélectionnez "Rapide" → Format "SQL"
5. Cliquez sur "Exécuter" pour télécharger le fichier `.sql`

### 5.2 Importer dans Railway
Railway propose plusieurs options :

**Option A : Via Railway CLI (recommandé)**
1. Installez Railway CLI :
```bash
npm i -g @railway/cli
```

2. Connectez-vous :
```bash
railway login
```

3. Liez votre projet :
```bash
railway link
```

4. Importez votre base de données :
```bash
railway run mysql -h MYSQL_HOST -u MYSQL_USER -p < votre_export.sql
```

**Option B : Via MySQL Workbench ou HeidiSQL**
1. Récupérez les informations de connexion MySQL dans Railway (onglet "Variables")
2. Connectez-vous avec un client MySQL (MySQL Workbench, HeidiSQL, etc.)
3. Importez le fichier `.sql`

**Option C : Via le terminal Railway**
1. Dans Railway, cliquez sur votre service MySQL
2. Allez dans l'onglet "Data"
3. Cliquez sur "Connect" pour obtenir les informations de connexion
4. Utilisez un client MySQL pour importer

## Étape 6 : Configurer le Domaine

### 6.1 Générer un domaine Railway
1. Cliquez sur votre service web (pas MySQL)
2. Allez dans l'onglet "Settings"
3. Section "Domains" → Cliquez sur "Generate Domain"
4. Railway va créer une URL comme `sgdi-production.up.railway.app`

### 6.2 (Optionnel) Ajouter un domaine personnalisé
Si vous avez un nom de domaine :
1. Dans "Settings" → "Domains"
2. Cliquez sur "Custom Domain"
3. Entrez votre domaine (ex: `sgdi.votredomaine.com`)
4. Configurez les enregistrements DNS selon les instructions de Railway

## Étape 7 : Vérification du Déploiement

1. Attendez que le déploiement soit terminé (statut "Active")
2. Cliquez sur l'URL générée
3. Testez la connexion avec les comptes de démonstration

## Étape 8 : Configuration de l'Email (Important)

### 8.1 Configurer les variables d'email
1. Dans Railway, cliquez sur votre service web
2. Allez dans "Variables"
3. Ajoutez les variables suivantes (si elles n'existent pas déjà) :

```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=votre-email@gmail.com
SMTP_PASSWORD=votre-mot-de-passe-application
SMTP_FROM=noreply@sgdi.cm
```

**Note** : Pour Gmail, vous devez créer un "Mot de passe d'application" :
1. Google Account → Sécurité → Validation en deux étapes (activez-la)
2. Mots de passe des applications → Créer
3. Utilisez ce mot de passe dans `SMTP_PASSWORD`

### 8.2 Modifier le fichier de configuration email

Si votre projet a un fichier de configuration email (vérifiez dans `config/`), assurez-vous qu'il utilise les variables d'environnement :

```php
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
```

## Étape 9 : Tests Post-Déploiement

Testez les fonctionnalités suivantes :

- [ ] Connexion avec chaque rôle utilisateur
- [ ] Création d'un dossier
- [ ] Upload de documents
- [ ] Notifications email
- [ ] Circuit de visa
- [ ] Registre public

## Dépannage

### Problème : "Application Error"
1. Vérifiez les logs dans Railway (onglet "Deployments" → cliquez sur le déploiement)
2. Vérifiez que toutes les variables d'environnement sont configurées

### Problème : "Database Connection Failed"
1. Vérifiez que le service MySQL est actif
2. Vérifiez les variables d'environnement MySQL
3. Vérifiez que le fichier `config/database.php` utilise bien `getenv()`

### Problème : "Uploads ne fonctionnent pas"
1. Vérifiez les permissions dans le Dockerfile
2. Les fichiers uploadés sont stockés de manière éphémère sur Railway
3. Pour une solution permanente, utilisez un service de stockage externe (AWS S3, Cloudinary, etc.)

### Problème : "Emails ne partent pas"
1. Vérifiez les variables SMTP
2. Vérifiez que Gmail autorise l'accès (Mot de passe d'application)
3. Consultez les logs d'erreur

## Maintenance et Mises à Jour

Pour mettre à jour votre application après des modifications :

```bash
# Ajouter les modifications
git add .

# Créer un commit
git commit -m "Description des modifications"

# Pousser vers GitHub
git push origin main
```

Railway redéploiera automatiquement votre application !

## Coûts

- **GitHub** : Gratuit
- **Railway** :
  - 5$ de crédits gratuits/mois (~500 heures)
  - Suffisant pour 1-2 mois de présentation
  - Ensuite : ~5-10$/mois selon l'utilisation

## Support

- Railway Docs : https://docs.railway.app
- GitHub Docs : https://docs.github.com
- Pour des questions sur SGDI : consultez le README.md

---

**Prêt pour la présentation !** 🚀

Votre application sera accessible 24/7 avec une URL professionnelle.
