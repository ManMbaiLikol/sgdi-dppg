# Déploiement des Comptes sur Railway

## 🎯 Problème
Les comptes **Ministre** et **Sous-Directeur** ont été créés dans la base de données **locale** (WAMP), mais pas sur **Railway**.

## ✅ Solution : 3 Méthodes

---

## 📱 MÉTHODE 1 : Via Script PHP Web (RECOMMANDÉE)

### Avantages
- ✅ Plus simple
- ✅ Interface visuelle
- ✅ Pas besoin de Railway CLI
- ✅ Fonctionne directement depuis le navigateur

### Étapes

#### 1. Créer le Compte Ministre

**URL:**
```
https://sgdi-dppg-production.up.railway.app/utilities/create_compte_ministre.php
```

**Résultat attendu:**
- Message de succès
- Affichage des identifiants
- Username: `ministre`
- Mot de passe: `Ministre@2025`

#### 2. Créer le Compte Sous-Directeur

**URL:**
```
https://sgdi-dppg-production.up.railway.app/utilities/create_compte_sousdirecteur.php
```

**Résultat attendu:**
- Message de succès
- Username: `SDTD_Abena` (si existe déjà) ou `sousdirecteur` (si nouveau)
- Mot de passe: `admin123`

#### 3. Vérification

**Tester la connexion Ministre:**
```
https://sgdi-dppg-production.up.railway.app/
Username: ministre
Mot de passe: Ministre@2025
```

---

## 💻 MÉTHODE 2 : Via Railway CLI

### Prérequis
- Railway CLI installé ✅ (déjà installé)
- Connexion au projet ✅

### Étape 1 : Créer le Compte Ministre

```bash
# Exécuter le script SQL via Railway CLI
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE < database/railway_add_compte_ministre.sql
```

**Ou directement en SQL:**

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
SELECT 'ministre', 'ministre@minee.cm', '\$2y\$10\$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', 'ministre', 'CABINET', 'Ministre', '+237690000009', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'ministre');
"
```

### Étape 2 : Créer le Compte Sous-Directeur

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
SELECT 'sousdirecteur', 'sousdirecteur@dppg.cm', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sous_directeur', 'SOUS-DIRECTEUR', 'SDTD', '+237690000007', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'sousdirecteur');
"
```

### Étape 3 : Vérifier

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
SELECT username, email, role, nom, prenom, actif FROM users WHERE role IN ('ministre', 'sous_directeur');
"
```

---

## 🌐 MÉTHODE 3 : Via Interface Web Railway

### Étape 1 : Accéder à la base de données

1. **Aller sur Railway Dashboard**
   ```
   https://railway.app/project/68c95763-4b88-4d46-855d-653da4fa916c
   ```

2. **Cliquer sur le service MySQL**

3. **Onglet "Data"** ou **"Connect"**

4. **Ouvrir phpMyAdmin** (si disponible) ou **MySQL Console**

### Étape 2 : Exécuter les requêtes SQL

**Pour le Ministre:**
```sql
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
SELECT 'ministre', 'ministre@minee.cm', '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', 'ministre', 'CABINET', 'Ministre', '+237690000009', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'ministre');
```

**Pour le Sous-Directeur:**
```sql
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
SELECT 'sousdirecteur', 'sousdirecteur@dppg.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sous_directeur', 'SOUS-DIRECTEUR', 'SDTD', '+237690000007', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'sousdirecteur');
```

### Étape 3 : Vérifier
```sql
SELECT username, email, role, nom, prenom, actif
FROM users
WHERE role IN ('ministre', 'sous_directeur');
```

---

## 🔍 Vérification Complète

### Script de Diagnostic Railway

Créez et exécutez ce fichier pour vérifier tous les comptes :

**URL:**
```
https://sgdi-dppg-production.up.railway.app/utilities/check_workflow_ministre.php
```

Ce script affiche :
- ✅ Tous les statuts de dossiers
- ✅ Nombre de visas par rôle
- ✅ Dossiers prêts pour chaque niveau
- ✅ Diagnostic complet

---

## 📋 Checklist Post-Création

### Comptes à Créer sur Railway

- [ ] **Ministre**
  - Username: `ministre`
  - Mot de passe: `Ministre@2025`
  - Rôle: `ministre`

- [ ] **Sous-Directeur**
  - Username: `sousdirecteur` (ou `SDTD_Abena` si existant)
  - Mot de passe: `admin123`
  - Rôle: `sous_directeur`

### Tests à Effectuer

- [ ] **Connexion Ministre sur Railway**
  ```
  https://sgdi-dppg-production.up.railway.app/
  ministre / Ministre@2025
  ```

- [ ] **Accès Dashboard Ministre**
  ```
  https://sgdi-dppg-production.up.railway.app/modules/ministre/dashboard.php
  ```

- [ ] **Connexion Sous-Directeur**
  ```
  sousdirecteur / admin123
  ```

- [ ] **Vérifier circuit complet**
  - Chef Service → Visa 1/3
  - Sous-Directeur → Visa 2/3
  - Directeur → Visa 3/3
  - Ministre → Décision finale

---

## ⚡ Commandes Rapides

### Vérifier les comptes existants sur Railway

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "SELECT username, role, actif FROM users ORDER BY role;"
```

