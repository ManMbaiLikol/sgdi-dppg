# 📊 INTÉGRATION DES STATISTIQUES AVANCÉES

## Vue d'Ensemble

Ce guide explique comment ajouter les 4 sections statistiques avancées dans les dashboards :
1. **Infrastructures opérationnelles**
2. **Infrastructures fermées/démantelées**
3. **Top 5 opérateurs les plus actifs**
4. **Top 5 motifs de rejet/irrégularité**

---

## 📁 Fichiers Créés

### 1. Fonctions dans `modules/dossiers/functions.php`
```php
getStatistiquesInfrastructuresOperationnelles()  // Stats opérationnelles
getStatistiquesInfrastructuresFermees()          // Stats fermées
getOperateursPlusActifs($limit = 5)              // Top opérateurs
getTop5MotifsRejet($limit = 5)                   // Top motifs rejet
getEvolutionMensuellesPaiements($mois = 6)       // Évolution paiements
```

### 2. Composant réutilisable `includes/dashboard_stats_avancees.php`
Affiche les 4 sections avec:
- Design responsive
- Cards Bootstrap 5
- Icônes Font Awesome
- Badges colorés

---

## 🚀 Intégration dans les Dashboards

### Chef de Service

**Fichier**: `modules/chef_service/dashboard_avance.php`

**Étape 1**: Après les graphiques existants, ajouter:
```php
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
```

**Position recommandée**: Juste avant `require_once '../../includes/footer.php';`

---

### Sous-Directeur

**Fichier**: `modules/sous_directeur/dashboard.php`

**Étape 1**: À la fin du contenu, avant le footer, ajouter:
```php
<!-- Statistiques Avancées -->
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
```

---

### Directeur

**Fichier**: `modules/directeur/dashboard.php`

**Étape 1**: À la fin du contenu, avant le footer, ajouter:
```php
<!-- Statistiques Avancées -->
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
```

---

### Ministre

**Fichier**: `modules/ministre/dashboard.php`

**Étape 1**: À la fin du contenu, avant le footer, ajouter:
```php
<!-- Statistiques Avancées -->
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
```

---

## 📋 Code d'Intégration Complet

### Exemple pour Chef Service

```php
<?php
// ... code existant du dashboard ...

// Après les graphiques Chart.js
?>

</div> <!-- Fin container-fluid des graphiques -->

<!-- Statistiques Avancées -->
<div class="container-fluid mt-4">
    <h2 class="h4 mb-3">
        <i class="fas fa-chart-bar"></i> Statistiques Avancées
    </h2>
    <?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
```

---

## 🎨 Personnalisation

### Modifier le nombre d'éléments dans les Tops

Dans chaque dashboard, avant d'inclure le composant :

```php
<?php
// Personnaliser les limites
function getOperateursPlusActifs($limit = 10) { ... }  // Top 10 au lieu de 5
function getTop5MotifsRejet($limit = 10) { ... }        // Top 10 au lieu de 5
?>

<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
```

### Modifier les couleurs

Éditer `includes/dashboard_stats_avancees.php` :

```php
<!-- Changer la couleur de la carte Opérationnelles -->
<div class="card-header bg-success text-white">  <!-- Remplacer bg-success -->
```

---

## 📊 Données Affichées

### 1. Infrastructures Opérationnelles

**KPIs affichés**:
- ✅ Nombre d'opérationnels
- ⏸️ Fermés temporairement
- 🚫 Fermés définitivement
- 💥 Démantelés
- 📈 Taux opérationnel (barre de progression)

**Calcul du taux**:
```
Taux = (Opérationnels / Total autorisés) × 100
```

---

### 2. Infrastructures Fermées/Démantelées

**Tableau avec**:
- Type d'infrastructure
- Statut (badge coloré)
- Nombre

**Badges**:
- 🟡 Fermé Temporaire (warning)
- 🔴 Fermé Définitif (danger)
- ⚫ Démantelé (dark)

---

### 3. Top 5 Opérateurs

**Pour chaque opérateur**:
- Position (1-5)
- Nom de l'opérateur
- Nombre total de dossiers
- 🟢 Nombre autorisés
- 🔴 Nombre rejetés (si > 0)

**Tri**: Par nombre total de dossiers (DESC)

---

### 4. Top 5 Motifs de Rejet

