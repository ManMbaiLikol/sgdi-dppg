-- Migration : Ajout des champs contrat d'approvisionnement
-- Date : 2025-10-25
-- Description : Ajout du numéro de contrat et société contractante pour les points consommateurs

USE sgdi_mvp;

-- Ajout des champs pour le contrat d'approvisionnement
ALTER TABLE fiches_inspection
    ADD COLUMN numero_contrat_approvisionnement VARCHAR(100) NULL COMMENT 'Numéro du contrat d\'approvisionnement',
    ADD COLUMN societe_contractante VARCHAR(200) NULL COMMENT 'Nom de la société contractante';

-- Message de confirmation
SELECT 'Migration des champs contrat d\'approvisionnement terminée avec succès!' as Message;
