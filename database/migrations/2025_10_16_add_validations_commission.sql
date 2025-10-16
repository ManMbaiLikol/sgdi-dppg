-- Migration: Ajout table validations_commission et colonnes validation
-- Date: 2025-10-16
-- Description: Permet au chef de commission de valider/rejeter les inspections

-- Table des validations par le chef de commission
CREATE TABLE IF NOT EXISTS validations_commission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fiche_id INT NOT NULL,
    commission_id INT NOT NULL,
    chef_commission_id INT NOT NULL,
    decision ENUM('approuve', 'rejete') NOT NULL,
    commentaires TEXT,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE,
    FOREIGN KEY (chef_commission_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_fiche (fiche_id),
    INDEX idx_commission (commission_id),
    INDEX idx_chef (chef_commission_id),
    INDEX idx_decision (decision),
    INDEX idx_date (date_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Validations des inspections par les chefs de commission';

-- Ajouter colonnes validation à fiches_inspection
ALTER TABLE fiches_inspection
ADD COLUMN IF NOT EXISTS date_validation DATETIME NULL AFTER statut
COMMENT 'Date de validation par le cadre DPPG';

ALTER TABLE fiches_inspection
ADD COLUMN IF NOT EXISTS valideur_id INT NULL AFTER date_validation
COMMENT 'ID du cadre DPPG qui a validé la fiche';

-- Ajouter foreign key si pas déjà présente
SET @exist := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fiches_inspection'
    AND CONSTRAINT_NAME = 'fk_fiches_inspection_valideur'
);

SET @sqlstmt := IF(
    @exist = 0,
    'ALTER TABLE fiches_inspection ADD CONSTRAINT fk_fiches_inspection_valideur FOREIGN KEY (valideur_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter commission_id à fiches_inspection si pas présent
ALTER TABLE fiches_inspection
ADD COLUMN IF NOT EXISTS commission_id INT NULL AFTER dossier_id
COMMENT 'ID de la commission (copie depuis dossiers pour accès direct)';

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_fiche_statut ON fiches_inspection(statut);
CREATE INDEX IF NOT EXISTS idx_fiche_commission ON fiches_inspection(commission_id);
CREATE INDEX IF NOT EXISTS idx_fiche_valideur ON fiches_inspection(valideur_id);
