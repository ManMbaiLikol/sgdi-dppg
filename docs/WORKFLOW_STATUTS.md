# Workflow de Validation des Dossiers d'Implantation

## Vue d'ensemble

Ce document décrit le workflow complet de validation d'un dossier d'implantation dans le système SGDI, avec les statuts, les rôles responsables et les actions à chaque étape.

## Workflow des Statuts

### 1. Création du dossier
- **Statut** : `cree`
- **Rôle** : Chef de Service SDTD
- **Action** : Création du dossier avec toutes les informations de base
- **Label** : "Créé"

### 2. Création de la note de frais
- **Statut** : `en_cours`
- **Rôle** : Chef de Service SDTD
- **Action** : Constitution de la commission et génération de la note de frais
- **Label** : "En cours"

### 3. Transmission de la note au demandeur
- **Statut** : `note_transmise`
- **Rôle** : Chef de Service SDTD
- **Action** : Transmission de la note de frais au demandeur pour paiement
- **Label** : "Note transmise"

### 4. Paiement de la note
- **Statut** : `paye`
- **Rôle** : Billeteur DPPG
- **Action** : Enregistrement du paiement effectué par le demandeur
- **Label** : "Payé"
- **Note** : Une notification automatique est envoyée à tous les membres de la commission

### 5. Analyse juridique (DAJ)
**Deux possibilités :**

#### 5a. Dossier avec irrégularités
- **Statut** : `en_huitaine`
- **Rôle** : Cadre DAJ
- **Action** : Identification d'irrégularités (pièces non conformes ou manquantes)
- **Label** : "En huitaine"
- **Note** : Le demandeur a 8 jours pour régulariser le dossier

#### 5b. Dossier conforme
- **Statut** : `analyse_daj`
- **Rôle** : Cadre DAJ
- **Action** : Validation de la conformité juridique du dossier
- **Label** : "Analysé"

### 6. Inspection technique
- **Statut** : `inspecte`
- **Rôle** : Cadre DPPG (Inspecteur)
- **Action** : Visite sur site et rédaction du rapport d'inspection
- **Label** : "Inspecté"

### 7. Validation par le Chef de Commission
- **Statut** : `validation_commission`
- **Rôle** : Chef de Commission
- **Action** : Validation du rapport d'inspection
- **Label** : "Validé par la commission"

### 8. Visa du Sous-Directeur SDTD
- **Statut** : `visa_sous_directeur`
- **Rôle** : Sous-Directeur SDTD
- **Action** : Premier niveau de visa des rapports
- **Label** : "Visa Sous-Directeur"

### 9. Visa du Directeur DPPG
- **Statut** : `visa_directeur`
- **Rôle** : Directeur DPPG
- **Action** : Deuxième niveau de visa et transmission au Cabinet
- **Label** : "Visa Directeur"

### 10. Décision ministérielle
- **Statut** : `decide`
- **Rôle** : Cabinet/Secrétariat Ministre
- **Action** : Prise de décision finale (favorable ou défavorable)
- **Label** : "Décidé"

### 11. Enregistrement de la décision
**Deux possibilités :**

#### 11a. Décision favorable
- **Statut** : `autorise`
- **Rôle** : Chef de Service SDTD
- **Action** : Enregistrement de l'autorisation et publication au registre public
- **Label** : "Autorisé"

#### 11b. Décision défavorable
- **Statut** : `rejete`
- **Rôle** : Chef de Service SDTD
- **Action** : Enregistrement du rejet
- **Label** : "Rejeté"

## Statuts additionnels

### Statut : `brouillon`
- **Usage** : Dossier en cours de création, non finalisé
- **Label** : "Brouillon"
- **Note** : Pour compatibilité avec les dossiers existants

### Statut : `visa_chef_service`
- **Usage** : Visa du Chef de Service (si utilisé dans le workflow)
- **Label** : "Visa Chef Service"

### Statut : `valide`
- **Usage** : Dossier validé (usage générique)
- **Label** : "Validé"

### Statut : `ferme`
- **Usage** : Gestion opérationnelle - infrastructure fermée
- **Label** : "Fermé"

### Statut : `suspendu`
- **Usage** : Gestion opérationnelle - infrastructure suspendue
- **Label** : "Suspendu"

## Schéma récapitulatif

```
1. Création
   └─> cree (Chef Service)
       │
2. Note de frais
   └─> en_cours (Chef Service)
       │
3. Transmission
   └─> note_transmise (Chef Service)
       │
4. Paiement
   └─> paye (Billeteur)
       │
5. Analyse DAJ
   ├─> en_huitaine (Cadre DAJ - irrégularités)
   └─> analyse_daj (Cadre DAJ - conforme)
       │
6. Inspection
   └─> inspecte (Cadre DPPG)
       │
7. Validation commission
   └─> validation_commission (Chef Commission)
       │
8. Visa Sous-Directeur
   └─> visa_sous_directeur (Sous-Directeur)
       │
9. Visa Directeur
   └─> visa_directeur (Directeur)
       │
10. Décision ministérielle
    └─> decide (Cabinet/Ministre)
        │
11. Enregistrement
    ├─> autorise (Chef Service - favorable)
    └─> rejete (Chef Service - défavorable)
```

## Notes importantes

1. **Ordre strict** : Les étapes doivent être respectées dans l'ordre indiqué
2. **Notification automatique** : Après le paiement, tous les membres de la commission sont notifiés
3. **Huitaine** : Si le dossier est en huitaine, le demandeur a 8 jours pour régulariser
4. **Registre public** : Seuls les dossiers avec statut `autorise` sont publiés au registre public
5. **Permissions** : Chaque rôle ne peut effectuer que les actions qui lui sont attribuées

## Codes couleur (badges Bootstrap)

- `brouillon` → secondary (gris)
- `cree` → info (bleu clair)
- `en_cours` → warning (orange)
- `note_transmise` → info (bleu clair)
- `paye` → primary (bleu)
- `en_huitaine` → danger (rouge)
- `analyse_daj` → info (bleu clair)
- `inspecte` → primary (bleu)
- `validation_commission` → success (vert)
- `visa_sous_directeur` → info (bleu clair)
- `visa_directeur` → info (bleu clair)
- `decide` → dark (noir)
- `autorise` → success (vert)
- `rejete` → danger (rouge)
- `ferme` → secondary (gris)
- `suspendu` → warning (orange)

---

**Date de dernière mise à jour** : 2025-10-08
**Version** : 1.0
