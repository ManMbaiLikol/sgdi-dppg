# Instructions d'import des dossiers historiques

## Vue d'ensemble

Ce module permet d'importer les dossiers d'autorisation traités avant la mise en place du SGDI. Deux templates sont disponibles :

1. **template_import_stations_service.csv** - Pour les stations-service
2. **template_import_points_consommateurs.csv** - Pour les points consommateurs

## Format des fichiers

- **Format accepté** : CSV (séparateur point-virgule `;`) ou Excel (.xlsx)
- **Encodage** : UTF-8
- **Limite par fichier** : 200 lignes maximum
- **Taille maximale** : 5 MB

## Colonnes obligatoires

### Pour TOUS les types d'infrastructure

| Colonne | Description | Format | Obligatoire |
|---------|-------------|--------|-------------|
| `numero_dossier` | Numéro du dossier (laissez vide pour génération auto) | Texte | Non |
| `type_infrastructure` | Type exact (voir liste ci-dessous) | Texte | **Oui** |
| `nom_demandeur` | Nom de la société opérateur | Texte | **Oui** |
| `region` | Région du Cameroun (voir liste ci-dessous) | Texte | **Oui** |
| `ville` | Ville ou localité | Texte | **Oui** |
| `latitude` | Coordonnée latitude | Nombre décimal | Non |
| `longitude` | Coordonnée longitude | Nombre décimal | Non |
| `date_autorisation` | Date de la décision ministérielle | JJ/MM/AAAA | **Oui** |
| `numero_decision` | Numéro de la décision ministérielle | Texte | **Oui** |
| `observations` | Remarques éventuelles | Texte | Non |

### Colonnes supplémentaires pour POINTS CONSOMMATEURS

| Colonne | Description | Format | Obligatoire |
|---------|-------------|--------|-------------|
| `entreprise_beneficiaire` | Nom de l'entreprise bénéficiaire | Texte | **Oui** |
| `activite_entreprise` | Secteur d'activité | Texte | Non |

## Valeurs valides

### Types d'infrastructure (à copier exactement)

- `Implantation station-service`
- `Reprise station-service`
- `Implantation point consommateur`
- `Reprise point consommateur`
- `Implantation dépôt GPL`
- `Implantation centre emplisseur`

### Régions (à copier exactement)

- `Adamaoua`
- `Centre`
- `Est`
- `Extrême-Nord`
- `Littoral`
- `Nord`
- `Nord-Ouest`
- `Ouest`
- `Sud`
- `Sud-Ouest`

### Format des dates

Utilisez **JJ/MM/AAAA** :
- Correct : `15/03/2015`
- Correct : `01/12/2020`
- Incorrect : `15-03-2015`
- Incorrect : `2015/03/15`

### Coordonnées GPS (optionnelles)

- **Latitude** : Entre -90 et 90 (ex: `4.0511`)
- **Longitude** : Entre -180 et 180 (ex: `9.7679`)

Pour trouver les coordonnées GPS :
1. Ouvrir Google Maps
2. Cliquer droit sur l'emplacement
3. Cliquer sur les coordonnées pour les copier

## Numérotation automatique

Si vous laissez la colonne `numero_dossier` **vide**, le système génère automatiquement un numéro au format :

**Format** : `HIST-[TYPE]-[REGION]-[ANNEE]-[SEQUENCE]`

