# 🚀 Installation Rapide - Système de Permissions

## ⚡ Installation en 3 minutes

### Méthode 1 : Via phpMyAdmin (RECOMMANDÉE)

1. **Ouvrir phpMyAdmin**
   ```
   http://localhost/phpmyadmin
   ```

2. **Sélectionner la base de données**
   - Cliquer sur `sgdi_db` (ou `sgdi_mvp`) dans le panneau gauche

3. **Exécuter le script SQL**
   - Cliquer sur l'onglet **"SQL"** en haut
   - Copier-coller le contenu du fichier :
     ```
     database/install_permissions.sql
     ```
   - Cliquer sur **"Exécuter"**

4. **Vérifier l'installation**
   - Vous devriez voir un message de succès
   - La page affiche :
     - "Permissions créées : ~70"
     - Liste des modules avec leur nombre de permissions

5. **C'est terminé !**
   - Aller sur : `http://localhost/dppg-implantation/modules/permissions/index.php`

---

### Méthode 2 : Via la page d'installation PHP

1. **Accéder à la page d'installation**
   ```
   http://localhost/dppg-implantation/modules/permissions/install.php
   ```

2. **Vérifier les résultats**
   - ✅ Table 'permissions' créée
   - ✅ Table 'user_permissions' créée
   - ✅ ~70 permissions insérées

3. **Accéder à la gestion**
   - Cliquer sur "Accéder à la gestion des permissions"

---

### Méthode 3 : En ligne de commande MySQL

```bash
# Depuis le terminal Windows (avec MySQL dans PATH)
cd C:\wamp64\www\dppg-implantation

# Exécuter le script
mysql -uroot -p sgdi_db < database\install_permissions.sql

# Ou si vous utilisez un mot de passe root
mysql -uroot -proot sgdi_db < database\install_permissions.sql
```

---

## ✅ Vérification de l'installation

### Via phpMyAdmin

1. Sélectionner la base `sgdi_db`
2. Vérifier que ces tables existent :
   - ✓ `permissions` (doit contenir ~70 lignes)
   - ✓ `user_permissions` (vide au début)

### Via l'interface web

1. Se connecter en tant qu'**Admin**
2. Aller dans **Administration > Permissions**
3. Vous devriez voir :
   - Liste de tous les utilisateurs
   - Statistiques : "70 permissions disponibles"

---

## 🔧 Dépannage

### Erreur : "Table 'sgdi_db.permissions' doesn't exist"

**Solution** : Installer les tables via phpMyAdmin (Méthode 1)

### Erreur : "Base table or view not found: user_permissions"

**Solution** : Le script SQL n'a pas été exécuté correctement
1. Supprimer les tables existantes si nécessaire :
   ```sql
   DROP TABLE IF EXISTS user_permissions;
   DROP TABLE IF EXISTS permissions;
   ```
2. Réexécuter le script complet `install_permissions.sql`

### Erreur : "Cannot add or update a child row: foreign key constraint"

**Solution** : La table `users` n'existe pas
1. Vérifier que la base de données principale est installée
2. Vérifier que la table `users` existe avec des données

### La page install.php redirige vers la connexion

**Solution** : Vous n'êtes pas connecté en tant qu'admin
1. Se connecter d'abord
2. Vérifier que le rôle est bien `admin`

---

## 📊 Résultat attendu

Après installation, vous devriez avoir :

```sql
-- Table permissions
+----+-------------------+------------+---------------------------+------------------+
| id | code              | module     | nom                       | description      |
+----+-------------------+------------+---------------------------+------------------+
|  1 | dossiers.create   | dossiers   | Créer un dossier          | Permet de créer...|
|  2 | dossiers.view     | dossiers   | Voir les dossiers         | Permet de consul.|
| .. | ...               | ...        | ...                       | ...              |
| 70 | admin.settings    | admin      | Paramètres système        | Permet de modif..|
+----+-------------------+------------+---------------------------+------------------+
70 rows in set

-- Table user_permissions (vide initialement)
+----+---------+---------------+-------------+------------------+
| id | user_id | permission_id | accordee_par| date_attribution |
+----+---------+---------------+-------------+------------------+
(Empty set)
```

---

## 🎯 Prochaines étapes

Une fois l'installation terminée :

1. **Configurer les permissions**
   - Aller dans Administration > Permissions
   - Pour chaque utilisateur, cliquer sur "Gérer les permissions"
   - Utiliser le bouton "Permissions recommandées"

2. **Tester les accès**
   - Se connecter avec différents comptes
   - Vérifier que les accès sont bien restreints

3. **Consulter la documentation**
   - `docs/PERMISSIONS_GUIDE.md` : Guide complet
   - `docs/README_PERMISSIONS.md` : Référence rapide

---

## 📝 Contenu du script install_permissions.sql

Le script crée :

- ✅ Table `permissions` (70 permissions)
- ✅ Table `user_permissions` (vide au départ)
- ✅ Index pour optimiser les performances
- ✅ Clés étrangères pour l'intégrité référentielle

**Modules couverts (14)** :
- Dossiers (8 permissions)
- Commission (4)
- Paiements (4)
- DAJ (4)
- Inspections (5)
- Visa (4)
- Décisions (3)
- Documents (4)
- Utilisateurs (6)
- Huitaine (3)
- GPS (4)
- Rapports (4)
- Registre Public (1)
- Carte (2)
- Administration (4)

**Total : ~70 permissions**

---

## 💡 Astuces

### Installation silencieuse

Pour installer sans ouvrir phpMyAdmin :

```sql
-- Créer un fichier quick_install.php à la racine
<?php
require_once 'config/database.php';

$sql = file_get_contents('database/install_permissions.sql');
$pdo->exec($sql);

echo "Installation terminée !";
?>
```

### Vérification rapide

```sql
-- Compter les permissions
SELECT COUNT(*) FROM permissions;
-- Résultat attendu : ~70

-- Voir les modules
SELECT module, COUNT(*) as nb
FROM permissions
GROUP BY module;
```

---

## ✅ Checklist d'installation

- [ ] Base de données `sgdi_db` existe
- [ ] Table `users` existe avec des utilisateurs
- [ ] Script `install_permissions.sql` exécuté
- [ ] Table `permissions` contient ~70 lignes
- [ ] Table `user_permissions` existe (vide)
- [ ] Menu "Administration > Permissions" visible pour les admins
- [ ] Page `/modules/permissions/index.php` accessible

---

**Installation terminée avec succès ?** 🎉

Rendez-vous sur : `http://localhost/dppg-implantation/modules/permissions/index.php`
