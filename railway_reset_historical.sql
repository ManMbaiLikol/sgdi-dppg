-- ============================================
-- Script SQL pour Railway : Suppression des stations historiques
-- ============================================
-- ATTENTION : Ce script supprime TOUTES les stations historiques
-- À exécuter UNIQUEMENT sur Railway via l'interface PostgreSQL
-- ============================================

-- Étape 1 : Compter les stations historiques avant suppression
SELECT
    COUNT(*) as total_historiques,
    COUNT(CASE WHEN coordonnees_gps IS NOT NULL THEN 1 END) as avec_gps,
    COUNT(CASE WHEN coordonnees_gps IS NULL THEN 1 END) as sans_gps
FROM dossiers
WHERE est_historique = 1;

-- Étape 2 : Afficher quelques exemples pour vérification
SELECT
    id,
    numero,
    nom_demandeur,
    region,
    ville,
    est_historique,
    date_creation
FROM dossiers
WHERE est_historique = 1
LIMIT 10;

-- Étape 3 : SUPPRESSION (décommenter après vérification)
-- DELETE FROM dossiers WHERE est_historique = 1;

-- Étape 4 : Vérifier que la suppression a réussi
-- SELECT COUNT(*) as restant FROM dossiers WHERE est_historique = 1;

-- Étape 5 : Vérifier les autres dossiers (non historiques)
-- SELECT COUNT(*) as dossiers_actifs FROM dossiers WHERE est_historique = 0 OR est_historique IS NULL;
