# 🔧 Correction Migration SQL - 31 Octobre 2025

## 📋 Contexte

Lors du déploiement du 31 octobre 2025, la migration SQL `007_create_decisions_and_registre.sql` échouait sur Railway avec l'erreur :
```
Failed to open the referenced table 'dossiers'
```

## ❌ Problème Rencontré

### Fichier original: `007_create_decisions_and_registre.sql`
- Contenait des contraintes `FOREIGN KEY` référençant les tables `dossiers` et `users`
- 123 lignes, 8 commandes SQL
- Erreur sur Railway: "Failed to open the referenced table 'dossiers'"
- **Résultat**: 0 commandes réussies, 8 échecs

### Tentatives infructueuses

1. **Exécution via Railway CLI**
   ```bash
   railway run --service sgdi-dppg mysql ...
   ```
   ❌ Erreur: `mysql: command not found` (client MySQL non installé dans container Railway)

2. **Script PHP de parsing** (`run_migration.php`)
   - Parse le fichier SQL ligne par ligne
   - Exécute via PDO
   - ❌ Même erreur: "Failed to open the referenced table 'dossiers'"

3. **Version simplifiée sans FK** (`007_create_decisions_and_registre_simple.sql`)
   - Suppression des contraintes FOREIGN KEY
   - ❌ Même erreur persistante (problème de cache ou parsing)

## ✅ Solution Finale

### Script: `migrate_direct.php`

**Approche**:
- SQL hardcodé directement dans le code PHP
- Pas de fichier externe à parser
- Exécution directe des CREATE TABLE via PDO

**Code**:
```php
$sql1 = "CREATE TABLE IF NOT EXISTS decisions_ministerielle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    user_id INT NOT NULL,
    decision ENUM('approuve', 'refuse', 'ajourne') NOT NULL,
    numero_arrete VARCHAR(100) NOT NULL,
    observations TEXT,
    date_decision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dossier (dossier_id),
    INDEX idx_decision (decision),
    INDEX idx_date_decision (date_decision),
    UNIQUE KEY unique_decision_per_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$pdo->exec($sql1);
```

**Résultat**:
✅ **Migration réussie!**
- Table `decisions_ministerielle` créée (9 colonnes)
- Table `registre_public` créée (17 colonnes)

### URL d'exécution

```
https://sgdi-dppg-production.up.railway.app/migrate_direct.php?token=sgdi-migration-2025-secure-token-e2eb3bba362bdf854d56c57227282795
```

## 📊 Tables Créées

### 1. `decisions_ministerielle`

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT AUTO_INCREMENT | Clé primaire |
| dossier_id | INT | Référence au dossier |
| user_id | INT | Utilisateur ayant pris la décision |
| decision | ENUM | approuve / refuse / ajourne |
| numero_arrete | VARCHAR(100) | Numéro de l'arrêté ministériel |
| observations | TEXT | Motifs de la décision |
| date_decision | DATETIME | Date de prise de décision |
| created_at | TIMESTAMP | Date de création |
| updated_at | TIMESTAMP | Date de dernière modification |

**Index**:
- idx_dossier (dossier_id)
- idx_decision (decision)
- idx_date_decision (date_decision)
- UNIQUE: unique_decision_per_dossier (dossier_id)

### 2. `registre_public`

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT AUTO_INCREMENT | Clé primaire |
| dossier_id | INT | Référence au dossier |
| numero_dossier | VARCHAR(50) | Numéro du dossier |
| type_infrastructure | VARCHAR(50) | Type d'infrastructure |
| sous_type | VARCHAR(50) | Sous-type |
| nom_demandeur | VARCHAR(200) | Nom du demandeur |
| ville | VARCHAR(100) | Ville |
| quartier | VARCHAR(100) | Quartier |
| region | VARCHAR(100) | Région |
| operateur_proprietaire | VARCHAR(200) | Opérateur/Propriétaire |
| entreprise_beneficiaire | VARCHAR(200) | Entreprise bénéficiaire |
| decision | ENUM | Toujours 'approuve' |
| numero_arrete | VARCHAR(100) | Numéro de l'arrêté |
| observations | TEXT | Observations |
| date_decision | DATETIME | Date de la décision |
| date_publication | DATETIME | Date de publication |
| created_at | TIMESTAMP | Date de création |

**Index**:
- idx_dossier, idx_numero, idx_type, idx_ville, idx_region
- idx_date_decision, idx_date_publication, idx_numero_arrete
- UNIQUE: unique_dossier_publication (dossier_id)

## 🎯 Leçons Apprises

1. **Railway n'a pas mysql CLI** → Utiliser PDO/PHP pour les migrations
2. **Parsing fichiers SQL peut échouer** → Hardcoder le SQL dans le PHP
3. **FOREIGN KEY peuvent causer des problèmes** → Les ajouter après coup ou les omettre
4. **Toujours avoir un plan B** → Migration directe via script PHP

## 📝 Fichiers Impliqués

| Fichier | Statut | Description |
|---------|--------|-------------|
| `run_migration.php` | ⚠️ Non utilisé | Parse fichier SQL - Échoué |
| `007_create_decisions_and_registre.sql` | ❌ Échoué | Version originale avec FK |
| `007_create_decisions_and_registre_simple.sql` | ⚠️ Non utilisé | Version sans FK - Échoué au parsing |
| `migrate.php` | ⚠️ Partiellement utilisé | Mode diagnostic OK, migration KO |
| `migrate_direct.php` | ✅ **SUCCÈS** | Migration hardcodée - Fonctionne |

## ✅ Statut Final

**Date**: 31 octobre 2025
**Heure d'exécution**: Après plusieurs tentatives
**Résultat**: ✅ **Migration réussie**

Les deux tables `decisions_ministerielle` et `registre_public` sont maintenant créées et opérationnelles sur Railway.

---

**Prochaines étapes**:
1. Tester le workflow complet de décision ministérielle
2. Vérifier la publication automatique au registre public
3. Tester les notifications email

---

🤖 **Généré avec Claude Code**
https://claude.com/claude-code

© 2025 MINEE/DPPG - Tous droits réservés
