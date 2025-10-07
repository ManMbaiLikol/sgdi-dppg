# Instructions de Développement - Système de Gestion des Dossiers d'Implantation (SGDI)

## Mission
Tu dois développer un système web complet en PHP/MySQL pour gérer les dossiers d'implantation des infrastructures pétrolières au Cameroun pour le MINEE/DPPG.

## 🎯 Objectif Principal
Créer une application web locale qui automatise complètement le workflow de traitement des demandes d'implantation/reprise d'infrastructures pétrolières, depuis la création du dossier jusqu'à la publication de la décision ministérielle.

## 🛠️ Stack Technique Imposée
- **Langage** : PHP 7.4+ uniquement
- **Base de données** : MySQL 5.7+
- **Frontend** : HTML5, CSS3, JavaScript, Bootstrap 4/5
- **Serveur** : Apache/Nginx (environnement XAMPP/WAMP recommandé)
- **Environnement** : Développement local obligatoire

## 📋 Fonctionnalités Essentielles à Développer

### 1. Système d'Authentification
- Login/logout sécurisé avec sessions PHP
- Gestion des rôles et permissions
- Protection CSRF
- Hash des mots de passe (bcrypt)

### 2. Gestion des Utilisateurs (10 rôles)
**Implémente ces rôles exacts avec leurs permissions :**

#### Chef de Service SDTD (rôle central)
- Créer et gérer tous les dossiers
- Constituer et préparer les commissions (3 membres obligatoires)
- Générer les notes de frais
- Suivre les paiements
- Valider et enregistrer les décisions
- Premier niveau de visa

#### Billeteur DPPG
- Enregistrer les paiements
- Éditer les reçus avec détails
- Notification automatique au Chef de Service

#### Chef de Commission
- Coordonner les visites
- Valider les rapports d'inspection
- Formuler recommandations au cadre DPPG

#### Cadre DAJ
- Analyse juridique post-commission
- Validation conformité réglementaire

#### Cadre DPPG (Inspecteur)
- Réaliser les inspections d'infrastructure
- Rédiger les rapports d'inspection
- Contrôle de complétude

#### Sous-Directeur SDTD
- Deuxième niveau de visa

#### Directeur DPPG  
- Troisième niveau de visa
- Transmission pour décision ministérielle

#### Cabinet/Secrétariat Ministre
- Décision finale (approbation/refus)

#### Admin Système
- Gestion complète des utilisateurs
- Configuration système

#### Lecteur Public
- Consultation du registre public uniquement

### 3. Gestion des Dossiers (6 types d'infrastructure)

**Développe les 6 types avec leurs spécificités :**

1. **Implantation station-service** → Opérateur propriétaire
2. **Reprise station-service** → Opérateur propriétaire  
3. **Implantation point consommateur** → Opérateur + Entreprise bénéficiaire + Contrat livraison
4. **Reprise point consommateur** → Opérateur + Entreprise bénéficiaire + Contrat livraison
5. **Implantation dépôt GPL** → Entreprise installatrice
6. **Implantation centre emplisseur** → Opérateur de gaz OU Entreprise constructrice

**Chaque dossier doit avoir :**
- Numéro unique auto-généré
- Statuts trackés (15+ statuts minimum)
- Géolocalisation
- Upload multiple de documents
- Historique complet des actions

### 4. Workflow Automatisé (11 étapes obligatoires)

**Implémente ce workflow exact :**

1. **Création dossier** par Chef Service + Upload pièces
2. **Constitution commission** par Chef Service (cadre DPPG + cadre DAJ + chef commission)
3. **Génération note de frais** automatique
4. **Enregistrement paiement** par Billeteur → notification automatique
5. **Analyse juridique** par cadre DAJ
6. **Contrôle complétude** par cadre DPPG (Inspecteur)
7. **Inspection infrastructure** par cadre DPPG + Rapport
8. **Validation rapport** par Chef de Commission
9. **Circuit visa** : Chef Service → Sous-Directeur → Directeur
10. **Décision ministérielle** (Approbation/Refus)
11. **Publication registre public** automatique

### 5. Gestion de la "Huitaine"
- Système automatique de décompte (8 jours)
- Notifications J-2, J-1, J (échéance)  
- Rejet automatique si non-régularisation
- Interface de régularisation

### 6. Module Documents
- Upload multiple (PDF, DOC, JPG, PNG)
- Versioning automatique
- Contrôle d'intégrité (checksum)
- Signature électronique simple
- Documents requis selon le type d'infrastructure

### 7. Système de Notifications
- **Email** : PHPMailer pour changements de statut, échéances
- **In-app** : Dashboard avec compteurs, alertes
- **Historique** : Toutes notifications archivées

### 8. Module Commission Spécialisé
- Interface constitution (Chef Service nomme 3 personnes)
- Gestion des profils membres
- Planning des visites
- Validation des rapports

### 9. Module Billeterie
- Génération automatique notes de frais
- Interface enregistrement paiements
- Édition reçus détaillés
- Notification instantanée Chef Service

### 10. Registre Public
- Interface publique (sans authentification)
- Recherche multi-critères
- Affichage décisions publiées
- Export données publiques

### 11. Statistiques et Reporting
- Dashboards avec graphiques
- Métriques par type d'infrastructure
- Délais moyens par étape
- Export Excel/PDF

## 🗄️ Structure Base de Données

**Tables principales à créer :**

