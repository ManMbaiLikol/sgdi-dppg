# 📊 DASHBOARDS AVANCÉS - DOCUMENTATION

## Vue d'Ensemble

Les dashboards avancés utilisent **Chart.js 4.4.0** pour fournir des graphiques interactifs et des visualisations de données en temps réel.

---

## 🎯 Dashboards Disponibles

### 1. Dashboard Admin Avancé
**Fichier**: `modules/admin/dashboard_avance.php`

**Accès**:
- Rôle: `admin`
- URL: `/modules/admin/dashboard_avance.php`
- Lien depuis dashboard: Bouton "Dashboard Avancé"

**KPIs Affichés**:
- Total Dossiers
- Utilisateurs Actifs
- Paiements
- Montant Total (FCFA)

**Graphiques Disponibles** (6):

1. **Répartition par Statut** (Donut)
   - Type: Doughnut chart
   - Données: Tous les statuts avec comptage
   - Couleurs: Code couleur par statut
   - Position légende: Droite

2. **Types d'Infrastructure** (Pie)
   - Type: Pie chart
   - Données: Répartition par type d'infrastructure
   - Position légende: Droite

3. **Évolution Mensuelle** (Barres)
   - Type: Bar chart (empilé)
   - Période: 6 derniers mois
   - Datasets: Créés, Approuvés, Rejetés
   - Axe Y: Commence à 0

4. **Taux d'Approbation** (Gauge)
   - Type: Doughnut (180°)
   - Affichage: Pourcentage au centre
   - Couleur dynamique:
     - Vert: ≥75%
     - Jaune: ≥50%
     - Rouge: <50%

5. **Top 10 Régions** (Barres Horizontales)
   - Type: Horizontal bar
   - Données: 10 régions les plus actives
   - Couleurs: Arc-en-ciel

6. **Temps Moyen de Traitement** (Ligne)
   - Type: Line chart
   - Période: 6 derniers mois
   - Unité: Jours
   - Zone remplie: Oui

---

### 2. Dashboard Chef Service Avancé
**Fichier**: `modules/chef_service/dashboard_avance.php`

**Accès**:
- Rôle: `chef_service`
- URL: `/modules/chef_service/dashboard_avance.php`

**KPIs Affichés**:
- Total Dossiers
- En Cours
- Ce Mois
- À Viser

**Graphiques Disponibles** (5):

1. **État des Dossiers** (Donut)
   - Type: Doughnut chart
   - Données: Répartition des statuts
   - Tooltip: Affiche pourcentage

2. **Taux d'Approbation** (Gauge)
   - Type: Demi-cercle (180°)
   - Calcul: Basé sur toutes les décisions
   - Affichage: Nombre de décisions total

3. **Activité (30 derniers jours)** (Ligne)
   - Type: Line chart
   - Données: Dossiers créés par jour
   - Tension: 0.4 (courbe lisse)
   - Zone remplie: Oui

4. **Par Type** (Pie)
   - Type: Pie chart
   - Données: Types d'infrastructure

5. **Top 10 Régions** (Barres)
   - Type: Horizontal bar
   - Données: Régions les plus actives

---

## 📚 Bibliothèque Chart.js

**Fichier**: `assets/js/charts.js`

### Fonctions Disponibles

#### 1. `createStatutChart(canvasId, data)`
Crée un graphique donut pour les statuts.
```javascript
// Format data:
[{
    label: 'Brouillon',
    value: 12,
    color: '#95a5a6'
}, ...]
```

#### 2. `createEvolutionChart(canvasId, data)`
Crée un graphique en barres pour l'évolution mensuelle.
```javascript
// Format data:
[{
    month: 'Jan 2025',
    crees: 15,
    approuves: 10,
    rejetes: 2
}, ...]
```

#### 3. `createTempsTraitementChart(canvasId, data)`
Graphique en ligne pour le temps de traitement.
```javascript
// Format data:
[{
    month: 'Jan 2025',
    duree: 14.5
}, ...]
```

#### 4. `createRegionsChart(canvasId, data)`
Barres horizontales pour les régions.
```javascript
// Format data:
[{
    region: 'Littoral',
    count: 45
}, ...]
```

#### 5. `createTypesChart(canvasId, data)`
Graphique camembert pour les types.
```javascript
// Format data:
[{
    type: 'Station-service',
    count: 30
}, ...]
```

#### 6. `createTauxReussiteGauge(canvasId, percentage)`
Gauge semi-circulaire pour taux de réussite.
```javascript
// Example:
createTauxReussiteGauge('chartTaux', 78.5);
```

#### 7. `createPerformanceRadar(canvasId, data)`
Graphique radar pour performance (non utilisé actuellement).

---

## 🎨 Code Couleurs des Statuts

```javascript
const colors = {
    'brouillon': '#95a5a6',          // Gris
    'en_cours': '#3498db',           // Bleu
    'paye': '#9b59b6',               // Violet
    'analyse_daj': '#e67e22',        // Orange
    'inspecte': '#f39c12',           // Jaune orangé
    'validation_chef_commission': '#16a085',  // Turquoise
    'visa_chef_service': '#27ae60',  // Vert
    'visa_sous_directeur': '#2ecc71', // Vert clair
    'visa_directeur': '#1abc9c',     // Cyan
    'autorise': '#2ecc71',           // Vert
    'rejete': '#e74c3c',             // Rouge
    'en_huitaine': '#e67e22'         // Orange
}
```

