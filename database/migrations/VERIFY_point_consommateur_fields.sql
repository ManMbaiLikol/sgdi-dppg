-- Script de vérification - Champs Point Consommateur
-- Date : 2025-10-25
-- Utilisation : Exécuter ce script pour vérifier que la migration a été appliquée correctement

USE sgdi_mvp;

-- Vérifier l'existence des nouveaux champs
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME IN (
      'besoins_mensuels_litres',
      'parc_engin',
      'systeme_recuperation_huiles',
      'nombre_personnels',
      'superficie_site',
      'batiments_site',
      'infra_eau',
      'infra_electricite',
      'reseau_camtel',
      'reseau_mtn',
      'reseau_orange',
      'reseau_nexttel'
  )
ORDER BY COLUMN_NAME;

-- Résultat attendu : 12 lignes
-- Si vous obtenez moins de 12 lignes, la migration n'a pas été complètement appliquée

-- Compter le nombre de nouveaux champs
SELECT
    COUNT(*) as nb_nouveaux_champs,
    CASE
        WHEN COUNT(*) = 12 THEN '✅ Migration complète !'
        WHEN COUNT(*) = 0 THEN '❌ Migration non appliquée'
        ELSE CONCAT('⚠️ Migration partielle (', COUNT(*), '/12 champs)')
    END as statut
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME IN (
      'besoins_mensuels_litres',
      'parc_engin',
      'systeme_recuperation_huiles',
      'nombre_personnels',
      'superficie_site',
      'batiments_site',
      'infra_eau',
      'infra_electricite',
      'reseau_camtel',
      'reseau_mtn',
      'reseau_orange',
      'reseau_nexttel'
  );
