-- Ajouter les rôles manquants à la table users
ALTER TABLE users
MODIFY COLUMN role ENUM(
    'admin',
    'chef_service',
    'sous_directeur',
    'directeur',
    'ministre',
    'cadre_dppg',
    'cadre_daj',
    'chef_commission',
    'billeteur',
    'lecteur'
) NOT NULL;
