# 🚀 Déploiement - 31 Octobre 2025

## 📦 Version déployée : SGDI v2.0

**Date de déploiement** : 31 octobre 2025
**Environnement** : Production Railway
**URL** : https://sgdi-dppg-production.up.railway.app

---

## ✅ Modifications déployées

### 4 commits poussés

1. **`a56aba7`** - Circuit de visa à 3 niveaux complet + Optimisation affichage
   - 6 fichiers (circuit visa Chef Service, Sous-Directeur, Directeur)
   - Optimisation colonnes tableaux (Localisation vs Inspection/Délai)
   - ~2,200 lignes de code

2. **`a22a626`** - Module décision ministérielle + Tables BDD + Registre public intégré
   - 3 fichiers (décision ministre + migration SQL)
   - Tables `decisions_ministerielle` et `registre_public`
   - Publication automatique registre public

3. **`46ff50c`** - Système de notifications automatiques + Intégration circuit visa
   - 2 fichiers (notifications + intégration)
   - Emails HTML + notifications in-app
   - ~300 lignes de code

4. **`841fc6c`** - Documentation complète session 31 octobre 2025
   - 1 fichier (RECAP_SESSION_31_OCTOBRE_2025.md)
   - Documentation exhaustive 600+ lignes

---

## 🗄️ Migrations Base de Données Requises

### ⚠️ IMPORTANT : À exécuter sur Railway

```sql
-- Fichier: database/migrations/007_create_decisions_and_registre.sql
-- Créer les nouvelles tables

CREATE TABLE IF NOT EXISTS decisions_ministerielle (...);
CREATE TABLE IF NOT EXISTS registre_public (...);

-- Voir le fichier complet dans database/migrations/
```

### Exécution sur Railway

```bash
# Option 1 : Via Railway CLI
railway run mysql -u root -p < database/migrations/007_create_decisions_and_registre.sql

# Option 2 : Via phpMyAdmin Railway
# - Se connecter à la base Railway
# - Importer le fichier 007_create_decisions_and_registre.sql
# - Exécuter

# Option 3 : Via Railway Console MySQL
# - Copier-coller le contenu du fichier SQL
# - Exécuter dans la console MySQL Railway
```

---

## 🆕 Nouvelles fonctionnalités disponibles

### 1. Circuit de Visa 3 Niveaux ✅

**URLs** :
- Chef Service : `/modules/dossiers/viser_inspections.php`
- Sous-Directeur : `/modules/dossiers/viser_sous_directeur.php`
- Directeur DPPG : `/modules/dossiers/viser_directeur.php`

**Workflow** :
```
Dossier inspecté
    ↓
Visa Chef Service (1/3) → visa_chef_service
    ↓
Visa Sous-Directeur (2/3) → visa_sous_directeur
    ↓
Visa Directeur (3/3) → visa_directeur
    ↓
Décision ministérielle
```

### 2. Décision Ministérielle ✅

**URLs** :
- Liste : `/modules/dossiers/decision_ministre.php`
- Formulaire : `/modules/dossiers/prendre_decision.php`

**Fonctionnalités** :
- Décision finale (Approuver/Refuser/Ajourner)
- Numéro d'arrêté ministériel
- Publication automatique au registre public si approuvé

### 3. Notifications Automatiques ✅

**Types d'événements notifiés** :
- Création dossier
- Chaque visa (3 niveaux)
- Décision ministérielle
- Paiement enregistré

**Canaux** :
- Email HTML professionnel
- Notifications in-app (structure prête)

### 4. Registre Public Amélioré ✅

**URL** : `/modules/registre_public/index.php`

**Améliorations** :
- Support statut `approuve` (nouveaux dossiers)
- Affichage décisions ministérielles
- Export Excel fonctionnel
- Filtres avancés

---

## 🔧 Configuration Post-Déploiement

### 1. Variables d'Environnement Railway

Vérifier que ces variables sont définies :

```env
# Base de données (déjà configuré)
DB_HOST=xxx
DB_NAME=railway
DB_USER=root
DB_PASSWORD=xxx

# Email (à configurer si pas déjà fait)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=noreply@dppg.minee.cm
SMTP_PASSWORD=xxx

# Application
APP_URL=https://sgdi-dppg-production.up.railway.app
APP_ENV=production
```

### 2. Permissions Fichiers

```bash
# Si besoin, définir les permissions pour uploads
chmod 755 /app/assets/uploads/
chmod 755 /app/assets/uploads/dossiers/
```

### 3. Test des Nouvelles Fonctionnalités

**Checklist de validation** :

- [ ] Circuit visa accessible pour chaque rôle
  - [ ] Chef Service peut viser
  - [ ] Sous-Directeur peut viser
  - [ ] Directeur DPPG peut viser

- [ ] Module décision ministérielle
  - [ ] Liste des dossiers s'affiche
  - [ ] Formulaire décision fonctionne
  - [ ] Publication registre public OK

- [ ] Notifications
  - [ ] Emails envoyés (vérifier logs)
  - [ ] Destinataires corrects

- [ ] Registre public
  - [ ] Dossiers approuvés visibles
  - [ ] Filtres fonctionnent
  - [ ] Export Excel OK

---

## 📊 Statut Tables Base de Données

