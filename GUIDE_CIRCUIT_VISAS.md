# Guide Complet : Circuit des Visas jusqu'au Ministre

## 🎯 Objectif
Faire progresser un dossier du statut `inspecte` jusqu'à la décision ministérielle finale.

---

## 📊 Prérequis

Avant de démarrer le circuit des visas, le dossier doit avoir :

- ✅ Statut : `inspecte`
- ✅ Inspection terrain réalisée
- ✅ Rapport d'inspection rédigé
- ✅ (Optionnel) Validation du rapport par le Chef de Commission

**Vérification :** Actuellement, vous avez **1 dossier prêt** au statut `inspecte`.

---

## 🔄 Circuit Complet des Visas (3 niveaux)

### NIVEAU 1/3 : Visa Chef Service SDTD

**Rôle :** Chef Service
**Identifiants :** `chef` / `chef123`

#### Étapes :

1. **Connexion**
   ```
   http://localhost/dppg-implantation/
   Username: chef
   Mot de passe: chef123
   ```

2. **Accéder aux dossiers à viser**
   - URL: `/modules/dossiers/viser_inspections.php`
   - Ou depuis le dashboard : "Dossiers inspectés à viser"

3. **Sélectionner le dossier**
   - Cliquez sur le dossier au statut `inspecte`
   - Consultez le rapport d'inspection

4. **Apposer le visa**
   - URL: `/modules/dossiers/apposer_visa.php?id=XX`
   - Choix :
     - ✅ **Approuver** → Transmission au Sous-Directeur
     - ❌ **Rejeter** → Dossier rejeté
     - 🔄 **Demander modification** → Retour pour corrections
   - Observations (optionnel)
   - Valider

5. **Résultat**
   - Statut devient : `visa_chef_service`
   - Dossier transmis au Sous-Directeur SDTD

---

### NIVEAU 2/3 : Visa Sous-Directeur SDTD

**Rôle :** Sous-Directeur
**Identifiants :** Vous devez créer ce compte si inexistant

#### Création du compte Sous-Directeur (si nécessaire)

```sql
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
VALUES ('sousdirecteur', 'sousdirecteur@dppg.cm',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'sous_directeur', 'SOUS-DIRECTEUR', 'SDTD', '+237690000007', 1);
```
Mot de passe : `admin123` (à changer après connexion)

#### Étapes :

1. **Connexion**
   ```
   Username: sousdirecteur
   Mot de passe: admin123
   ```

2. **Accéder aux dossiers à viser**
   - URL: `/modules/dossiers/viser_sous_directeur.php`
   - Filtre : statut = `visa_chef_service`

3. **Apposer le visa**
   - URL: `/modules/dossiers/apposer_visa_sous_directeur.php?id=XX`
   - Choix :
     - ✅ **Approuver** → Transmission au Directeur
     - ❌ **Rejeter** → Dossier rejeté
     - 🔄 **Demander modification** → Retour au Chef Service
   - Observations (optionnel)
   - Valider

4. **Résultat**
   - Statut devient : `visa_sous_directeur`
   - Dossier transmis au Directeur DPPG

---

### NIVEAU 3/3 : Visa Directeur DPPG

**Rôle :** Directeur
**Identifiants :** `directeur` / `dir123`

#### Étapes :

1. **Connexion**
   ```
   Username: directeur
   Mot de passe: dir123
   ```

2. **Accéder aux dossiers à viser**
   - URL: `/modules/dossiers/viser_directeur.php`
   - Filtre : statut = `visa_sous_directeur`

3. **Apposer le visa final**
   - URL: `/modules/dossiers/apposer_visa_directeur.php?id=XX`
   - Choix :
     - ✅ **Approuver** → **Transmission au Ministre** ⭐
     - ❌ **Rejeter** → Dossier rejeté
     - 🔄 **Demander modification** → Retour au Sous-Directeur
   - Observations (optionnel)
   - Valider

4. **Résultat**
   - Statut devient : `visa_directeur` ✨
   - **Dossier transmis au Cabinet/Secrétariat du Ministre**
   - **Apparaît dans l'espace Ministre**

---

### NIVEAU FINAL : Décision Ministérielle

**Rôle :** Ministre
**Identifiants :** `ministre` / `Ministre@2025`

#### Étapes :

1. **Connexion**
   ```
   Username: ministre
   Mot de passe: Ministre@2025
   ```

2. **Accéder aux dossiers**
   - URL: `/modules/dossiers/decision_ministre.php`
   - Liste des dossiers avec statut `visa_directeur`

3. **Prendre la décision finale**
   - URL: `/modules/dossiers/prendre_decision.php?id=XX`
   - Choix :
     - ✅ **Approuver** → Publication automatique registre public
     - ❌ **Refuser** → Dossier refusé (public)
     - ⏸️ **Ajourner** → Retour pour complément
   - Numéro d'arrêté (obligatoire) : ex. `ARRETE_001/2025`
   - Observations (optionnel)
   - Valider

