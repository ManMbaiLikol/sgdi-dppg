-- ============================================
-- Import des stations historiques MINEE pour MySQL
-- Version adaptée pour Railway MySQL
-- Généré le : 2025-11-03
-- ============================================

-- Étape 1 : Vérification avant import
-- Copier ces lignes séparément pour voir les résultats avant de continuer

SELECT COUNT(*) as total_avant FROM dossiers;
SELECT COUNT(*) as historiques_avant FROM dossiers WHERE est_historique = 1;

-- Étape 2 : Suppression des stations historiques existantes
-- ATTENTION : Cette requête supprime TOUTES les stations historiques !
-- Décommenter la ligne suivante UNIQUEMENT après vérification de l'étape 1

-- DELETE FROM dossiers WHERE est_historique = 1;

-- Étape 3 : Import des nouvelles stations MINEE
-- Après avoir exécuté le DELETE ci-dessus, copiez TOUTES les lignes INSERT ci-dessous
-- (Ne pas exécuter avant d'avoir fait le DELETE !)

