-- Script de correction pour afficher les points sur la carte Railway
-- Exécuter APRÈS avoir lu les résultats du diagnostic

-- ========================================
-- Option 1 : Créer des dossiers de test avec coordonnées GPS
-- ========================================

-- Décommentez cette section si vous n'avez AUCUN dossier autorisé avec coordonnées

/*
-- Créer un dossier de test à Yaoundé
INSERT INTO dossiers (
    numero, type_infrastructure, sous_type, nom_demandeur,
    ville, region, coordonnees_gps, operateur_proprietaire,
    statut, user_id, date_creation
) VALUES (
    'TEST-RAILWAY-001',
    'station_service',
    'implantation',
    'TotalEnergies Cameroun',
    'Yaoundé',
    'Centre',
    '3.8480,11.5021',
    'TotalEnergies',
    'autorise',
    1,
    NOW()
);

-- Créer un dossier de test à Douala
INSERT INTO dossiers (
    numero, type_infrastructure, sous_type, nom_demandeur,
    ville, region, coordonnees_gps, operateur_proprietaire,
    statut, user_id, date_creation
) VALUES (
    'TEST-RAILWAY-002',
    'depot_gpl',
    'implantation',
    'TRADEX SARL',
    'Douala',
    'Littoral',
    '4.0511,9.7679',
    'TRADEX',
    'autorise',
    1,
    NOW()
);
*/

-- ========================================
-- Option 2 : Mettre à jour des dossiers existants
-- ========================================

-- Ajouter des coordonnées GPS aux dossiers qui n'en ont pas
-- Remplacez les ID par ceux de vos vrais dossiers

/*
-- Exemple : Ajouter coordonnées à un dossier existant
UPDATE dossiers
SET
    coordonnees_gps = '3.8480,11.5021',  -- Yaoundé
    operateur_proprietaire = COALESCE(operateur_proprietaire, nom_demandeur)
WHERE id = 1  -- Remplacer par l'ID réel
AND statut = 'autorise';
*/

-- ========================================
-- Option 3 : Autoriser des dossiers existants
-- ========================================

-- Si vous avez des dossiers avec coordonnées mais pas autorisés
-- Décommentez et adaptez :

/*
UPDATE dossiers
SET statut = 'autorise'
WHERE coordonnees_gps IS NOT NULL
AND coordonnees_gps != ''
AND statut = 'decide'  -- Ou autre statut proche
LIMIT 3;  -- Limiter pour tester
*/

-- ========================================
-- Option 4 : Copier les données locales vers Railway
-- ========================================

-- Si vous voulez copier vos données locales vers Railway :
-- 1. Exportez depuis votre base locale (WAMP) via HeidiSQL
-- 2. Sélectionnez uniquement les dossiers autorisés avec GPS
-- 3. Exportez en SQL
-- 4. Importez ici sur Railway

-- ========================================
-- Vérification après correction
-- ========================================

-- Exécutez cette requête pour vérifier :
SELECT
    'Dossiers prêts pour la carte' as info,
    COUNT(*) as count
FROM dossiers
WHERE statut = 'autorise'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != '';

-- Liste des dossiers affichables
SELECT
    numero,
    type_infrastructure,
    nom_demandeur,
    ville,
    coordonnees_gps,
    operateur_proprietaire
FROM dossiers
WHERE statut = 'autorise'
AND coordonnees_gps IS NOT NULL
AND coordonnees_gps != '';
