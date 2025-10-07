# Installation du Module DAJ - Analyse Juridique et Réglementaire

## Vue d'ensemble

Le rôle **Cadre DAJ** (Direction des Affaires Juridiques) était manquant du système SGDI. Ce module ajoute :

- Le rôle `cadre_daj` dans le système d'authentification
- L'interface d'analyse juridique et réglementaire
- L'intégration dans le workflow des dossiers (étape 5)
- Le nouveau statut `analyse_daj` dans le cycle de vie des dossiers

## Étapes d'installation

### 1. Mise à jour de la base de données

Exécuter le script SQL suivant dans votre base de données MySQL :

```bash
mysql -u root -p sgdi_mvp < database/add_daj_role.sql
```

Ce script :
- Ajoute l'utilisateur DAJ avec le login `daj` / mot de passe `daj123`
- Crée la table `analyses_daj` pour stocker les analyses juridiques
- Ajoute les contraintes de clés étrangères

### 2. Workflow modifié

Le nouveau workflow inclut l'analyse DAJ :

1. **Dossier création** (chef_service)
2. **Constitution commission** (chef_service)
3. **Note de frais automatique**
4. **Enregistrement paiement** (billeteur) → notification automatique
5. **🆕 Analyse juridique DAJ** (cadre_daj) → nouveau statut `analyse_daj`
6. **Contrôle complétude** (cadre_dppg)
7. **Inspection infrastructure** (cadre_dppg)
8. **Validation rapport** (chef_commission)
9. **Circuit visa** (chef_service → sous_directeur → directeur)
10. **Décision ministérielle**
11. **Publication registre public**

### 3. Nouveaux fichiers ajoutés

- `modules/daj/analyse.php` - Interface d'analyse juridique
- `modules/daj/list.php` - Liste des dossiers pour analyse DAJ
- `modules/daj/functions.php` - Fonctions spécifiques DAJ
- `database/add_daj_role.sql` - Script de mise à jour base de données

### 4. Fichiers modifiés

- `dashboard.php` - Ajout du rôle DAJ dans les statistiques et actions
- `includes/functions.php` - Ajout du statut `analyse_daj`

## Fonctionnalités du module DAJ

### Interface d'analyse juridique

- **Consultation des documents** soumis par le demandeur
- **Analyse réglementaire** avec statuts :
  - En cours d'analyse
  - Conforme - Validé
  - Conforme avec réserves
  - Non conforme - Rejet
- **Observations juridiques** détaillées
- **Liste des documents manquants** ou non conformes
- **Recommandations** pour la suite

### Tableau de bord DAJ

- **Statistiques** : dossiers à analyser, en cours, terminés, ce mois
- **Actions rapides** : accès direct à l'analyse juridique
- **Liste filtrée** des dossiers selon le statut

### Workflow automatisé

- Après paiement, les dossiers passent automatiquement en attente d'analyse DAJ
- Une fois l'analyse terminée (statut ≠ 'en_cours'), le dossier passe au statut `analyse_daj`
- Les cadres DPPG ne voient que les dossiers avec statut `analyse_daj` pour inspection

## Comptes de test

- **Login** : `daj`
- **Mot de passe** : `daj123`
- **Rôle** : `cadre_daj`
- **Nom** : MBONGO Celestine

## Vérification de l'installation

1. Se connecter avec le compte DAJ
2. Vérifier que le dashboard affiche les actions pour "Analyser juridiquement"
3. Accéder à la liste des dossiers payés en attente d'analyse
4. Tester l'interface d'analyse sur un dossier payé
5. Vérifier que le statut passe à `analyse_daj` après validation

## Conformité workflow CLAUDE.md

✅ **Étape 5 implémentée** : "Analyse juridique par DAJ"
✅ **Rôle Cadre DAJ** : Analyse juridique et réglementaire des pièces
✅ **Commission 3 membres** : DAJ fait partie des membres obligatoires
✅ **Séquence respectée** : DAJ avant inspection DPPG
✅ **Notifications** : Système d'alertes entre étapes

Le module DAJ complète l'implémentation du workflow tel que défini dans les spécifications CLAUDE.md.