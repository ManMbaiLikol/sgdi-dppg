-- Migration: Correction de l'ENUM des rôles dans la table users
-- Date: 2025-10-24
-- Description: Ajoute tous les rôles manquants selon les spécifications CLAUDE.md

-- Mettre à jour l'ENUM de la colonne role pour inclure tous les 9 rôles + lecteur public
ALTER TABLE users
MODIFY COLUMN role ENUM(
    'admin',
    'chef_service',
    'sous_directeur',
    'directeur',
    'cabinet',
    'cadre_dppg',
    'cadre_daj',
    'chef_commission',
    'billeteur',
    'lecteur_public'
) NOT NULL;

-- Mettre à jour aussi les colonnes de statut dans la table dossiers si elles existent
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM(
    'brouillon',
    'cree',
    'en_cours',
    'note_transmise',
    'paye',
    'en_huitaine',
    'analyse_daj',
    'controle_completude',
    'inspecte',
    'validation_commission',
    'visa_chef_service',
    'visa_sous_directeur',
    'visa_directeur',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu'
) DEFAULT 'brouillon';

-- Mettre à jour la table historique si elle existe
ALTER TABLE historique
MODIFY COLUMN ancien_statut ENUM(
    'brouillon',
    'cree',
    'en_cours',
    'note_transmise',
    'paye',
    'en_huitaine',
    'analyse_daj',
    'controle_completude',
    'inspecte',
    'validation_commission',
    'visa_chef_service',
    'visa_sous_directeur',
    'visa_directeur',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu'
) NULL,
MODIFY COLUMN nouveau_statut ENUM(
    'brouillon',
    'cree',
    'en_cours',
    'note_transmise',
    'paye',
    'en_huitaine',
    'analyse_daj',
    'controle_completude',
    'inspecte',
    'validation_commission',
    'visa_chef_service',
    'visa_sous_directeur',
    'visa_directeur',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu'
) NULL;

-- Note: La colonne derniere_connexion sera ajoutée manuellement si nécessaire
-- MySQL versions < 8.0.29 ne supportent pas ADD COLUMN IF NOT EXISTS
