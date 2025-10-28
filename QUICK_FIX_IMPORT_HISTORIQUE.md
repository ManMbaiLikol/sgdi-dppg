# ⚡ Quick Fix - Module Import Historique

**Date :** 28 octobre 2025
**Temps estimé :** 10 minutes

---

## 🎯 Problème

Le module d'import de dossiers historiques avait des erreurs sur Railway :
- ❌ Colonne `est_historique` inexistante
- ❌ Statut `historique_autorise` non reconnu
- ❌ Erreurs SQL lors de l'import

---

## ✅ Solution en 3 étapes

### Étape 1 : Appliquer la migration SQL (5 min)

**En local (WAMP) :**
```bash
mysql -u root sgdi_mvp < database/migrations/add_import_historique_FIXED.sql
```

**Sur Railway :**
Aller sur : `https://votre-app.railway.app/modules/import_historique/migrate_database.php`
- Se connecter en admin
- Cocher "Je confirme..."
- Cliquer sur "Exécuter la migration"
- Attendre les messages de succès ✅

---

### Étape 2 : Tester (2 min)

Aller sur : `https://votre-app.railway.app/modules/import_historique/test_fixed.php`

**Résultat attendu :**
```
✅ Tous les tests ont réussi !
Tests réussis : 8 / 8 (100%)
```

---

### Étape 3 : Vérifier avec un import test (3 min)

1. Aller sur : `/modules/import_historique/index.php`
2. Télécharger le template stations-service
3. Remplir 5 lignes de test
4. Importer
5. Confirmer
6. Vérifier que les dossiers sont créés ✅

---

## 🔧 Fichiers modifiés

1. **Migration SQL corrigée**
   - `database/migrations/add_import_historique_FIXED.sql`
   - Ajoute les colonnes manquantes
   - Corrige le statut ENUM

2. **Code PHP corrigé**
   - `modules/import_historique/functions.php`
   - Détection automatique de la table historique
   - Compatible avec la structure actuelle

3. **Script de test**
   - `modules/import_historique/test_fixed.php`
   - 8 tests automatiques
   - Rapport détaillé

---

## 📊 Ce qui a été ajouté

### Colonnes dans `dossiers`
- `est_historique` (BOOLEAN)
- `importe_le` (DATETIME)
- `importe_par` (INT)
- `source_import` (VARCHAR)
- `numero_decision_ministerielle` (VARCHAR)
- `date_decision_ministerielle` (DATE)
- `lieu_dit` (VARCHAR)

### Nouvelles tables
- `entreprises_beneficiaires`
- `logs_import_historique`

### Vue SQL
- `v_dossiers_historiques`

---

## ✅ Checklist rapide

- [ ] Migration SQL exécutée
- [ ] Tests passent (8/8)
- [ ] Import test réussi
- [ ] Dossiers visibles au registre public

---

## 🚨 En cas d'erreur

**Erreur : "Unknown column 'est_historique'"**
→ La migration n'a pas été exécutée. Recommencer l'étape 1.

**Erreur : "Data truncated for column 'statut'"**
→ Le statut ENUM n'a pas été mis à jour. Ré-exécuter la migration.

**Tests échouent**
→ Consulter le document complet : `CORRECTIONS_MODULE_IMPORT_HISTORIQUE.md`

---

**Voir détails complets :** `CORRECTIONS_MODULE_IMPORT_HISTORIQUE.md`
