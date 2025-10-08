-- Script de diagnostic pour la carte du lecteur sur Railway
-- À exécuter via HeidiSQL sur la base Railway

-- ========================================
-- 1. Vérifier la structure de la table
-- ========================================
SELECT '=== STRUCTURE DE LA TABLE dossiers ===' AS info;

SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dossiers'
AND COLUMN_NAME IN ('coordonnees_gps', 'operateur_proprietaire', 'statut', 'latitude', 'longitude')
ORDER BY ORDINAL_POSITION;

-- ========================================
-- 2. Compter les dossiers autorisés
-- ========================================
SELECT '=== DOSSIERS AUTORISÉS ===' AS info;

SELECT COUNT(*) as total_autorises
FROM dossiers
WHERE statut = 'autorise';

-- ========================================
-- 3. Compter les dossiers autorisés AVEC coordonnées GPS
-- ========================================
SELECT '=== DOSSIERS AUTORISÉS AVEC COORDONNÉES ===' AS info;

SELECT COUNT(*) as avec_coordonnees
FROM dossiers
WHERE statut = 'autorise'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != '';

-- ========================================
-- 4. Lister les dossiers autorisés et leurs coordonnées
-- ========================================
SELECT '=== DÉTAILS DES DOSSIERS AUTORISÉS ===' AS info;

SELECT
    id,
    numero,
    type_infrastructure,
    nom_demandeur,
    ville,
    region,
    coordonnees_gps,
    operateur_proprietaire,
    statut
FROM dossiers
WHERE statut = 'autorise'
ORDER BY id;

-- ========================================
-- 5. Vérifier tous les statuts existants
-- ========================================
SELECT '=== RÉPARTITION PAR STATUT ===' AS info;

SELECT statut, COUNT(*) as count
FROM dossiers
GROUP BY statut
ORDER BY count DESC;

-- ========================================
-- 6. Test de la fonction getAllInfrastructuresForMap
-- ========================================
SELECT '=== TEST DE LA REQUÊTE DE LA CARTE ===' AS info;

SELECT
    id,
    numero,
    type_infrastructure,
    sous_type,
    nom_demandeur,
    ville,
    region,
    coordonnees_gps,
    statut,
    date_creation,
    operateur_proprietaire
FROM dossiers
WHERE coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
AND statut = 'autorise'
ORDER BY date_creation DESC;

-- ========================================
-- 7. Vérifier le format des coordonnées GPS
-- ========================================
SELECT '=== FORMAT DES COORDONNÉES GPS ===' AS info;

SELECT
    numero,
    coordonnees_gps,
    CASE
        WHEN coordonnees_gps REGEXP '^-?[0-9]+\\.?[0-9]*\\s*,\\s*-?[0-9]+\\.?[0-9]*$' THEN 'Format valide'
        ELSE 'Format INVALIDE'
    END as format_validation,
    LENGTH(coordonnees_gps) as longueur
FROM dossiers
WHERE coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
LIMIT 10;

-- ========================================
-- RÉSUMÉ DU DIAGNOSTIC
-- ========================================
SELECT '=== RÉSUMÉ DU DIAGNOSTIC ===' AS info;

SELECT
    'Total dossiers' as metrique,
    COUNT(*) as valeur
FROM dossiers
UNION ALL
SELECT
    'Dossiers autorisés',
    COUNT(*)
FROM dossiers
WHERE statut = 'autorise'
UNION ALL
SELECT
    'Avec coordonnées GPS',
    COUNT(*)
FROM dossiers
WHERE statut = 'autorise'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
UNION ALL
SELECT
    'Avec operateur_proprietaire',
    COUNT(*)
FROM dossiers
WHERE statut = 'autorise'
AND operateur_proprietaire IS NOT NULL
AND operateur_proprietaire != '';
