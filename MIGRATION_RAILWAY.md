# Migration des corrections sur Railway - 24 octobre 2025

## ✅ Étapes déjà effectuées

1. ✅ Corrections appliquées localement
2. ✅ Migrations testées en local avec succès
3. ✅ Code poussé sur GitHub (commit 85b3531)
4. ✅ Déploiement Railway déclenché

## 🔄 Étapes à suivre pour appliquer les migrations sur Railway

### Option 1: Via l'interface web (RECOMMANDÉ)

1. **Accéder au script de migration**

   Une fois le déploiement terminé (environ 2-5 minutes), accédez à:
   ```
   https://votre-domaine-railway.app/database/migrations/run_all_migrations.php?token=sgdi_migration_2025
   ```

   ⚠️ **Important**: Remplacez `votre-domaine-railway.app` par votre vrai domaine Railway

2. **Vérifier l'exécution**

   Vous devriez voir:
   ```
   ✅ Migration 001_fix_roles_enum.sql terminée avec succès!
   ✅ Migration 002_fix_commissions_role_enum.sql terminée avec succès!
   🎉 TOUTES LES MIGRATIONS ONT ÉTÉ APPLIQUÉES AVEC SUCCÈS!
   ```

3. **Tester les corrections**
   - Allez sur `modules/users/list.php`
   - Sélectionnez un rôle dans le filtre
   - Vérifiez que les résultats s'affichent

### Option 2: Via Railway CLI

```bash
# Se connecter au shell Railway
railway shell

# Exécuter les migrations
php database/migrations/run_migration_001.php
php database/migrations/run_migration_002.php

# Vérifier
php -r "require 'config/database.php'; \$stmt = \$pdo->query('SHOW COLUMNS FROM users LIKE \"role\"'); print_r(\$stmt->fetch());"
```

### Option 3: Via la base de données Railway directement

1. Accédez à la base de données Railway via le dashboard
2. Ouvrez l'onglet "Query"
3. Exécutez le contenu de `database/migrations/001_fix_roles_enum.sql`
4. Exécutez le contenu de `database/migrations/002_fix_commissions_role_enum.sql`

## 🔍 Vérification post-migration

### 1. Vérifier les rôles users
```sql
SHOW COLUMNS FROM users LIKE 'role';
```
Devrait afficher:
```
enum('admin','chef_service','sous_directeur','directeur','cabinet','cadre_dppg','cadre_daj','chef_commission','billeteur','lecteur_public')
```

### 2. Vérifier les rôles chef_commission
```sql
SHOW COLUMNS FROM commissions LIKE 'chef_commission_role';
```
Devrait afficher:
```
enum('chef_service','chef_commission','sous_directeur','directeur')
```

### 3. Tester les filtres
- Allez sur "Gestion des utilisateurs"
- Filtrez par "Chef de Commission" → devrait afficher des résultats
- Filtrez par "Cadre DAJ" → devrait afficher des résultats
- Filtrez par "Cadre DPPG" → devrait afficher des résultats

### 4. Tester la constitution de commission
- Créez ou ouvrez un dossier
- Essayez de constituer une commission
- Sélectionnez un Chef de Commission
- Vérifiez qu'aucune erreur SQL n'apparaît

## 📋 Corrections appliquées

### Migration 001: Correction ENUM rôles et statuts
- ✅ Ajout des 10 rôles complets dans `users.role`
- ✅ Ajout de tous les statuts workflow dans `dossiers.statut`
- ✅ Mise à jour `historique.ancien_statut` et `nouveau_statut`

### Migration 002: Correction ENUM chef_commission_role
- ✅ Ajout de 'chef_commission' et 'sous_directeur'

### Code: Fix bug filtre actif
- ✅ `modules/users/list.php` ligne 14
- ✅ Correction `intval('')` qui retournait 0 au lieu de ''

## 🚨 Dépannage

### Problème: "Token invalide"
**Solution**: Ajoutez `?token=sgdi_migration_2025` à l'URL

### Problème: "Fichier non trouvé"
**Solution**: Attendez que le déploiement Railway soit terminé (vérifiez sur railway.app)

### Problème: "Migrations déjà appliquées"
**Solution**: C'est normal! Les migrations ignorent automatiquement les modifications déjà faites

### Problème: Les filtres ne fonctionnent toujours pas
**Vérifications**:
1. Videz le cache du navigateur (Ctrl+F5)
2. Vérifiez que les migrations ont bien été exécutées (voir section Vérification)
3. Consultez les logs Railway pour voir s'il y a des erreurs

## 📊 Liens utiles

- **Dashboard Railway**: https://railway.app/project/68c95763-4b88-4d46-855d-653da4fa916c
- **GitHub Repository**: https://github.com/ManMbaiLikol/sgdi-dppg
- **Logs déploiement**: https://railway.com/project/.../service/.../logs

## 🔐 Sécurité

⚠️ **IMPORTANT**: Après avoir exécuté les migrations avec succès:

1. Supprimez ou renommez le fichier `run_all_migrations.php` pour éviter les exécutions multiples
2. Ou ajoutez une variable d'environnement `MIGRATION_TOKEN` avec un token unique dans Railway

```bash
# Via Railway CLI
railway variables set MIGRATION_TOKEN=votre_token_secret_unique
```

## 📝 Suivi

- [ ] Déploiement Railway terminé
- [ ] Migrations exécutées sur Railway
- [ ] Vérification rôles users
- [ ] Vérification rôles chef_commission
- [ ] Test filtres utilisateurs
- [ ] Test constitution commission
- [ ] Script de migration sécurisé/supprimé

## 📞 Support

En cas de problème:
1. Consultez les logs Railway
2. Vérifiez le fichier `CORRECTIONS_2025-10-24.md`
3. Contactez l'administrateur système

---
Date: 24 octobre 2025
Version: 1.0
Status: En attente d'exécution des migrations sur Railway
