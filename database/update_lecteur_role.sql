-- Script pour mettre à jour les utilisateurs ayant le rôle 'lecteur' (qui n'existe plus)
-- À exécuter sur Railway via HeidiSQL

-- 1. Vérifier les utilisateurs avec le rôle 'lecteur'
SELECT id, username, nom, prenom, role FROM users WHERE role = 'lecteur';

-- 2. Mettre à jour le rôle 'lecteur' en 'admin' (ou autre rôle approprié)
-- ATTENTION: Modifier le rôle selon vos besoins avant d'exécuter !
-- Options: 'admin', 'chef_service', 'billeteur', 'chef_commission', 'cadre_daj', 'cadre_dppg', 'sous_directeur', 'directeur', 'ministre'

-- Exemple: Mettre tous les lecteurs en admin
UPDATE users SET role = 'admin' WHERE role = 'lecteur';

-- OU mettre un utilisateur spécifique:
-- UPDATE users SET role = 'admin' WHERE username = 'nom_utilisateur_specifique';

-- 3. Vérification après mise à jour
SELECT id, username, nom, prenom, role FROM users WHERE role = 'lecteur';
-- (devrait retourner 0 résultats)
