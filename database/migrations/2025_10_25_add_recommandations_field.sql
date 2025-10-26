-- Migration : Ajout du champ recommandations
-- Date : 2025-10-25
-- Description : Ajout d'une section RECOMMANDATIONS dans la fiche d'inspection

USE sgdi_mvp;

-- Ajout du champ recommandations
ALTER TABLE fiches_inspection
    ADD COLUMN recommandations TEXT NULL COMMENT 'Recommandations de l\'inspecteur' AFTER observations_generales;

-- Message de confirmation
SELECT 'Migration du champ recommandations terminée avec succès!' as Message;