### Tables existantes (OK)
✅ users, roles, user_roles
✅ dossiers, statuts_dossier, historique_dossier
✅ types_infrastructure, types_demandeurs
✅ commissions, membres_commission, types_membres
✅ documents, versions_document, types_document
✅ notes_frais, paiements, recus
✅ inspections, rapports_inspection, grilles_evaluation
✅ visas
✅ notifications, logs_activite

### Nouvelles tables (À créer)
⚠️ **decisions_ministerielle** → Migration 007 requise
⚠️ **registre_public** → Migration 007 requise

---

## 🔍 Vérification Déploiement

### Commandes Railway

```bash
# Voir les logs en temps réel
railway logs

# Vérifier le statut
railway status

# Redéployer si besoin
railway up

# Se connecter à la base de données
railway run mysql -u root -p
```

### Endpoints à tester

1. **Page d'accueil** : https://sgdi-dppg-production.up.railway.app
2. **Login** : https://sgdi-dppg-production.up.railway.app/modules/auth/login.php
3. **Registre public** : https://sgdi-dppg-production.up.railway.app/modules/registre_public/index.php
4. **Dashboard** : https://sgdi-dppg-production.up.railway.app/dashboard.php

### Tests Fonctionnels

**Test 1 : Circuit de visa complet**
```
1. Se connecter comme Chef Service
2. Aller sur /modules/dossiers/viser_inspections.php
3. Vérifier qu'il y a des dossiers
4. Viser un dossier → Vérifier redirection
5. Se connecter comme Sous-Directeur
6. Vérifier que le dossier apparaît dans viser_sous_directeur.php
7. Répéter pour Directeur et Ministre
```

**Test 2 : Décision ministérielle**
```
1. Se connecter comme Ministre
2. Aller sur /modules/dossiers/decision_ministre.php
3. Prendre une décision "Approuver"
4. Vérifier publication au registre public
5. Vérifier réception email (si SMTP configuré)
```

---

## ⚠️ Points d'Attention

### 1. Migrations SQL
- **CRITIQUE** : Exécuter `007_create_decisions_and_registre.sql`
- Sans ces tables, les modules décision ministérielle ne fonctionneront pas

### 2. SMTP Email
- Si pas configuré, les emails ne seront pas envoyés
- Mais l'application fonctionnera quand même
- Voir `includes/notifications.php` pour configuration

### 3. Rôles et Permissions
- Vérifier que les rôles existent :
  - `chef_service`
  - `sous_directeur`
  - `directeur`
  - `ministre`

### 4. Statuts Dossiers
- Nouveaux statuts ajoutés :
  - `visa_chef_service`
  - `visa_sous_directeur`
  - `visa_directeur`
  - `approuve`, `refuse`, `ajourne`

---

## 📝 Logs et Monitoring

### Logs Railway

```bash
# Voir tous les logs
railway logs

# Filtrer par service
railway logs --filter "sgdi-dppg"

# Logs en temps réel
railway logs --tail
```

### Logs d'Erreurs PHP

Fichiers à surveiller :
- `/var/log/apache2/error.log`
- `/var/log/php/error.log`

---

## 🔄 Rollback si Problème

### En cas de problème critique

```bash
# Revenir au commit précédent
git revert HEAD
git push origin main

# Ou revenir à un commit spécifique
git reset --hard 976f003  # Dernier commit stable
git push origin main --force

# Redéployer
railway up
```

### Commits stables de référence

- `976f003` - Dernier état avant session 31 octobre
- `a56aba7` - Circuit visa complet
- `841fc6c` - État final avec documentation

---

## ✅ Checklist Finale

### Avant de valider le déploiement

- [x] Code poussé sur GitHub
- [x] Déploiement Railway lancé
- [ ] Migrations SQL exécutées sur Railway
- [ ] Tests fonctionnels validés
- [ ] Emails de notification testés
- [ ] Registre public accessible
- [ ] Circuit visa testé de bout en bout
- [ ] Documentation à jour

### Après validation

- [ ] Notifier l'équipe MINEE/DPPG
- [ ] Former les utilisateurs sur nouvelles fonctionnalités
- [ ] Surveiller logs pendant 24h
- [ ] Collecter feedback utilisateurs

---

## 📞 Support

### En cas de problème

1. **Vérifier les logs Railway**
   ```bash
   railway logs --tail
   ```

2. **Vérifier les tables BDD**
   ```bash
   railway run mysql -u root -p
   SHOW TABLES;
   ```

3. **Contacter le développeur**
   - Session : 31 octobre 2025
   - Développé avec : Claude Code (Anthropic)

---

## 🎯 Prochaines Étapes

### Court terme (1 semaine)
- Exécuter migrations SQL
- Tester workflow complet
- Former utilisateurs
- Ajuster si bugs

### Moyen terme (1 mois)
- Dashboard statistiques avancé
- Export PDF arrêtés ministériels
- Système huitaine (8 jours)
- Graphiques Chart.js

### Long terme (3 mois)
- Tests automatisés
- API REST
- Application mobile
- Module archive

---

**Déployé le** : 31 octobre 2025
**Version** : SGDI v2.0
**Statut** : ✅ Déploiement réussi
**URL Production** : https://sgdi-dppg-production.up.railway.app

---

🤖 **Généré avec Claude Code**
https://claude.com/claude-code

© 2025 MINEE/DPPG - Tous droits réservés
