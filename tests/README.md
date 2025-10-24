# 🧪 Suite de Tests SGDI

Suite de tests automatisés pour valider le bon fonctionnement du Système de Gestion des Dossiers d'Implantation.

## 📋 Tests Disponibles

### 1. Tests Permissions Dossiers (`permissions/test_dossiers_permissions.php`)

Valide les corrections de sécurité appliquées le 24 octobre 2025:

**Test 1:** Cadre DPPG - Visibilité commission uniquement
- ✅ Vérifie qu'un cadre DPPG ne voit QUE les dossiers où il est membre de la commission
- ✅ Vérifie spécifiquement que Christian ABANDA ne voit PAS les dossiers de Salomon MAÏ

**Test 2:** Fonction `canAccessDossier()`
- ✅ Valide le contrôle d'accès pour tous les rôles (admin, chef_service, cadre_dppg, etc.)
- ✅ Vérifie les règles spécifiques à chaque rôle

**Test 3:** Page "Faire une inspection"
- ✅ Valide le filtrage par commission dans `modules/fiche_inspection/list_dossiers.php`
- ✅ Vérifie que seuls les dossiers assignés sont visibles

**Test 4:** Cohérence getDossiers() et countDossiers()
- ✅ Vérifie que les deux fonctions retournent le même nombre de dossiers

---

## 🚀 Exécution des Tests

### Option 1: Ligne de commande (Recommandé)

```bash
cd C:\wamp64\www\dppg-implantation\tests
php run_tests.php
```

### Option 2: Via le navigateur (Local)

```
http://localhost/dppg-implantation/tests/run_tests.php?token=sgdi_test_2025
```

### Option 3: Via Railway (Production)

```bash
curl "https://sgdi-dppg-production.up.railway.app/tests/run_tests.php?token=sgdi_test_2025"
```

### Option 4: Test spécifique

```bash
# Test permissions uniquement
php tests/permissions/test_dossiers_permissions.php
```

---

## 📊 Sortie des Tests

```
╔════════════════════════════════════════════════════════════════════╗
║         SUITE DE TESTS - PERMISSIONS DOSSIERS                     ║
║         Date: 24/10/2025 22:30:15                                 ║
╚════════════════════════════════════════════════════════════════════╝

📋 TEST 1: Cadre DPPG - Visibilité dossiers commission uniquement
======================================================================
👤 Utilisateur: Christian ABANDA (ID: 27)
📊 Dossiers visibles: 1

  • Dossier SS20251024025528: ✅ Membre commission

🔍 Vérification dossiers de Salomon MAÏ:
  • PC20251010224931: ✅ NON VISIBLE
  • PC20251010222326: ✅ NON VISIBLE

✅ TEST RÉUSSI

[... autres tests ...]

╔════════════════════════════════════════════════════════════════════╗
║                        RÉSUMÉ DES TESTS                            ║
╚════════════════════════════════════════════════════════════════════╝

  Tests exécutés: 4
  ✅ Réussis: 4
  ❌ Échoués: 0

🎉 TOUS LES TESTS SONT PASSÉS! 🎉
Les corrections de permissions fonctionnent correctement.
```

---

## 🔒 Sécurité

### Tests en production (Railway)

- **Token requis:** `sgdi_test_2025`
- Ne pas exposer le token publiquement
- Les tests ne modifient PAS les données (lecture seule)

### Recommandations

1. **Exécuter après chaque modification de permissions**
2. **Vérifier avant déploiement en production**
3. **Garder les tests à jour avec les règles de gestion**

---

## 🛠️ Ajouter de Nouveaux Tests

### Structure d'un test

```php
<?php
require_once __DIR__ . '/../../config/database.php';

class MesNouveauxTests {
    private $pdo;
    private $results = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function testMonNouveau() {
        echo "\n📋 TEST: Mon nouveau test\n";
        echo str_repeat("=", 70) . "\n";

        // Logique du test
        $resultat = true; // ou false

        echo "\n" . ($resultat ? "✅ TEST RÉUSSI" : "❌ TEST ÉCHOUÉ") . "\n";
        return $resultat;
    }

    public function runAll() {
        // Exécuter tous les tests
        return $this->testMonNouveau();
    }
}

// Point d'entrée
if (php_sapi_name() === 'cli' || (isset($_GET['run']) && $_GET['run'] === 'tests')) {
    $tester = new MesNouveauxTests($pdo);
    $success = $tester->runAll();
    exit($success ? 0 : 1);
}
?>
```

### Ajouter au script principal

Éditer `run_tests.php` et ajouter:

```php
$test_files = [
    'permissions/test_dossiers_permissions.php' => 'Tests Permissions Dossiers',
    'mon_module/mes_nouveaux_tests.php' => 'Mes Nouveaux Tests'  // ← Ajouter ici
];
```

---

## 📝 Historique

### 24 octobre 2025
- ✅ Création suite de tests permissions
- ✅ Tests corrections bug visibilité dossiers cadre_dppg
- ✅ Tests fonction `canAccessDossier()`
- ✅ Tests page "Faire une inspection"

---

## 📞 Support

En cas de problème avec les tests:
1. Vérifier la connexion à la base de données
2. Vérifier que les utilisateurs de test existent (Christian ID: 27, Salomon ID: 16)
3. Vérifier les permissions PHP sur le répertoire `tests/`

---

**Dernière mise à jour:** 24 octobre 2025
