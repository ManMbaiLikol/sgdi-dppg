-- Migration simplifiée: Table validations_commission
-- Date: 2025-10-16

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
