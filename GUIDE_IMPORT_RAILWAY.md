# 🚀 Guide d'Import des Stations Historiques MINEE sur Railway

## 📋 Vue d'ensemble

Ce guide explique comment supprimer les anciennes stations historiques et importer les nouvelles données MINEE **directement sur Railway** (base PostgreSQL en production).

## ⚠️ AVERTISSEMENT

**Cette opération est IRRÉVERSIBLE !**

- ❌ Supprime TOUTES les stations historiques existantes (1006 stations)
- ✅ Importe 1006 nouvelles stations MINEE SANS coordonnées GPS
- 📍 Les GPS devront être ajoutés progressivement ultérieurement

## 📁 Fichiers générés

### 1. `railway_import_minee.sql` (404 Ko)
Fichier SQL complet contenant :
- Vérifications pré-import
- Suppression des stations historiques
- 1006 INSERT pour les nouvelles stations
- Vérifications post-import
- Statistiques par région et opérateur

### 2. `generate_railway_import.php`
Script PHP qui génère le fichier SQL à partir du CSV MINEE local

### 3. `railway_reset_historical.sql`
Script SQL de vérification avant suppression (optionnel)

## 🔧 Procédure d'Import sur Railway

### Étape 1 : Accéder à Railway Dashboard

1. Ouvrir https://railway.app/dashboard
2. Sélectionner votre projet **sgdi-dppg**
3. Cliquer sur le service **PostgreSQL**
4. Aller dans l'onglet **"Data"** ou **"Query"**

### Étape 2 : Vérification avant import (Optionnel mais recommandé)

Exécuter ces requêtes pour voir l'état actuel :

```sql
-- Compter les stations actuelles
SELECT COUNT(*) as total FROM dossiers;

-- Compter les stations historiques
SELECT COUNT(*) as historiques FROM dossiers WHERE est_historique = true;

-- Voir quelques exemples
SELECT id, numero, nom_demandeur, region, ville, coordonnees_gps
FROM dossiers
WHERE est_historique = true
LIMIT 10;
```

### Étape 3 : Exécuter l'import complet

1. **Ouvrir le fichier** `railway_import_minee.sql` dans un éditeur de texte
2. **Copier TOUT le contenu** (Ctrl+A, Ctrl+C)
3. **Coller dans la console Query de Railway**
4. **Cliquer sur "Run Query"** ou "Execute"

### Étape 4 : Vérifier le résultat

Après l'exécution, vous devriez voir :

```
✅ DELETE : X lignes supprimées (anciennes stations historiques)
✅ INSERT : 1006 lignes insérées (nouvelles stations MINEE)
```

Les requêtes de vérification à la fin du script afficheront :

```sql
-- Total de dossiers
total_apres: 1006 (ou plus si vous avez des dossiers non-historiques)

-- Stations historiques
historiques_apres: 1006

-- Répartition par région (exemple)
Centre: 350
Littoral: 200
Ouest: 180
...
```

## 📊 Résultat attendu

### Statistiques de l'import

