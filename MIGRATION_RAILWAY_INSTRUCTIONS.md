# 🚀 Instructions pour exécuter la migration sur Railway

## ⚠️ IMPORTANT : Migration requise !

Vous devez exécuter la migration sur la base de données Railway pour corriger l'erreur :
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'annee_mise_en_service' in 'field list'
```

---

## 📋 Méthode 1 : Via navigateur web (RECOMMANDÉE)

### Étape 1 : Trouver votre URL Railway

1. Allez sur https://railway.app/dashboard
2. Cliquez sur votre projet "sgdi-dppg"
3. Cliquez sur votre service
4. Cherchez l'URL de déploiement (ex: `sgdi-dppg-production.up.railway.app`)

### Étape 2 : Exécuter la migration

Ouvrez cette URL dans votre navigateur :
```
https://VOTRE-URL-RAILWAY.railway.app/database/migrations/run_migration_web.php?token=sgdi-migration-2025
```

**Exemple :**
```
https://sgdi-dppg-production.up.railway.app/database/migrations/run_migration_web.php?token=sgdi-migration-2025
```

### Étape 3 : Vérifier l'exécution

Vous verrez une interface colorée qui affiche :
- ✅ Les colonnes ajoutées avec succès
- ○ Les colonnes déjà présentes
- ✗ Les erreurs éventuelles

**Résultat attendu :**
```
Migration terminée !
✓ Ajoutés : 0-12
○ Déjà présents : 0-12
✗ Erreurs : 0
```

### Étape 4 : Sécurité (IMPORTANT)

Après l'exécution réussie, **supprimez le script** pour sécurité :

**Option A : Via Git (recommandé)**
```bash
cd C:\wamp64\www\dppg-implantation
git rm database/migrations/run_migration_web.php
git commit -m "Security: Remove migration web script after execution"
git push origin main
```

**Option B : Via Railway CLI**
```bash
railway run rm database/migrations/run_migration_web.php
```

---

## 📋 Méthode 2 : Via Railway CLI (Alternative)

### Prérequis
Railway CLI doit être installé et vous devez être connecté.

### Étape 1 : Connexion
```bash
cd C:\wamp64\www\dppg-implantation
railway login
```
Cela ouvrira votre navigateur pour authentification.

### Étape 2 : Lier le projet (si nécessaire)
```bash
railway link
```
Sélectionnez votre projet dans la liste.

### Étape 3 : Exécuter la migration
```bash
railway run php database/migrations/run_fix_simple.php
```

### Étape 4 : Vérifier
```bash
railway run php database/migrations/verification_finale.php
```

---

## 📋 Méthode 3 : Via Shell Railway (Web)

### Étape 1 : Ouvrir le shell
1. Allez sur https://railway.app/dashboard
2. Cliquez sur votre projet
3. Cliquez sur votre service
4. Onglet "Settings" → "Service Settings"
5. Cherchez "Shell" ou "Terminal"

### Étape 2 : Exécuter
```bash
php database/migrations/run_fix_simple.php
```

---

## 🔍 Ce que la migration fait

La migration va ajouter ces **colonnes manquantes** à la table `dossiers` :

| Colonne | Type | Description |
|---------|------|-------------|
| `departement` | VARCHAR(100) | Département (ex: Mfoundi) |
| `arrondissement` | VARCHAR(100) | Arrondissement |
| `quartier` | VARCHAR(100) | Quartier |
| `zone_type` | ENUM | urbaine/rurale |
| `lieu_dit` | VARCHAR(200) | Lieu-dit (ex: Dabbadji) |
| `adresse_precise` | TEXT | Adresse complète |
| `annee_mise_en_service` | YEAR | Année de mise en service |
| `operateur_gaz` | VARCHAR(200) | Pour centre emplisseur |
| `entreprise_constructrice` | VARCHAR(200) | Pour centre emplisseur |
| `capacite_enfutage` | VARCHAR(100) | Pour centre emplisseur |

**Plus :**
- Ajout de `centre_emplisseur` dans l'ENUM `type_infrastructure`
- Ajout de `remodelage` dans l'ENUM `sous_type`

---

## ✅ Vérification post-migration

Après avoir exécuté la migration, testez :

1. **Connectez-vous à votre application Railway**
2. **Allez dans un dossier historique**
3. **Cliquez sur "Modifier"**
4. **Faites une modification simple** (ex: changez le département)
5. **Enregistrez**

Si aucune erreur n'apparaît → ✅ Migration réussie !

---

## 🆘 En cas de problème

### Erreur : "Token invalide"
➡️ Vérifiez que vous avez bien mis `?token=sgdi-migration-2025` dans l'URL

### Erreur : "404 Not Found"
➡️ Attendez 2-3 minutes que Railway finisse le déploiement

### Erreur : "Database connection failed"
➡️ Vérifiez que les variables d'environnement de la base de données sont correctes dans Railway

### Erreur : "Column already exists"
➡️ Pas grave ! Cela signifie que la colonne a déjà été ajoutée. Continuez.

---

## 📞 Support

Si vous rencontrez des problèmes, vérifiez les logs Railway :
1. Railway Dashboard → Votre projet
2. Onglet "Deployments"
3. Cliquez sur le dernier déploiement
4. Consultez les logs

---

**Date de création :** 2025-11-24
**Commits concernés :**
- `5d1e2f1` - Fix: Correction des erreurs de modification des dossiers historiques
- `f8f6393` - Add: Script web pour exécuter la migration sur Railway
- `a92db22` - Feat: Simplification de la carte du registre public
