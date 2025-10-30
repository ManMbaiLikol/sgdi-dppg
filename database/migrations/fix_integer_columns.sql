-- Migration : Corriger les types de colonnes pour accepter des entiers sans décimales
-- Date : 2025-10-26
-- Raison : Les champs nombre_personnels doivent être INT, pas DECIMAL

ALTER TABLE fiches_inspection
MODIFY COLUMN nombre_personnels INT NULL COMMENT 'Nombre de personnels employés';

-- Pour superficie_site et besoins_mensuels_litres, garder DECIMAL mais augmenter précision
-- Pas de changement nécessaire, déjà DECIMAL(15,2)
