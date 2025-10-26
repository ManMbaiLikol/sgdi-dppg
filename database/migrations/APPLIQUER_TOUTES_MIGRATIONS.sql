-- Script pour appliquer toutes les migrations des fiches d'inspection
-- Date : 2025-10-25
-- Description : Applique tous les nouveaux champs en une seule fois

USE sgdi_mvp;

-- Migration 1 : Champs spécifiques aux points consommateurs
ALTER TABLE fiches_inspection
    ADD COLUMN IF NOT EXISTS besoins_mensuels_litres DECIMAL(12,2) NULL COMMENT 'Besoins moyens mensuels en produits pétroliers (en litres)',
    ADD COLUMN IF NOT EXISTS parc_engin TEXT NULL COMMENT 'Le parc d\'engin de la société',
    ADD COLUMN IF NOT EXISTS systeme_recuperation_huiles TEXT NULL COMMENT 'Système de récupération des huiles usées',
    ADD COLUMN IF NOT EXISTS nombre_personnels INT NULL COMMENT 'Le nombre de personnels employés',
    ADD COLUMN IF NOT EXISTS superficie_site DECIMAL(10,2) NULL COMMENT 'La superficie du site (mètre carré)',
    ADD COLUMN IF NOT EXISTS batiments_site TEXT NULL COMMENT 'Bâtiments du site',
    ADD COLUMN IF NOT EXISTS infra_eau TINYINT DEFAULT 0 COMMENT 'Présence d\'infrastructure Eau',
    ADD COLUMN IF NOT EXISTS infra_electricite TINYINT DEFAULT 0 COMMENT 'Présence d\'infrastructure Électricité',
    ADD COLUMN IF NOT EXISTS reseau_camtel TINYINT DEFAULT 0 COMMENT 'Présence réseau CAMTEL',
    ADD COLUMN IF NOT EXISTS reseau_mtn TINYINT DEFAULT 0 COMMENT 'Présence réseau MTN',
    ADD COLUMN IF NOT EXISTS reseau_orange TINYINT DEFAULT 0 COMMENT 'Présence réseau ORANGE',
    ADD COLUMN IF NOT EXISTS reseau_nexttel TINYINT DEFAULT 0 COMMENT 'Présence réseau NEXTTEL';

-- Migration 2 : Champs contrat d'approvisionnement
ALTER TABLE fiches_inspection
    ADD COLUMN IF NOT EXISTS numero_contrat_approvisionnement VARCHAR(100) NULL COMMENT 'Numéro du contrat d\'approvisionnement',
    ADD COLUMN IF NOT EXISTS societe_contractante VARCHAR(200) NULL COMMENT 'Nom de la société contractante';

-- Migration 3 : Champ recommandations
ALTER TABLE fiches_inspection
    ADD COLUMN IF NOT EXISTS recommandations TEXT NULL COMMENT 'Recommandations de l\'inspecteur';

-- Vérification : compter le nombre de nouveaux champs
SELECT
    COUNT(*) as nb_champs_ajoutes,
    CASE
        WHEN COUNT(*) >= 17 THEN '✅ Toutes les migrations appliquées avec succès !'
        ELSE CONCAT('⚠️ Seulement ', COUNT(*), ' champs sur 17 ont été ajoutés')
    END as statut
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME IN (
      'besoins_mensuels_litres', 'parc_engin', 'systeme_recuperation_huiles',
      'nombre_personnels', 'superficie_site', 'batiments_site',
      'infra_eau', 'infra_electricite',
      'reseau_camtel', 'reseau_mtn', 'reseau_orange', 'reseau_nexttel',
      'numero_contrat_approvisionnement', 'societe_contractante',
      'recommandations'
  );

-- Afficher la liste complète des nouveaux champs
SELECT
    COLUMN_NAME as 'Champ',
    DATA_TYPE as 'Type',
    COLUMN_COMMENT as 'Description'
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sgdi_mvp'
  AND TABLE_NAME = 'fiches_inspection'
  AND COLUMN_NAME IN (
      'besoins_mensuels_litres', 'parc_engin', 'systeme_recuperation_huiles',
      'nombre_personnels', 'superficie_site', 'batiments_site',
      'infra_eau', 'infra_electricite',
      'reseau_camtel', 'reseau_mtn', 'reseau_orange', 'reseau_nexttel',
      'numero_contrat_approvisionnement', 'societe_contractante',
      'recommandations'
  )
ORDER BY COLUMN_NAME;

-- Message de confirmation
SELECT '✅ Toutes les migrations ont été appliquées avec succès !' as Message;
