# 🚀 Synchronisation Base de Données avec Railway

## 🎯 Objectif
Ce guide vous aide à synchroniser votre base de données locale avec Railway en 3 étapes simples.

## 📋 Prérequis
- WAMP/MySQL local avec la base `sgdi_mvp`
- Compte Railway avec le projet SGDI déployé
- HeidiSQL ou MySQL Workbench installé

## ⚡ Méthode Rapide (Recommandée)

### Étape 1: Export Automatique

**Windows:**
```bash
# Double-cliquer sur:
database/export_for_railway.bat
```

**Linux/Mac:**
```bash
# Donner les permissions d'exécution
chmod +x database/export_for_railway.sh

# Exécuter
./database/export_for_railway.sh
```

➡️ **Résultat**: Fichier créé `database/sgdi_mvp_railway_export.sql`

### Étape 2: Récupérer les Identifiants Railway

1. Aller sur https://railway.app
2. Ouvrir votre projet **SGDI**
3. Cliquer sur le service **MySQL** (pas le service web)
4. Onglet **"Connect"** ou **"Variables"**
5. Noter les informations:
   - `MYSQLHOST` (ex: monorail.proxy.rlwy.net)
   - `MYSQLPORT` (ex: 12345)
   - `MYSQLUSER` (ex: root)
   - `MYSQLPASSWORD` (ex: xyz123...)
   - `MYSQLDATABASE` (ex: railway)

### Étape 3: Importer dans Railway

#### Option A: HeidiSQL (Windows - Le plus simple)

1. **Télécharger**: https://www.heidisql.com/download.php
2. **Installer et ouvrir** HeidiSQL
3. **Nouvelle connexion**:
   - Cliquer sur "Nouveau" → Renommer "Railway SGDI"
   - Type réseau: **MySQL (TCP/IP)**
   - Hostname/IP: Coller `MYSQLHOST`
   - Utilisateur: Coller `MYSQLUSER`
   - Mot de passe: Coller `MYSQLPASSWORD`
   - Port: Coller `MYSQLPORT`
   - Base de données: Coller `MYSQLDATABASE`
4. **Tester** → **Ouvrir**
5. **Importer**:
   - Fichier → "Charger un fichier SQL..."
   - Sélectionner `database/sgdi_mvp_railway_export.sql`
   - Cliquer sur **"Exécuter"** (icône ▶)
   - Patienter (1-5 minutes)

#### Option B: MySQL Workbench

1. **Télécharger**: https://dev.mysql.com/downloads/workbench/
2. Créer connexion avec les identifiants Railway
3. Server → Data Import → Import from Self-Contained File
4. Sélectionner `database/sgdi_mvp_railway_export.sql`
5. Start Import

## ✅ Vérification

### 1. Dans HeidiSQL/MySQL Workbench

```sql
-- Compter les utilisateurs
SELECT COUNT(*) FROM users;

-- Compter les dossiers
SELECT COUNT(*) FROM dossiers;

-- Voir les rôles
SELECT role, COUNT(*) FROM users GROUP BY role;
```

### 2. Sur Railway Web

1. Aller sur votre URL Railway (ex: https://sgdi-production.up.railway.app)
2. Se connecter avec:
   - **Admin**: admin@sgdi.cm / Admin@2024
   - **Chef Service**: chef.service@minee.cm / Chef@2024
3. Vérifier que les dossiers et données sont présents

## 📚 Documentation Complète

Pour plus de détails, méthodes alternatives et dépannage:
👉 **Voir:** `database/IMPORT_RAILWAY.md`

## 🔄 Mises à Jour Futures

Après chaque modification locale importante:

1. **Exporter**: Relancer `export_for_railway.bat` (ou `.sh`)
2. **Importer**: Réimporter le nouveau fichier SQL dans Railway

## ⚠️ Important

- **Sauvegarde**: Avant d'importer, Railway écrasera les données existantes
- **Téléchargements**: Les fichiers uploadés localement ne sont PAS inclus dans l'export SQL
- **Uploads Railway**: Pensez à configurer un stockage externe (AWS S3, Cloudinary) pour la production

## 🆘 Dépannage Rapide

| Problème | Solution |
|----------|----------|
| "Access denied" | Vérifier Host, Port, User, Password de Railway |
| "Unknown database" | Utiliser le nom exact de `MYSQLDATABASE` |
| "Table already exists" | Export doit avoir option "DROP TABLE" activée |
| Import très lent | Normal pour grandes bases (patience) |

## 📞 Support

- Guide complet: `database/IMPORT_RAILWAY.md`
- Déploiement Railway: `DEPLOIEMENT_RAILWAY.md`
- Issues: Créer une issue sur GitHub

---

**🎉 Base de données synchronisée avec Railway!**
