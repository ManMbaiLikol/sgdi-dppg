# 🎨 Guide UX/UI - SGDI

Guide complet des améliorations d'expérience utilisateur et d'interface implémentées dans le SGDI.

---

## 📋 Table des matières

1. [Thème sombre/clair](#thème-sombreclair)
2. [Tableaux interactifs](#tableaux-interactifs)
3. [Wizard de création](#wizard-de-création)
4. [Responsive design](#responsive-design)
5. [Progressive Web App](#progressive-web-app)
6. [Accessibilité](#accessibilité)

---

## 🌓 Thème sombre/clair

### Fonctionnalités

- **Changement instantané** : Cliquez sur le bouton soleil/lune dans la navbar
- **Sauvegarde automatique** : Votre préférence est enregistrée dans localStorage
- **Détection système** : S'adapte automatiquement à la préférence de votre OS
- **Transitions fluides** : Changement de thème animé en 0.3s

### Utilisation

```javascript
// Changer le thème manuellement
SGDI.setTheme('dark'); // ou 'light'

// Basculer entre les thèmes
SGDI.toggleTheme();

// Obtenir le thème actuel
const currentTheme = SGDI.getTheme();
```

### Personnalisation

Les variables CSS sont définies dans `assets/css/theme.css` :

```css
:root {
    --bg-primary: #ffffff;
    --text-primary: #212529;
    --link-color: #0d6efd;
    /* ... */
}

[data-theme="dark"] {
    --bg-primary: #1a1d20;
    --text-primary: #e9ecef;
    --link-color: #4d9eff;
    /* ... */
}
```

### Éléments supportés

- ✅ Cartes et conteneurs
- ✅ Formulaires et inputs
- ✅ Tableaux
- ✅ Modales
- ✅ Dropdowns
- ✅ Alertes
- ✅ DataTables
- ✅ Cartes Leaflet
- ✅ Badges et boutons

---

## 📊 Tableaux interactifs

### DataTables avancés

Tous les tableaux peuvent utiliser la classe `.datatable` pour bénéficier de :

- **Tri dynamique** : Cliquez sur les en-têtes de colonnes
- **Recherche globale** : Cherchez dans toutes les colonnes
- **Pagination** : 10, 25, 50, 100 ou tous les résultats
- **Export** : Excel, PDF, Copier, Imprimer
- **Colonnes personnalisables** : Afficher/masquer les colonnes
- **Recherche par colonne** : Filtres inline dans les headers

### Classes disponibles

```html
<!-- Tableau standard avec toutes les fonctionnalités -->
<table class="datatable">...</table>

<!-- Tableau simple sans pagination (pour petites listes) -->
<table class="datatable-simple">...</table>

<!-- Tableau sans export (pour données sensibles) -->
<table class="datatable-no-export">...</table>
```

### Exports

Les boutons d'export sont automatiquement configurés :

| Bouton | Format | Description |
|--------|--------|-------------|
| **Copier** | Clipboard | Copie les données dans le presse-papier |
| **Excel** | .xlsx | Export vers Microsoft Excel |
| **PDF** | .pdf | Export en format PDF (paysage A4) |
| **Imprimer** | Print | Impression optimisée |
| **Colonnes** | Vue | Afficher/masquer des colonnes |

### Fonctions JavaScript

```javascript
// Initialiser un tableau personnalisé
SGDI.initDataTable('#myTable', {
    order: [[0, 'desc']],
    pageLength: 50
});

// Recharger les données
SGDI.reloadDataTable('#myTable');

// Ajouter une recherche par colonne
SGDI.addColumnSearch('#myTable', '#searchInput', 0);

// Ajouter des filtres par statut
SGDI.addStatutFilter('#myTable');

// Ajouter des recherches dans les headers
SGDI.addHeaderSearch('#myTable');
```

### Tableau de dossiers spécialisé

```javascript
SGDI.initDossiersTable('#dossiersTable', '/api/dossiers.php');
```

Génère automatiquement :
- Colonnes formatées (numéro, demandeur, type, statut, date)
- Badges colorés par statut
- Actions (bouton Voir)
- Tri par date décroissante

---

## 🧙‍♂️ Wizard de création

### Accès

- Menu → Dossiers → Créer un dossier
- URL : `/modules/dossiers/create_wizard.php`

### Étapes du wizard

1. **Type d'infrastructure** : Sélection type et sous-type
2. **Demandeur** : Informations du demandeur
3. **Localisation** : Adresse et GPS
4. **Détails spécifiques** : Champs selon le type
5. **Vérification** : Récapitulatif avant soumission

### Fonctionnalités

#### ✅ Validation en temps réel

- Validation instantanée des champs requis
- Messages d'erreur contextuels
- Vérification du format email
- Validation du téléphone camerounais (6XXXXXXXX)
- Blocage de navigation si champ invalide

#### 💾 Sauvegarde automatique

- **Auto-save** : Sauvegarde toutes les 2 secondes après inactivité
- **Brouillons** : Stockés dans la table `dossiers_brouillons`
- **Indicateur visuel** : "Brouillon sauvegardé" en haut à droite
- **Reprise automatique** : Restauration au retour

#### 📋 Barre de progression

- Pourcentage de complétion
- Étape X sur 5
- Indicateurs visuels par étape

#### 🎯 Champs conditionnels

Les champs spécifiques apparaissent selon le type :

- **Station-service** : Opérateur propriétaire
- **Point consommateur** : Entreprise bénéficiaire + Contrat
- **Dépôt GPL** : Entreprise installatrice
- **Centre emplisseur** : Opérateur de gaz OU Entreprise constructrice

#### 📄 Récapitulatif final

Avant soumission, affichage de toutes les données saisies organisées par section.

### Navigation

- **Suivant** : Passe à l'étape suivante (avec validation)
- **Précédent** : Retour à l'étape précédente
- **Annuler** : Retour à la liste (brouillon conservé)
- **Créer le dossier** : Soumission finale (étape 5)

---

## 📱 Responsive design

### Breakpoints

Le design s'adapte à toutes les tailles d'écran :

| Device | Largeur | Optimisations |
|--------|---------|---------------|
| **Smartphone** | < 576px | Navigation compacte, cartes empilées |
| **Smartphone paysage** | ≥ 576px | Meilleure utilisation de l'espace |
| **Tablette** | ≥ 768px | Cartes en 2 colonnes |
| **Desktop** | ≥ 992px | Layout standard |
| **Grand écran** | ≥ 1200px | Espacement optimal |

### Optimisations mobile

#### Navigation
- Navbar compacte avec menu hamburger
- Dropdown pleine largeur
- Badge de notifications réduit
- Bouton de thème simplifié

#### Cartes et conteneurs
- Padding réduit (0.75rem)
- Border-radius ajusté
- Titres plus petits
- Meilleure hiérarchie

#### Tableaux
- Scroll horizontal automatique
- Police réduite (0.875rem)
- Padding des cellules optimisé
- Boutons d'export empilés

#### Formulaires
- Font-size 16px (évite le zoom iOS)
- Boutons pleine largeur
- Input groups empilés
- Labels verticaux

#### Wizard
- Étapes en grille 5 colonnes
- Cercles plus petits (30px)
- Labels tronqués
- Boutons pleine largeur

#### Cartes interactives
- Hauteur réduite (300px)
- Contrôles repositionnés
- Popups compacts

### Touch optimizations

Pour les appareils tactiles :

- **Zone de touch minimale** : 44x44px (Apple/Google)
- **Feedback visuel** : Scale 0.98 au tap
- **Désactivation du hover** : Sur écrans tactiles
- **Scroll fluide** : -webkit-overflow-scrolling

### Mode paysage mobile

Adapté pour smartphones en orientation paysage :
- Hauteur navbar réduite
- Modales scrollables (90vh max)
- Wizard content ajusté (300px min)

### Print styles

Optimisation pour l'impression :

- Suppression navbar, boutons, actions
- Thème clair forcé
- Bordures visibles
- URLs des liens affichées
- Page break après chaque carte

---

## 📲 Progressive Web App

### Installation

L'application peut être installée sur :

- ✅ Android (Chrome, Edge, Samsung Internet)
- ✅ iOS/iPadOS (Safari)
- ✅ Windows (Chrome, Edge)
- ✅ macOS (Chrome, Edge, Safari)
- ✅ Linux (Chrome, Edge, Firefox)

### Fonctionnalités PWA

#### 🔌 Mode hors ligne

- **Service Worker** : Cache intelligent des ressources
- **Stratégie** : Network First, puis Cache
- **Page offline** : `/offline.html` avec design personnalisé
- **Synchronisation** : Auto-sync au retour en ligne

#### 💾 Cache automatique

Ressources mises en cache :
- Pages récemment visitées
- CSS et JavaScript
- Bootstrap et Font Awesome
- jQuery et DataTables
- Images et icônes

#### 📱 Raccourcis d'application

4 raccourcis disponibles dans le launcher :

1. **Nouveau dossier** → `/modules/dossiers/create_wizard.php`
2. **Liste des dossiers** → `/modules/dossiers/list.php`
3. **Huitaines urgentes** → `/modules/huitaine/list.php?filter=urgents`
4. **Carte** → `/modules/carte/index.php`

#### 🔔 Notifications push

Support des notifications :
- Huitaines urgentes
- Changements de statut
- Alertes système
- Actions rapides dans la notification

#### 🎨 Intégration système

- **Icônes** : 8 tailles (72px à 512px)
- **Splash screen** : Généré automatiquement
- **Thème** : #0d6efd (bleu Bootstrap)
- **Mode standalone** : Barre d'adresse masquée
- **Orientation** : Portrait par défaut

### Prompt d'installation

Un banner apparaît après 30 secondes :

- **Message** : "Installer SGDI - Installez l'application..."
- **Bouton** : "Installer" (déclenche le prompt natif)
- **Fermeture** : Préférence sauvegardée dans localStorage
- **Non-intrusif** : Ne s'affiche qu'une fois

### Gestion des mises à jour

- **Détection automatique** : Vérification au chargement
- **Prompt utilisateur** : "Nouvelle version disponible. Actualiser ?"
- **Mise à jour silencieuse** : Si l'utilisateur accepte
- **Reload automatique** : Après activation du nouveau SW

### Service Worker

Fichier : `/service-worker.js`

Fonctionnalités :
- ✅ Cache stratégique (CACHE_NAME: sgdi-v1.0)
- ✅ Network first strategy
- ✅ Fallback vers cache si offline
- ✅ Synchronisation en arrière-plan
- ✅ Notifications push
- ✅ IndexedDB pour données pendantes

### Commandes utiles

```javascript
// Vider le cache
navigator.serviceWorker.controller.postMessage({
    type: 'CLEAR_CACHE'
});

// Forcer la mise à jour
navigator.serviceWorker.controller.postMessage({
    type: 'SKIP_WAITING'
});

// Vérifier l'installation
if (localStorage.getItem('pwa-installed') === 'true') {
    console.log('PWA installée');
}

// Détecter le mode standalone
if (window.matchMedia('(display-mode: standalone)').matches) {
    console.log('App en mode standalone');
}
```

---

## ♿ Accessibilité

### Standards WCAG 2.1

L'application respecte les recommandations **WCAG 2.1 Niveau AA** :

#### ✅ Contraste

- Ratio minimum 4.5:1 pour le texte normal
- Ratio minimum 3:1 pour le texte large
- Vérification en thème sombre et clair
- Mode high-contrast supporté

#### ✅ Navigation clavier

- Tous les éléments interactifs accessibles au clavier
- Ordre de tabulation logique
- Focus visible (outline 2px bleu)
- Skip links pour navigation rapide

#### ✅ Lecteurs d'écran

- Attributs ARIA appropriés
- Labels explicites sur tous les formulaires
- Alt text sur toutes les images
- Rôles ARIA sur les éléments interactifs
- Live regions pour les alertes

#### ✅ Responsive et zoom

- Support zoom jusqu'à 200%
- Pas de scroll horizontal < 320px
- Font-size minimum 16px (mobile)
- Touch targets >= 44px

#### ✅ Mouvements et animations

- Respect de `prefers-reduced-motion`
- Animations désactivables
- Pas de clignotement > 3 fois/seconde
- Transitions fluides optionnelles

### Fonctionnalités d'accessibilité

#### Focus visible

```css
:focus-visible {
    outline: 2px solid var(--link-color);
    outline-offset: 2px;
}
```

#### Réduction de mouvement

```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

#### Contraste élevé

```css
@media (prefers-contrast: high) {
    .btn { border-width: 2px; }
    .form-control:focus { border-width: 3px; }
}
```

### Balises sémantiques

- `<header>`, `<nav>`, `<main>`, `<footer>`
- `<article>`, `<section>`, `<aside>`
- Heading hierarchy (H1 → H6)
- `<button>` pour les actions
- `<a>` pour la navigation

### Tests d'accessibilité

Outils recommandés :
- **axe DevTools** : Extension Chrome/Firefox
- **WAVE** : Web accessibility evaluation
- **Lighthouse** : Audit automatique
- **NVDA/JAWS** : Tests avec lecteurs d'écran

---

## 🚀 Performance

### Optimisations

- **Lazy loading** : Images et iframes
- **Code splitting** : JS chargé à la demande
- **Minification** : CSS et JS minifiés
- **Compression** : Gzip/Brotli recommandé
- **CDN** : Bootstrap, jQuery, Font Awesome
- **Cache** : Service Worker + Cache-Control

### Métriques cibles

| Métrique | Cible | Description |
|----------|-------|-------------|
| **FCP** | < 1.8s | First Contentful Paint |
| **LCP** | < 2.5s | Largest Contentful Paint |
| **FID** | < 100ms | First Input Delay |
| **CLS** | < 0.1 | Cumulative Layout Shift |
| **TTI** | < 3.8s | Time to Interactive |

### Lighthouse Score

Objectifs :
- **Performance** : > 90
- **Accessibilité** : > 95
- **Best Practices** : > 90
- **SEO** : > 90
- **PWA** : 100

---

## 📚 Ressources

### Fichiers CSS

- `assets/css/style.css` - Styles de base
- `assets/css/theme.css` - Système de thèmes
- `assets/css/responsive.css` - Responsive design

### Fichiers JavaScript

- `assets/js/app.js` - Fonctions globales
- `assets/js/theme.js` - Gestion du thème
- `assets/js/datatables-config.js` - Configuration DataTables
- `assets/js/wizard.js` - Wizard de création

### Fichiers PWA

- `manifest.json` - Manifest PWA
- `service-worker.js` - Service Worker
- `offline.html` - Page hors ligne
- `browserconfig.xml` - Configuration Microsoft

### Documentation externe

- [Bootstrap 5.1 Docs](https://getbootstrap.com/docs/5.1/)
- [DataTables Docs](https://datatables.net/manual/)
- [Font Awesome Icons](https://fontawesome.com/icons)
- [Leaflet Maps](https://leafletjs.com/)
- [PWA Builder](https://www.pwabuilder.com/)
- [WCAG 2.1](https://www.w3.org/WAI/WCAG21/quickref/)

---

## 🎯 Bonnes pratiques

### Pour les développeurs

1. **Toujours tester** sur mobile réel (pas seulement émulateur)
2. **Utiliser les classes Bootstrap** avant de créer du CSS custom
3. **Respecter les variables CSS** du système de thème
4. **Ajouter les attributs ARIA** sur nouveaux composants
5. **Tester avec Service Worker** désactivé (mode offline)
6. **Valider l'accessibilité** avec axe DevTools
7. **Optimiser les images** (WebP recommandé)
8. **Lazy load** les ressources lourdes

### Pour les utilisateurs

1. **Installer l'application** pour meilleur performance
2. **Utiliser le mode sombre** pour économiser batterie (OLED)
3. **Exporter en Excel** plutôt que PDF pour gros tableaux
4. **Activer les notifications** pour alertes huitaines
5. **Utiliser le wizard** plutôt que formulaire classique
6. **Sauvegarder les brouillons** avant de quitter
7. **Mettre à jour** quand notifié

---

**Version** : 1.0
**Date** : Octobre 2025
**Statut** : ✅ Production ready
