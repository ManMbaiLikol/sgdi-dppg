# 🚀 QUICK START - SGDI

**Démarrage rapide en 5 minutes !**

---

## ⚡ Pour les pressés

```bash
# 1. Accéder à l'application
http://localhost/dppg-implantation

# 2. Se connecter
Utilisateur: admin
Mot de passe: Admin@2025

# 3. C'est parti ! 🎉
```

---

## 📋 ÉTAPES DÉTAILLÉES

### Étape 1: Accès (30 secondes)

**URL:** `http://localhost/dppg-implantation/`

Vous verrez la page de connexion SGDI avec le logo MINEE/DPPG.

---

### Étape 2: Connexion (30 secondes)

**Comptes disponibles:**

| Utilisateur | Mot de passe | Rôle |
|------------|--------------|------|
| `admin` | `Admin@2025` | Administrateur |
| `chef.service` | `Test@2025` | Chef de Service |
| `billeteur.test` | `Test@2025` | Billeteur |
| `cadre.dppg` | `Test@2025` | Inspecteur DPPG |
| `cadre.daj` | `Test@2025` | Cadre DAJ |

⚠️ **Changez ces mots de passe en production !**

---

### Étape 3: Premier dossier (2 minutes)

**En tant que Chef de Service:**

1. **Connexion** avec `chef.service`

2. **Cliquer** sur "Nouveau dossier" (gros bouton bleu)

3. **Wizard - Étape 1:** Choisir type
   - Type: `Station-service`
   - Sous-type: `Implantation`
   - Cliquer "Suivant"

4. **Wizard - Étape 2:** Informations demandeur
   - Nom: `TOTAL CAMEROUN`
   - Téléphone: `+237 600 000 001`
   - Email: `total@example.com`
   - Cliquer "Suivant"

5. **Wizard - Étape 3:** Localisation
   - Région: `Centre`
   - Ville: `Yaoundé`
   - Adresse: `Carrefour Bastos`
   - Cliquer "Suivant"

6. **Wizard - Étape 4:** Informations spécifiques
   - Opérateur propriétaire: `TOTAL CAMEROUN`
   - Cliquer "Suivant"

7. **Wizard - Étape 5:** Documents
   - Cliquer "Créer le dossier" (upload optionnel pour le test)

✅ **Dossier créé !** Numéro: DPPG-2025-001

---

### Étape 4: Constituer commission (1 minute)

1. **Cliquer** sur le dossier créé

2. **Onglet "Commission"**

3. **Sélectionner 3 membres:**
   - Cadre DPPG: Choisir dans la liste
   - Cadre DAJ: Choisir dans la liste
   - Chef commission: Choisir dans la liste

4. **Cliquer** "Constituer la commission"

✅ **Commission constituée !** Note de frais générée automatiquement.

---

### Étape 5: Enregistrer paiement (1 minute)

**Se déconnecter et reconnecter avec `billeteur.test`**

1. **Menu** "Dossiers en attente de paiement"

2. **Cliquer** sur le dossier

3. **Onglet "Paiement"** → "Enregistrer un paiement"

4. **Remplir:**
   - Montant: `250 000` (pré-rempli depuis note de frais)
   - Mode: `Virement`
   - Date: Aujourd'hui
   - Référence: `VIR-2025-001`

5. **Cliquer** "Enregistrer"

✅ **Paiement enregistré !** Reçu PDF généré + notifications envoyées.

---

## 🎯 WORKFLOW COMPLET EN 5 MINUTES

**Pour tester le workflow complet de A à Z:**

### 1. Chef Service (2 min)
- ✅ Créer dossier
- ✅ Constituer commission

### 2. Billeteur (1 min)
- ✅ Enregistrer paiement

### 3. Cadre DAJ (1 min)
- Se connecter: `cadre.daj`
- "Mes dossiers à analyser"
- Cliquer sur le dossier
- Onglet "Analyse DAJ"
- Avis: "Conforme"
- Cliquer "Soumettre"

### 4. Cadre DPPG (2 min)
- Se connecter: `cadre.dppg`
- "Mes dossiers en contrôle"
- Marquer comme complet
- "Mes inspections à réaliser"
- Rédiger rapport d'inspection:
  * Date: Aujourd'hui
  * Rapport: "Infrastructure conforme aux normes"
  * Conformité: "Oui"
- Cliquer "Soumettre"

### 5. Chef Commission (1 min)
- Se connecter: `chef.commission`
- "Rapports en attente de validation"
- Lire le rapport
- Cliquer "Valider le rapport"

### 6. Circuit visa (3 min)
- Chef Service → "Apposer mon visa"
- (Sous-directeur si compte existe) → Visa
- (Directeur si compte existe) → Visa + "Transmettre au ministre"

### 7. Ministre (1 min)
- (Si compte ministre existe)
- "Dossiers transmis pour décision"
- Décision: "Approuver"
- Référence: "ARR-2025-001"
- Cliquer "Enregistrer la décision"

✅ **Dossier complet !** Automatiquement publié au registre public.

---

## 🌐 CONSULTER LE REGISTRE PUBLIC

**Sans authentification :**

1. **URL:** `http://localhost/dppg-implantation/modules/registre_public/`

2. **Rechercher** l'infrastructure que vous venez de créer