- **Lignes traitées** : 1101 lignes du CSV
- **Stations importées** : 1006 stations
- **Lignes ignorées** : 95 (nom d'opérateur vide)

### Structure des données importées

Chaque station contient :

| Champ | Exemple | Note |
|-------|---------|------|
| `numero` | "1", "2", "3"... | Numéro d'enregistrement MINEE |
| `nom_demandeur` | "ABP PETROLEUM" | Nom de l'opérateur (Marketer) |
| `type_infrastructure` | "station_service" | Type fixe |
| `sous_type` | "implantation" | Sous-type fixe |
| `region` | "Centre" | Région administrative |
| `ville` | "Yaoundé" | Ville/Localité |
| `adresse_precise` | "Lieu-dit: Olezoa, Quartier: Olezoa..." | Adresse structurée complète |
| `statut` | "historique_autorise" | Statut fixe |
| `est_historique` | `TRUE` | Marqueur de station historique |
| `coordonnees_gps` | `NULL` | ⚠️ TOUS les GPS sont NULL |
| `user_id` | 1 | Admin système |
| `date_creation` | NOW() | Date d'import |

### Format de l'adresse

L'adresse complète combine tous les détails géographiques :

```
Lieu-dit: [lieu-dit], Quartier: [quartier], Arrondissement: [arrondissement],
Département: [département], Zone: [zone d'implantation]
```

Exemple :
```
Lieu-dit: Olezoa, Quartier: Olezoa, Arrondissement: Yaoundé IIIe,
Département: Mfoundi, Zone: Urbaine
```

## 🔍 Vérifications Post-Import

### 1. Vérifier le nombre total

```sql
SELECT COUNT(*) FROM dossiers WHERE est_historique = true;
-- Résultat attendu: 1006
```

### 2. Vérifier que TOUS les GPS sont NULL

```sql
SELECT COUNT(*) FROM dossiers
WHERE est_historique = true AND coordonnees_gps IS NOT NULL;
-- Résultat attendu: 0 (aucune station avec GPS)
```

### 3. Top 10 opérateurs

```sql
SELECT nom_demandeur, COUNT(*) as nb_stations
FROM dossiers
WHERE est_historique = true
GROUP BY nom_demandeur
ORDER BY nb_stations DESC
LIMIT 10;
```

### 4. Répartition par région

```sql
SELECT region, COUNT(*) as nb_stations
FROM dossiers
WHERE est_historique = true
GROUP BY region
ORDER BY nb_stations DESC;
```

## 🎯 Prochaines étapes après l'import

1. **✅ Vérifier l'import** avec les requêtes ci-dessus
2. **📍 Ajouter les GPS progressivement** via l'interface web :
   - Module "Stations Historiques"
   - Édition individuelle ou par lot
   - Vérification automatique de la contrainte 500m
3. **🗺️ Visualiser sur la carte** : `modules/carte/index.php`
4. **📊 Générer des statistiques** : `diagnostic_data_quality.php`

## 🆘 En cas de problème

### Erreur de syntaxe SQL

Si vous rencontrez une erreur de syntaxe :
- Vérifiez que vous avez copié **TOUT** le contenu du fichier
- Assurez-vous qu'il n'y a pas de caractères spéciaux corrompus
- Essayez d'exécuter par blocs (BEGIN...COMMIT)

### Import incomplet

Si l'import s'arrête avant la fin :
- Vérifiez les logs Railway pour les erreurs
- Exécutez manuellement les vérifications :
  ```sql
  SELECT COUNT(*) FROM dossiers WHERE est_historique = true;
  ```
- Si nécessaire, recommencez l'import (le script supprime d'abord les anciennes données)

### Rollback (annulation)

Si vous voulez annuler l'import :
- Railway garde des backups automatiques
- Vous pouvez restaurer une version précédente depuis le Dashboard
- Ou créer une nouvelle migration avec vos données de backup

## 📝 Notes importantes

1. **GPS NULL** : C'est NORMAL et VOULU. Les GPS seront ajoutés progressivement.
2. **Doublons** : Le script ne vérifie pas les doublons. Les anciennes données sont totalement supprimées.
3. **Performance** : L'import de 1006 stations prend environ 2-5 secondes sur Railway.
4. **Transaction** : Tout l'import est dans une transaction (BEGIN...COMMIT), donc soit tout réussit, soit rien ne change.

## ✅ Checklist de validation

- [ ] Backup de la base Railway créé (optionnel)
- [ ] Vérifications pré-import exécutées
- [ ] Script SQL copié et exécuté sur Railway
- [ ] Aucune erreur SQL affichée
- [ ] 1006 stations historiques présentes
- [ ] TOUS les GPS sont NULL
- [ ] Statistiques par région cohérentes
- [ ] Interface web fonctionne correctement
- [ ] Carte affiche les stations (sans GPS pour l'instant)

---

**Généré le** : 2025-11-03
**Taille du SQL** : 404.7 Ko
**Stations importées** : 1006
**Source** : `F:/PROJETS DPPG/Stations_Service-1_ANALYSE.csv`
