-- Ajout du rôle Chef de Commission au système SGDI
-- Version sécurisée - Exécuter ligne par ligne si nécessaire

USE sgdi_mvp;

-- 1. Ajouter le rôle 'chef_commission' à la table users
ALTER TABLE users
MODIFY COLUMN role ENUM('chef_service', 'cadre_dppg', 'cadre_daj', 'billeteur', 'directeur', 'admin', 'chef_commission') NOT NULL;

-- 2. Ajouter les champs de validation dans la table inspections
ALTER TABLE inspections
ADD COLUMN valide_par_chef_commission TINYINT DEFAULT 0 AFTER conforme;

ALTER TABLE inspections
ADD COLUMN chef_commission_id INT NULL AFTER valide_par_chef_commission;

ALTER TABLE inspections
ADD COLUMN date_validation_chef_commission TIMESTAMP NULL AFTER chef_commission_id;

ALTER TABLE inspections
ADD COLUMN observations_chef_commission TEXT NULL AFTER date_validation_chef_commission;

-- 3. Ajouter la contrainte de clé étrangère
ALTER TABLE inspections
ADD CONSTRAINT fk_inspections_chef_commission FOREIGN KEY (chef_commission_id) REFERENCES users(id);

-- 4. Créer la table notifications si elle n'existe pas
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    dossier_id INT NULL,
    lue TINYINT DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE
);

-- 5. Ajouter des index (ignorer les erreurs si déjà existants)
-- Exécuter ces lignes une par une et ignorer les erreurs "Duplicate key name"
ALTER TABLE inspections ADD INDEX idx_inspections_chef_commission (chef_commission_id);
ALTER TABLE inspections ADD INDEX idx_inspections_validation_chef (valide_par_chef_commission);
ALTER TABLE notifications ADD INDEX idx_user_lue (user_id, lue);
ALTER TABLE notifications ADD INDEX idx_date (date_creation);

-- 6. Mettre à jour les ENUM de statuts
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete') DEFAULT 'cree';

ALTER TABLE historique
MODIFY COLUMN ancien_statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete');

ALTER TABLE historique
MODIFY COLUMN nouveau_statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete');

-- 7. Créer un utilisateur de test Chef de Commission
-- Le mot de passe doit être haché en PHP avec password_hash('chef_com123', PASSWORD_DEFAULT)
-- Pour l'instant, utiliser un mot de passe temporaire

-- Fin du script
SELECT 'Script exécuté avec succès! Le rôle Chef de Commission a été ajouté.' AS message;