```sql
-- Utilisateurs et rôles
users, roles, user_roles

-- Dossiers et workflow  
dossiers, statuts_dossier, historique_dossier

-- Types d'infrastructure et demandeurs
types_infrastructure, types_demandeurs

-- Commission et membres
commissions, membres_commission, types_membres

-- Documents et versioning
documents, versions_document, types_document

-- Paiements et billeterie
notes_frais, paiements, recus

-- Inspections et rapports
inspections, rapports_inspection, grilles_evaluation

-- Notifications et logs
notifications, logs_activite

-- Registre public
decisions_publiees, registre_public
```

## 🎨 Interface Utilisateur

### Dashboard Adaptatif
- Vue différente selon le rôle
- Compteurs temps réel
- Actions rapides contextuelles
- Graphiques statistiques

### Formulaires Intelligents
- Validation côté client + serveur
- Champs conditionnels selon le type
- Sauvegarde auto (localStorage)
- Guides contextuels

### Tables de Données
- Pagination, tri, recherche
- Filtres avancés
- Actions en lot
- Export Excel/PDF

## 🔒 Sécurité Obligatoire

- **Sessions** : Durée limitée, régénération ID
- **CSRF** : Tokens sur tous les formulaires
- **XSS** : Échappement HTML systématique  
- **SQL Injection** : Requêtes préparées exclusivement
- **Upload** : Validation type MIME + extension
- **Logs** : Audit trail complet

## 📱 Responsive Design
- Bootstrap 4/5 obligatoire
- Compatible mobile/tablette
- Interface intuitive

## ⚡ Performance
- Requêtes MySQL optimisées
- Indexes sur colonnes clés
- Cache PHP (si nécessaire)
- Compression images

## 📂 Structure de Projet Recommandée

```
sgdi/
├── config/
│   ├── database.php
│   ├── auth.php
│   └── constants.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── sidebar.php
├── assets/
│   ├── css/
│   ├── js/
│   └── uploads/
├── modules/
│   ├── auth/
│   ├── dossiers/
│   ├── commission/
│   ├── billeterie/
│   ├── inspection/
│   └── public/
├── database/
│   ├── schema.sql
│   └── seed.sql
└── docs/
    ├── installation.md
    └── user_guide.md
```

## 🚀 Étapes de Développement

### Phase 1 : Socle (Semaines 1-6)
1. **Semaine 1-2** : Setup projet + BDD + authentification
2. **Semaine 3-4** : Gestion utilisateurs + rôles + permissions  
3. **Semaine 5-6** : Interface administration + dashboard base

### Phase 2 : Dossiers (Semaines 7-12)
1. **Semaine 7-8** : CRUD dossiers + 6 types d'infrastructure
2. **Semaine 9-10** : Constitution commission + workflow base
3. **Semaine 11-12** : Upload documents + gestion versions

### Phase 3 : Workflow (Semaines 13-18)
1. **Semaine 13-14** : Module inspection + rapports
2. **Semaine 15-16** : Module billeterie + paiements
3. **Semaine 17-18** : Circuit visa + notifications

### Phase 4 : Finalisation (Semaines 19-22)
1. **Semaine 19-20** : Registre public + statistiques
2. **Semaine 21** : Tests complets + corrections
3. **Semaine 22** : Documentation + déploiement

## ✅ Critères d'Acceptance

### Fonctionnel
- [ ] 10 rôles implémentés avec bonnes permissions
- [ ] 6 types d'infrastructure gérés correctement  
- [ ] Workflow 11 étapes fonctionnel
- [ ] Huitaine automatique opérationnelle
- [ ] Notifications email + in-app
- [ ] Registre public accessible
- [ ] Statistiques complètes

### Technique  
- [ ] Code PHP propre (PSR standards)
- [ ] Base de données normalisée
- [ ] Sécurité : Sessions + CSRF + SQL préparé
- [ ] Interface responsive Bootstrap
- [ ] Performance : requêtes optimisées
- [ ] Documentation complète

## 🎯 Points Critiques

1. **Rôles et Permissions** : Implémentation stricte des 10 rôles
2. **Workflow** : Respect absolu des 11 étapes  
3. **Types d'Infrastructure** : Gestion différenciée des 6 types
4. **Commission** : Constitution obligatoire 3 membres
5. **Billeterie** : Notification instantanée après paiement
6. **Huitaine** : Automatisation complète du décompte
7. **Inspection** : Réalisée par cadre DPPG exclusivement
8. **Registre Public** : Accessible sans authentification

## 📋 Livrables Finaux

1. **Code source complet** avec structure claire
2. **Base de données** : Schema + données de test
3. **Documentation technique** : Installation + architecture  
4. **Manuel utilisateur** : Guide par rôle
5. **Scripts de déploiement** : Setup automatique
6. **Données de démonstration** : Jeu d'essai complet

## 🔄 Instructions Spécifiques pour le Développement

1. **Commence par la base de données** : Crée le schéma complet avant le code
2. **Authentification d'abord** : Système de login robuste
3. **Rôles ensuite** : Implémente tous les rôles avant les fonctionnalités
4. **Workflow étape par étape** : Une étape à la fois, teste avant de passer à la suivante
5. **Interface utilisateur** : Develop mobile-first avec Bootstrap
6. **Tests continus** : Teste chaque fonctionnalité avant d'avancer
7. **Documentation en parallèle** : Documente au fur et à mesure

## 📞 Support et Questions

Si tu as des questions sur les spécifications métier ou des clarifications sur le workflow, demande des précisions. L'objectif est de créer un système professionnel, complet et utilisable immédiatement par le MINEE/DPPG.

---

**IMPORTANT** : Ce système doit être opérationnel dès la livraison. Chaque fonctionnalité doit être entièrement fonctionnelle, pas juste une maquette. L'objectif est la mise en production immédiate.

**Version** : 1.0  
**Date** : Septembre 2025  
**Destinataire** : Claude Code  
**Priorité** : Critique