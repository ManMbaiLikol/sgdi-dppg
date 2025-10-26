-- Migration : Augmenter la taille de superficie_site pour accepter de très grandes valeurs
-- Date : 2025-10-26
-- Raison : DECIMAL(10,2) est trop petit pour certains sites industriels

ALTER TABLE fiches_inspection
MODIFY COLUMN superficie_site DECIMAL(15,2) NULL COMMENT 'La superficie du site (mètre carré)';

-- De même pour besoins_mensuels_litres
ALTER TABLE fiches_inspection
MODIFY COLUMN besoins_mensuels_litres DECIMAL(15,2) NULL COMMENT 'Besoins moyens mensuels en produits pétroliers (en litres)';
