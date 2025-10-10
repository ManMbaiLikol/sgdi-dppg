-- Script de réassignation des dossiers orphelins
-- Utilisé quand un administrateur est supprimé et ses dossiers deviennent orphelins

-- Étape 1: Identifier les dossiers orphelins (dont le user_id n'existe plus)
SELECT
    d.id,
    d.numero,
    d.nom_demandeur,
    d.user_id as ancien_user_id,
    d.statut,
    d.date_creation
FROM dossiers d
LEFT JOIN users u ON d.user_id = u.id
WHERE u.id IS NULL
ORDER BY d.date_creation DESC;

-- Étape 2: Trouver l'ID du nouvel administrateur
-- (Remplacer 'nouveau_admin@email.com' par l'email du nouvel admin)
SELECT id, username, nom, prenom, email, role
FROM users
WHERE role = 'admin' AND actif = 1
ORDER BY date_creation DESC;

-- Étape 3: Réassigner les dossiers orphelins au nouvel admin
-- IMPORTANT: Remplacer <NEW_ADMIN_ID> par l'ID du nouvel administrateur
-- Exemple: Si le nouvel admin a l'ID 42, remplacer <NEW_ADMIN_ID> par 42

-- ATTENTION: Décommenter et exécuter la ligne suivante après avoir vérifié l'ID
-- UPDATE dossiers SET user_id = <NEW_ADMIN_ID> WHERE user_id NOT IN (SELECT id FROM users);

-- Alternative plus sûre: réassigner un par un en spécifiant les IDs de dossiers
-- UPDATE dossiers SET user_id = <NEW_ADMIN_ID> WHERE id IN (1, 2, 3, ...);

-- Étape 4: Vérifier que tous les dossiers ont maintenant un créateur valide
SELECT COUNT(*) as nombre_dossiers_orphelins
FROM dossiers d
LEFT JOIN users u ON d.user_id = u.id
WHERE u.id IS NULL;

-- Ce nombre devrait être 0 après la réassignation

-- Étape 5 (optionnel): Mettre à jour l'historique pour logger cette réassignation
-- INSERT INTO historique (dossier_id, user_id, action, description, date_action)
-- SELECT
--     d.id,
--     <NEW_ADMIN_ID>,
--     'reassignation',
--     CONCAT('Dossier réassigné à l\'administrateur ID ', <NEW_ADMIN_ID>, ' suite à suppression de l\'ancien créateur'),
--     NOW()
-- FROM dossiers d
-- LEFT JOIN users u ON d.user_id = u.id
-- WHERE u.id IS NULL;
