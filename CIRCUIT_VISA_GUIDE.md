# Guide du Circuit de Visa à 3 Niveaux - SGDI

## 📋 Vue d'ensemble

Le système SGDI implémente désormais un circuit de visa hiérarchique à 3 niveaux avant la décision ministérielle finale.

## 🔄 Circuit Complet

```
1. Chef Commission (Validation inspection)
   ↓
2. Chef de Service SDTD (Visa niveau 1)
   ↓
3. Sous-Directeur SDTD (Visa niveau 2)
   ↓
4. Directeur DPPG (Visa niveau 3 - Final)
   ↓
5. Cabinet Ministre (Décision finale)
   ↓
6. Publication automatique au registre public (si approuvé)
```

## 👥 Utilisateurs et Rôles

### Comptes de démonstration créés:

| Rôle | Username | Mot de passe | Description |
|------|----------|--------------|-------------|
| **Sous-Directeur SDTD** | `sousdirecteur` | `sousdir123` | Visa niveau 2 |
| **Directeur DPPG** | `directeur` | `dir123` | Visa niveau 3 (final) |
| **Cabinet Ministre** | `ministre` | `ministre123` | Décision finale |
| Chef Service | `chef` | `chef123` | Visa niveau 1 |
| Chef Commission | Voir `chef.commission` | - | Validation inspection |

## 🎯 Statuts du Workflow

Les nouveaux statuts ajoutés:

- `validation_chef_commission` - En attente validation par Chef Commission
- `visa_chef_service` - En attente visa Chef Service (niveau 1)
- `visa_sous_directeur` - En attente visa Sous-Directeur (niveau 2)
- `visa_directeur` - En attente visa Directeur (niveau 3)
- `autorise` - Approuvé par le Ministre
- `rejete` - Refusé à un niveau quelconque

## 📍 Modules et Interfaces

### 1. Module Sous-Directeur
**Chemin**: `/modules/sous_directeur/`

**Fichiers**:
- `dashboard.php` - Tableau de bord avec statistiques
- `viser.php` - Interface de visa

**Fonctionnalités**:
- ✅ Visualisation des dossiers en attente (statut `visa_chef_service`)
- ✅ Consultation du dossier complet
- ✅ Historique des visas précédents
- ✅ 3 actions possibles:
  - **Approuver** → Passe au Directeur
  - **Demander modification** → Retourne en arrière
  - **Rejeter** → Rejet définitif
- ✅ Observations optionnelles

### 2. Module Directeur
**Chemin**: `/modules/directeur/`

**Fichiers**:
- `dashboard.php` - Tableau de bord
- `viser.php` - Interface de visa final

**Fonctionnalités**:
- ✅ Visualisation des dossiers après visa Sous-Directeur
- ✅ Consultation complète du circuit de visa
- ✅ Visa final avant transmission au Ministre
- ✅ Statistiques et dossiers validés

### 3. Module Cabinet Ministre
**Chemin**: `/modules/ministre/`

**Fichiers**:
- `dashboard.php` - Tableau de bord
- `decider.php` - Interface de décision ministérielle

**Fonctionnalités**:
- ✅ Dossiers ayant reçu tous les visas
- ✅ Décision finale: APPROUVER / REFUSER
- ✅ Référence de la décision (N° arrêté)
- ✅ Publication automatique au registre public si approuvé
- ✅ Historique complet du circuit

## 📧 Notifications Email

### Templates créés:

1. **`paiement_enregistre.php`** - Notification après paiement
2. **`visa_accorde.php`** - Notification après chaque visa
3. **`decision_ministerielle.php`** - Notification décision finale
4. **`huitaine_alerte.php`** - Alerte délai huitaine

### Configuration:

Fichier: `config/email.php`

```php
return [
    'enabled' => false, // Mettre à true pour activer
    'from' => [
        'email' => 'noreply@sgdi.cm',
        'name' => 'SGDI - MINEE/DPPG'
    ],
    // ... autres paramètres SMTP
];
```

### Test des emails:

```bash
php test_emails.php
```

Génère 5 fichiers HTML de test dans le dossier racine.

