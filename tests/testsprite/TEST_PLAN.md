# TestSprite - Plan de Tests Automatisés SGDI

## Vue d'ensemble

Plan de tests automatisés E2E pour le Système de Gestion des Dossiers d'Implantation (SGDI) utilisant TestSprite avec Playwright.

---

## Objectifs des Tests

1. **Workflow complet** - Valider les 11 étapes du processus
2. **Contrôle d'accès** - Vérifier les permissions des 9 rôles utilisateurs
3. **Intégrité des données** - Valider la cohérence des données entre modules
4. **Notifications** - Tester le système de notifications email et in-app
5. **Documents** - Valider l'upload, versioning et téléchargement
6. **Système Huitaine** - Tester le compteur de 8 jours et alertes

---

## Architecture des Tests

```
tests/testsprite/
├── TEST_PLAN.md                          # Ce document
├── config/
│   └── testsprite.config.js              # Configuration Playwright
├── fixtures/
│   ├── users.json                        # Données utilisateurs test
│   ├── dossiers.json                     # Données dossiers test
│   └── documents/                        # Documents tests (PDF, images)
├── e2e/
│   ├── 01-authentication/
│   │   ├── login.spec.js                 # Tests connexion
│   │   └── role-access.spec.js           # Tests accès par rôle
│   ├── 02-workflow/
│   │   ├── creation-dossier.spec.js      # Étape 1: Création dossier
│   │   ├── constitution-commission.spec.js # Étape 2: Commission
│   │   ├── paiement.spec.js              # Étapes 3-4: Paiement
│   │   ├── analyse-daj.spec.js           # Étape 5: Analyse DAJ
│   │   ├── inspection.spec.js            # Étapes 6-7: Inspection
│   │   ├── validation-rapport.spec.js    # Étape 8: Validation
│   │   ├── circuit-visa.spec.js          # Étape 9: Visas
│   │   ├── decision-ministerielle.spec.js # Étape 10: Décision
│   │   └── registre-public.spec.js       # Étape 11: Publication
│   ├── 03-roles/
│   │   ├── chef-service.spec.js          # Tests rôle Chef Service
│   │   ├── billeteur.spec.js             # Tests rôle Billeteur
│   │   ├── chef-commission.spec.js       # Tests rôle Chef Commission
│   │   ├── cadre-daj.spec.js             # Tests rôle Cadre DAJ
│   │   ├── cadre-dppg.spec.js            # Tests rôle Inspecteur
│   │   ├── sous-directeur.spec.js        # Tests rôle Sous-Directeur
│   │   ├── directeur.spec.js             # Tests rôle Directeur
│   │   ├── ministre.spec.js              # Tests rôle Ministre
│   │   └── admin.spec.js                 # Tests rôle Admin
│   ├── 04-infrastructure-types/
│   │   ├── station-service.spec.js       # Implantation/Reprise SS
│   │   ├── point-consommateur.spec.js    # Implantation/Reprise PC
│   │   ├── depot-gpl.spec.js             # Dépôt GPL
│   │   └── centre-emplisseur.spec.js     # Centre emplisseur
│   ├── 05-commission/
│   │   ├── nomination-membres.spec.js    # Nomination 3 membres
│   │   ├── planification-visite.spec.js  # Planification
│   │   └── validation-rapport.spec.js    # Validation
│   ├── 06-documents/
│   │   ├── upload.spec.js                # Upload fichiers
│   │   ├── versioning.spec.js            # Gestion versions
│   │   ├── download.spec.js              # Téléchargement
│   │   └── validation-types.spec.js      # Validation types requis
│   ├── 07-huitaine/
│   │   ├── creation-huitaine.spec.js     # Création compteur
│   │   ├── notifications-j2.spec.js      # Alerte J-2
│   │   ├── notifications-j1.spec.js      # Alerte J-1
│   │   ├── notifications-j0.spec.js      # Alerte deadline
│   │   ├── regularisation.spec.js        # Régularisation
│   │   └── rejet-auto.spec.js            # Rejet automatique
│   ├── 08-notifications/
│   │   ├── email-paiement.spec.js        # Notif paiement
│   │   ├── email-visa.spec.js            # Notif visa
│   │   ├── email-decision.spec.js        # Notif décision
│   │   └── in-app-alerts.spec.js         # Notifications in-app
│   ├── 09-registre-public/
│   │   ├── access-public.spec.js         # Accès sans auth
│   │   ├── search.spec.js                # Recherche multi-critères
│   │   └── export.spec.js                # Export données
│   └── 10-security/
│       ├── csrf-protection.spec.js       # Protection CSRF
│       ├── sql-injection.spec.js         # Prévention SQL injection
│       ├── xss-protection.spec.js        # Protection XSS
│       ├── file-upload-security.spec.js  # Sécurité upload
│       └── session-management.spec.js    # Gestion sessions
└── utils/
    ├── helpers.js                        # Fonctions utilitaires
    ├── db-setup.js                       # Setup base de données test
    └── cleanup.js                        # Nettoyage après tests

```