4. **Résultat si approuvé**
   - Statut devient : `approuve`
   - **Publication automatique au registre public**
   - Visible sur `/modules/registre_public/` (sans authentification)

---

## 🚀 Procédure Rapide : Tester le Workflow Complet

### Scénario : Faire progresser 1 dossier jusqu'au Ministre

```bash
# 1. CHEF SERVICE (visa 1/3)
http://localhost/dppg-implantation/
→ Connexion: chef / chef123
→ Aller sur: modules/dossiers/viser_inspections.php
→ Cliquer sur le dossier inspecté
→ Approuver avec visa

# 2. SOUS-DIRECTEUR (visa 2/3)
→ Déconnexion
→ Connexion: sousdirecteur / admin123
→ Aller sur: modules/dossiers/viser_sous_directeur.php
→ Cliquer sur le dossier
→ Approuver avec visa

# 3. DIRECTEUR (visa 3/3)
→ Déconnexion
→ Connexion: directeur / dir123
→ Aller sur: modules/dossiers/viser_directeur.php
→ Cliquer sur le dossier
→ Approuver avec visa
→ ✨ Dossier transmis au Ministre !

# 4. MINISTRE (décision finale)
→ Déconnexion
→ Connexion: ministre / Ministre@2025
→ Aller sur: modules/dossiers/decision_ministre.php
→ ✅ Le dossier apparaît !
→ Cliquer "Prendre décision"
→ Approuver + Numéro arrêté
→ ✨ Publication automatique !

# 5. VÉRIFICATION PUBLIQUE
→ Déconnexion (pas nécessaire)
→ Aller sur: modules/registre_public/
→ ✅ Le dossier approuvé est visible !
```

---

## 📊 Vérification de l'État Actuel

### Script de Diagnostic

Exécutez ce script pour voir où en sont vos dossiers :

```bash
http://localhost/dppg-implantation/check_workflow_ministre.php
```

Ce script affiche :
- Tous les statuts et leur nombre
- Dossiers prêts pour chaque niveau de visa
- Circuit complet des visas existants
- Diagnostic du workflow

---

## 🔧 Comptes Nécessaires

| Rôle | Username | Mot de passe | Statut |
|------|----------|--------------|--------|
| Admin | `admin` | `admin123` | ✅ Existe |
| Chef Service | `chef` | `chef123` | ✅ Existe |
| Sous-Directeur | `sousdirecteur` | `admin123` | ⚠️ À créer |
| Directeur | `directeur` | `dir123` | ✅ Existe |
| Ministre | `ministre` | `Ministre@2025` | ✅ Existe |

### Créer le compte Sous-Directeur

```sql
-- Via phpMyAdmin ou ligne de commande MySQL
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
VALUES ('sousdirecteur', 'sousdirecteur@dppg.cm',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'sous_directeur', 'SOUS-DIRECTEUR', 'SDTD', '+237690000007', 1);
```

Ou via script PHP :
```bash
http://localhost/dppg-implantation/utilities/create_compte_ministre.php
# (Adapter pour sous_directeur)
```

---

## ⚠️ Points d'Attention

### 1. Ordre des Visas

Les visas **DOIVENT** être apposés dans l'ordre :
1. Chef Service
2. Sous-Directeur
3. Directeur

Impossible de sauter une étape !

### 2. Statuts Requis

Chaque niveau attend un statut précis :
- Chef Service → `inspecte`
- Sous-Directeur → `visa_chef_service`
- Directeur → `visa_sous_directeur`
- Ministre → `visa_directeur`

### 3. Actions Possibles

À chaque niveau, 3 actions :
- **Approuver** : Transmet au niveau suivant
- **Rejeter** : Arrête le dossier
- **Demander modification** : Retour en arrière

---

## 🎯 Résumé : Pourquoi l'Espace Ministre est Vide ?

### Diagnostic

✅ Le workflow fonctionne correctement
✅ Le compte Ministre existe et est configuré
✅ La requête de l'espace Ministre est correcte (statut = 'visa_directeur')

❌ **PROBLÈME** : Aucun dossier n'a encore reçu les 3 visas
❌ Aucun dossier n'a le statut `visa_directeur`

### Solution

**Faites progresser au moins 1 dossier à travers les 3 niveaux de visa :**

1. Chef Service apposse visa → `visa_chef_service`
2. Sous-Directeur apposse visa → `visa_sous_directeur`
3. Directeur apposse visa → `visa_directeur`
4. **Le dossier apparaît chez le Ministre ! ✅**

---

## 📞 Aide

- **Script de vérification** : `check_workflow_ministre.php`
- **Vue rapport** : `workflow_report.html`
- **Ce guide** : `GUIDE_CIRCUIT_VISAS.md`

---

**Bon workflow ! 🚀**

*Dernière mise à jour : 7 novembre 2025*
