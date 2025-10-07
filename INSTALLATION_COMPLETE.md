# 📋 Guide d'installation complet - SGDI avec Workflow Huitaine

## ✅ Checklist d'installation

### 1. Base de données

- [ ] **Exécuter la migration SQL des huitaines**
  ```sql
  -- Via phpMyAdmin ou ligne de commande
  SOURCE database/add_huitaine_workflow.sql;
  ```

- [ ] **Vérifier les tables créées**
  ```sql
  SHOW TABLES LIKE '%huitaine%';
  -- Doit afficher:
  -- - huitaine
  -- - historique_huitaine
  -- - alertes_huitaine
  ```

- [ ] **Vérifier les vues créées**
  ```sql
  SHOW FULL TABLES WHERE Table_type = 'VIEW';
  -- Doit inclure:
  -- - huitaines_actives
  -- - statistiques_huitaine
  ```

### 2. Système de fichiers

- [x] **Dossier logs créé**
  - Emplacement : `C:\wamp64\www\dppg-implantation\logs\`
  - Permissions : Lecture/Écriture
  - Protection : `index.php` ajouté

- [ ] **Vérifier les permissions**
  ```cmd
  # Windows : Clic droit sur logs → Propriétés → Sécurité
  # Autoriser "Écriture" pour l'utilisateur Apache/PHP
  ```

### 3. Configuration CRON

#### Windows (Recommandé)

- [ ] **Exécuter le script de configuration**
  1. Clic droit sur `cron\configurer_cron_windows.bat`
  2. Sélectionner "Exécuter en tant qu'administrateur"
  3. Vérifier le message de succès

- [ ] **Tester manuellement**
  ```cmd
  cd C:\wamp64\www\dppg-implantation\cron
  tester_cron.bat
  ```

- [ ] **Vérifier la tâche planifiée**
  ```cmd
  schtasks /query /tn SGDI_Verifier_Huitaines
  ```

#### Linux/Mac

- [ ] **Configurer le crontab**
  ```bash
  crontab -e

  # Ajouter cette ligne
  0 * * * * php /path/to/dppg-implantation/cron/verifier_huitaines.php
  ```

- [ ] **Tester manuellement**
  ```bash
  php /path/to/dppg-implantation/cron/verifier_huitaines.php
  ```

### 4. Vérification des fonctionnalités

- [ ] **Menu "Huitaines" visible**
  - Se connecter avec rôle : chef_service, admin, cadre_dppg, ou cadre_daj
  - Vérifier la présence du lien "Huitaines" dans le menu
  - Badge avec nombre d'urgences visible si applicable

- [ ] **Créer une huitaine de test**
  1. Ouvrir un dossier
  2. Menu Actions → "Créer une huitaine"
  3. Remplir le formulaire
  4. Vérifier la création

- [ ] **Vérifier l'affichage dans le dossier**
  - Retourner sur la page du dossier
  - Vérifier l'alerte huitaine en haut de page
  - Compte à rebours doit être affiché

- [ ] **Consulter la liste des huitaines**
  - Accéder à `modules/huitaine/list.php`
  - Vérifier les statistiques
  - Tester les filtres (Urgentes, Expirées)

- [ ] **Dashboard avec alertes**
  - Vérifier l'alerte orange/rouge si huitaines urgentes
  - Statistiques visibles

### 5. Tests fonctionnels

#### Test 1 : Créer une huitaine
```
1. Connectez-vous avec chef_service
2. Ouvrez un dossier existant
3. Actions → Créer une huitaine
4. Type : "Document manquant"
5. Description : "Test de création de huitaine"
6. Valider
✅ Vérifier : Huitaine créée, date limite = aujourd'hui + 8 jours ouvrables
```

#### Test 2 : Compte à rebours
```
1. Retourner sur le dossier
2. Observer l'alerte en haut
✅ Vérifier : Nombre de jours restants affiché
✅ Vérifier : Couleur badge (vert > 2j, jaune ≤ 2j, rouge ≤ 1j)
```

#### Test 3 : Liste des huitaines
```
1. Menu → Huitaines
✅ Vérifier : Statistiques affichées
✅ Vérifier : Huitaine de test visible
✅ Vérifier : Filtres fonctionnels
```

#### Test 4 : Régulariser
```
1. Dans la liste, cliquer "Régulariser"
2. Saisir commentaire : "Test de régularisation"
3. Valider
✅ Vérifier : Message de succès
✅ Vérifier : Huitaine marquée "Régularisée"
✅ Vérifier : Statut du dossier restauré
```

#### Test 5 : CRON (Test manuel)
```
# Windows
cd cron
tester_cron.bat

# Linux/Mac
php cron/verifier_huitaines.php

