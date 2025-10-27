# Module d'Import de Dossiers Historiques

## Vue d'ensemble

Ce module permet d'importer dans le SGDI les dossiers d'autorisation traités **avant la mise en place du système**. Il s'agit principalement de :
- **995+ stations-service** (implantations et reprises)
- **500+ points consommateurs** (implantations et reprises)
- Dépôts GPL et centres emplisseurs

## Fonctionnalités

### 1. Import par lots
- Upload de fichiers CSV ou Excel
- Validation automatique des données
- Prévisualisation avant import
- Traitement par lots de 10 dossiers simultanément
- Maximum 200 lignes par fichier

### 2. Validation des données
Validation automatique de :
- ✅ Champs obligatoires
- ✅ Types d'infrastructure valides
- ✅ Régions valides
- ✅ Format des dates (JJ/MM/AAAA)
- ✅ Coordonnées GPS (si fournies)
- ✅ Données spécifiques par type (ex: entreprise bénéficiaire pour PC)

### 3. Génération automatique des numéros
Format : `HIST-[TYPE]-[REGION]-[ANNEE]-[SEQ]`

Exemples :
- `HIST-SS-LT-2015-001` → Station-Service, Littoral, 2015
- `HIST-PC-CE-2018-045` → Point Consommateur, Centre, 2018

### 4. Statut spécial
Les dossiers importés reçoivent le statut `HISTORIQUE_AUTORISE` qui :
- Contourne le workflow normal (pas de commission, paiement, visa, etc.)
- Apparaît directement dans le registre public
- Est marqué avec un badge "Historique"
- Conserve la date d'autorisation réelle

### 5. Tableau de bord
- Statistiques globales
- Répartition par type d'infrastructure
- Répartition par région
- Historique des imports
- Graphiques interactifs

## Structure des fichiers

```
modules/import_historique/
├── index.php                          # Page principale d'upload
├── preview.php                        # Prévisualisation et validation
├── process.php                        # Traitement de l'import
├── ajax_import_single.php             # Import AJAX d'un dossier
├── result.php                         # Page de résultats
├── dashboard.php                      # Tableau de bord
├── download_template.php              # Téléchargement des templates
├── functions.php                      # Fonctions métier
├── README.md                          # Documentation technique
└── templates/
    ├── template_import_stations_service.csv
    ├── template_import_points_consommateurs.csv
    └── INSTRUCTIONS_IMPORT.md         # Guide utilisateur
```

## Installation

### 1. Migration de la base de données

Exécuter le script SQL :

```bash
mysql -u root -p sgdi < database/migrations/add_import_historique.sql
```

Ou via phpMyAdmin : importer le fichier `add_import_historique.sql`

### 2. Créer le répertoire temporaire

```bash
mkdir -p uploads/temp
chmod 755 uploads/temp
```

### 3. Permissions utilisateurs

Seuls ces rôles peuvent accéder au module :
- **Admin Système** : Accès complet
- **Chef de Service SDTD** : Import et validation

## Utilisation

### Workflow d'import

1. **Télécharger le template**
   - Stations-service : `template_import_stations_service.csv`
   - Points consommateurs : `template_import_points_consommateurs.csv`

2. **Remplir les données**
   - Respecter l'en-tête (ne pas modifier)
   - Utiliser le format de date JJ/MM/AAAA
   - Copier-coller les noms exacts (régions, types)

3. **Upload du fichier**
   - Sélectionner le fichier CSV/Excel
   - Ajouter une description (optionnel)
   - Confirmer le format

4. **Validation et prévisualisation**
   - Vérifier les données affichées
   - Télécharger le rapport d'erreurs si nécessaire
   - Corriger et réimporter si erreurs

5. **Confirmation de l'import**
   - Cocher la case de confirmation
   - Cliquer sur "Confirmer l'import"
   - Attendre la fin du traitement

6. **Vérification**
   - Consulter le rapport de résultats
   - Vérifier dans le registre public
   - Consulter le tableau de bord

### Format CSV requis

**Pour stations-service :**
```csv
numero_dossier;type_infrastructure;nom_demandeur;region;ville;latitude;longitude;date_autorisation;numero_decision;observations
```

**Pour points consommateurs (colonnes supplémentaires) :**
```csv
;entreprise_beneficiaire;activite_entreprise
```

### Types d'infrastructure valides

- `Implantation station-service`
- `Reprise station-service`
- `Implantation point consommateur`
- `Reprise point consommateur`
- `Implantation dépôt GPL`
- `Implantation centre emplisseur`

### Régions valides

Adamaoua, Centre, Est, Extrême-Nord, Littoral, Nord, Nord-Ouest, Ouest, Sud, Sud-Ouest

## Structure de la base de données

### Nouvelles colonnes dans `dossiers`

```sql
est_historique BOOLEAN            -- TRUE pour dossiers importés
importe_le DATETIME               -- Date/heure de l'import
importe_par INT                   -- ID utilisateur importeur
source_import VARCHAR(100)        -- Description de l'import
numero_decision_ministerielle     -- Numéro décision
date_decision_ministerielle       -- Date décision
```