### Compter les utilisateurs par rôle

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "SELECT role, COUNT(*) as nb FROM users GROUP BY role;"
```

### Lister tous les rôles disponibles

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "SELECT DISTINCT role FROM users;"
```

---

## 🚨 Dépannage

### Problème : "Access denied"

**Solution:**
```bash
# Vérifier les variables d'environnement Railway
railway variables

# Vérifier qu'elles sont bien définies:
# - MYSQLHOST
# - MYSQLPORT
# - MYSQLUSER
# - MYSQLPASSWORD
# - MYSQLDATABASE
```

### Problème : "Compte déjà existant"

**Solution:**
```bash
# Vérifier si le compte existe
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "SELECT * FROM users WHERE username = 'ministre';"

# Si existe, réinitialiser le mot de passe
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
UPDATE users
SET password = '\$2y\$10\$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW'
WHERE username = 'ministre';
"
```

### Problème : "Rôle 'ministre' n'existe pas"

**Solution:**

Le rôle `ministre` doit être ajouté à l'ENUM de la colonne `role` :

```bash
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
ALTER TABLE users
MODIFY COLUMN role ENUM('admin', 'chef_service', 'cadre_dppg', 'cadre_daj', 'billeteur', 'chef_commission', 'sous_directeur', 'directeur', 'ministre') NOT NULL;
"
```

---

## 📊 Résumé

### Option Recommandée : MÉTHODE 1 (Script PHP Web)

1. **Ouvrir navigateur**
2. **Aller sur:** `https://sgdi-dppg-production.up.railway.app/utilities/create_compte_ministre.php`
3. **Vérifier** le message de succès
4. **Tester** la connexion : `ministre` / `Ministre@2025`
5. **Répéter** pour sous-directeur si nécessaire

**Temps estimé:** 2 minutes ⚡

---

## ✅ Confirmation Finale

Après création des comptes, vérifiez :

```bash
# Via Railway CLI
railway run mysql -u root -p$MYSQLPASSWORD -h $MYSQLHOST -P $MYSQLPORT $MYSQLDATABASE -e "
SELECT username, email, role, nom, prenom, actif
FROM users
WHERE username IN ('ministre', 'sousdirecteur')
ORDER BY role;
"
```

**Résultat attendu:**
```
+---------------+------------------------+----------------+-----------------+-----------+-------+
| username      | email                  | role           | nom             | prenom    | actif |
+---------------+------------------------+----------------+-----------------+-----------+-------+
| ministre      | ministre@minee.cm      | ministre       | CABINET         | Ministre  |     1 |
| sousdirecteur | sousdirecteur@dppg.cm  | sous_directeur | SOUS-DIRECTEUR  | SDTD      |     1 |
+---------------+------------------------+----------------+-----------------+-----------+-------+
```

---

**Bonne création de comptes sur Railway ! 🚀**

*Dernière mise à jour : 7 novembre 2025*