---

## Scénarios de Tests Critiques

### 🔐 Scénario 1: Workflow Complet (11 étapes)

**Description:** Tester le parcours complet d'un dossier depuis la création jusqu'à la publication

**Étapes:**
1. Chef Service crée dossier + upload documents
2. Chef Service constitue commission (3 membres)
3. Système génère note de frais automatiquement
4. Billeteur enregistre paiement → notification envoyée
5. Cadre DAJ fait analyse juridique
6. Inspecteur DPPG fait contrôle complétude
7. Inspecteur DPPG réalise inspection + rapport
8. Chef Commission valide rapport
9. Circuit visa: Chef Service → Sous-Directeur → Directeur
10. Ministre prend décision (Approbation)
11. Système publie automatiquement au registre public

**Assertions:**
- Chaque étape ne peut être effectuée que par le bon rôle
- Les transitions de statut sont correctes
- Les notifications sont envoyées au bon moment
- Les données sont sauvegardées correctement
- Le registre public affiche la décision finale

---

### 🔒 Scénario 2: Contrôle d'Accès Strict par Commission

**Description:** Vérifier qu'un cadre DPPG ne voit QUE les dossiers où il est membre de commission

**Utilisateurs tests:**
- Christian ABANDA (ID: 27) - Cadre DPPG
- Salomon MAÏ (ID: 16) - Cadre DPPG

**Test:**
1. Créer 3 dossiers:
   - Dossier A: Commission avec Christian
   - Dossier B: Commission avec Salomon
   - Dossier C: Commission avec autre inspecteur
2. Se connecter comme Christian
3. Vérifier qu'il voit UNIQUEMENT le Dossier A
4. Tenter d'accéder directement au Dossier B par URL
5. Vérifier qu'il reçoit une erreur 403

**Assertions:**
- Liste dossiers filtrée correctement
- Accès direct bloqué
- Fonctions `getDossiers()` et `canAccessDossier()` cohérentes

---

### ⏱️ Scénario 3: Système Huitaine avec Régularisation

**Description:** Tester le compteur de 8 jours et le système de régularisation

**Étapes:**
1. Chef Service crée dossier incomplet (documents manquants)
2. Système crée "huitaine" automatiquement
3. Simuler passage du temps (J-2):
   - Vérifier notification envoyée
4. Simuler J-1:
   - Vérifier nouvelle notification
5. Chef Service régularise (upload documents manquants)
6. Vérifier que huitaine est marquée "régularisée"
7. Workflow continue normalement

**Scénario alternatif (rejet):**
1. Ne pas régulariser avant deadline
2. Vérifier rejet automatique du dossier au jour J
3. Vérifier notification de rejet envoyée

---

### 📄 Scénario 4: Types d'Infrastructure Spécifiques

**Description:** Valider les règles spécifiques pour chaque type d'infrastructure

**Test Point Consommateur:**
1. Sélectionner type "Implantation point consommateur"
2. Vérifier champs requis:
   - Opérateur (requis)
   - Entreprise bénéficiaire (requis)
   - Contrat de livraison (requis)
3. Vérifier documents requis spécifiques au type
4. Tenter de créer sans contrat → erreur attendue

**Test Station-Service:**
1. Sélectionner type "Implantation station-service"
2. Vérifier champs requis:
   - Opérateur propriétaire (requis)
   - Entreprise bénéficiaire (non affiché)
3. Créer avec données valides
4. Vérifier sauvegarde correcte

---

### 📧 Scénario 5: Notifications Email Automatiques

**Description:** Valider l'envoi automatique des emails aux bons moments

**Test:**
1. Billeteur enregistre paiement
   - Vérifier email envoyé à Chef Service
   - Vérifier email envoyé au demandeur
   - Contenu: "Paiement enregistré, inspection programmée"
2. Directeur accorde visa
   - Vérifier email envoyé à Ministre
   - Contenu: "Dossier prêt pour décision"
3. Ministre approuve dossier
   - Vérifier email envoyé au demandeur
   - Contenu: "Décision favorable, consulter registre public"

**Assertions:**
- Emails envoyés dans les 30 secondes
- Contenu HTML correct avec logo
- Destinataires corrects
- Liens fonctionnels

---

### 🔍 Scénario 6: Registre Public Sans Authentification

**Description:** Vérifier que n'importe qui peut consulter le registre public

**Test:**
1. Naviguer vers `/public/registre.php` sans être connecté
2. Vérifier accès autorisé (pas de redirection login)
3. Faire recherche par numéro dossier
4. Faire recherche par localisation
5. Exporter résultats en PDF
6. Vérifier que seules les décisions publiées sont visibles

**Assertions:**
- Aucune authentification requise
- Recherche fonctionne correctement
- Export PDF généré
- Données sensibles non affichées (emails, téléphones internes)

---

## Données de Test

### Utilisateurs Tests