---

## 💾 Requêtes SQL Utilisées

### Répartition par Statut
```sql
SELECT statut, COUNT(*) as count
FROM dossiers
GROUP BY statut
ORDER BY count DESC
```

### Évolution Mensuelle (6 mois)
```sql
SELECT DATE_FORMAT(date_creation, '%Y-%m') as month,
       COUNT(*) as crees
FROM dossiers
WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY month
ORDER BY month ASC
```

### Top 10 Régions
```sql
SELECT region, COUNT(*) as count
FROM dossiers
WHERE region IS NOT NULL AND region != ''
GROUP BY region
ORDER BY count DESC
LIMIT 10
```

### Taux d'Approbation
```sql
SELECT
    SUM(CASE WHEN decision = 'approuve' THEN 1 ELSE 0 END) as approuves,
    COUNT(*) as total
FROM decisions
```

### Temps Moyen de Traitement
```sql
SELECT
    DATE_FORMAT(dec.date_decision, '%Y-%m') as month,
    AVG(DATEDIFF(dec.date_decision, d.date_creation)) as duree
FROM decisions dec
INNER JOIN dossiers d ON dec.dossier_id = d.id
WHERE dec.date_decision >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY month
ORDER BY month ASC
```

### Activité Récente (30 jours)
```sql
SELECT DATE(date_creation) as jour, COUNT(*) as count
FROM dossiers
WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY jour
ORDER BY jour ASC
```

---

## 🔧 Configuration Chart.js

### CDN Utilisé
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

### Configuration Globale
```javascript
Chart.defaults.font.family = 'Arial, sans-serif';
Chart.defaults.plugins.legend.display = true;
Chart.defaults.plugins.legend.position = 'bottom';
```

### Options Communes
- **Responsive**: `true`
- **maintainAspectRatio**: `false`
- **Hauteur canvas**: 300-350px

---

## 🚀 Utilisation

### 1. Dans un nouveau dashboard PHP

```php
<?php
require_once '../../includes/auth.php';
requireRole('votre_role');

// Préparer les données
$sql = "SELECT statut, COUNT(*) as count FROM dossiers GROUP BY statut";
$stmt = $pdo->query($sql);
$data = [];
while ($row = $stmt->fetch()) {
    $data[] = [
        'label' => getStatutLabel($row['statut']),
        'value' => (int)$row['count'],
        'color' => $colors[$row['statut']] ?? '#95a5a6'
    ];
}

require_once '../../includes/header.php';
?>

<!-- Canvas pour le graphique -->
<canvas id="monGraphique" style="height: 300px;"></canvas>

<!-- Charger Chart.js et helpers -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo url('assets/js/charts.js'); ?>"></script>

<script>
const data = <?php echo json_encode($data); ?>;

document.addEventListener('DOMContentLoaded', function() {
    createStatutChart('monGraphique', data);
});
</script>
```

### 2. Créer un graphique personnalisé

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monCanvas');

    new Chart(ctx, {
        type: 'bar',  // ou 'line', 'pie', 'doughnut', 'radar'
        data: {
            labels: ['Jan', 'Fev', 'Mar'],
            datasets: [{
                label: 'Données',
                data: [12, 19, 3],
                backgroundColor: 'rgba(54, 162, 235, 0.7)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
```

---

## 📊 Navigation

### Depuis Dashboard Standard
Tous les utilisateurs avec accès voient un bouton "Dashboard Avancé" dans leur menu d'actions rapides (si implémenté pour leur rôle).

### Retour Dashboard Standard
Bouton "Dashboard Standard" en haut à droite de chaque dashboard avancé.

---

## ✅ Tests à Effectuer

1. **Affichage des graphiques**
   - Vérifier que tous les graphiques se chargent
   - Tester la réactivité (resize fenêtre)

2. **Données en temps réel**
   - Créer un nouveau dossier → vérifier mise à jour stats
   - Valider un dossier → vérifier taux d'approbation

3. **Interactions**
   - Hover sur graphiques → tooltips affichés
   - Click sur légende → toggle dataset

4. **Performance**
   - Temps de chargement < 2 secondes
   - Pas de lag lors des interactions

---

## 🐛 Dépannage

### Graphique ne s'affiche pas
- Vérifier que Chart.js CDN est chargé
- Vérifier console navigateur pour erreurs JS
- S'assurer que `charts.js` est chargé après Chart.js

### Données incorrectes
- Vérifier requêtes SQL dans console
- S'assurer que `json_encode()` est utilisé pour PHP → JS
- Vérifier format des données (voir exemples ci-dessus)

### Graphique trop petit/grand
- Ajuster `height` du canvas (300-400px recommandé)
- Vérifier `maintainAspectRatio: false` dans options

---

## 📈 Améliorations Futures

- [ ] Filtres par période personnalisée
- [ ] Export graphiques en PNG
- [ ] Graphiques additionnels (sparklines, gauges)
- [ ] Animations personnalisées
- [ ] Mode sombre pour les graphiques
- [ ] Comparaisons année sur année
- [ ] Prédictions basées sur tendances

---

**Date de création**: 2025-01-05
**Version**: 1.0
**Dernière mise à jour**: 2025-01-05