3. **Voir la carte interactive**

4. **Consulter les statistiques publiques**

5. **Exporter en Excel**

---

## 📊 TABLEAU DE BORD

**Chaque rôle a un dashboard personnalisé:**

### Chef de Service
- Total dossiers créés
- Dossiers en cours
- Dossiers payés
- Actions rapides
- Carte des infrastructures
- Statistiques

### Billeteur
- Dossiers en attente de paiement
- Paiements du mois
- Montant total encaissé
- Derniers paiements

### Cadre DAJ
- Dossiers à analyser
- Analyses effectuées
- Taux conformité
- Dossiers en huitaine

### Cadre DPPG
- Dossiers en contrôle
- Inspections à réaliser
- Rapports rédigés
- Carte des sites

---

## 🔔 NOTIFICATIONS

**Automatiques à chaque:**
- Changement de statut
- Paiement enregistré
- Huitaine (J-2, J-1, deadline)
- Affectation à une commission
- Décision finale

**Voir les notifications:**
- Icône cloche en haut à droite
- Compteurs sur le dashboard

---

## 📄 DOCUMENTS

**Uploader des documents:**
1. Ouvrir un dossier
2. Onglet "Documents"
3. "Ajouter des documents"
4. Sélectionner fichiers (PDF, DOC, JPG, PNG)
5. Choisir type de document
6. Cliquer "Uploader"

**Formats acceptés:** PDF, DOC, DOCX, JPG, PNG
**Taille max:** 10 Mo par fichier

---

## 📈 EXPORTS

### Export Excel
1. N'importe quelle liste de dossiers
2. Appliquer filtres (optionnel)
3. Cliquer "Exporter Excel"
4. Télécharger le CSV

### Rapport PDF
1. Ouvrir un dossier
2. Bouton "Générer rapport PDF"
3. Rapport complet s'affiche (prêt à imprimer)

---

## 🗺️ CARTE INTERACTIVE

**Accès:**
- Menu "Carte des infrastructures" (utilisateurs authentifiés)
- OU `modules/registre_public/carte.php` (public)

**Fonctionnalités:**
- Marqueurs colorés par type
- Clustering automatique
- Pop-ups avec détails
- Filtres (type, région)
- Statistiques en temps réel

---

## ⏰ SYSTÈME HUITAINE

**Déclenchement automatique** quand dossier incomplet.

**Délai:** 8 jours pour régulariser

**Notifications:**
- J-2: Alerte
- J-1: Alerte urgente
- J: Deadline

**Régularisation:**
1. Demandeur fournit documents manquants
2. Chef Service vérifie
3. "Régulariser la huitaine"
4. Dossier reprend son cours

**Si non régularisé:** Rejet automatique

---

## 🔒 SÉCURITÉ

### Bonnes pratiques

✅ **Changer mots de passe par défaut**
✅ **Utiliser mots de passe forts** (min 8 caractères, maj/min/chiffres/symboles)
✅ **Ne jamais partager identifiants**
✅ **Se déconnecter après utilisation**
✅ **Vérifier URL avant de saisir mot de passe**

### En cas de problème
- Mot de passe oublié: Cliquer "Mot de passe oublié ?" sur page connexion
- Compte bloqué: Contacter administrateur
- Bug/Erreur: Contacter support@dppg.cm

---

## 📞 AIDE RAPIDE

### Problèmes fréquents

**❓ Je ne peux pas me connecter**
- Vérifier nom d'utilisateur et mot de passe
- Vider cache navigateur (Ctrl+F5)
- Essayer avec un autre navigateur

**❓ L'upload échoue**
- Vérifier taille fichier (max 10 Mo)
- Vérifier format (PDF, DOC, JPG, PNG uniquement)
- Réessayer avec fichier plus petit

**❓ Je ne vois pas mes dossiers**
- Vérifier filtres appliqués
- Actualiser la page (F5)
- Vérifier que vous avez les permissions pour ce type de dossier

**❓ Notification non reçue**
- Vérifier email dans votre profil
- Vérifier dossier spam
- Les emails peuvent être désactivés en configuration

---

## 📚 ALLER PLUS LOIN

**Documentation complète:**
- `docs/GUIDE_UTILISATEUR_COMPLET.md` - Guide exhaustif 70+ pages
- `docs/GUIDE_RAPIDE_PAR_ROLE.md` - Cartes de référence par rôle
- `README.md` - Vue d'ensemble du projet

**Guides spécialisés:**
- `GUIDE_HUITAINE.md` - Système de délais
- `GUIDE_TEST_CARTOGRAPHIE.md` - Fonctionnalités géographiques
- `GUIDE_UX_UI.md` - Design et ergonomie

---

## 🎉 C'EST PARTI !

Vous êtes maintenant prêt à utiliser le **SGDI**.

**Prochaines étapes:**
1. Créez votre premier dossier de test
2. Testez le workflow complet
3. Explorez les différents rôles
4. Consultez le registre public
5. Générez vos premiers rapports

**Besoin d'aide?**
- 📧 Email: support@dppg.cm
- 📞 Tél: +237 XXX XXX XXX
- 📖 Documentation: `docs/`

---

**Bonne utilisation du SGDI ! 🇨🇲**

*MINEE/DPPG - République du Cameroun*
