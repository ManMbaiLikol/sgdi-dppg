# 🔄 Synchronisation Base de Données Railway ↔ Local

Ce dossier contient les scripts pour synchroniser les données entre Railway (production) et votre environnement local (WAMP).

## 🎯 Pourquoi synchroniser?

**Problème**: Les utilisateurs testent sur Railway, les bugs sont signalés avec leurs données, mais vous développez en local avec d'autres données.

**Solution**: Importer la base de données de Railway en local pour reproduire exactement les mêmes conditions et débugger efficacement.

---

## 📥 Synchronisation Railway → Local

### Option 1: Script automatique Windows (RECOMMANDÉ)

```batch
sync\sync_railway_to_local.bat
```

Ce script fait TOUT automatiquement:
1. Exporte la base Railway
2. Crée un backup de votre base locale
3. Importe les données de Railway en local

### Option 2: PHP (multi-plateforme)

```bash
# 1. Exporter de Railway (nécessite Railway CLI + bash)
bash sync/export_railway_db.sh

# 2. Importer en local
php sync/import_to_local.php sync/backups/latest.sql
```

### Option 3: Linux/Mac

```bash
# Tout-en-un
bash sync/export_railway_db.sh && bash sync/import_to_local.sh
```

---

## ⚙️ Prérequis

### 1. Railway CLI

```bash
npm install -g @railway/cli
railway login
cd C:\wamp64\www\dppg-implantation
railway link
```

### 2. MySQL en ligne de commande (déjà dans WAMP)

Vérifiez que `mysql` est dans le PATH:
```bash
mysql --version
```

Si non, ajoutez au PATH Windows:
```
C:\wamp64\bin\mysql\mysql8.0.x\bin
```

### 3. Git Bash (pour scripts .sh sur Windows)

Déjà installé si vous avez Git for Windows.

---

## 📋 Processus détaillé

### Étape par étape:

#### 1. Export depuis Railway

```bash
bash sync/export_railway_db.sh
```

**Ce qu'il fait:**
- Se connecte à Railway via CLI
- Exporte toute la base de données (structure + données)
- Sauvegarde dans `sync/backups/railway_backup_YYYYMMDD_HHMMSS.sql`
- Crée un lien symbolique `sync/backups/latest.sql`

**Durée**: 10-30 secondes selon la taille de la base

#### 2. Import en local

```bash
php sync/import_to_local.php sync/backups/latest.sql
```

**Ce qu'il fait:**
- Crée un backup de votre base locale actuelle
- Supprime la base locale
- Recrée une base vide
- Importe le dump de Railway
- Affiche les statistiques

**Durée**: 5-15 secondes

---

## 🔍 Vérifications post-import

Le script affiche automatiquement:

```
Statistiques de la base importée:
   Tables: 25
   Utilisateurs: 46
   Dossiers: 14
   Rôles users: enum('admin','chef_service',...)
   Rôles commission: enum('chef_service','chef_commission',...)
```

**Vérifiez que:**
- ✅ Le nombre d'utilisateurs correspond à la production
- ✅ Le nombre de dossiers correspond à la production
- ✅ Les ENUMs sont corrects (incluent tous les rôles)

---

## 📤 Synchronisation Local → Railway (Migrations uniquement)

**IMPORTANT**: Ne JAMAIS pousser les données de test en production!

On pousse uniquement:
- ✅ Les **migrations** (changements de structure)
- ✅ Le **code**
- ❌ JAMAIS les données

### Pousser des migrations sur Railway:

1. **Testez la migration en local** avec les données importées:
   ```bash
   php database/migrations/run_migration_00X.php
   ```

2. **Si ça fonctionne, commitez et pushez**:
   ```bash
   git add database/migrations/
   git commit -m "Migration: Description"
   git push origin main
   ```

3. **Exécutez la migration sur Railway**:
   ```bash
   # Via URL
   https://votre-app.railway.app/database/migrations/run_all_migrations.php?token=sgdi_migration_2025

   # Ou via CLI
   railway run php database/migrations/run_migration_00X.php
   ```

---

## 🗂️ Structure des fichiers

```
sync/
├── README.md                      # Ce fichier
├── export_railway_db.sh           # Export Railway (bash)
├── import_to_local.sh             # Import local (bash)
├── import_to_local.php            # Import local (PHP)
├── sync_railway_to_local.bat      # Script Windows tout-en-un
└── backups/                       # Dossier des backups (ignoré par git)
    ├── railway_backup_20251024_143000.sql
    ├── local_backup_before_import_20251024_143100.sql
    └── latest.sql → lien vers le dernier backup
```

