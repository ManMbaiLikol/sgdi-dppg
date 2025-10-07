# Guide Utilisateur SGDI - Système de Gestion des Dossiers d'Implantation

## Table des matières

1. [Introduction](#introduction)
2. [Connexion au système](#connexion)
3. [Guides par rôle](#guides-par-rôle)
   - [Chef de Service SDTD](#chef-de-service)
   - [Billeteur DPPG](#billeteur)
   - [Cadre DAJ](#cadre-daj)
   - [Cadre DPPG (Inspecteur)](#cadre-dppg)
   - [Chef de Commission](#chef-de-commission)
   - [Sous-Directeur SDTD](#sous-directeur)
   - [Directeur DPPG](#directeur)
   - [Cabinet/Ministre](#ministre)
   - [Administrateur Système](#admin)
   - [Lecteur Public](#lecteur)

---

## 1. Introduction {#introduction}

Le SGDI est une application web de gestion complète des dossiers d'implantation d'infrastructures pétrolières pour le MINEE/DPPG.

### Types d'infrastructures gérées
- **Station-service** (Implantation / Reprise)
- **Point consommateur** (Implantation / Reprise)
- **Dépôt GPL** (Implantation)
- **Centre emplisseur** (Implantation)

### Workflow en 11 étapes
1. Création du dossier + upload documents
2. Constitution de la commission (3 membres)
3. Génération automatique note de frais
4. Enregistrement paiement → notification
5. Analyse juridique (DAJ)
6. Contrôle complétude (DPPG)
7. Inspection terrain + rapport
8. Validation rapport (Chef commission)
9. Circuit visa (Chef Service → Sous-Dir → Dir)
10. Décision ministérielle
11. Publication au registre public

---

## 2. Connexion au système {#connexion}

### Accès
- URL: `http://localhost/dppg-implantation` (environnement local)
- Page de connexion: `index.php`

### Première connexion
1. Saisir votre **nom d'utilisateur** et **mot de passe**
2. Cliquer sur "Se connecter"
3. Vous serez redirigé vers votre tableau de bord personnalisé

### Mot de passe oublié
1. Cliquer sur "Mot de passe oublié ?"
2. Saisir votre email
3. Suivre les instructions reçues par email

---

## 3. Guides par rôle {#guides-par-rôle}

## 3.1 Chef de Service SDTD {#chef-de-service}

### Responsabilités
- Création et gestion centralisée de tous les dossiers
- Constitution des commissions d'inspection
- Premier niveau de visa
- Supervision générale du workflow

### Actions principales

#### A. Créer un nouveau dossier

1. **Accès**: Tableau de bord → "Nouveau dossier" OU Menu "Dossiers" → "Créer un dossier"

2. **Étape 1: Type d'infrastructure**
   - Sélectionner le type (Station-service, Point consommateur, Dépôt GPL, Centre emplisseur)
   - Choisir le sous-type (Implantation ou Reprise)
   - Cliquer "Suivant"

3. **Étape 2: Informations du demandeur**
   - Nom/Raison sociale **(obligatoire)**
   - Personne de contact
   - Téléphone **(obligatoire)**
   - Email (recommandé pour notifications)

4. **Étape 3: Localisation**
   - Région **(obligatoire)**
   - Ville **(obligatoire)**
   - Adresse précise
   - Coordonnées GPS (latitude, longitude) - _optionnel mais recommandé_

5. **Étape 4: Informations spécifiques**

   _Selon le type d'infrastructure:_

   **Station-service:**
   - Opérateur propriétaire **(obligatoire)**

   **Point consommateur:**
   - Opérateur **(obligatoire)**
   - Entreprise bénéficiaire **(obligatoire)**
   - Contrat de livraison

   **Dépôt GPL:**
   - Entreprise installatrice **(obligatoire)**

   **Centre emplisseur:**
   - Opérateur de gaz OU Entreprise constructrice **(obligatoire)**

6. **Étape 5: Upload des documents**

   Documents requis:
   - Pièce d'identité du demandeur
   - Plan d'implantation
   - Autorisation du terrain
   - Étude d'impact environnemental
   - Autres documents justificatifs

   _Formats acceptés: PDF, DOC, DOCX, JPG, PNG (max 10 Mo par fichier)_

7. **Validation**
   - Vérifier toutes les informations
   - Cliquer "Créer le dossier"
   - Un numéro unique sera automatiquement généré (ex: DPPG-2025-001)

#### B. Constituer une commission

1. Accéder au dossier: "Mes dossiers" → Cliquer sur le dossier
2. Cliquer sur l'onglet "Commission"
3. Sélectionner **3 membres obligatoires**:
   - 1 Cadre DPPG (inspecteur)
   - 1 Cadre DAJ (juriste)
   - 1 Chef de commission (président)
4. Cliquer "Constituer la commission"
5. La note de frais sera **automatiquement générée**

#### C. Apposer le visa

1. Aller dans "Dossiers en attente de visa"
2. Sélectionner le dossier
3. Vérifier le rapport d'inspection
4. Cliquer "Apposer mon visa"
5. Le dossier passe au Sous-Directeur

#### D. Gérer les huitaines

**Qu'est-ce qu'une huitaine?**
Délai de 8 jours donné au demandeur pour régulariser un dossier incomplet.

**Déclenchement:**
1. Accéder au dossier incomplet
2. Onglet "Huitaine" → "Déclencher une huitaine"
3. Indiquer le motif (document manquant, information erronée, etc.)
4. Le système notifie automatiquement à J-2, J-1, et J (deadline)
5. Si non régularisé → rejet automatique

**Régularisation:**
1. Le demandeur fournit les documents manquants
2. Chef Service vérifie
3. Cliquer "Régulariser la huitaine"
4. Le dossier reprend son cours normal

#### E. Consulter les statistiques

**Tableau de bord:**
- Total dossiers créés
- Dossiers en cours
- Dossiers payés
- Taux d'approbation
- Délai moyen de traitement

**Carte interactive:**
- Menu "Carte" → Visualiser toutes les infrastructures autorisées
- Filtrer par type, région
- Statistiques géographiques

---

## 3.2 Billeteur DPPG {#billeteur}

### Responsabilités
- Enregistrement des paiements
- Génération des reçus
- Suivi des encaissements

### Actions principales

#### A. Enregistrer un paiement

1. **Accès**: "Dossiers en attente de paiement" OU "Tous les dossiers" → Filtrer statut "En cours"

2. **Saisie du paiement**:
   - Ouvrir le dossier
   - Onglet "Paiement" → "Enregistrer un paiement"
   - Remplir le formulaire:
     * Montant **(vérifié avec note de frais)**
     * Mode de paiement (Espèces, Chèque, Virement)
     * Date de paiement
     * Référence (n° chèque ou virement)
     * Observations (optionnel)

3. **Validation**:
   - Cliquer "Enregistrer le paiement"
   - Un reçu est **automatiquement généré** (PDF)
   - **Notification automatique** envoyée au demandeur et aux cadres DPPG/DAJ

4. **Imprimer le reçu**:
   - Cliquer sur "Télécharger le reçu"
   - Format PDF prêt à imprimer

#### B. Consulter l'historique des paiements

1. Menu "Paiements" → "Liste des paiements"
2. Filtres disponibles:
   - Par période
   - Par mode de paiement
   - Par montant
3. Export Excel disponible

---

## 3.3 Cadre DAJ {#cadre-daj}

### Responsabilités
- Analyse juridique et réglementaire des dossiers
- Vérification de la conformité légale
- Validation juridique

### Actions principales

#### A. Analyser un dossier

1. **Accès**: "Mes dossiers à analyser" (statut "Payé")

2. **Analyse juridique**:
   - Ouvrir le dossier
   - Onglet "Analyse DAJ"
   - Vérifier:
     * Conformité des documents d'identité
     * Validité des autorisations
     * Respect des normes réglementaires
     * Complétude du dossier juridique

3. **Rédaction de l'avis**:
   - Champ "Avis juridique": Détailler l'analyse
   - Sélectionner la conformité:
     * ✅ **Conforme** → Le dossier peut continuer
     * ⚠️ **Conforme sous réserve** → Préciser les réserves
     * ❌ **Non conforme** → Indiquer les motifs

4. **Observations et recommandations**:
   - Préciser les points d'attention
   - Recommandations pour l'inspection

5. **Validation**:
   - Cliquer "Soumettre l'analyse"
   - Le dossier passe au "Contrôle DPPG"

#### B. Gérer les huitaines juridiques

Si documents juridiques manquants:
1. Demander une huitaine au Chef de Service
2. Indiquer précisément les documents requis
3. Suivre la régularisation

---

## 3.4 Cadre DPPG (Inspecteur) {#cadre-dppg}

### Responsabilités
- Inspection physique des infrastructures
- Contrôle de complétude des dossiers
- Rédaction des rapports d'inspection
- Vérification conformité technique

### Actions principales

#### A. Contrôler la complétude d'un dossier

1. **Accès**: "Dossiers en contrôle DPPG"

2. **Vérification**:
   - Ouvrir le dossier
   - Onglet "Contrôle DPPG"
   - Vérifier que tous les documents requis sont présents
   - Check-list automatique affichée

3. **Actions possibles**:
   - ✅ **Dossier complet** → Passer à l'inspection
   - ❌ **Dossier incomplet** → Demander huitaine

#### B. Réaliser une inspection

1. **Planification**:
   - "Mes inspections à réaliser"
   - Coordonner avec le Chef de commission
   - Noter la date de visite

2. **Sur le terrain**:
   - Utiliser la grille d'évaluation technique
   - Prendre photos (upload direct depuis mobile possible)
   - Noter les observations

3. **Rédaction du rapport**:
   - Retour au bureau → Ouvrir le dossier
   - Onglet "Inspection" → "Rédiger le rapport"
   - Remplir:
     * Date d'inspection
     * Rapport détaillé (minimum 200 caractères)
     * Conformité:
       - ✅ **Conforme** (infrastructure respecte les normes)
       - ⚠️ **Conforme sous réserve** (ajustements mineurs requis)
       - ❌ **Non conforme** (non-respect des normes)
     * Recommandations
     * Observations complémentaires

4. **Upload photos**:
   - Joindre les photos de l'inspection
   - Formats: JPG, PNG

5. **Validation**:
   - Cliquer "Soumettre le rapport"
   - Le rapport est envoyé au Chef de commission pour validation

#### C. Géolocaliser une infrastructure

1. Lors de l'inspection, noter les coordonnées GPS exactes
2. Dans le dossier → Onglet "Localisation"
3. Saisir latitude et longitude OU cliquer sur la carte interactive
4. Valider
5. L'infrastructure apparaîtra sur la carte publique une fois autorisée

---

## 3.5 Chef de Commission {#chef-de-commission}

### Responsabilités
- Coordination des visites d'inspection
- Validation des rapports d'inspection
- Présidence de la commission

### Actions principales

#### A. Consulter mes commissions

1. Menu "Mes commissions"
2. Liste des dossiers où vous êtes chef de commission
3. Statuts:
   - 🟡 Commission constituée
   - 🟠 En mission (inspection en cours)
   - 🟢 Rapport validé

#### B. Planifier une visite

1. Accéder au dossier
2. Onglet "Commission" → "Planifier la visite"
3. Choisir:
   - Date de visite
   - Heure
   - Lieu de rendez-vous
4. Les membres sont **automatiquement notifiés**

#### C. Valider un rapport d'inspection

1. **Accès**: "Rapports en attente de validation"

2. **Lecture du rapport**:
   - Ouvrir le dossier
   - Onglet "Inspection"
   - Lire attentivement le rapport du cadre DPPG
   - Consulter les photos

3. **Validation**:
   - Avis du chef de commission (optionnel)
   - Actions:
     * ✅ **Valider le rapport** → Dossier passe au circuit de visa
     * ❌ **Demander révision** → Retour à l'inspecteur avec commentaires

4. **Signature électronique**:
   - Confirmer avec votre mot de passe
   - Le rapport est signé et validé

---

## 3.6 Sous-Directeur SDTD {#sous-directeur}

### Responsabilités
- Deuxième niveau de visa
- Supervision du traitement des dossiers

### Actions principales

#### A. Apposer le visa

1. **Accès**: "Dossiers en attente de mon visa"

2. **Vérification**:
   - Ouvrir le dossier
   - Consulter:
     * Rapport d'inspection validé
     * Avis DAJ
     * Visa du Chef de Service
   - Vérifier la cohérence du dossier

3. **Décision**:
   - ✅ **Apposer mon visa** → Passe au Directeur
   - ❌ **Refuser** → Indiquer le motif (dossier retourne en arrière)
   - ⏸️ **Demander complément** → Préciser les éléments manquants

4. **Validation**:
   - Cliquer "Apposer mon visa"
   - Signature électronique (mot de passe requis)

---

## 3.7 Directeur DPPG {#directeur}

### Responsabilités
- Troisième niveau de visa (final avant ministre)
- Transmission des dossiers au ministère
- Validation finale technique

### Actions principales

#### A. Visa du Directeur

1. **Accès**: "Dossiers en attente de mon visa"

2. **Examen approfondi**:
   - Vérifier tous les visas précédents
   - S'assurer de la complétude
   - Valider la conformité technique et juridique

3. **Décision**:
   - ✅ **Apposer mon visa** → Transmission au Ministre
   - ❌ **Refuser** → Motif détaillé
   - 📝 **Demander révision** → Commentaires

4. **Transmission au Ministre**:
   - Cliquer "Transmettre au Ministre"
   - Le dossier change de statut → "Transmission ministre"

#### B. Consulter les statistiques globales

1. Menu "Statistiques"
2. Tableaux de bord:
   - Performance globale
   - Délais de traitement par étape
   - Taux d'approbation
   - Évolution mensuelle
3. Export Excel/PDF disponible

---

## 3.8 Cabinet/Ministre {#ministre}

### Responsabilités
- Décision finale (Approbation / Refus)
- Signature de l'arrêté ministériel

### Actions principales

#### A. Prendre la décision finale

1. **Accès**: "Dossiers transmis pour décision"

2. **Examen du dossier complet**:
   - Lire l'intégralité du dossier
   - Consulter tous les avis
   - Vérifier les visas

3. **Décision**:
   - ✅ **APPROUVER** l'implantation
     * Saisir la référence de l'arrêté ministériel
     * Date de décision
     * Observations (optionnel)

   - ❌ **REFUSER** l'implantation
     * Indiquer le motif détaillé du refus
     * Date de décision

4. **Validation**:
   - Cliquer "Enregistrer la décision"
   - Le dossier est **automatiquement publié** au registre public
   - Notifications envoyées au demandeur

#### B. Consulter le registre des décisions

1. Menu "Registre des décisions"
2. Historique complet de toutes les décisions
3. Filtres: Approuvées / Refusées / Par période

---

## 3.9 Administrateur Système {#admin}

### Responsabilités
- Gestion complète des utilisateurs
- Configuration du système
- Maintenance et sauvegardes
- Support technique

### Actions principales

#### A. Gérer les utilisateurs

1. **Créer un utilisateur**:
   - Menu "Utilisateurs" → "Nouvel utilisateur"
   - Remplir:
     * Nom, Prénom
     * Email
     * Téléphone
     * Nom d'utilisateur (unique)
     * Mot de passe (min 8 caractères)
   - Assigner un ou plusieurs rôles
   - Activer/Désactiver

2. **Modifier un utilisateur**:
   - Liste des utilisateurs → Cliquer sur l'utilisateur
   - Modifier les informations
   - Changer les rôles
   - Réinitialiser le mot de passe

3. **Désactiver/Réactiver**:
   - Liste des utilisateurs
   - Toggle "Actif/Inactif"
   - Un utilisateur inactif ne peut plus se connecter

#### B. Gérer les rôles et permissions

1. Menu "Rôles" → Liste des 10 rôles
2. Pour chaque rôle:
   - Voir les permissions
   - Liste des utilisateurs ayant ce rôle

#### C. Consulter les logs

1. **Logs d'activité**:
   - Menu "Logs" → "Activité système"
   - Filtrer par:
     * Utilisateur
     * Action
     * Période
     * Module

2. **Logs emails**:
   - Menu "Logs" → "Emails"
   - Voir tous les emails envoyés
   - Statut: Envoyé / Échoué / Désactivé

#### D. Configuration du système

1. **Paramètres généraux**:
   - Menu "Configuration"
   - Nom de l'application
   - Logo
   - Couleurs du thème

2. **Configuration email**:
   - Paramètres SMTP
   - Email expéditeur
   - Activer/Désactiver l'envoi réel

3. **Sauvegardes**:
   - Menu "Maintenance" → "Sauvegardes"
   - Créer une sauvegarde manuelle
   - Restaurer une sauvegarde
   - Programmer des sauvegardes automatiques

---

## 3.10 Lecteur Public {#lecteur}

### Responsabilités
- Consultation du registre public uniquement
- Aucune modification possible

### Actions principales

#### A. Consulter le registre public

1. **Accès sans authentification**: `modules/registre_public/`

2. **Recherche avancée**:
   - Par mot-clé (n° dossier, nom, opérateur, ville)
   - Par type d'infrastructure
   - Par région
   - Par ville
   - Par statut (Autorisées / Refusées / Fermées)
   - Par année de décision

3. **Consulter un dossier**:
   - Cliquer sur "Voir détails"
   - Informations publiques affichées:
     * Numéro de dossier
     * Type d'infrastructure
     * Localisation
     * Opérateur
     * Décision finale
     * Référence de la décision

#### B. Carte interactive publique

1. Accès: Menu "Carte"
2. Voir toutes les infrastructures autorisées
3. Filtrer par type et région
4. Cliquer sur un marqueur pour voir les détails

#### C. Statistiques publiques

1. Menu "Statistiques"
2. Voir:
   - Nombre total d'infrastructures autorisées
   - Répartition par type
   - Répartition par région
   - Évolution mensuelle
   - Top opérateurs

#### D. Exporter les données

1. Après une recherche
2. Cliquer "Exporter Excel"
3. Télécharger le fichier CSV (compatible Excel)

---

## Fonctionnalités transversales

### Notifications

**Types de notifications:**
- 🔔 In-app (compteurs sur le dashboard)
- 📧 Email (si configuré)

**Événements déclencheurs:**
- Changement de statut
- Paiement enregistré
- Huitaine (J-2, J-1, J)
- Inspection réalisée
- Décision finale
- Affectation à une commission

**Consulter:**
- Icône cloche en haut à droite
- "Voir toutes les notifications"

### Historique

Chaque dossier conserve un historique complet:
- Toutes les actions
- Qui a fait quoi
- Quand
- Changements de statut
- Documents uploadés

**Accès:** Onglet "Historique" dans le dossier

### Documents

**Upload:**
- Formats acceptés: PDF, DOC, DOCX, JPG, PNG
- Taille max: 10 Mo par fichier
- Plusieurs fichiers en une fois

**Versioning:**
- Si vous uploadez un document avec le même nom, une nouvelle version est créée
- L'ancienne version est conservée

**Téléchargement:**
- Cliquer sur le nom du document
- Téléchargement direct

### Export et rapports

**Export Excel:**
- Liste de dossiers → Bouton "Exporter Excel"
- Tous les filtres appliqués sont respectés

**Rapport PDF:**
- Ouvrir un dossier
- Bouton "Générer rapport PDF"
- Rapport complet imprimable

---

## Résolution des problèmes courants

### Je ne peux pas me connecter

✅ **Solutions:**
- Vérifier votre nom d'utilisateur et mot de passe
- Vider le cache du navigateur
- Vérifier que votre compte est actif (contacter l'admin)
- Utiliser "Mot de passe oublié"

### L'upload de document échoue

✅ **Solutions:**
- Vérifier la taille (max 10 Mo)
- Vérifier le format (PDF, DOC, JPG, PNG uniquement)
- Vérifier votre connexion internet
- Essayer avec un autre navigateur

### Je ne reçois pas les notifications email

✅ **Solutions:**
- Vérifier votre adresse email dans votre profil
- Vérifier vos spams
- Contacter l'administrateur (emails peut-être désactivés en configuration)

### Un dossier n'apparaît pas dans ma liste

✅ **Solutions:**
- Vérifier les filtres appliqués
- Vérifier que vous avez les permissions pour ce dossier
- Vérifier le statut du dossier
- Actualiser la page (F5)

---

## Support et assistance

**Contact support:**
- Email: support@dppg.cm
- Téléphone: +237 XXX XXX XXX

**Horaires:**
- Lundi - Vendredi: 8h - 17h

**En cas d'urgence:**
- Contacter l'administrateur système directement

---

**Version du guide:** 1.0
**Dernière mise à jour:** Octobre 2025
**MINEE/DPPG - République du Cameroun**
