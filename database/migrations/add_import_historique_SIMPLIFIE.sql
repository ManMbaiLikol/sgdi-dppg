-- Migration simplifiée pour Railway - Module import historique
-- À exécuter via l'interface web Railway

-- ÉTAPE 1 : Ajouter les colonnes à la table dossiers
ALTER TABLE dossiers
ADD COLUMN IF NOT EXISTS est_historique BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS importe_le DATETIME NULL,
ADD COLUMN IF NOT EXISTS importe_par INT NULL,
ADD COLUMN IF NOT EXISTS source_import VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS numero_decision_ministerielle VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS date_decision_ministerielle DATE NULL;

-- ÉTAPE 2 : Ajouter les index
ALTER TABLE dossiers
ADD INDEX IF NOT EXISTS idx_est_historique (est_historique),
ADD INDEX IF NOT EXISTS idx_importe_par (importe_par),
ADD INDEX IF NOT EXISTS idx_numero_decision (numero_decision_ministerielle);

-- ÉTAPE 3 : Ajouter la clé étrangère (ignorer si existe déjà)
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'dossiers'
               AND CONSTRAINT_NAME = 'fk_dossiers_importe_par');

SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE dossiers ADD CONSTRAINT fk_dossiers_importe_par FOREIGN KEY (importe_par) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Contrainte déjà existante" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 4 : Créer le statut HISTORIQUE_AUTORISE
INSERT INTO statuts_dossier (code, libelle, description, ordre, created_at)
VALUES (
    'HISTORIQUE_AUTORISE',
    'Dossier Historique Autorisé',
    'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI',
    100,
    NOW()
)
ON DUPLICATE KEY UPDATE
    libelle = 'Dossier Historique Autorisé',
    description = 'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI';

-- ÉTAPE 5 : Créer la table entreprises_beneficiaires
CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL,
    activite VARCHAR(200) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    INDEX idx_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ÉTAPE 6 : Créer la table de logs
CREATE TABLE IF NOT EXISTS logs_import_historique (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    fichier_nom VARCHAR(255) NOT NULL,
    source_import VARCHAR(100) NULL,
    nb_lignes_total INT NOT NULL,
    nb_success INT NOT NULL DEFAULT 0,
    nb_errors INT NOT NULL DEFAULT 0,
    duree_secondes INT NULL,
    details TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FIN
SELECT 'Migration exécutée avec succès !' AS message;