---

## ⚠️ Avertissements

### Données sensibles

Les backups contiennent:
- ✅ Structure de la base
- ✅ Données utilisateurs (noms, emails, etc.)
- ✅ Données des dossiers
- ❌ PAS les mots de passe en clair (hashés)

**Le dossier `sync/backups/` est dans `.gitignore` pour éviter de commiter les données.**

### Taille des backups

- Base vide: ~100 KB
- Base avec 50 utilisateurs et 20 dossiers: ~200-500 KB
- Base avec 500 utilisateurs et 200 dossiers: ~2-5 MB

**Nettoyez régulièrement** les vieux backups:
```bash
# Garder seulement les 5 derniers backups
cd sync/backups
ls -t | tail -n +6 | xargs rm
```

---

## 🔧 Dépannage

### Erreur: Railway CLI non installé

```bash
npm install -g @railway/cli
railway login
```

### Erreur: Projet Railway non lié

```bash
cd C:\wamp64\www\dppg-implantation
railway link
```

Sélectionnez: `genuine-determination` → `sgdi-dppg`

### Erreur: mysql command not found

**Solution Windows:**
1. Panneau de configuration → Système → Variables d'environnement
2. Variable PATH → Modifier
3. Ajouter: `C:\wamp64\bin\mysql\mysql8.0.x\bin`
4. Redémarrer le terminal

### Erreur: Access denied for user

Vérifiez `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sgdi_mvp');
define('DB_USER', 'root');
define('DB_PASS', ''); // Souvent vide sur WAMP
```

### Import échoue: base trop grosse

Pour les très grosses bases (>10 MB):
```bash
# Augmenter les limites PHP
php -d memory_limit=512M -d max_execution_time=300 sync/import_to_local.php
```

---

## 📊 Cas d'usage typiques

### Cas 1: Bug signalé sur Railway

```bash
# 1. Importer les données de Railway
sync\sync_railway_to_local.bat

# 2. Reproduire le bug en local avec les vraies données
# 3. Corriger le code
# 4. Tester que ça fonctionne
# 5. Pusher le fix sur GitHub/Railway
git add .
git commit -m "Fix: Description"
git push origin main
```

### Cas 2: Tester une migration avec données réelles

```bash
# 1. Importer les données de Railway
php sync/import_to_local.php

# 2. Créer la migration
# database/migrations/003_nouvelle_migration.sql

# 3. Tester avec les vraies données
php database/migrations/run_migration_003.php

# 4. Si OK, pusher
git add database/migrations/003_nouvelle_migration.sql
git commit -m "Migration: Description"
git push origin main

# 5. Exécuter sur Railway
railway run php database/migrations/run_migration_003.php
```

### Cas 3: Réinitialiser la base locale

```bash
# Importer une base fraîche depuis Railway
sync\sync_railway_to_local.bat
```

---

## 🔐 Sécurité

- ✅ Les backups sont locaux uniquement (pas de cloud)
- ✅ Le dossier `sync/backups/` est dans `.gitignore`
- ✅ Les mots de passe sont hashés (bcrypt)
- ⚠️ Ne partagez JAMAIS les fichiers .sql (contiennent des données sensibles)

---

## 📅 Maintenance

### Nettoyage automatique des vieux backups

Ajoutez à votre `.gitignore`:
```
sync/backups/*.sql
!sync/backups/.gitkeep
```

### Automatisation (optionnel)

Pour synchroniser automatiquement chaque jour:

**Windows Task Scheduler:**
```batch
Créer une tâche programmée qui exécute:
C:\wamp64\www\dppg-implantation\sync\sync_railway_to_local.bat
```

**Linux/Mac cron:**
```bash
# Chaque jour à 2h du matin
0 2 * * * cd /path/to/project && bash sync/export_railway_db.sh
```

---

## 📞 Support

En cas de problème:
1. Vérifiez les prérequis ci-dessus
2. Consultez la section Dépannage
3. Vérifiez les logs dans `C:\wamp64\logs\`
4. Contactez l'administrateur système

---

**Date de création**: 24 octobre 2025
**Dernière mise à jour**: 24 octobre 2025
**Auteur**: Claude Code
