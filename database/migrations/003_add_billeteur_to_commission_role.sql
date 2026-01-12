-- Migration: Ajout du rôle 'billeteur' pour chef de commission
-- Date: 2026-01-12
-- Description: Permet à BANA ESSAMA Joseph (billeteur) d'être désigné comme chef de commission

-- Mettre à jour l'ENUM de chef_commission_role pour inclure le rôle billeteur
ALTER TABLE commissions
MODIFY COLUMN chef_commission_role ENUM(
    'chef_service',
    'chef_commission',
    'sous_directeur',
    'directeur',
    'billeteur'
) NOT NULL;
