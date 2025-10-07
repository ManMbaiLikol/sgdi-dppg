-- Table pour sauvegarder les brouillons de dossiers
-- Permet la sauvegarde automatique du wizard

USE sgdi_mvp;

CREATE TABLE IF NOT EXISTS dossiers_brouillons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    data TEXT NOT NULL COMMENT 'Données du formulaire en JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Brouillons de dossiers pour sauvegarde automatique du wizard';

-- Note: Pas de clé étrangère car la table 'users' peut avoir un nom différent
-- La contrainte d'intégrité sera gérée au niveau application
