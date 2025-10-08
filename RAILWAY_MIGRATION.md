# Migration de la base de données Railway

## Méthode recommandée : Via l'interface web Railway

### Étape 1 : Récupérer les credentials

1. Allez sur https://railway.app
2. Sélectionnez votre projet **sgdi-dppg**
3. Cliquez sur le service **MySQL**
4. Onglet **"Variables"** ou **"Connect"**
5. Notez les informations :
   - `MYSQL_HOST` (ex: containers-us-west-xxx.railway.app)
   - `MYSQL_PORT` (ex: 6543)
   - `MYSQL_USER` (ex: root)
   - `MYSQL_PASSWORD` (votre mot de passe)
   - `MYSQL_DATABASE` (ex: railway ou sgdi_mvp)

### Étape 2 : Se connecter avec MySQL Workbench (ou autre client)

**Option 1 : MySQL Workbench** (Interface graphique)
1. Télécharger : https://dev.mysql.com/downloads/workbench/
2. Créer une nouvelle connexion :
   - Connection Name: Railway SGDI
   - Hostname: `MYSQL_HOST`
   - Port: `MYSQL_PORT`
   - Username: `MYSQL_USER`
   - Password: `MYSQL_PASSWORD`
3. Tester la connexion
4. Se connecter
5. Ouvrir le fichier `database/migration_workflow_2025_01_08.sql`
6. Exécuter le script (bouton ⚡ ou Ctrl+Shift+Enter)

**Option 2 : DBeaver** (Gratuit, multi-plateforme)
1. Télécharger : https://dbeaver.io/download/
2. Nouvelle connexion → MySQL
3. Entrer les credentials Railway
4. Tester et connecter
5. Ouvrir et exécuter le script SQL

**Option 3 : Via ligne de commande Windows**

Si vous avez MySQL installé localement (avec WAMP) :

```bash
# Remplacer les valeurs par vos credentials Railway
mysql -h MYSQL_HOST -P MYSQL_PORT -u MYSQL_USER -p MYSQL_DATABASE < database\migration_workflow_2025_01_08.sql
```

Exemple :
```bash
mysql -h containers-us-west-123.railway.app -P 6543 -u root -p railway < database\migration_workflow_2025_01_08.sql
```
Puis entrer le mot de passe quand demandé.

### Étape 3 : Vérifier la migration

Après l'exécution du script, exécutez ces requêtes pour vérifier :

```sql
-- Vérifier les statuts disponibles
SHOW COLUMNS FROM dossiers LIKE 'statut';

-- Vérifier la colonne motif_fermeture
SHOW COLUMNS FROM dossiers LIKE 'motif_fermeture';

-- Vérifier la table historique
SHOW COLUMNS FROM historique WHERE Field IN ('ancien_statut', 'nouveau_statut');

-- Vérifier les dossiers existants
SELECT statut, COUNT(*) as count FROM dossiers GROUP BY statut;
```

## Alternative : Via Railway Dashboard (Console SQL)

Certains projets Railway ont une console SQL intégrée :

1. Railway Dashboard → MySQL Service
2. Onglet **"Query"** ou **"Data"** (selon la version)
3. Copier-coller le contenu du fichier `database/migration_workflow_2025_01_08.sql`
4. Exécuter

## En cas de problème

Si vous rencontrez des erreurs :

1. **"Table doesn't exist"** : Vérifiez que vous êtes connecté à la bonne base de données
2. **"Column already exists"** : Normal, le script gère déjà ce cas
3. **"Access denied"** : Vérifiez les credentials Railway

## Notes importantes

⚠️ **Avant d'exécuter le script :**
- Faites un backup de la base de données Railway (via l'interface Railway)
- Le script est idempotent (peut être exécuté plusieurs fois sans problème)

⚠️ **Après la migration :**
- Le déploiement Railway devrait redémarrer automatiquement
- Vérifiez que l'application fonctionne correctement
- Testez la création d'un nouveau dossier
