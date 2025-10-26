-- Migration : Ajouter le champ "lettre_desistement" aux documents techniques
-- Date : 2025-10-26
-- Description : Ajout du document "Lettre de désistement" pour les stations-services

SET SQL_MODE='ALLOW_INVALID_DATES';

ALTER TABLE fiches_inspection
ADD COLUMN lettre_desistement BOOLEAN DEFAULT FALSE COMMENT 'Lettre de désistement disponible' AFTER plan_masse;
