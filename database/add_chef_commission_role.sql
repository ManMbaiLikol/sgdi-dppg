-- Ajout du rôle Chef de Commission au système SGDI
-- Ce script ajoute le nouveau rôle et met à jour les structures nécessaires

USE sgdi_mvp;

-- 1. Ajouter le rôle 'chef_commission' à la table users
ALTER TABLE users
MODIFY COLUMN role ENUM('chef_service', 'cadre_dppg', 'cadre_daj', 'billeteur', 'directeur', 'admin', 'chef_commission') NOT NULL;

-- 2. Ajouter un champ pour la validation du chef de commission dans la table inspections
ALTER TABLE inspections
ADD COLUMN valide_par_chef_commission TINYINT DEFAULT 0 AFTER conforme,
ADD COLUMN chef_commission_id INT NULL AFTER valide_par_chef_commission,
ADD COLUMN date_validation_chef_commission TIMESTAMP NULL AFTER chef_commission_id,
ADD COLUMN observations_chef_commission TEXT NULL AFTER date_validation_chef_commission,
ADD CONSTRAINT fk_inspections_chef_commission FOREIGN KEY (chef_commission_id) REFERENCES users(id);

-- 3. Créer une table pour les notifications (si elle n'existe pas déjà)
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
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    INDEX idx_user_lue (user_id, lue),
    INDEX idx_date (date_creation)
);

-- 4. Ajouter des index pour optimiser les requêtes
-- Vérifier si l'index existe avant de le créer
ALTER TABLE inspections ADD INDEX idx_inspections_chef_commission (chef_commission_id);
ALTER TABLE inspections ADD INDEX idx_inspections_validation_chef (valide_par_chef_commission);

-- 5. Mettre à jour le statut des dossiers pour inclure une étape de validation par le chef de commission
-- Le workflow devient: ... -> inspecte -> validation_chef_commission -> valide -> decide
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete') DEFAULT 'cree';

-- Mettre à jour la table historique aussi
ALTER TABLE historique
MODIFY COLUMN ancien_statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete'),
MODIFY COLUMN nouveau_statut ENUM('cree', 'en_cours', 'paye', 'analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide', 'autorise', 'rejete');

-- Note: La table decisions n'a pas de colonne statut dans le schéma actuel
-- Cette ligne est commentée pour éviter les erreurs

-- 6. Ajouter un utilisateur de démonstration Chef de Commission (optionnel)
-- Mot de passe: chef_com123 (hashé avec password_hash)
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
VALUES ('chef_com', 'chef.commission@minee.cm', '$2y$10$YourHashedPasswordHere', 'chef_commission', 'MBARGA', 'Paul', '+237670000005', 1)
ON DUPLICATE KEY UPDATE username = username;

-- Note: Le mot de passe doit être haché côté PHP. Ceci est un exemple.

COMMIT;
