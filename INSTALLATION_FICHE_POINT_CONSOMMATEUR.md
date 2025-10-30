# Installation - Amélioration Fiche Inspection Point Consommateur

## Installation rapide

### Étape unique : Appliquer la migration SQL

Vous avez le choix entre 2 méthodes :

#### Méthode 1 : Via ligne de commande MySQL (recommandée)
```bash
cd C:\wamp64\www\dppg-implantation
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_point_consommateur_fields.sql
```

#### Méthode 2 : Via phpMyAdmin
1. Ouvrir http://localhost/phpmyadmin
2. Sélectionner la base de données `sgdi_mvp` (ou `sgdi` selon votre configuration)
3. Cliquer sur l'onglet **SQL**
4. Copier le contenu du fichier `database/migrations/2025_10_25_add_point_consommateur_fields.sql`
5. Coller dans la zone de texte
6. Cliquer sur **Exécuter**

Vous devriez voir le message : **"Migration des champs Point Consommateur terminée avec succès!"**

## Vérification de l'installation

### 1. Vérifier que les champs ont été ajoutés
Via phpMyAdmin ou ligne de commande :
```sql
DESCRIBE fiches_inspection;
```

Vous devriez voir les nouveaux champs :
- besoins_mensuels_litres
- parc_engin
- systeme_recuperation_huiles
- nombre_personnels
- superficie_site
- batiments_site
- infra_eau
- infra_electricite
- reseau_camtel
- reseau_mtn
- reseau_orange
- reseau_nexttel

### 2. Tester le formulaire

#### Test 1 : Point Consommateur
1. Connectez-vous en tant que **cadre_dppg**
2. Ouvrez un dossier de type **"Point Consommateur"**
3. Cliquez sur **"Créer une fiche d'inspection"** ou **"Modifier la fiche"**
4. Vérifiez que la section 3 affiche :
   - Besoins moyens mensuels
   - Nombre de personnels
   - Superficie du site
   - Système récupération huiles
   - Parc d'engin
   - Bâtiments
   - Checkboxes infrastructures
   - Checkboxes réseaux
5. Vérifiez que la section 5 (Distances) n'est **PAS affichée**

#### Test 2 : Station-Service
1. Ouvrez un dossier de type **"Station-Service"**
2. Cliquez sur **"Créer une fiche d'inspection"** ou **"Modifier la fiche"**
3. Vérifiez que la section 3 affiche les champs traditionnels :
   - Date de mise en service
   - Autorisations MINEE/MINMIDT
   - Type de gestion
   - Documents techniques
   - Chef de piste / Gérant
4. Vérifiez que la section 5 (Distances) **EST affichée**

## Fichiers modifiés

Les modifications suivantes ont été appliquées automatiquement :

✅ **Base de données** :
- `database/migrations/2025_10_25_add_point_consommateur_fields.sql` (nouveau)

✅ **Frontend** :
- `modules/fiche_inspection/edit.php` (modifié)

✅ **Backend** :
- `modules/fiche_inspection/functions.php` (modifié)

✅ **Documentation** :
- `docs/AMELIORATION_FICHE_INSPECTION_POINT_CONSOMMATEUR.md` (nouveau)

## En cas de problème

### Problème : Les nouveaux champs ne s'affichent pas
**Solution** : Vérifiez que la migration SQL a bien été exécutée :
```sql
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME = 'besoins_mensuels_litres';
```
Résultat attendu : **1**

### Problème : Erreur SQL lors de la sauvegarde
**Solution** : Vérifiez que tous les champs ont été ajoutés correctement avec la commande DESCRIBE ci-dessus.

### Problème : Le formulaire affiche toujours l'ancienne version
**Solution** :
1. Videz le cache de votre navigateur (Ctrl+F5)
2. Vérifiez que vous êtes sur un dossier de type "point_consommateur"

## Retour en arrière (rollback)

Si vous souhaitez annuler les modifications :
```sql
ALTER TABLE fiches_inspection
    DROP COLUMN besoins_mensuels_litres,
    DROP COLUMN parc_engin,
    DROP COLUMN systeme_recuperation_huiles,
    DROP COLUMN nombre_personnels,
    DROP COLUMN superficie_site,
    DROP COLUMN batiments_site,
    DROP COLUMN infra_eau,
    DROP COLUMN infra_electricite,
    DROP COLUMN reseau_camtel,
    DROP COLUMN reseau_mtn,
    DROP COLUMN reseau_orange,
    DROP COLUMN reseau_nexttel;
```

⚠️ **Attention** : Cette opération supprimera définitivement les données saisies dans ces champs.

## Support

Pour plus de détails, consultez la documentation complète :
📄 `docs/AMELIORATION_FICHE_INSPECTION_POINT_CONSOMMATEUR.md`
