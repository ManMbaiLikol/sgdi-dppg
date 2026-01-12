-- Migration: Ajout de date_modification à la table commissions
-- Date: 2026-01-12
-- Description: Permet de tracker les modifications de commission

ALTER TABLE commissions
ADD COLUMN date_modification DATETIME NULL DEFAULT NULL AFTER date_constitution;
