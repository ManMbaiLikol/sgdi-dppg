# Guide Complet: Synchronisation Railway ↔ Local

## 🎯 Objectif

Importer les données de production (Railway) vers votre environnement local (WAMP) pour débugger avec les vraies données utilisateurs.

---

## ⚠️ Problème Identifié

**Railway utilise un réseau interne**: La base MySQL sur Railway (`mysql.railway.internal`) n'est accessible que depuis les conteneurs Railway, pas depuis votre machine locale. C'est pourquoi les commandes `railway run` avec mysqldump ne fonctionnent pas directement.

---

## 🔄 Méthodes de Synchronisation

### **Méthode 1: Export HTTP (RECOMMANDÉ)**

Cette méthode déploie temporairement un endpoint PHP sur Railway qui exporte la base et la rend téléchargeable.

#### Avantages:
- ✅ Fonctionne sur Windows sans bash
- ✅ Pas besoin de mysqldump
- ✅ Export complet (structure + données)

#### Étapes:

```bash
php sync/sync_via_http.php
```

Le script va:
1. Créer et déployer `export_db_temp.php` sur Railway
2. Vous demander l'URL de votre app Railway
3. Télécharger le dump SQL depuis Railway
4. Importer en local
5. Supprimer le fichier temporaire de Railway

#### URL Railway:
Trouvez votre URL dans le dashboard Railway:
- railway.app → Projet → Service sgdi-dppg → Settings → Public Networking
- Exemple: `https://sgdi-dppg-production.up.railway.app`

---

### **Méthode 2: Via Railway Dashboard (Manuelle)**

Si Railway fournit une interface de backup:

1. Allez sur railway.app
2. Projet → Services → MySQL (ou Database)
3. Cherchez une option "Backup" ou "Export"
4. Téléchargez le fichier SQL
5. Importez localement:
   ```bash
   php sync/import_to_local.php chemin/vers/backup.sql
   ```

---

### **Méthode 3: Railway Connect (Avancé)**

Utilise la commande `railway connect` pour accéder directement à MySQL:

**⚠️ Nécessite**: MySQL client installé localement

```bash
# Pas encore implémenté - En développement
railway connect [database-service]
```

---

## 📋 Diagnostic

Avant de synchroniser, vérifiez les prérequis:

```bash
php sync/diagnostic.php
```

Le script vérifie:
- ✅ Railway CLI installé et authentifié
- ✅ Projet lié
- ✅ Variables d'environnement accessibles
- ✅ MySQL local fonctionnel
- ✅ Configuration database.php

---

## 🔧 Import Seul (Si vous avez déjà un dump SQL)

Si vous avez déjà téléchargé ou obtenu un fichier SQL:

```bash
php sync/import_to_local.php fichier.sql
```

Le script:
1. Crée un backup de sécurité de votre base locale
2. Supprime et recrée la base
3. Importe le dump Railway
4. Affiche les statistiques

---

## 📊 Vérification Post-Import

Après import, vérifiez dans phpMyAdmin:

1. http://localhost/phpmyadmin
2. Base `sgdi_mvp`
3. Tables: `users`, `dossiers`, `commissions`
4. Vérifiez le nombre de lignes correspond à Railway

---

## 🚨 Sécurité

### Fichier `export_db_temp.php`:
- ⚠️ Contient un endpoint accessible via HTTP
- ⚠️ Protégé par un token secret
- ⚠️ **DOIT être supprimé immédiatement après usage**
- ✅ Le script `sync_via_http.php` le supprime automatiquement

### Backups locaux:
- ✅ Stockés dans `sync/backups/` (ignoré par git)
- ✅ Ne contiennent PAS de mots de passe en clair (hashés)
- ⚠️ Contiennent des données sensibles (emails, noms)
- ❌ Ne jamais commiter dans git

---

## 🐛 Dépannage

### Erreur: "MYSQL_HOST not found"

```bash
# Vérifiez les variables Railway:
railway run php sync/check_railway_env.php
```

### Erreur: "mysql command not found"

**Windows**: Ajoutez MySQL au PATH
```
C:\wamp64\bin\mysql\mysql8.0.x\bin
```

### Erreur: "Access denied"

Vérifiez `config/database.php`:
```php
define('DB_USER', 'root');
define('DB_PASS', ''); // Souvent vide sur WAMP
```

### Export HTTP échoue

1. Vérifiez l'URL Railway (doit commencer par https://)
2. Vérifiez que le fichier est bien déployé sur Railway
3. Attendez 30-60 secondes après le push (déploiement)
4. Vérifiez les logs Railway:
   ```bash
   railway logs
   ```

---

## 📁 Structure des Fichiers

```
sync/
├── GUIDE_SYNCHRONISATION.md        # Ce fichier
├── diagnostic.php                  # Vérification prérequis
├── check_railway_env.php           # Liste variables Railway
├── sync_via_http.php              # ⭐ Script principal (HTTP)
├── import_to_local.php            # Import uniquement
├── railway_export_pdo.php         # Export PDO (ne fonctionne pas en local)
└── backups/                       # Backups SQL (ignoré par git)
    ├── railway_backup_*.sql
    └── local_backup_*.sql
```

**Fichier temporaire** (à supprimer après usage):
```
export_db_temp.php                  # Endpoint HTTP pour export
```

---

## 🎯 Workflow Recommandé

### 1. Première synchronisation:

```bash
# 1. Vérifier les prérequis
php sync/diagnostic.php

# 2. Synchroniser
php sync/sync_via_http.php
```

### 2. Bug signalé sur Railway:

```bash
# 1. Synchroniser les données
php sync/sync_via_http.php

# 2. Reproduire le bug en local avec les vraies données
# 3. Corriger le code
# 4. Tester localement

# 5. Pusher la correction
git add .
git commit -m "Fix: Description du bug"
git push origin main
```

### 3. Tester une migration:

```bash
# 1. Synchroniser
php sync/sync_via_http.php

# 2. Appliquer la migration en local
php database/migrations/run_migration_00X.php

# 3. Vérifier que tout fonctionne

# 4. Si OK, appliquer sur Railway
railway run php database/migrations/run_migration_00X.php
```

---

## 💡 Cas d'Usage

### Débugger l'erreur de constitution commission:

```bash
# 1. Importer les données Railway
php sync/sync_via_http.php

# 2. Aller sur http://localhost/dppg-implantation/
# 3. Se connecter comme chef_service
# 4. Essayer de constituer une commission
# 5. Reproduire l'erreur EXACTEMENT comme sur Railway
# 6. Corriger le code
# 7. Pusher la correction
```

---

## ⚡ Alternatives Futures

### Railway API (En investigation):
Railway pourrait offrir une API pour exporter la base directement.

### Railway CLI Database Commands:
Le CLI Railway évolue - de nouvelles commandes database pourraient apparaître.

### MySQL Public Connection:
Configurer une connexion MySQL publique sur Railway (déconseillé pour la sécurité).

---

## 📞 Support

En cas de problème:
1. Lancez le diagnostic: `php sync/diagnostic.php`
2. Vérifiez ce guide
3. Consultez les logs Railway: `railway logs`
4. Vérifiez la documentation Railway: railway.app/docs

---

**Date**: 24 octobre 2025
**Auteur**: Claude Code
**Version**: 2.0 - Méthode HTTP
