# Déploiement Rapide sur Railway.app

## Guide en 5 Étapes (15-20 minutes)

### ✅ Étape 1 : GitHub (5 min)
```bash
# Dans Git Bash, à la racine du projet :
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/VOTRE_USERNAME/sgdi-dppg.git
git push -u origin main
```

**Si erreur** : Créez d'abord le dépôt sur GitHub.com → New Repository

### ✅ Étape 2 : Railway.app (2 min)
1. Allez sur https://railway.app
2. "Login with GitHub"
3. "New Project" → "Deploy from GitHub repo"
4. Sélectionnez `sgdi-dppg`

### ✅ Étape 3 : Base de Données (2 min)
1. Dans votre projet Railway : "+ New"
2. "Database" → "Add MySQL"
3. Attendez la création (30 secondes)

### ✅ Étape 4 : Importer les Données (5 min)

**Sur votre PC** :
1. WAMP → phpMyAdmin
2. Exportez la base `sgdi_mvp` en SQL

**Option Rapide - Via Railway Web Interface** :
1. Railway → MySQL service → "Data" tab
2. Copiez les informations de connexion
3. Utilisez HeidiSQL ou MySQL Workbench pour vous connecter
4. Importez le fichier .sql

**OU via Railway CLI** :
```bash
npm i -g @railway/cli
railway login
railway link
# Puis utilisez les credentials pour importer avec MySQL
```

### ✅ Étape 5 : Générer l'URL (1 min)
1. Railway → Votre service web (pas MySQL)
2. "Settings" → "Domains" → "Generate Domain"
3. Cliquez sur l'URL → **Application en ligne !** 🎉

## Configuration Email (Optionnel - 3 min)

Railway → Votre service → "Variables" → Ajoutez :
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=votre-email@gmail.com
SMTP_PASSWORD=votre-mot-de-passe-application
```

Pour Gmail : Activez la validation en 2 étapes → Créez un "Mot de passe d'application"

## Comptes de Test

Une fois déployé, testez avec :
- **Admin** : admin@dppg.cm / Admin2024!
- **Chef Service** : chef.service@dppg.cm / Chef2024!
- **Billeteur** : billeteur@dppg.cm / Billeteur2024!

## Problèmes Courants

**❌ Erreur de connexion BD** → Vérifiez que MySQL est actif dans Railway

**❌ 404 ou page blanche** → Attendez 2-3 min (déploiement en cours)

**❌ "Application Error"** → Railway → Deployments → Logs (vérifier les erreurs)

## Mise à Jour de l'Application

Après toute modification :
```bash
git add .
git commit -m "Description"
git push
```

Railway redéploie automatiquement en 2-3 minutes !

## Coût

- **1-2 mois gratuits** avec les 5$ de crédits
- Ensuite : ~5-10$/mois
- Pas de carte bancaire requise au début

---

**Documentation complète** : `DEPLOIEMENT_RAILWAY.md`
