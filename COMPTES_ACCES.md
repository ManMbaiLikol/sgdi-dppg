# 🔐 COMPTES D'ACCÈS - SGDI v2.0

## 📋 Liste Complète des Comptes de Démonstration

### 🔴 ADMINISTRATEURS

| Rôle | Username | Mot de passe | Description |
|------|----------|--------------|-------------|
| **Admin Système** | `admin` | `admin123` | Administration complète du système |

---

### 👔 CIRCUIT DE VISA (Hiérarchique)

#### Niveau 0 - Validation Inspection
| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Chef Commission** | Voir note¹ | - | Valide les rapports d'inspection |

#### Niveau 1 - Premier Visa
| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Chef Service SDTD** | `chef` | `chef123` | Premier visa après validation commission |

#### Niveau 2 - Deuxième Visa
| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Sous-Directeur SDTD** | `sousdirecteur` | `sousdir123` | Deuxième visa dans la hiérarchie |

#### Niveau 3 - Visa Final
| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Directeur DPPG** | `directeur` | `dir123` | Visa final avant transmission ministre |

#### Niveau 4 - Décision Finale
| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Cabinet Ministre** | `ministre` | `ministre123` | Décision ministérielle finale (APPROUVER/REFUSER) |

---

### 💼 RÔLES OPÉRATIONNELS

| Rôle | Username | Mot de passe | Responsabilité |
|------|----------|--------------|----------------|
| **Billeteur DPPG** | `billeteur` | `bill123` | Enregistrement des paiements |
| **Cadre DPPG (Inspecteur)** | `cadre` | `cadre123` | Inspections terrain |
| **Cadre DPPG 2** | `cadre2` | `cadre123` | Second inspecteur |
| **Cadre DAJ** | Voir note² | - | Analyse juridique |

---

### 👁️ CONSULTATION PUBLIQUE

| Rôle | Username | Mot de passe | Accès |
|------|----------|--------------|-------|
| **Lecteur Public** | `lecteur` | `lecteur123` | Consultation registre public uniquement |

---

## 🔄 CIRCUIT COMPLET - EXEMPLE D'UTILISATION

### Étape par étape:

1. **Chef Service** (`chef`)
   - Créer un dossier
   - Constituer la commission
   - Générer la note de frais

2. **Billeteur** (`billeteur`)
   - Enregistrer le paiement
   - Générer le reçu

3. **Cadre DAJ** (si configuré)
   - Effectuer l'analyse juridique

4. **Cadre DPPG** (`cadre`)
   - Réaliser l'inspection
   - Rédiger le rapport

5. **Chef Commission**
   - Valider le rapport d'inspection
   - → Statut: `visa_chef_service`

6. **Chef Service** (`chef`)
   - Apposer le 1er visa
   - → Statut: `visa_sous_directeur`

7. **Sous-Directeur** (`sousdirecteur`) ✨ **NOUVEAU**
   - Apposer le 2e visa
   - → Statut: `visa_directeur`

8. **Directeur** (`directeur`) ✨ **AMÉLIORÉ**
   - Apposer le visa final (3e niveau)
   - → Statut: `visa_directeur` (prêt pour ministre)

9. **Cabinet Ministre** (`ministre`) ✨ **NOUVEAU**
   - Prendre la décision finale
   - Saisir référence arrêté
   - → Statut: `autorise` ou `rejete`

10. **Registre Public**
    - Si approuvé: publication automatique
    - Accessible via `lecteur` ou sans authentification

---

## 🎯 DASHBOARDS PAR RÔLE

| Rôle | URL Dashboard |
|------|---------------|
| Admin | `/dashboard.php` |
| Chef Service | `/dashboard.php` |
| Sous-Directeur | `/modules/sous_directeur/dashboard.php` ✨ |
| Directeur | `/modules/directeur/dashboard.php` ✨ |
| Ministre | `/modules/ministre/dashboard.php` ✨ |
| Billeteur | `/dashboard.php` |
| Cadre DPPG | `/dashboard.php` |
| Chef Commission | `/modules/chef_commission/dashboard.php` |
| Lecteur | `/modules/registre_public/index.php` |

---

## 📧 EMAILS DE TEST

Tous les comptes utilisent des emails fictifs:
- `admin@sgdi.cm`
- `chef.service@dppg.cm`
- `sousdirecteur@dppg.cm`
- `directeur@dppg.cm`
- `cabinet@minee.cm`
- etc.

Pour tester les notifications:
1. Configurer SMTP dans `config/email.php`
2. Modifier les emails dans la base
3. Activer: `'enabled' => true`

---

## 🔒 SÉCURITÉ

### Mots de passe par défaut
Tous les mots de passe suivent le pattern: `{role}123`

⚠️ **IMPORTANT EN PRODUCTION**:
- Changer TOUS les mots de passe
- Forcer changement à la première connexion
- Implémenter politique de mot de passe forte
- Activer authentification 2FA (optionnel)

---

## 🆘 RÉINITIALISATION

### Mot de passe oublié?
Utiliser le module admin:
- Admin: `/modules/users/reset_password.php`
- Ou script: `php reset_passwords.php`

### Recréer les utilisateurs?
```bash
php update_roles_and_users.php
```

---

## 📊 STATISTIQUES

**Total utilisateurs**: 14
- Admin: 1
- Circuit visa: 4 (Chef Service, Sous-Dir, Dir, Ministre)
- Opérationnels: 5 (Billeteur, 2 Cadres DPPG, Chef Commission, Cadre DAJ)
- Public: 1
- Tests/Divers: 3

---

## 🎓 FORMATION

### Priorité de formation par rôle:

1. **Sous-Directeur** ✨ NOUVEAU
   - Interface de visa niveau 2
   - Consultation historique
   - Actions: Approuver/Modifier/Rejeter

2. **Directeur** ✨ AMÉLIORÉ
   - Visa final (niveau 3)
   - Transmission au ministre
   - Validation dernière instance

3. **Ministre** ✨ NOUVEAU
   - Décision ministérielle
   - Référence arrêtés
   - Publication automatique

---

## 📞 SUPPORT

**Module aide**: `/help` (à créer)
**Documentation**: `/docs`
**Guide circuit visa**: `CIRCUIT_VISA_GUIDE.md`

---

## Notes

¹ Le Chef Commission est nommé lors de la constitution de la commission par le Chef Service. Il n'a pas de compte dédié par défaut mais peut être créé via le module admin.

² Le Cadre DAJ peut être créé via le module admin. Le système prévoit ce rôle mais aucun compte de test n'est créé par défaut.

---

**Dernière mise à jour**: 05 Octobre 2024
**Version**: 2.0
**Statut**: ✅ Complet
