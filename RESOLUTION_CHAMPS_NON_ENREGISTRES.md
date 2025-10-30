# 🔧 Résolution : Champs non enregistrés

## ⚠️ Problème

Les champs suivants ne s'enregistrent pas lors de la validation de la fiche :
- Section 3 (INFORMATIONS TECHNIQUES) - tous les champs spécifiques aux points consommateurs
- Section 8 (RECOMMANDATIONS)

## ✅ Solution rapide

Les nouveaux champs n'existent pas encore dans la base de données. Il faut appliquer les migrations SQL.

---

## 🚀 SOLUTION EN 1 ÉTAPE

### Méthode 1 : Script tout-en-un (recommandé)

```bash
mysql -u root sgdi_mvp < database/migrations/APPLIQUER_TOUTES_MIGRATIONS.sql
```

**OU via phpMyAdmin** :
1. Ouvrir http://localhost/phpmyadmin
2. Sélectionner la base `sgdi_mvp`
3. Onglet **SQL**
4. Copier/coller le contenu du fichier :
   `database/migrations/APPLIQUER_TOUTES_MIGRATIONS.sql`
5. Cliquer sur **Exécuter**

---

## 📋 Champs qui seront ajoutés (17 au total)

### Section 3 - Points Consommateurs (14 champs)
1. ✅ `numero_contrat_approvisionnement`
2. ✅ `societe_contractante`
3. ✅ `besoins_mensuels_litres`
4. ✅ `nombre_personnels`
5. ✅ `superficie_site`
6. ✅ `systeme_recuperation_huiles`
7. ✅ `parc_engin`
8. ✅ `batiments_site`
9. ✅ `infra_eau`
10. ✅ `infra_electricite`
11. ✅ `reseau_camtel`
12. ✅ `reseau_mtn`
13. ✅ `reseau_orange`
14. ✅ `reseau_nexttel`

### Section 8 - Recommandations (1 champ)
15. ✅ `recommandations`

---

## 🔍 Vérification après l'installation

### Méthode 1 : Via MySQL
```sql
SELECT COUNT(*) as nb_nouveaux_champs
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME IN (
      'besoins_mensuels_litres', 'parc_engin', 'systeme_recuperation_huiles',
      'nombre_personnels', 'superficie_site', 'batiments_site',
      'infra_eau', 'infra_electricite',
      'reseau_camtel', 'reseau_mtn', 'reseau_orange', 'reseau_nexttel',
      'numero_contrat_approvisionnement', 'societe_contractante',
      'recommandations'
  );
```

**Résultat attendu** : `15` (ou plus si vous avez déjà appliqué certaines migrations)

### Méthode 2 : Via phpMyAdmin
1. Sélectionner la base `sgdi_mvp`
2. Cliquer sur la table `fiches_inspection`
3. Onglet **Structure**
4. Vérifier que les 15 nouveaux champs sont présents

---

## 🧪 Test après l'installation

1. **Rechargez la page** de la fiche d'inspection (Ctrl+F5)
2. **Remplissez les champs** :
   - Section 3 : Numéro contrat, Société contractante, Besoins mensuels, etc.
   - Section 8 : Recommandations
3. **Cliquez sur "Enregistrer le brouillon"**
4. **Rechargez la page** → Les données doivent être conservées ✅

---

## ❌ Si le problème persiste

### Vérifier que les migrations ont été appliquées
```sql
DESCRIBE fiches_inspection;
```

Cherchez les champs suivants dans la liste :
- `recommandations`
- `numero_contrat_approvisionnement`
- `societe_contractante`
- `besoins_mensuels_litres`
- etc.

### Vérifier les erreurs PHP
1. Ouvrir le fichier de log Apache/PHP
2. Chercher les erreurs SQL du type :
   ```
   Unknown column 'recommandations' in 'field list'
   ```

### Solution alternative : Appliquer les migrations une par une

Si le script tout-en-un ne fonctionne pas, appliquez les migrations individuellement :

```bash
# Migration 1 : Champs points consommateurs
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_point_consommateur_fields.sql

# Migration 2 : Contrat d'approvisionnement
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_contrat_approvisionnement_fields.sql

# Migration 3 : Recommandations
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_recommandations_field.sql
```

---

## 📊 Récapitulatif des migrations

| Migration | Fichier | Champs ajoutés |
|-----------|---------|----------------|
| Points consommateurs | `2025_10_25_add_point_consommateur_fields.sql` | 12 champs |
| Contrat approvisionnement | `2025_10_25_add_contrat_approvisionnement_fields.sql` | 2 champs |
| Recommandations | `2025_10_25_add_recommandations_field.sql` | 1 champ |
| **TOTAL** | - | **15 champs** |

---

## ✅ Après l'installation

Une fois les migrations appliquées :
1. ✅ Les champs de la section 3 seront enregistrés
2. ✅ La section 8 (Recommandations) sera enregistrée
3. ✅ Toutes les futures fiches fonctionneront correctement
4. ✅ Les fiches existantes pourront être mises à jour

---

**Note importante** : Les données saisies AVANT l'application des migrations ne seront PAS récupérées. Il faudra les saisir à nouveau après avoir appliqué les migrations.

---

**Besoin d'aide ?** Consultez la documentation complète :
- `INSTALLATION_FICHE_POINT_CONSOMMATEUR.md`
- `AJOUT_CONTRAT_APPROVISIONNEMENT.md`
- `AJOUT_SECTION_RECOMMANDATIONS.md`
