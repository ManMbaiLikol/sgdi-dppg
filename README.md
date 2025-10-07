# SGDI - Système de Gestion des Dossiers d'Implantation

![Version](https://img.shields.io/badge/version-1.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1)
![License](https://img.shields.io/badge/license-Proprietary-red)

Application web complète de gestion des dossiers d'implantation d'infrastructures pétrolières pour le **Ministère de l'Eau et de l'Énergie (MINEE)** - **Direction du Pétrole, du Produit Pétrolier et du Gaz (DPPG)** - République du Cameroun.

---

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Technologies](#technologies)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Documentation](#documentation)
- [Support](#support)

---

## ✨ Fonctionnalités

### 🎯 Gestion complète du workflow (11 étapes)

1. **Création du dossier** + upload documents
2. **Constitution commission** (3 membres obligatoires)
3. **Génération automatique** note de frais
4. **Enregistrement paiement** → notification automatique
5. **Analyse juridique** (DAJ)
6. **Contrôle complétude** (DPPG)
7. **Inspection terrain** + rapport
8. **Validation rapport** (Chef commission)
9. **Circuit visa** (Chef Service → Sous-Dir → Dir)
10. **Décision ministérielle** (Approbation/Refus)
11. **Publication automatique** au registre public

### 👥 10 Rôles utilisateurs

1. **Chef de Service SDTD** - Gestion centralisée
2. **Billeteur DPPG** - Enregistrement paiements
3. **Cadre DAJ** - Analyse juridique
4. **Cadre DPPG** - Inspections terrain
5. **Chef de Commission** - Validation rapports
6. **Sous-Directeur SDTD** - 2ème niveau visa
7. **Directeur DPPG** - 3ème niveau visa + transmission
8. **Cabinet/Ministre** - Décision finale
9. **Admin Système** - Gestion complète
10. **Lecteur Public** - Consultation registre

### 🏗️ 6 Types d'infrastructures

- **Station-service** (Implantation / Reprise)
- **Point consommateur** (Implantation / Reprise)
- **Dépôt GPL** (Implantation)
- **Centre emplisseur** (Implantation)

### 🌟 Fonctionnalités avancées

- ⏰ **Système "Huitaine"** avec notifications J-2, J-1, J
- 🗺️ **Géolocalisation GPS** et carte interactive
- 📧 **Notifications email** automatiques
- 📊 **Tableaux de bord** personnalisés par rôle
- 📈 **Statistiques** en temps réel
- 📄 **Export PDF/Excel**
- 🔍 **Registre public** sans authentification
- 📱 **PWA** (Progressive Web App)
- 🎨 **Interface moderne** et responsive
- 🔒 **Sécurité renforcée** (CSRF, XSS, SQL injection)

---

## 🛠️ Technologies

### Backend
- **PHP** 7.4+
- **MySQL** 5.7+ / MariaDB 10.3+
- **PDO** (requêtes préparées)

### Frontend
- **HTML5** / **CSS3**
- **JavaScript** (Vanilla)
- **Bootstrap** 5.1
- **Font Awesome** 6.0
- **Leaflet** (cartes interactives)
- **Chart.js** (graphiques)

### Outils
- **Apache** 2.4+ / **Nginx**
- **Git** (versioning)

---

## 📦 Installation

### Méthode 1: Installation automatique (Recommandée)

1. **Cloner ou télécharger** le projet
   ```bash
   git clone https://github.com/minee-dppg/sgdi.git
   cd sgdi
   ```

2. **Créer la base de données**
   ```sql
   CREATE DATABASE sgdi_mvp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Lancer l'installateur**
   - Accéder à `http://localhost/sgdi/install.php`
   - Suivre les étapes:
     1. Vérification prérequis
     2. Configuration base de données
     3. Installation automatique

4. **Connexion**
   - URL: `http://localhost/sgdi/`
   - Utilisateur: `admin`
   - Mot de passe: `Admin@2025`
   - **⚠️ CHANGER LE MOT DE PASSE IMMÉDIATEMENT**

### Méthode 2: Installation manuelle

1. **Copier les fichiers**
   ```bash
   cp -r sgdi/ /var/www/html/
   ```

2. **Configuration base de données**
   ```bash
   # Éditer config/database.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sgdi_mvp');
   define('DB_USER', 'votre_user');
   define('DB_PASS', 'votre_password');
   ```

3. **Importer le schéma**
   ```bash
   mysql -u root -p sgdi_mvp < database/schema.sql
   mysql -u root -p sgdi_mvp < database/seed.sql
   ```

4. **Permissions**
   ```bash
   chmod -R 775 uploads/
   chmod -R 775 logs/
   chown -R www-data:www-data uploads/ logs/
   ```

5. **Accéder à l'application**
   ```
   http://localhost/sgdi/
   ```

---

## ⚙️ Configuration

### Configuration Email (Optionnel)

Éditer `config/email.php`:

```php
define('SMTP_HOST', 'smtp.votreserveur.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@dppg.cm');
define('SMTP_PASSWORD', 'votre_password');
define('EMAIL_ENABLED', true); // Activer l'envoi réel
```

### Tâches planifiées (Cron)

```bash
# Vérifier huitaines tous les jours à 9h
0 9 * * * php /var/www/html/sgdi/cron/verifier_huitaines.php

# Sauvegarde quotidienne à 2h
0 2 * * * php /var/www/html/sgdi/cron/backup_database.php
```

### Configuration Apache (Production)

```apache
<VirtualHost *:80>
    ServerName sgdi.dppg.cm
    DocumentRoot /var/www/html/sgdi

    <Directory /var/www/html/sgdi>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sgdi_error.log
    CustomLog ${APACHE_LOG_DIR}/sgdi_access.log combined
</VirtualHost>
```

### SSL/HTTPS (Recommandé)

```bash
certbot --apache -d sgdi.dppg.cm
```

---

## 🚀 Utilisation

### Comptes de démonstration

Après installation, ces comptes sont disponibles:

| Rôle | Utilisateur | Mot de passe |
|------|-------------|--------------|
| Admin Système | `admin` | `Admin@2025` |
| Chef Service | `chef.service` | `Chef@2025` |
| Billeteur | `billeteur` | `Bill@2025` |
| Cadre DAJ | `cadre.daj` | `Daj@2025` |
| Cadre DPPG | `cadre.dppg` | `Dppg@2025` |

**⚠️ Changez tous les mots de passe en production!**

### Workflow rapide

**1. Créer un dossier** (Chef Service)
   - Menu "Dossiers" → "Créer"
   - Suivre le wizard 5 étapes
   - Upload documents

**2. Constituer commission** (Chef Service)
   - Ouvrir dossier → Onglet "Commission"
   - Sélectionner 3 membres
   - Valider

**3. Enregistrer paiement** (Billeteur)
   - "Dossiers en attente" → Onglet "Paiement"
   - Saisir montant, mode, référence
   - Valider → Notification auto

**4. Analyser juridiquement** (Cadre DAJ)
   - "Mes dossiers à analyser"
   - Rédiger avis: Conforme / Non conforme
   - Soumettre

**5. Inspecter** (Cadre DPPG)
   - "Mes inspections"
   - Rédiger rapport terrain
   - Upload photos
   - Soumettre

**6. Valider rapport** (Chef Commission)
   - "Rapports en attente"
   - Vérifier rapport
   - Valider ou demander révision

**7. Circuit visa** (Chef → Sous-Dir → Dir)
   - "Dossiers en attente de visa"
   - Vérifier et apposer visa

**8. Décision finale** (Ministre)
   - "Dossiers transmis"
   - Approuver ou Refuser
   - Saisir référence arrêté

**9. Consultation publique**
   - Accès sans login: `/modules/registre_public/`
   - Recherche, carte, statistiques

---

## 📚 Documentation

### Guides utilisateurs

- **[Guide utilisateur complet](docs/GUIDE_UTILISATEUR_COMPLET.md)** - 70+ pages exhaustives
- **[Guide rapide par rôle](docs/GUIDE_RAPIDE_PAR_ROLE.md)** - Cartes de référence
- **[Guide système huitaine](GUIDE_HUITAINE.md)** - Détails délais
- **[Guide cartographie](GUIDE_TEST_CARTOGRAPHIE.md)** - Fonctionnalités géo
- **[Guide UX/UI](GUIDE_UX_UI.md)** - Design et ergonomie

### Guides techniques

- **[Guide démarrage rapide](DEMARRAGE_RAPIDE.md)** - Installation express
- **[Guide installation complète](INSTALLATION_COMPLETE.md)** - Déploiement production
- **[Phase 4 - Finalisation](PHASE_4_FINALISATION_COMPLETE.md)** - Détails implémentation
- **[CLAUDE.md](CLAUDE.md)** - Instructions développement

### Documentation API

- **[Fonctions communes](includes/functions.php)** - Utilitaires
- **[Fonctions dossiers](modules/dossiers/functions.php)** - Métier
- **[Fonctions email](includes/email.php)** - Notifications
- **[Fonctions huitaine](includes/huitaine_functions.php)** - Délais
- **[Fonctions carte](includes/map_functions.php)** - Géolocalisation

---

## 🧪 Tests

### Lancer les tests

```bash
# Test workflow complet (11 étapes)
php tests/test_workflow_complet.php

# Test système huitaine
php tests/test_huitaine.php
```

### Résultats attendus

```
=== TEST DU WORKFLOW COMPLET SGDI ===
✓ Tests réussis: 45
✗ Tests échoués: 0

🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS!
```

---

## 📊 Structure du projet

```
sgdi/
├── config/              # Configuration
│   ├── database.php
│   ├── app.php
│   └── email.php
├── includes/            # Fichiers communs
│   ├── auth.php
│   ├── functions.php
│   ├── email.php
│   ├── header.php
│   └── footer.php
├── modules/             # Modules fonctionnels
│   ├── dossiers/
│   ├── paiements/
│   ├── daj/
│   ├── chef_commission/
│   ├── carte/
│   ├── registre_public/
│   ├── rapports/
│   └── users/
├── assets/              # Ressources statiques
│   ├── css/
│   ├── js/
│   └── uploads/
├── database/            # Scripts SQL
│   ├── schema.sql
│   └── seed.sql
├── tests/               # Tests automatisés
├── docs/                # Documentation
├── cron/                # Tâches planifiées
├── logs/                # Logs système
├── install.php          # Installateur
├── index.php            # Page connexion
├── dashboard.php        # Tableau de bord
└── README.md            # Ce fichier
```

---

## 🔒 Sécurité

### Mesures implémentées

- ✅ **Sessions sécurisées** avec régénération ID
- ✅ **Tokens CSRF** sur tous les formulaires
- ✅ **Requêtes préparées** (PDO) contre SQL injection
- ✅ **Échappement HTML** contre XSS
- ✅ **Validation MIME** sur uploads
- ✅ **Contrôle d'accès** basé sur rôles (RBAC)
- ✅ **Audit trail** complet
- ✅ **Mots de passe hashés** (bcrypt)
- ✅ **Logs sécurité**

### Bonnes pratiques

- Changer mots de passe par défaut
- Utiliser HTTPS en production
- Sauvegardes régulières
- Mise à jour PHP/MySQL
- Monitoring logs erreurs
- Limiter tentatives connexion

---

## 🐛 Résolution de problèmes

### Problème: Connexion impossible

**Solution:**
```bash
# Vérifier config base de données
cat config/database.php

# Tester connexion MySQL
mysql -u root -p sgdi_mvp

# Vérifier utilisateur existe
mysql> SELECT * FROM users WHERE username = 'admin';
```

### Problème: Upload échoue

**Solution:**
```bash
# Vérifier permissions
ls -la uploads/
chmod -R 775 uploads/

# Vérifier taille max upload PHP
php -i | grep upload_max_filesize
# Éditer php.ini si nécessaire
upload_max_filesize = 10M
post_max_size = 10M
```

### Problème: Emails non envoyés

**Solution:**
```php
// Vérifier config/email.php
define('EMAIL_ENABLED', true);
define('EMAIL_DEBUG', true); // Pour debug

// Consulter logs
tail -f logs/email.log
```

### Problème: Page blanche

**Solution:**
```php
// Activer affichage erreurs (development uniquement)
// Dans index.php ou config/app.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Consulter logs Apache
tail -f /var/log/apache2/error.log
```

---

## 📞 Support

### Contact

- **Email support:** support@dppg.cm
- **Téléphone:** +237 XXX XXX XXX
- **Horaires:** Lundi - Vendredi, 8h - 17h

### Bugs et améliorations

- Créer un ticket sur le système interne de support
- Fournir:
  * Description détaillée
  * Étapes de reproduction
  * Captures d'écran
  * Logs d'erreur

---

## 🔄 Maintenance

### Quotidien
- [ ] Vérifier logs erreurs
- [ ] Surveiller espace disque

### Hebdomadaire
- [ ] Vérifier sauvegardes
- [ ] Analyser statistiques

### Mensuel
- [ ] Optimiser base de données
- [ ] Revue logs sécurité
- [ ] Rapport d'activité

---

## 📝 Changelog

### Version 1.0 (Octobre 2025)
- ✅ Version initiale production
- ✅ Workflow complet 11 étapes
- ✅ 10 rôles utilisateurs
- ✅ 6 types d'infrastructures
- ✅ Système huitaine
- ✅ Géolocalisation GPS
- ✅ Notifications email
- ✅ Registre public
- ✅ Export PDF/Excel
- ✅ PWA
- ✅ Tests automatisés
- ✅ Documentation complète

---

## 📄 Licence

**Proprietary** - © 2025 MINEE/DPPG - République du Cameroun

Ce logiciel est la propriété exclusive du Ministère de l'Eau et de l'Énergie (MINEE) - Direction DPPG. Toute reproduction, distribution ou utilisation non autorisée est strictement interdite.

---

## 👥 Auteurs

**Développé pour:** MINEE/DPPG - République du Cameroun
**Année:** 2025
**Statut:** ✅ Production Ready

---

**🇨🇲 Fait avec ❤️ pour le MINEE/DPPG - République du Cameroun**
