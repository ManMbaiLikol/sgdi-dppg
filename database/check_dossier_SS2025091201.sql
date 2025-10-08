-- Diagnostic spécifique pour le dossier SS2025091201
-- À exécuter via HeidiSQL sur la base Railway

-- ========================================
-- 1. Vérifier si le dossier existe
-- ========================================
SELECT '=== LE DOSSIER EXISTE-T-IL ? ===' AS info;

SELECT
    CASE
        WHEN COUNT(*) > 0 THEN 'OUI - Le dossier existe'
        ELSE 'NON - Le dossier n\'existe pas sur Railway'
    END as resultat,
    COUNT(*) as count
FROM dossiers
WHERE numero = 'SS2025091201';

-- ========================================
-- 2. Détails complets du dossier
-- ========================================
SELECT '=== DÉTAILS COMPLETS DU DOSSIER ===' AS info;

SELECT
    id,
    numero,
    type_infrastructure,
    sous_type,
    nom_demandeur,
    contact_demandeur,
    ville,
    region,
    coordonnees_gps,
    operateur_proprietaire,
    statut,
    date_creation,
    user_id
FROM dossiers
WHERE numero = 'SS2025091201';

-- ========================================
-- 3. Vérifier chaque condition de la carte
-- ========================================
SELECT '=== VÉRIFICATION DES CONDITIONS ===' AS info;

SELECT
    numero,

    -- Condition 1 : Statut autorisé
    CASE
        WHEN statut = 'autorise' THEN '✓ OUI'
        ELSE CONCAT('✗ NON - Statut actuel: ', statut)
    END as statut_autorise,

    -- Condition 2 : Coordonnées GPS existent
    CASE
        WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN '✓ OUI'
        ELSE '✗ NON - Coordonnées GPS vides'
    END as a_coordonnees,

    -- Condition 3 : Format des coordonnées
    CASE
        WHEN coordonnees_gps REGEXP '^-?[0-9]+\\.?[0-9]*\\s*,\\s*-?[0-9]+\\.?[0-9]*$' THEN '✓ OUI'
        ELSE CONCAT('✗ NON - Format invalide: ', COALESCE(coordonnees_gps, 'NULL'))
    END as format_valide,

    -- Condition 4 : operateur_proprietaire existe
    CASE
        WHEN operateur_proprietaire IS NOT NULL THEN '✓ OUI'
        ELSE '✗ NON - operateur_proprietaire NULL'
    END as a_operateur,

    -- Valeurs réelles
    coordonnees_gps as coordonnees_brutes,
    operateur_proprietaire as operateur

FROM dossiers
WHERE numero = 'SS2025091201';

-- ========================================
-- 4. Test de parsing des coordonnées
-- ========================================
SELECT '=== TEST DE PARSING GPS ===' AS info;

SELECT
    numero,
    coordonnees_gps,
    -- Extraire latitude
    TRIM(SUBSTRING_INDEX(coordonnees_gps, ',', 1)) as latitude_extraite,
    -- Extraire longitude
    TRIM(SUBSTRING_INDEX(coordonnees_gps, ',', -1)) as longitude_extraite,
    -- Vérifier si numérique
    CASE
        WHEN TRIM(SUBSTRING_INDEX(coordonnees_gps, ',', 1)) REGEXP '^-?[0-9]+\\.?[0-9]*$'
         AND TRIM(SUBSTRING_INDEX(coordonnees_gps, ',', -1)) REGEXP '^-?[0-9]+\\.?[0-9]*$'
        THEN '✓ Coordonnées valides'
        ELSE '✗ Coordonnées invalides'
    END as validation
FROM dossiers
WHERE numero = 'SS2025091201';

-- ========================================
-- 5. Simuler la requête de la carte
-- ========================================
SELECT '=== SIMULATION REQUÊTE CARTE ===' AS info;

-- Cette requête devrait retourner le dossier s'il doit s'afficher
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
WHERE numero = 'SS2025091201'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
AND statut = 'autorise';

-- ========================================
-- 6. Comparaison avec dossier local
-- ========================================
SELECT '=== INFORMATIONS POUR COMPARAISON ===' AS info;

SELECT
    'Vérifiez ces informations avec votre base locale' as instruction,
    numero,
    MD5(CONCAT_WS('|',
        COALESCE(numero, ''),
        COALESCE(type_infrastructure, ''),
        COALESCE(nom_demandeur, ''),
        COALESCE(coordonnees_gps, ''),
        COALESCE(statut, '')
    )) as empreinte_donnees
FROM dossiers
WHERE numero = 'SS2025091201';

-- ========================================
-- DIAGNOSTIC FINAL
-- ========================================
SELECT '=== DIAGNOSTIC FINAL ===' AS info;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✗ PROBLÈME : Le dossier n\'existe pas sur Railway'
        WHEN statut != 'autorise' THEN CONCAT('✗ PROBLÈME : Statut = ', statut, ' (devrait être autorise)')
        WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN '✗ PROBLÈME : Pas de coordonnées GPS'
        WHEN operateur_proprietaire IS NULL THEN '⚠ ATTENTION : operateur_proprietaire est NULL (peut causer erreur JS)'
        ELSE '✓ Le dossier devrait s\'afficher sur la carte'
    END as diagnostic
FROM dossiers
WHERE numero = 'SS2025091201';
