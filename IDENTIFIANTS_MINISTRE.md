# 🔑 Identifiants Compte Ministre

**Date de création:** 7 novembre 2025

---

## 👤 Informations de Connexion

| Champ | Valeur |
|-------|--------|
| **Username** | `ministre` |
| **Mot de passe** | `Ministre@2025` |
| **Email** | ministre@minee.cm |
| **Rôle** | Cabinet/Secrétariat Ministre |
| **Nom** | CABINET |
| **Prénom** | Ministre |
| **Téléphone** | +237690000009 |
| **Statut** | ✅ Actif |

---

## 🌐 URLs de Connexion

### Local (WAMP)
```
http://localhost/dppg-implantation/
```

### Railway (Production)
```
https://votre-app.railway.app/
```

---

## 🎯 Fonctionnalités Espace Ministre

### Tableau de Bord
- **URL:** `/modules/ministre/dashboard.php`
- Vue d'ensemble des dossiers en attente de décision
- Statistiques : approuvés/refusés du mois
- Décisions récentes

### Décisions Ministérielles
- **URL:** `/modules/dossiers/decision_ministre.php`
- Liste des dossiers avec statut `visa_directeur`
- Visualisation circuit complet des visas
- Formulaire de décision (Approuver/Refuser/Ajourner)

### Prendre une Décision
- **URL:** `/modules/dossiers/prendre_decision.php?id=XX`
- Formulaire de décision ministérielle
- 3 options : Approuvé / Refusé / Ajourné
- Numéro d'arrêté obligatoire
- Observations optionnelles

---

## ✅ Workflow de Décision

1. **Connexion** avec identifiants ministre
2. **Accès Dashboard** → Vue dossiers en attente
3. **Sélection dossier** → Visualisation complète
4. **Prise de décision** :
   - ✅ **Approuver** → Statut devient `approuve` + Publication automatique au registre public
   - ❌ **Refuser** → Statut devient `refuse` (visible publiquement pour transparence)
   - ⏸️ **Ajourner** → Statut devient `ajourne` (retour pour complément)
5. **Publication automatique** (si approuvé)

---

## 📊 Après Approbation

Lorsqu'un dossier est **approuvé** :

1. ✅ Statut du dossier → `approuve`
2. ✅ Insertion dans `decisions_ministerielle`
3. ✅ **Publication automatique** dans `registre_public`
4. ✅ Historique complet enregistré
5. ✅ Notification automatique (si emails configurés)
6. ✅ **Visible instantanément** sur le registre public

### Registre Public
- **URL:** `/modules/registre_public/`
- Accessible **sans authentification**
- Recherche par type, région, ville, année
- Carte interactive avec géolocalisation
- Export des données (CSV/Excel)

---

## 🔒 Sécurité & Bonnes Pratiques

### Recommandations Immédiates

1. ✅ **Changer le mot de passe** après première connexion
   - Menu : Profil → Changer mot de passe
   - Utilisez un mot de passe fort (12+ caractères, majuscules, chiffres, symboles)

2. ✅ **Supprimer le script de création**
   ```bash
   rm create_compte_ministre.php
   # ou déplacer vers utilities/
   mv create_compte_ministre.php utilities/
   ```

3. ✅ **Notez les identifiants** en lieu sûr
   - Gestionnaire de mots de passe recommandé
   - Ne partagez pas le mot de passe par email

4. ✅ **Vérifiez l'accès**
   - Testez la connexion
   - Vérifiez que vous voyez bien les dossiers `visa_directeur`

---

## 📝 Permissions du Rôle Ministre

### Actions Autorisées

✅ Consulter tous les dossiers
✅ Voir le circuit complet des visas
✅ Visualiser rapports d'inspection
✅ Visualiser analyses juridiques (DAJ)
✅ **Prendre décision finale** (Approuver/Refuser/Ajourner)
✅ Ajouter observations à la décision
✅ Saisir numéro d'arrêté ministériel
✅ Voir statistiques globales

### Actions Non Autorisées

❌ Créer des dossiers (rôle Chef Service)
❌ Modifier des dossiers existants
❌ Apposer des visas (rôles Chef Service, Sous-Directeur, Directeur)
❌ Faire des inspections (rôle Cadre DPPG)
❌ Enregistrer des paiements (rôle Billeteur)

---

## 🧪 Test de Connexion

### Étape par Étape

1. **Ouvrir navigateur** → `http://localhost/dppg-implantation/`

2. **Saisir identifiants** :
   - Username: `ministre`
   - Mot de passe: `Ministre@2025`

3. **Cliquer "Se connecter"**

4. **Vérifier redirection** → Dashboard Ministre

5. **Tester fonctionnalités** :
   - Voir liste dossiers en attente
   - Ouvrir un dossier
   - Visualiser circuit des visas
   - (Optionnel) Prendre une décision test

---

## 🆘 Dépannage

### Problème : "Identifiants invalides"

**Solutions :**
```sql
-- Vérifier que le compte existe
SELECT * FROM users WHERE username = 'ministre';

-- Vérifier que le rôle est correct
SELECT username, role, actif FROM users WHERE role = 'ministre';
```

### Problème : "Accès refusé"

**Solutions :**
- Vérifier que `actif = 1`
- Vérifier que le rôle est bien `ministre`
- Effacer cache navigateur

### Problème : "Pas de dossiers en attente"

**Normal si :**
- Aucun dossier n'a encore atteint le statut `visa_directeur`
- Pour tester, créez un dossier test et faites-le progresser jusqu'à ce statut

### Réinitialiser le mot de passe

**Via script :**
```bash
php create_compte_ministre.php
# Le script détectera que le compte existe et affichera les identifiants
```

**Via SQL :**
```sql
UPDATE users
SET password = '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW'
WHERE username = 'ministre';
-- Mot de passe réinitialisé à : Ministre@2025
```

---

## 📞 Support

En cas de problème persistant :

1. Consulter les logs : `logs/` directory
2. Vérifier la base de données
3. Contacter l'administrateur système

---

## ✅ Checklist Post-Création

- [ ] Connexion testée avec succès
- [ ] Mot de passe changé
- [ ] Script `create_compte_ministre.php` supprimé ou déplacé
- [ ] Identifiants notés en lieu sûr
- [ ] Accès au dashboard validé
- [ ] Test de prise de décision effectué
- [ ] Vérification publication registre public

---

**🎉 Compte Ministre opérationnel !**

*Dernière mise à jour : 7 novembre 2025*
