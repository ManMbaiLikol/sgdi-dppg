# Debug: Constitution de Commission

## Problème

Erreur lors de la constitution de commission:
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'chef_commission_role' at row 1
```

## Corrections apportées

### 1. Debug logging ajouté

Le code log maintenant toutes les valeurs dans `php_error.log`:

```
=== DEBUG CONSTITUTION COMMISSION ===
chef_commission_id: XX
chef_commission_role RAW: 'valeur'
chef_commission_role CLEAN: 'valeur'
chef_commission_role LENGTH: X
```

### 2. Validation côté serveur améliorée

Le code vérifie maintenant que le rôle est valide avant l'insertion:
- chef_service
- chef_commission
- sous_directeur
- directeur

### 3. Debug JavaScript amélioré

Le JavaScript log maintenant dans la Console du navigateur:
- Quand `updateChefRole()` est appelé
- La valeur récupérée du `data-role`
- La valeur mise dans le champ caché

### 4. Validation côté client ajoutée

Le formulaire valide maintenant avant soumission:
- Que le champ `chef_commission_role` n'est pas vide
- Que le rôle est dans la liste des rôles valides
- Affiche une alerte si problème détecté

### 5. Indicateur visuel

Un message "✓ Rôle: XXX" s'affiche maintenant sous le sélecteur pour confirmer que le JavaScript a fonctionné.

## Comment tester

### Étape 1: Ouvrir la Console du navigateur

1. Dans Chrome/Edge: `F12` → Onglet "Console"
2. Dans Firefox: `F12` → Onglet "Console"

### Étape 2: Accéder au formulaire

Allez sur `modules/dossiers/commission.php?id=XXX` (avec un ID de dossier valide)

### Étape 3: Sélectionner un chef de commission

1. **Sélectionnez un utilisateur** dans le menu déroulant
2. **Vérifiez dans la Console** que vous voyez:
   ```
   === updateChefRole appelé ===
   Select value: 18
   Role récupéré: chef_commission
   data-role attribute: chef_commission
   Champ chef_commission_role mis à jour: chef_commission
   ```

3. **Vérifiez l'indicateur visuel**:
   Un message "✓ Rôle: chef_commission" (ou autre) doit apparaître en vert sous le sélecteur

### Étape 4: Soumettre le formulaire

1. **Remplissez les autres champs** (Cadre DPPG, Cadre DAJ)
2. **Cliquez sur "Constituer la commission"**
3. **Vérifiez dans la Console**:
   ```
   === SOUMISSION FORMULAIRE ===
   chef_commission_id: 18
   chef_commission_role: chef_commission
   Validation OK, soumission du formulaire...
   ```

### Étape 5: Vérifier les logs PHP

Si l'erreur persiste, vérifiez le fichier de logs PHP:

**Sur WAMP:**
```
C:\wamp64\logs\php_error.log
```

Vous devriez voir:
```
=== DEBUG CONSTITUTION COMMISSION ===
chef_commission_id: 18
chef_commission_role RAW: 'chef_commission'
chef_commission_role CLEAN: 'chef_commission'
chef_commission_role LENGTH: 16
...
=== VALEURS À INSÉRER ===
dossier_id: 1
chef_commission_id: 18
chef_commission_role: 'chef_commission'
...
```

## Erreurs possibles et solutions

### Erreur: Le JavaScript ne s'exécute pas

**Symptômes:**
- Rien dans la Console
- Pas d'indicateur "✓ Rôle: XXX"

**Solutions:**
1. Vider le cache du navigateur (Ctrl+F5)
2. Vérifier qu'il n'y a pas d'erreur JavaScript dans la Console
3. Vérifier que jQuery ou autre librairie ne bloque pas l'exécution

### Erreur: "Rôle invalide: ''"

**Symptômes:**
- Le champ `chef_commission_role` est vide
- Le JavaScript ne remplit pas le champ

**Solutions:**
1. Vérifier que l'attribut `data-role` est bien présent sur les options:
   ```html
   <option value="18" data-role="chef_commission">...</option>
   ```
2. Recharger la page complètement (F5)
3. Resélectionner le chef de commission

### Erreur: "Data truncated"

**Symptômes:**
- L'erreur SQL persiste malgré le debug
- Le rôle apparaît correct dans les logs

**Solutions possibles:**

#### Solution A: Vérifier l'ENUM en base

```bash
php -r "require 'config/database.php'; \$stmt = \$pdo->query('SHOW COLUMNS FROM commissions LIKE \"chef_commission_role\"'); print_r(\$stmt->fetch());"
```

Devrait afficher:
```
Type: enum('chef_service','chef_commission','sous_directeur','directeur')
```

#### Solution B: Ré-exécuter les migrations

```bash
php database/migrations/run_migration_002.php
```

#### Solution C: Migration manuelle

Si les migrations ne s'exécutent pas, exécutez directement en SQL:

```sql
ALTER TABLE commissions
MODIFY COLUMN chef_commission_role ENUM(
    'chef_service',
    'chef_commission',
    'sous_directeur',
    'directeur'
) NOT NULL;
```

### Erreur: Alerte "Le rôle n'a pas été détecté"

**Symptômes:**
- Une alerte s'affiche lors de la soumission
- Le formulaire ne se soumet pas

**Solutions:**
1. C'est normal! Le JavaScript protège contre les erreurs
2. Recharger la page (F5)
3. Resélectionner le chef de commission
4. Vérifier la Console pour voir pourquoi le rôle n'a pas été détecté

## Vérification rapide de l'ENUM

Pour vérifier rapidement l'état de l'ENUM:

```bash
php -r "
require 'config/database.php';
\$stmt = \$pdo->query('SHOW COLUMNS FROM commissions LIKE \"chef_commission_role\"');
\$col = \$stmt->fetch();
echo 'Type: ' . \$col['Type'] . PHP_EOL;
"
```

## Rapport de bug

Si le problème persiste après toutes ces vérifications, fournissez:

1. **Logs JavaScript** (copier la Console complète)
2. **Logs PHP** (extraits de php_error.log)
3. **État de l'ENUM** (résultat de SHOW COLUMNS)
4. **Capture d'écran** du formulaire avec l'indicateur visible
5. **Quel utilisateur** a été sélectionné comme chef de commission

## Nettoyage après résolution

Une fois le problème résolu, vous pouvez retirer le debug:

1. **Supprimer les `error_log()`** dans `commission.php` lignes 60-66 et 87-92
2. **Simplifier le JavaScript** (garder juste la validation de base)
3. **Retirer l'indicateur visuel** si non désiré

## Documentation connexe

- `CORRECTIONS_2025-10-24.md` - Corrections des ENUM
- `MIGRATION_RAILWAY.md` - Migrations Railway
- `database/migrations/002_fix_commissions_role_enum.sql` - Migration ENUM