**Exemples** :
- `HIST-SS-LT-2015-001` (Station-Service, Littoral, 2015, #1)
- `HIST-PC-CE-2018-045` (Point Consommateur, Centre, 2018, #45)
- `HIST-GPL-OU-2019-003` (Dépôt GPL, Ouest, 2019, #3)

**Codes types** :
- `SS` = Station-Service
- `PC` = Point Consommateur
- `GPL` = Dépôt GPL
- `CE` = Centre Emplisseur

**Codes régions** :
- `AD` = Adamaoua
- `CE` = Centre
- `ES` = Est
- `EN` = Extrême-Nord
- `LT` = Littoral
- `NO` = Nord
- `NW` = Nord-Ouest
- `OU` = Ouest
- `SU` = Sud
- `SW` = Sud-Ouest

## Processus d'import

### 1. Préparation du fichier

1. Ouvrir le template correspondant dans Excel ou LibreOffice
2. **NE PAS modifier la ligne d'en-tête**
3. Remplir les données ligne par ligne
4. Commencer à partir de la ligne 6 (les lignes 2-5 contiennent des exemples)
5. Enregistrer au format CSV (séparateur point-virgule)

### 2. Upload et validation

1. Accéder au module "Import Historique" dans le SGDI
2. Cliquer sur "Choisir un fichier"
3. Sélectionner votre fichier CSV
4. Cliquer sur "Valider et Prévisualiser"

Le système va :
- ✅ Vérifier le format du fichier
- ✅ Valider chaque ligne
- ✅ Afficher les erreurs éventuelles
- ✅ Montrer un aperçu des données à importer

### 3. Correction des erreurs

Si des erreurs sont détectées :
1. Télécharger le rapport d'erreurs
2. Corriger les lignes problématiques dans votre fichier
3. Réimporter le fichier

### 4. Import final

Une fois la validation réussie :
1. Vérifier la prévisualisation
2. Cliquer sur "Confirmer l'import"
3. Attendre la confirmation
4. Télécharger le rapport d'import

## Erreurs courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| "Type d'infrastructure invalide" | Faute de frappe | Copier-coller depuis la liste valide |
| "Région invalide" | Nom incorrect | Utiliser exactement un nom de la liste |
| "Format de date invalide" | Mauvais format | Utiliser JJ/MM/AAAA |
| "Entreprise bénéficiaire obligatoire" | Champ vide pour PC | Remplir le nom de l'entreprise |
| "Latitude/Longitude invalide" | Valeur hors limites | Vérifier les coordonnées GPS |

## Conseils et bonnes pratiques

### Organisation de l'import

**Recommandé** : Importer par lots de 50-100 dossiers
- ✅ Plus facile à corriger en cas d'erreur
- ✅ Meilleure traçabilité
- ✅ Validation progressive

**Stratégie suggérée** :
1. Commencer par une région (ex: Littoral)
2. Valider l'import
3. Vérifier dans le registre public
4. Continuer avec les autres régions

### Données incomplètes

Si certaines informations manquent :
- **Coordonnées GPS** : Laisser vide (vous pourrez les ajouter plus tard)
- **Observations** : Laisser vide si rien à noter
- **Date exacte inconnue** : Utiliser le 01/01 de l'année connue

### Qualité des données

Pour assurer la qualité :
- ✅ Vérifier l'orthographe des noms de sociétés
- ✅ Uniformiser les noms (ex: "TOTAL" vs "Total Cameroun")
- ✅ Vérifier les numéros de décision
- ✅ S'assurer que les dates sont cohérentes

### Doublons

Le système vérifie automatiquement les doublons sur :
- Numéro de dossier (si fourni)
- Numéro de décision ministérielle

## Test pilote recommandé

Avant l'import massif :

1. **Créer un fichier test de 10 dossiers**
2. **Importer et vérifier**
3. **Consulter le registre public**
4. **Vérifier les statistiques**
5. **Si OK, procéder aux imports complets**

## Aide et support

En cas de problème :
1. Consulter ce document
2. Télécharger le rapport d'erreurs
3. Contacter l'administrateur système
4. Email : support.sgdi@minee.gov.cm

## Exemple complet - Station-Service

```csv
numero_dossier;type_infrastructure;nom_demandeur;region;ville;latitude;longitude;date_autorisation;numero_decision;observations
;Implantation station-service;TOTAL CAMEROUN;Littoral;Douala;4.0511;9.7679;15/03/2015;N°0125/MINEE/SG/DPPG/SDTD;Station autorisée avant SGDI
```

**Résultat attendu** :
- Numéro généré : `HIST-SS-LT-2015-001`
- Statut : `Dossier Historique Autorisé`
- Visible dans le registre public
- Badge "Historique" affiché

## Exemple complet - Point Consommateur

```csv
numero_dossier;type_infrastructure;nom_demandeur;entreprise_beneficiaire;activite_entreprise;region;ville;latitude;longitude;date_autorisation;numero_decision;observations
;Implantation point consommateur;TOTAL CAMEROUN;CIMENCAM;Fabrication de ciment;Littoral;Bonabéri;4.0725;9.7006;22/05/2016;N°0198/MINEE/SG/DPPG/SDTD;Point consommateur autorisé avant SGDI
```

**Résultat attendu** :
- Numéro généré : `HIST-PC-LT-2016-001`
- Entreprise bénéficiaire enregistrée
- Statut : `Dossier Historique Autorisé`
- Visible dans le registre public

---

**Version** : 1.0
**Date** : Janvier 2025
**SGDI - Système de Gestion des Dossiers d'Implantation - MINEE/DPPG**