**Pour chaque motif**:
- Position (1-5)
- Motif court (100 premiers caractères)
- 🔴 Nombre d'occurrences
- Texte complet si > 100 caractères (tronqué à 150)

**Source**: Table `decisions` où `decision = 'refuse'`

**Fallback**: Si aucun motif, affiche 5 motifs génériques avec 0 occurrences

---

## 🔧 Dépendances

### Base de Données

**Colonnes requises dans `dossiers`**:
- `statut_operationnel` (ENUM)
- `date_fermeture` (DATE)
- `operateur_proprietaire` (VARCHAR)
- `nom_demandeur` (VARCHAR)

**Colonnes requises dans `decisions`**:
- `decision` (ENUM: 'approuve', 'refuse')
- `motif` (TEXT)

**Colonnes requises dans `paiements`**:
- `date_paiement` (DATE)
- `montant` (DECIMAL)

### CSS/JS

**Bootstrap 5** (déjà inclus):
- Cards
- Badges
- Progress bars
- Tables

**Font Awesome 6** (déjà inclus):
- Icônes

---

## 🧪 Test

### Vérifier l'affichage

1. Se connecter avec le rôle approprié
2. Aller sur le dashboard
3. Vérifier que les 4 sections s'affichent
4. Vérifier les données (même si vides, doit afficher message)

### Test avec données vides

Si aucune donnée, doit afficher:
- **Opérationnelles**: "Aucune infrastructure autorisée"
- **Fermées**: "Aucune infrastructure fermée ou démantelée" ✅
- **Opérateurs**: "Aucun opérateur trouvé"
- **Motifs**: "Aucun rejet enregistré" 😊

---

## 📱 Responsive

### Desktop (> 992px)
- 2 colonnes (col-lg-6)
- Cards côte à côte

### Tablet (768px - 991px)
- 2 colonnes adaptées
- Tableaux scrollables

### Mobile (< 768px)
- 1 colonne (col-12 par défaut)
- Cards empilées
- Tableaux en mode scroll horizontal

---

## 🎯 Résultat Visuel

```
┌─────────────────────────────────────────────────────────────────┐
│  📊 Statistiques Avancées                                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────────┐  ┌────────────────────────┐       │
│  │ 🏭 Infrastructures     │  │ 🚫 Fermées/Démantelées│       │
│  │    Opérationnelles     │  │                        │       │
│  │                        │  │  Table avec badges     │       │
│  │  ✅ 45  ⏸️ 3          │  │                        │       │
│  │  🚫 2   💥 1          │  └────────────────────────┘       │
│  │                        │                                    │
│  │  ████████░░ 88%        │                                    │
│  └────────────────────────┘                                    │
│                                                                 │
│  ┌────────────────────────┐  ┌────────────────────────┐       │
│  │ 🏢 Top 5 Opérateurs    │  │ ⚠️ Top 5 Motifs Rejet │       │
│  │                        │  │                        │       │
│  │  1️⃣ TOTAL           │  │  1️⃣ Non-conformité    │       │
│  │     12 dossiers        │  │     8 occurrences      │       │
│  │                        │  │                        │       │
│  │  2️⃣ BOCOM           │  │  2️⃣ Docs incomplets   │       │
│  │     8 dossiers         │  │     5 occurrences      │       │
│  └────────────────────────┘  └────────────────────────┘       │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🚀 Déploiement

### Checklist d'intégration

Pour chaque dashboard (Chef, Sous-Dir, Dir, Ministre):

- [ ] Fichier ouvert dans l'éditeur
- [ ] Trouver la ligne `require_once '../../includes/footer.php';`
- [ ] Ajouter juste avant:
  ```php
  <!-- Statistiques Avancées -->
  <div class="container-fluid mt-4">
      <h2 class="h4 mb-3">
          <i class="fas fa-chart-bar"></i> Statistiques Avancées
      </h2>
      <?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
  </div>
  ```
- [ ] Sauvegarder
- [ ] Tester l'affichage

---

## 📝 Notes Importantes

1. **Performances**: Les requêtes sont optimisées avec GROUP BY et LIMIT
2. **Sécurité**: Toutes les sorties utilisent `sanitize()`
3. **Fallback**: Messages appropriés si aucune donnée
4. **Compatibilité**: Fonctionne même si colonnes manquantes (NULL)

---

**Date**: 5 Janvier 2025
**Version**: 1.0
**Auteur**: Équipe Dev SGDI