### Nouvelle table `entreprises_beneficiaires`

```sql
id INT PRIMARY KEY
dossier_id INT                    -- Référence au dossier
nom VARCHAR(200)                  -- Nom entreprise
activite VARCHAR(200)             -- Secteur d'activité
```

### Nouveau statut

```sql
code: HISTORIQUE_AUTORISE
libelle: Dossier Historique Autorisé
```

### Vue `v_dossiers_historiques`

Vue SQL pratique pour consulter tous les dossiers historiques avec leurs informations.

## Fonctions principales

### `peutImporterHistorique($role)`
Vérifie si un rôle peut accéder au module

### `validerLigneImport($ligne, $numero_ligne)`
Valide une ligne du fichier d'import

### `genererNumeroDossierHistorique($type, $region, $annee)`
Génère un numéro unique pour un dossier historique

### `lireCSV($fichier)`
Lit un fichier CSV et retourne un tableau

### `insererDossierHistorique($data, $user_id)`
Insère un dossier historique dans la base

### `getStatistiquesImport()`
Retourne les statistiques globales d'import

### `getHistoriqueImports($limit)`
Retourne l'historique des imports

## Intégration au registre public

Les dossiers historiques :
- ✅ Apparaissent dans le registre public
- ✅ Ont un badge "Historique" distinctif
- ✅ Sont inclus dans les recherches et filtres
- ✅ Affichent la date d'autorisation réelle
- ✅ Sont comptabilisés dans les statistiques globales

## Bonnes pratiques

### Import progressif
- Commencer par un test avec 50 dossiers
- Importer par région ou par type
- Valider après chaque lot
- Ne pas dépasser 200 lignes par fichier

### Qualité des données
- Vérifier l'orthographe des noms
- Uniformiser les dénominations
- Valider les numéros de décision
- Vérifier les dates

### Sécurité
- Sauvegarder la base avant import massif
- Conserver les fichiers sources
- Logger tous les imports
- Vérifier les doublons

## Dépannage

### Erreur "Type d'infrastructure invalide"
**Cause** : Faute de frappe dans le nom du type
**Solution** : Copier-coller depuis la liste valide

### Erreur "Format de date invalide"
**Cause** : Format incorrect (ex: AAAA-MM-JJ au lieu de JJ/MM/AAAA)
**Solution** : Utiliser le format JJ/MM/AAAA

### Erreur "Session expirée"
**Cause** : Plus de 30 minutes entre upload et confirmation
**Solution** : Réimporter le fichier

### Fichier trop volumineux
**Cause** : Plus de 200 lignes dans le fichier
**Solution** : Diviser en plusieurs fichiers

### Coordonnées GPS invalides
**Cause** : Latitude/longitude hors limites
**Solution** : Vérifier sur Google Maps

## Tests recommandés

### Phase 1 : Test pilote (50 dossiers)
1. Créer un fichier test de 50 dossiers variés
2. Tester l'import complet
3. Vérifier dans le registre public
4. Valider les statistiques

### Phase 2 : Import par région
1. Importer une région complète
2. Vérifier la cohérence
3. Procéder aux autres régions

### Phase 3 : Consolidation
1. Vérifier les statistiques globales
2. Contrôler les doublons
3. Valider le registre public complet

## Maintenance

### Nettoyage des fichiers temporaires

```bash
find uploads/temp -name "import_*" -mtime +7 -delete
```

### Requête pour vérifier les imports

```sql
SELECT
    DATE(importe_le) as date,
    COUNT(*) as nb_dossiers,
    GROUP_CONCAT(DISTINCT region) as regions
FROM dossiers
WHERE est_historique = 1
GROUP BY DATE(importe_le)
ORDER BY date DESC;
```

### Statistiques rapides

```sql
-- Total par type
SELECT ti.nom, COUNT(*) as total
FROM dossiers d
JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
WHERE d.est_historique = 1
GROUP BY ti.nom;

-- Total par région
SELECT region, COUNT(*) as total
FROM dossiers
WHERE est_historique = 1
GROUP BY region
ORDER BY total DESC;
```

## Support

Pour toute question ou problème :
1. Consulter le guide utilisateur (`INSTRUCTIONS_IMPORT.md`)
2. Vérifier ce README
3. Contacter l'administrateur système
4. Email : support.sgdi@minee.gov.cm

## Changelog

### Version 1.0 (Janvier 2025)
- ✅ Import par lots (CSV/Excel)
- ✅ Validation automatique
- ✅ Génération numéros automatique
- ✅ Prévisualisation
- ✅ Tableau de bord
- ✅ Intégration registre public
- ✅ Templates et documentation

---

**SGDI - Système de Gestion des Dossiers d'Implantation**
**MINEE/DPPG - Ministère de l'Eau et de l'Energie**
