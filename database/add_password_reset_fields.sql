-- Ajout des champs pour la gestion de réinitialisation des mots de passe
-- SGDI MVP

USE sgdi_mvp;

-- Ajout des colonnes pour la gestion des mots de passe
ALTER TABLE users
ADD COLUMN force_password_change TINYINT DEFAULT 0 COMMENT 'Force l\'utilisateur à changer son mot de passe à la prochaine connexion',
ADD COLUMN password_reset_date TIMESTAMP NULL COMMENT 'Date de la dernière réinitialisation du mot de passe',
ADD COLUMN derniere_connexion TIMESTAMP NULL COMMENT 'Date de la dernière connexion de l\'utilisateur';

-- Mise à jour du rôle enum pour inclure tous les rôles du système SGDI
ALTER TABLE users
MODIFY COLUMN role ENUM(
    'admin',
    'chef_service',
    'billeteur',
    'chef_commission',
    'cadre_daj',
    'cadre_dppg',
    'sous_directeur',
    'directeur',
    'cabinet',
    'lecteur_public'
) NOT NULL;

-- Table des logs d'activité pour tracer les actions sensibles
CREATE TABLE IF NOT EXISTS logs_activite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,

    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_action (user_id, action),
    INDEX idx_date (date_action)
);

-- Mise à jour des utilisateurs existants pour éviter les erreurs
UPDATE users SET derniere_connexion = date_creation WHERE derniere_connexion IS NULL;

-- Index pour améliorer les performances
ALTER TABLE users ADD INDEX idx_role (role);
ALTER TABLE users ADD INDEX idx_actif (actif);