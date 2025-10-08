-- Script de suppression du rôle "lecteur"
-- Le registre public remplace complètement l'espace lecteur

-- ========================================
-- 1. Supprimer les assignations du rôle lecteur
-- ========================================
DELETE FROM user_roles WHERE role_id = (SELECT id FROM roles WHERE code = 'lecteur');

-- ========================================
-- 2. Supprimer le rôle lecteur
-- ========================================
DELETE FROM roles WHERE code = 'lecteur';

-- ========================================
-- 3. Vérification
-- ========================================
SELECT '=== VÉRIFICATION ===' as info;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✓ Rôle lecteur supprimé avec succès'
        ELSE '✗ Le rôle lecteur existe encore'
    END as resultat
FROM roles WHERE code = 'lecteur';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✓ Aucun utilisateur n\'a le rôle lecteur'
        ELSE CONCAT('✗ ', COUNT(*), ' utilisateur(s) ont encore le rôle lecteur')
    END as resultat_users
FROM user_roles ur
JOIN roles r ON ur.role_id = r.id
WHERE r.code = 'lecteur';

-- ========================================
-- 4. Liste des rôles restants
-- ========================================
SELECT '=== RÔLES ACTIFS ===' as info;

SELECT id, nom, code, description
FROM roles
ORDER BY id;
