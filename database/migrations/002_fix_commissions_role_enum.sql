-- Migration: Correction de l'ENUM chef_commission_role dans la table commissions
-- Date: 2025-10-24
-- Description: Ajoute tous les rôles possibles pour un chef de commission

-- Mettre à jour l'ENUM de chef_commission_role pour inclure tous les rôles de direction possibles
ALTER TABLE commissions
MODIFY COLUMN chef_commission_role ENUM(
    'chef_service',
    'chef_commission',
    'sous_directeur',
    'directeur'
) NOT NULL;