```json
{
  "admin": {
    "email": "admin@minee.cm",
    "password": "Admin@2025",
    "role": "admin_systeme"
  },
  "chef_service": {
    "email": "chef.sdtd@minee.cm",
    "password": "Chef@2025",
    "role": "chef_service"
  },
  "billeteur": {
    "email": "billeteur@minee.cm",
    "password": "Billet@2025",
    "role": "billeteur"
  },
  "christian_abanda": {
    "email": "christian.abanda@minee.cm",
    "password": "Christian@2025",
    "role": "cadre_dppg",
    "id": 27
  },
  "salomon_mai": {
    "email": "salomon.mai@minee.cm",
    "password": "Salomon@2025",
    "role": "cadre_dppg",
    "id": 16
  },
  "chef_commission": {
    "email": "chef.commission@minee.cm",
    "password": "ChefCom@2025",
    "role": "chef_commission"
  },
  "cadre_daj": {
    "email": "daj@minee.cm",
    "password": "DAJ@2025",
    "role": "cadre_daj"
  },
  "sous_directeur": {
    "email": "sous.directeur@minee.cm",
    "password": "SousDir@2025",
    "role": "sous_directeur"
  },
  "directeur": {
    "email": "directeur.dppg@minee.cm",
    "password": "Directeur@2025",
    "role": "directeur"
  },
  "ministre": {
    "email": "cabinet.ministre@minee.cm",
    "password": "Ministre@2025",
    "role": "ministre"
  }
}
```

---

## Configuration TestSprite

### Variables d'environnement (.env.test)

```
# Base URL
BASE_URL=http://localhost/dppg-implantation

# Base de données test
DB_HOST=localhost
DB_NAME=sgdi_test
DB_USER=root
DB_PASSWORD=

# Email (mode test)
MAILER_HOST=smtp.mailtrap.io
MAILER_USER=test_user
MAILER_PASSWORD=test_password
MAILER_FROM=noreply@sgdi-test.cm

# TestSprite API Key
TESTSPRITE_API_KEY=sk-user-DVGGxC77HpnDgW9qHA7DewgRCLSSyKXuETEiz1JWHQtjkADDBe6dTCSesgFFy704juRzybqrvs9nfE_52M7VHi0Be-BcoKwScou8ngV2-kiDqquREgupcRgWzwHq7Wh_u-g
```

---

## Métriques de Succès

### Couverture des Tests

- ✅ **Workflow:** 100% des 11 étapes testées
- ✅ **Rôles:** 100% des 9 rôles testés
- ✅ **Types Infrastructure:** 100% des 6 types testés
- ✅ **Sécurité:** CSRF, SQL Injection, XSS, Upload
- ✅ **Notifications:** Email et in-app

### Performance

- Temps d'exécution total: < 20 minutes
- Taux de réussite: > 95%
- Aucun test flaky (instable)

---

## Exécution des Tests

### Via TestSprite Web Portal

1. Aller sur https://testsprite.com
2. Se connecter avec compte
3. Créer nouveau projet "SGDI-DPPG"
4. Uploader le plan de tests
5. Lancer génération automatique des tests
6. Exécuter les tests dans le cloud

### Via MCP Server (IDE Integration)

```bash
# Dans votre IDE (Cursor, VSCode, etc.)
# Installer le MCP server TestSprite
npm install -g @testsprite/mcp-server

# Lancer les tests
testsprite run --project sgdi-dppg --env test
```

### En local avec Playwright

```bash
cd tests/testsprite
npm install
npx playwright install

# Tous les tests
npm test

# Tests spécifiques
npm test -- e2e/02-workflow/

# Mode debug
npm run test:debug

# Rapport HTML
npm run test:report
```

---

## Maintenance des Tests

### Quand mettre à jour les tests?

1. **Nouveau rôle ajouté** → Créer tests dans `03-roles/`
2. **Workflow modifié** → Mettre à jour `02-workflow/`
3. **Nouveau type infrastructure** → Ajouter dans `04-infrastructure-types/`
4. **Règle de permission changée** → Mettre à jour `03-roles/` et `10-security/`

### CI/CD Integration

```yaml
# .github/workflows/tests.yml
name: TestSprite E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Start WAMP
        run: |
          sudo service mysql start
          php -S localhost:8000 &
      - name: Run TestSprite
        run: |
          cd tests/testsprite
          npm install
          npx playwright test
```

---

## Priorités de Tests

### Priorité 1 (Critique - Exécuter à chaque commit)
- ✅ Authentification et contrôle d'accès
- ✅ Workflow création dossier → paiement → inspection → décision
- ✅ Permissions par commission (cadre DPPG)

### Priorité 2 (Important - Exécuter quotidiennement)
- 🔶 Circuit complet des visas
- 🔶 Système huitaine avec régularisation
- 🔶 Notifications email automatiques

### Priorité 3 (Normal - Exécuter hebdomadairement)
- 🔷 Tests de tous les types d'infrastructure
- 🔷 Registre public et recherche
- 🔷 Gestion documents et versioning

---

**Dernière mise à jour:** 24 octobre 2025
**Version:** 1.0.0
