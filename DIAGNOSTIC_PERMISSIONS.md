# 🔍 Diagnostic Problème Permissions Dossiers

## Date: 24 octobre 2025

---

## 📋 Situation Constatée

**Utilisateur**: Christian Dénis ABANDA LOGA (ID: 27, Rôle: `cadre_dppg`)
**Problème**: Voit les dossiers de SALOMON OLIVIER MAÏ NGIDJOL (ID: 16, Rôle: `cadre_dppg`)
**Règle de gestion**: Seuls les membres de la commission d'un dossier peuvent y avoir accès

---

## 🔬 Analyse Effectuée

### 1. Dossiers Créés
- ✅ Christian: 0 dossier
- ✅ Salomon: 0 dossier

### 2. Fiches d'Inspection
- **Salomon est inspecteur de 2 fiches**:
  1. Dossier **PC20251010224931** (TotalEnergies)
  2. Dossier **PC20251010222326** (TotalEnergies)

### 3. Commissions

**Dossier PC20251010224931:**
- Cadre DPPG: Salomon MAÏ (ID 16)
- Cadre DAJ: EDOU (ID 54)
- Chef Commission: ABENA BEYALA (ID 19)
- **Christian N'EST PAS membre** ❌

**Dossier PC20251010222326:**
- Cadre DPPG: Salomon MAÏ (ID 16)
- Cadre DAJ: MINKOULOU (ID 51)
- Chef Commission: AMBOMBO OKOA AWA (ID 26)
- **Christian N'EST PAS membre** ❌

### 4. Dossiers Visibles par Christian
- **1 seul dossier**: SS20251024025528 (où il est membre de la commission) ✅

---

## 🐛 Problème Identifié

**Christian ne devrait PAS voir les 2 dossiers de Salomon**, car il n'est pas membre de leurs commissions.

### Hypothèses:

1. **Bug dans le code de filtrage** (fonction `getDossiers()`)
2. **Affichage sans filtre** quelque part (dashboard, liste, page spécifique)
3. **Confusion** entre "dossiers" et "fiches d'inspection"
4. **Session incorrecte** (Christian connecté avec un autre compte)

---

## 📜 Code Actuel - Fonction `getDossiers()`

**Fichier**: `modules/dossiers/functions.php` lignes 469-479

```php
case 'cadre_dppg':
    // Voir les dossiers qu'il a créés OU dont il est membre de la commission
    $where_conditions[] = "(d.user_id = ? OR EXISTS (
        SELECT 1 FROM commissions c
        WHERE c.dossier_id = d.id
        AND (c.cadre_dppg_id = ? OR c.chef_commission_id = ?)
    ))";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    break;
```

### Problème avec ce Code:

**Condition 1**: `d.user_id = ?` → Permet de voir les dossiers **créés par lui-même**
**Condition 2**: `EXISTS (SELECT... c.cadre_dppg_id = ? OR c.chef_commission_id = ?)` → Permet de voir les dossiers dont il est membre

**Selon la règle de gestion stricte**: "seuls les membres de la commission peuvent avoir accès"

👉 La condition 1 (`d.user_id = ?`) **ne devrait PAS exister** pour les cadres DPPG

---

## ✅ Solution Proposée

### Option 1: Application Stricte de la Règle

**Pour les cadre_dppg**: Ne voir QUE les dossiers dont ils sont membres de la commission

```php
case 'cadre_dppg':
    // Voir SEULEMENT les dossiers dont il est membre de la commission
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM commissions c
        WHERE c.dossier_id = d.id
        AND (c.cadre_dppg_id = ? OR c.cadre_daj_id = ? OR c.chef_commission_id = ?)
    )";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    break;
```

**Conséquences**:
- ✅ Respect strict de la règle de gestion
- ✅ Christian ne voit plus les dossiers de Salomon
- ⚠️ Un cadre_dppg ne voit plus les dossiers qu'il a créés LUI-MÊME (sauf s'il est dans la commission)
- ⚠️ Les dossiers SANS commission ne sont visibles par PERSONNE (sauf admin/chef_service)

### Option 2: Permission Mixte

Permettre de voir:
1. Les dossiers créés par soi-même
2. Les dossiers dont on est membre de la commission

```php
case 'cadre_dppg':
    $where_conditions[] = "(d.user_id = ? OR EXISTS (
        SELECT 1 FROM commissions c
        WHERE c.dossier_id = d.id
        AND (c.cadre_dppg_id = ? OR c.cadre_daj_id = ? OR c.chef_commission_id = ?)
    ))";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    break;
```

**Ajout**: `c.cadre_daj_id = ?` (oublié dans le code actuel)

---

## 🎯 Questions à Clarifier

1. **Où exactement** Christian voit-il les dossiers de Salomon?
   - Liste des dossiers (`modules/dossiers/list.php`)
   - Dashboard
   - Page de fiches d'inspection
   - Autre

2. **Quelle règle appliquer**?
   - Option 1: Stricte (seulement membres commission)
   - Option 2: Mixte (créateur + membres commission)

3. **Dossiers sans commission**?
   - Qui peut les voir?
   - Qui peut y accéder pour constituer la commission?

---

## 📊 Recommandation

**Après clarification avec l'utilisateur**, appliquer:

- **Option 1** si la règle est absolument stricte
- **Option 2** si on veut permettre aux créateurs de suivre leurs dossiers

Dans les deux cas, **AJOUTER** `c.cadre_daj_id = ?` dans la condition `EXISTS` car les cadres DAJ sont aussi membres des commissions.

---

**À suivre**: Correction du code après confirmation de l'utilisateur
