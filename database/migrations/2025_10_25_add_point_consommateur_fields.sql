-- Migration : Ajout des champs spécifiques pour les points consommateurs
-- Date : 2025-10-25
-- Description : Ajout des champs spécifiques aux fiches d'inspection des points consommateurs

USE sgdi_mvp;

-- Ajout des champs spécifiques aux points consommateurs dans la table fiches_inspection
ALTER TABLE fiches_inspection
    -- Informations techniques spécifiques aux points consommateurs
    ADD COLUMN besoins_mensuels_litres DECIMAL(12,2) NULL COMMENT 'Besoins moyens mensuels en produits pétroliers (en litres)',
    ADD COLUMN parc_engin TEXT NULL COMMENT 'Le parc d\'engin de la société',
    ADD COLUMN systeme_recuperation_huiles TEXT NULL COMMENT 'Système de récupération des huiles usées',
    ADD COLUMN nombre_personnels INT NULL COMMENT 'Le nombre de personnels employés',
    ADD COLUMN superficie_site DECIMAL(10,2) NULL COMMENT 'La superficie du site (mètre carré)',
    ADD COLUMN batiments_site TEXT NULL COMMENT 'Bâtiments du site',

    -- Infrastructures d'approvisionnement (cases à cocher)
    ADD COLUMN infra_eau TINYINT DEFAULT 0 COMMENT 'Présence d\'infrastructure Eau',
    ADD COLUMN infra_electricite TINYINT DEFAULT 0 COMMENT 'Présence d\'infrastructure Électricité',
    ADD COLUMN reseau_camtel TINYINT DEFAULT 0 COMMENT 'Présence réseau CAMTEL',
    ADD COLUMN reseau_mtn TINYINT DEFAULT 0 COMMENT 'Présence réseau MTN',
    ADD COLUMN reseau_orange TINYINT DEFAULT 0 COMMENT 'Présence réseau ORANGE',
    ADD COLUMN reseau_nexttel TINYINT DEFAULT 0 COMMENT 'Présence réseau NEXTTEL';

-- Message de confirmation
SELECT 'Migration des champs Point Consommateur terminée avec succès!' as Message;