## 🗄️ Base de Données

### Nouvelle table: `visas`

```sql
CREATE TABLE visas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    action ENUM('approuve', 'rejete', 'demande_modification'),
    observations TEXT,
    date_visa TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Nouveaux statuts dans `dossiers`:

```sql
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM(
    'brouillon',
    'en_cours',
    'paye',
    'analyse_daj',
    'inspecte',
    'validation_chef_commission',
    'visa_chef_service',         -- NOUVEAU
    'visa_sous_directeur',        -- NOUVEAU
    'visa_directeur',             -- NOUVEAU
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu',
    'en_huitaine'
);
```

## 🧪 Tests

### Test du circuit complet:

1. **Connexion Chef Service** (`chef` / `chef123`)
   - Créer un dossier
   - Constituer la commission
   - Générer note de frais

2. **Connexion Billeteur** (`billeteur` / `bill123`)
   - Enregistrer le paiement

3. **Connexion Cadre DAJ** (si configuré)
   - Effectuer l'analyse juridique

4. **Connexion Cadre DPPG** (`cadre` / `cadre123`)
   - Réaliser l'inspection

5. **Connexion Chef Commission**
   - Valider le rapport d'inspection
   - → Statut devient `visa_chef_service`

6. **Connexion Chef Service** (`chef` / `chef123`)
   - Apposer le 1er visa
   - → Statut devient `visa_sous_directeur`

7. **Connexion Sous-Directeur** (`sousdirecteur` / `sousdir123`)
   - Apposer le 2e visa
   - → Statut devient `visa_directeur`

8. **Connexion Directeur** (`directeur` / `dir123`)
   - Apposer le 3e visa (final)
   - → Statut devient `visa_directeur`

9. **Connexion Ministre** (`ministre` / `ministre123`)
   - Prendre la décision finale (APPROUVER / REFUSER)
   - Saisir la référence de l'arrêté
   - → Statut devient `autorise` ou `rejete`

10. **Vérification**
    - Si approuvé: consulter le registre public
    - Vérifier l'historique complet dans `visas`

## 📊 Statistiques

Chaque dashboard affiche:
- ✅ Nombre de dossiers en attente
- ✅ Nombre de visas/décisions du mois
- ✅ Approuvés vs Rejetés
- ✅ Total historique

## ⚙️ Scripts d'installation

### 1. Mise à jour des rôles et utilisateurs:
```bash
php update_roles_and_users.php
```

### 2. Configuration du workflow:
```bash
php setup_visa_complete.php
```

### 3. Création table visas:
```bash
php create_visas_table.php
```

### 4. Test emails:
```bash
php test_emails.php
```

## 🚀 Déploiement

### Checklist avant mise en production:

- [ ] Exécuter tous les scripts de migration
- [ ] Vérifier que les 3 nouveaux utilisateurs existent
- [ ] Tester le circuit complet de bout en bout
- [ ] Configurer le serveur SMTP pour les emails
- [ ] Activer l'envoi d'emails (`enabled => true`)
- [ ] Vérifier les permissions des dossiers
- [ ] Tester les notifications
- [ ] Former les utilisateurs sur les nouveaux rôles

## 📝 Logs et Historique

Toutes les actions sont tracées:
- Table `visas` - Historique des visas
- Table `historique` - Historique général des actions
- Table `logs_activite` - Logs système

## 🆘 Dépannage

### Problème: Utilisateurs manquants
**Solution**: Exécuter `php update_roles_and_users.php`

### Problème: Statuts non disponibles
**Solution**: Exécuter `php setup_visa_complete.php`

### Problème: Table visas inexistante
**Solution**: Exécuter `php create_visas_table.php`

### Problème: Emails non envoyés
**Solution**:
1. Vérifier `config/email.php` (`enabled => true`)
2. Configurer les paramètres SMTP
3. Tester avec `php test_emails.php`

## 📞 Support

Pour toute question:
- Consulter la documentation dans `/docs`
- Vérifier les logs dans `/logs`
- Contacter l'administrateur système

---

**Version**: 2.0
**Date**: 05/10/2024
**Auteur**: Équipe SGDI
