-- Script de correction pour le dossier SS2025091201 sur Railway
-- À exécuter via HeidiSQL APRÈS avoir vérifié le diagnostic

-- ========================================
-- OPTION A : Le dossier N'EXISTE PAS sur Railway
-- ========================================
-- Décommentez cette section si le diagnostic montre que le dossier n'existe pas

/*
INSERT INTO dossiers (
    numero,
    type_infrastructure,
    sous_type,
    nom_demandeur,
    ville,
    region,
    coordonnees_gps,
    operateur_proprietaire,
    statut,
    user_id,
    date_creation
) VALUES (
    'SS2025091201',
    'station_service',
    'implantation',
    'BOCOM PETROLEUM SA',
    'Ebolowa',
    'Sud',
    '2.915,11.154',
    'BOCOM PETROLEUM SA',
    'autorise',
    1,
    '2025-09-12 11:30:00'
);
*/

-- ========================================
-- OPTION B : Le dossier EXISTE mais données incomplètes
-- ========================================
-- Décommentez cette section si le dossier existe mais manque des données

/*
UPDATE dossiers
SET
    coordonnees_gps = '2.915,11.154',
    operateur_proprietaire = 'BOCOM PETROLEUM SA',
    statut = 'autorise',
    type_infrastructure = 'station_service',
    nom_demandeur = 'BOCOM PETROLEUM SA',
    ville = 'Ebolowa',
    region = 'Sud'
WHERE numero = 'SS2025091201';
*/

-- ========================================
-- OPTION C : Copier depuis une autre table (si backup existe)
-- ========================================
-- Si vous avez une table de backup

/*
INSERT INTO dossiers
SELECT * FROM dossiers_backup
WHERE numero = 'SS2025091201'
ON DUPLICATE KEY UPDATE
    coordonnees_gps = VALUES(coordonnees_gps),
    operateur_proprietaire = VALUES(operateur_proprietaire),
    statut = VALUES(statut);
*/

-- ========================================
-- Vérification APRÈS correction
-- ========================================

-- Vérifier que le dossier est maintenant correct
SELECT '=== VÉRIFICATION APRÈS CORRECTION ===' AS info;

SELECT
    numero,
    type_infrastructure,
    nom_demandeur,
    ville,
    region,
    coordonnees_gps,
    operateur_proprietaire,
    statut,
    CASE
        WHEN statut = 'autorise'
         AND coordonnees_gps IS NOT NULL
         AND coordonnees_gps != ''
         AND coordonnees_gps REGEXP '^-?[0-9]+\\.?[0-9]*\\s*,\\s*-?[0-9]+\\.?[0-9]*$'
        THEN '✓ Le dossier devrait maintenant s\'afficher sur la carte'
        ELSE '✗ Il reste un problème'
    END as resultat
FROM dossiers
WHERE numero = 'SS2025091201';

-- Test de la requête carte
SELECT '=== TEST REQUÊTE CARTE ===' AS info;

SELECT COUNT(*) as sera_affiche
FROM dossiers
WHERE numero = 'SS2025091201'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
AND statut = 'autorise';

SELECT
    CASE
        WHEN COUNT(*) > 0 THEN '✓ SUCCESS : Le dossier sera affiché'
        ELSE '✗ ERREUR : Le dossier ne sera toujours pas affiché'
    END as resultat_final
FROM dossiers
WHERE numero = 'SS2025091201'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
AND statut = 'autorise';