✅ Vérifier : Fichier log créé dans logs/
✅ Vérifier : Aucune erreur dans les logs
```

### 6. Configuration avancée (Optionnel)

- [ ] **Modifier le délai par défaut**
  - Fichier : `includes/huitaine_functions.php`
  - Ligne 55 : Changer `while ($jours_ajoutes < 8)`

- [ ] **Modifier l'heure du rapport quotidien**
  - Fichier : `cron/verifier_huitaines.php`
  - Ligne ~41 : Changer `if ($heure_actuelle == 8)`

- [ ] **Activer l'envoi d'emails**
  - Configurer PHPMailer dans `config/mail.php`
  - Compléter la fonction `creerAlerteEmail()` dans `includes/huitaine_functions.php`

## 🎯 Fonctionnalités disponibles

### Pour tous les rôles autorisés

| Fonctionnalité | Chef Service | Admin | Cadre DPPG | Cadre DAJ |
|----------------|--------------|-------|------------|-----------|
| Créer huitaine | ✅ | ✅ | ✅ | ✅ |
| Voir liste | ✅ | ✅ | ✅ | ✅ |
| Régulariser | ✅ | ✅ | ✅ | ✅ |
| Badge menu | ✅ | ✅ | ✅ | ✅ |
| Alerte dashboard | ✅ | ✅ | ✅ | ✅ |

### Interfaces disponibles

1. **`modules/huitaine/creer.php`**
   - Créer une nouvelle huitaine
   - Calcul automatique de la date limite
   - Types d'irrégularités prédéfinis

2. **`modules/huitaine/list.php`**
   - Liste complète avec statistiques
   - Filtres : Toutes / Urgentes / Expirées
   - Indicateurs visuels colorés

3. **`modules/huitaine/regulariser.php`**
   - Interface de régularisation
   - Commentaire obligatoire
   - Historique des alertes envoyées

4. **Dashboard intégré**
   - Alerte visuelle si urgences
   - Lien rapide vers les huitaines
   - Badge dans le menu navigation

5. **Vue dossier**
   - Alerte en haut de page si huitaine active
   - Compte à rebours en temps réel
   - Bouton "Créer une huitaine" dans Actions

## 🔔 Système d'alertes

### Alertes automatiques

| Moment | Type | Destinataires | Canal |
|--------|------|---------------|-------|
| J-2 | Warning | Demandeur + Responsable | Email + In-app |
| J-1 | Urgent | Demandeur + Responsable | Email + In-app |
| J (jour limite) | Critique | Demandeur + Responsable | Email + In-app |
| Après J | Rejet auto | Responsable + Admin | Email + In-app |

### Rapport quotidien (8h00)

Envoyé aux : Admin + Chef de service

Contenu :
- Statistiques globales
- Liste des huitaines urgentes
- Liste des huitaines expirées
- Durée moyenne de régularisation

## 📊 Statistiques disponibles

Dans le dashboard et la liste :
- **En cours** : Huitaines actives
- **Urgents** : ≤ 2 jours restants
- **Expirés** : Dépassé la date limite
- **Régularisés** : Total historique
- **Rejetés** : Total historique
- **Durée moyenne** : Temps moyen de régularisation (jours)

## 🔧 Maintenance

### Consulter les logs

```cmd
# Windows
type logs\huitaines_2025-10.log

# Linux/Mac
tail -f logs/huitaines_2025-10.log
```

### Forcer l'exécution du CRON

```cmd
# Windows
cd cron
tester_cron.bat

# Linux/Mac
php cron/verifier_huitaines.php
```

### Nettoyer les anciennes huitaines

```sql
-- Supprimer les huitaines de plus de 1 an
DELETE FROM huitaine
WHERE date_debut < DATE_SUB(NOW(), INTERVAL 1 YEAR)
AND statut IN ('regularise', 'rejete', 'annule');
```

## ⚠️ Problèmes courants

### "Table huitaine doesn't exist"
**Solution** : Exécuter `database/add_huitaine_workflow.sql`

### Le CRON ne s'exécute pas
**Solutions** :
1. Vérifier la tâche planifiée existe
2. Tester manuellement avec `tester_cron.bat`
3. Vérifier les permissions du dossier `logs/`

### Les alertes ne sont pas envoyées
**Solutions** :
1. Vérifier les logs
2. Tester le CRON manuellement
3. Vérifier la configuration PHPMailer

### Le badge ne s'affiche pas dans le menu
**Solution** : Vérifier que `huitaine_functions.php` est bien chargé dans `header.php`

## 🚀 Prochaines améliorations suggérées

- [ ] Envoi de SMS pour alertes critiques
- [ ] Notifications push navigateur
- [ ] Export Excel des huitaines
- [ ] Graphiques d'évolution temporelle
- [ ] Templates d'emails personnalisables
- [ ] Configuration des délais par type de dossier
- [ ] API REST pour intégration externe

## 📞 Support

### Logs disponibles
- `logs/huitaines_YYYY-MM.log` - Exécutions du CRON
- `logs/errors.log` - Erreurs PHP (si configuré)

### Commandes utiles

```bash
# Voir les huitaines actives
SELECT * FROM huitaines_actives;

# Statistiques
SELECT * FROM statistiques_huitaine;

# Historique d'une huitaine
SELECT * FROM historique_huitaine WHERE huitaine_id = X;
```

---

## ✅ Installation terminée !

Si tous les tests passent, votre système de workflow "Huitaine" est **opérationnel** !

Pour toute question, consultez :
- `GUIDE_HUITAINE.md` - Guide utilisateur complet
- `cron/README_CRON.md` - Documentation CRON détaillée
- Les commentaires dans le code source
