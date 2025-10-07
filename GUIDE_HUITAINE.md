# 🕐 Guide du système de workflow "Huitaine"

## Vue d'ensemble

Le système de "Huitaine" est un mécanisme réglementaire qui accorde au demandeur **8 jours ouvrables** pour régulariser une irrégularité constatée dans son dossier.

## ✨ Fonctionnalités

### 1. Création de huitaine
- **Qui** : Chef de service, Cadre DPPG, Cadre DAJ, Admin
- **Déclenchement** : Lorsqu'une irrégularité est constatée dans un dossier
- **Types d'irrégularités** :
  - Document manquant
  - Information incomplète
  - Non-conformité technique
  - Paiement partiel
  - Autre

### 2. Compte à rebours automatique
- **Calcul** : 8 jours ouvrables (excluant samedis et dimanches)
- **Affichage en temps réel** des jours restants
- **Indicateurs visuels** :
  - 🟢 Vert : > 2 jours restants
  - 🟡 Jaune : ≤ 2 jours restants
  - 🔴 Rouge : ≤ 1 jour ou expiré

### 3. Système d'alertes progressif

#### J-2 (2 jours avant expiration)
- ⚠️ Première alerte au demandeur
- 📧 Email automatique
- 🔔 Notification in-app pour le responsable

#### J-1 (1 jour avant expiration)
- 🚨 Alerte urgente
- 📧 Email de rappel
- 🔔 Notification renforcée

#### J (Jour d'expiration)
- ⛔ Alerte finale
- 📧 Email de dernière chance
- 🔔 Notification critique

#### Après J
- ❌ **Rejet automatique du dossier**
- 📧 Email de notification de rejet
- 📝 Enregistrement dans l'historique

### 4. Régularisation
- **Interface dédiée** pour valider la régularisation
- **Commentaire obligatoire** expliquant les corrections apportées
- **Restauration automatique** du statut précédent du dossier
- **Notification** au créateur de la huitaine

## 📋 Installation

### Étape 1 : Exécuter la migration SQL

```bash
# Via phpMyAdmin
Importer le fichier: database/add_huitaine_workflow.sql
```

Ou via ligne de commande :
```bash
mysql -u root -p sgdi_mvp < database/add_huitaine_workflow.sql
```

### Étape 2 : Vérifier les tables créées

Tables ajoutées :
- `huitaine` - Stocke les huitaines
- `historique_huitaine` - Traçabilité des actions
- `alertes_huitaine` - Gestion des notifications

Vues créées :
- `huitaines_actives` - Liste des huitaines en cours
- `statistiques_huitaine` - Statistiques en temps réel

### Étape 3 : Configurer le CRON

Le script `cron/verifier_huitaines.php` doit être exécuté **toutes les heures** pour :
- Envoyer les alertes J-2, J-1, J
- Rejeter automatiquement les huitaines expirées
- Générer le rapport quotidien (à 8h)

#### Configuration CRON (Linux/Mac)
```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne
0 * * * * php /path/to/dppg-implantation/cron/verifier_huitaines.php
```

#### Configuration sous Windows (Planificateur de tâches)
1. Ouvrir le Planificateur de tâches
2. Créer une tâche de base
3. Déclencheur : Toutes les heures
4. Action : `php.exe "C:\wamp64\www\dppg-implantation\cron\verifier_huitaines.php"`

### Étape 4 : Créer le dossier de logs

```bash
mkdir logs
chmod 755 logs
```

## 🎯 Utilisation

### Pour créer une huitaine

1. Accéder au dossier concerné
2. Menu "Actions" → "Créer une huitaine"
3. Sélectionner le type d'irrégularité
4. Décrire précisément l'irrégularité
5. Valider

### Pour suivre les huitaines actives

**Accès** : `modules/huitaine/list.php`

Filtres disponibles :
- 📋 Toutes les huitaines
- ⚠️ Urgentes (≤ 2 jours)
- 🔴 Expirées

### Pour régulariser une huitaine

1. Accéder à la liste des huitaines
2. Cliquer sur "Régulariser"
3. Saisir un commentaire détaillé
4. Valider

## 📊 Statistiques disponibles

Le dashboard affiche :
- **Nombre de huitaines en cours**
- **Nombre d'urgentes** (≤ 2 jours)
- **Nombre d'expirées**
- **Total régularisé**
- **Total rejeté**
- **Durée moyenne de régularisation** (en jours)

## 📧 Notifications

### Email automatiques
- Alerte J-2
- Alerte J-1
- Alerte J (finale)
- Notification de régularisation
- Notification de rejet

### Notifications in-app
Affichées dans le tableau de bord pour :
- Chef de service
- Administrateurs
- Responsable de la huitaine

### Rapport quotidien (8h)
Envoyé aux admins et chefs de service avec :
- Statistiques du jour
- Liste des huitaines urgentes
- Liste des huitaines expirées

## 🔧 Maintenance

### Logs
Les logs sont stockés dans `logs/huitaines_YYYY-MM.log`

Format :
```
[2025-10-03 14:00:00] === Début de la vérification des huitaines ===
[2025-10-03 14:00:01] Alertes envoyées: 3
[2025-10-03 14:00:02] Dossiers rejetés automatiquement: 1
[2025-10-03 14:00:03] === Fin de la vérification (succès) ===
```

### Vérifier le bon fonctionnement

```bash
# Exécuter manuellement le script
php cron/verifier_huitaines.php

# Vérifier les logs
tail -f logs/huitaines_2025-10.log
```

## 🎨 Personnalisation

### Modifier le délai (par défaut 8 jours)
Fichier : `includes/huitaine_functions.php`
```php
// Ligne 55 : Modifier le nombre de jours
while ($jours_ajoutes < 8) { // Changer cette valeur
```

### Modifier les seuils d'alertes
Fichier : `includes/huitaine_functions.php`
```php
// Lignes 261-283 : Modifier les conditions
if ($jours_restants == 2) { // J-2
if ($jours_restants == 1) { // J-1
if ($jours_restants == 0) { // J
```

## ❓ Dépannage

### Les alertes ne sont pas envoyées
- Vérifier que le CRON est bien configuré
- Vérifier les logs dans `logs/`
- Tester manuellement : `php cron/verifier_huitaines.php`

### Les huitaines expirées ne sont pas rejetées
- Vérifier le CRON
- Vérifier que le trigger SQL `after_huitaine_regularisation` existe
- Consulter les logs

### Erreur "Table huitaine doesn't exist"
- Exécuter la migration SQL : `database/add_huitaine_workflow.sql`

## 🔐 Permissions

| Rôle | Créer | Voir | Régulariser |
|------|-------|------|-------------|
| Chef de service | ✅ | ✅ | ✅ |
| Cadre DPPG | ✅ | ✅ | ✅ |
| Cadre DAJ | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ |
| Autres | ❌ | ❌ | ❌ |

## 📱 Interfaces disponibles

1. **`modules/huitaine/creer.php`** - Créer une huitaine
2. **`modules/huitaine/list.php`** - Liste et filtres
3. **`modules/huitaine/regulariser.php`** - Régularisation
4. **Dashboard** - Indicateurs visuels intégrés

## 🚀 Améliorations futures

- [ ] Envoi de SMS pour les alertes critiques
- [ ] Notifications push navigateur
- [ ] Export Excel des huitaines
- [ ] Graphiques d'évolution
- [ ] Configuration des délais par type de dossier
- [ ] Templates d'emails personnalisables

---

**📞 Support** : Consultez les logs et l'historique pour le débogage
**📚 Documentation** : Voir les commentaires dans le code source
