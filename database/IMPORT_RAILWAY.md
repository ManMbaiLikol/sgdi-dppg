# Guide d'Import de la Base de Données sur Railway

## Option 1: Export/Import via phpMyAdmin (Recommandé)

### Étape 1: Exporter la base de données locale

1. **Ouvrez phpMyAdmin** (http://localhost/phpmyadmin)
2. **Sélectionnez** la base de données `sgdi_mvp`
3. Cliquez sur l'onglet **"Exporter"**
4. Choisissez la méthode **"Personnalisée"**
5. **Configuration recommandée**:
   - Format: **SQL**
   - Tables: **Sélectionner tout**
   - Options de création:
     - ✅ Cocher "DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER"
     - ✅ Cocher "CREATE TABLE"
     - ✅ Cocher "IF NOT EXISTS"
   - Options de données:
     - ✅ Cocher "INSERT"
     - Type d'insertion: **INSERT**
     - ✅ Cocher "Utiliser des noms complets pour les INSERT"
6. Cliquez sur **"Exécuter"**
7. **Sauvegardez** le fichier (exemple: `sgdi_mvp_export.sql`)

### Étape 2: Importer sur Railway via MySQL Client

#### Option A: Via HeidiSQL (Windows - Recommandé)

1. **Télécharger HeidiSQL**: https://www.heidisql.com/download.php
2. **Installer et ouvrir** HeidiSQL
3. **Créer une nouvelle connexion**:
   - Cliquer sur "Nouveau"
   - Type réseau: **MySQL (TCP/IP)**

4. **Récupérer les informations de Railway**:
   - Aller sur https://railway.app
   - Ouvrir votre projet SGDI
   - Cliquer sur le service **MySQL**
   - Onglet **"Connect"**
   - Copier les informations:
     ```
     Host: <MYSQL_HOST>
     Port: <MYSQL_PORT>
     User: <MYSQL_USER>
     Password: <MYSQL_PASSWORD>
     Database: <MYSQL_DATABASE>
     ```

5. **Configurer HeidiSQL**:
   - Hostname/IP: `<MYSQL_HOST>` (ex: monorail.proxy.rlwy.net)
   - User: `<MYSQL_USER>` (ex: root)
   - Password: `<MYSQL_PASSWORD>`
   - Port: `<MYSQL_PORT>` (ex: 12345)

6. **Tester la connexion** → Cliquer sur "Ouvrir"

7. **Importer le fichier SQL**:
   - Clic droit sur la base de données
   - "Charger un fichier SQL..."
   - Sélectionner votre fichier `sgdi_mvp_export.sql`
   - Cliquer sur "Exécuter"

#### Option B: Via MySQL Workbench

1. **Télécharger MySQL Workbench**: https://dev.mysql.com/downloads/workbench/
2. **Créer une connexion**:
   - Cliquer sur "+" à côté de "MySQL Connections"
   - Connection Name: `Railway SGDI`
   - Hostname: `<MYSQL_HOST>`
   - Port: `<MYSQL_PORT>`
   - Username: `<MYSQL_USER>`
   - Password: Cliquer sur "Store in Vault..." → entrer `<MYSQL_PASSWORD>`
3. **Test Connection** → **OK**
4. **Double-cliquer** sur la connexion
5. **Importer**:
   - Menu: Server → Data Import
   - Import from Self-Contained File
   - Sélectionner votre fichier SQL
   - Default Target Schema: sélectionner la base de données Railway
   - Start Import

## Option 2: Via Railway CLI (Pour utilisateurs avancés)

### Installation Railway CLI

```bash
# Installer Railway CLI
npm install -g @railway/cli

# Se connecter
railway login

# Lier le projet
railway link
```

### Import de la base de données

```bash
# Méthode 1: Import direct
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE < database/sgdi_mvp_export.sql

# Méthode 2: Via variable d'environnement
railway run bash -c 'mysql -u $MYSQLUSER -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE < database/sgdi_mvp_export.sql'
```

**Note**: Remplacez les variables par les valeurs réelles de Railway

## Option 3: Export/Import depuis WAMP en ligne de commande

### Export depuis WAMP

```bash
# Ouvrir Git Bash dans C:\wamp64\bin\mysql\mysql8.0.x\bin
# Remplacer x par votre version

cd /c/wamp64/bin/mysql/mysql8.0.x/bin

# Exporter
./mysqldump.exe -u root -p sgdi_mvp > /c/wamp64/www/dppg-implantation/database/sgdi_mvp_export.sql
```

### Import vers Railway

Après l'export, utiliser HeidiSQL ou MySQL Workbench (Option A ou B ci-dessus)

## Vérification Post-Import

### 1. Vérifier les tables

```sql
SHOW TABLES;
```

Vous devriez voir toutes vos tables:
- users
- dossiers
- commissions
- documents
- paiements
- inspections
- visas
- decisions
- notifications
- logs_activite
- etc.

### 2. Vérifier les données

```sql
-- Compter les utilisateurs
SELECT COUNT(*) FROM users;

-- Compter les dossiers
SELECT COUNT(*) FROM dossiers;

-- Vérifier les rôles
SELECT role, COUNT(*) FROM users GROUP BY role;
```

### 3. Tester l'application

1. Aller sur votre URL Railway (ex: https://sgdi-production.up.railway.app)
2. Se connecter avec les comptes de test
3. Vérifier que les données sont présentes

## Dépannage

### Erreur: "Access denied"
- Vérifier les identifiants Railway (Host, Port, User, Password)
- S'assurer d'utiliser le bon port (généralement différent de 3306)

### Erreur: "Unknown database"
- Créer la base de données d'abord sur Railway
- Ou utiliser la base créée automatiquement par Railway

### Erreur: "Table already exists"
- Dans votre export, assurez-vous d'avoir coché "DROP TABLE" en première option
- Ou supprimer manuellement les tables existantes avant l'import

### Import très lent
- C'est normal pour de grandes bases
- Patience: peut prendre 5-15 minutes selon la taille

## Automatisation Future

Pour les prochaines mises à jour, vous pouvez:

1. **Créer des scripts de migration** dans `database/migrations/`
2. **Utiliser un système de versioning** de base de données
3. **Automatiser via Railway CLI** dans un script bash

## Sauvegarde Régulière

**Important**: Exportez régulièrement votre base Railway:

### Via HeidiSQL:
1. Connexion à Railway
2. Clic droit sur base → "Exporter la base de données en SQL"
3. Sauvegarder localement

### Via Railway CLI:
```bash
railway run mysqldump -u $MYSQLUSER -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE > backup_$(date +%Y%m%d).sql
```

---

**Votre base de données est maintenant synchronisée avec Railway!** 🎉
