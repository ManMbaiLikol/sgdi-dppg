-- Table pour stocker les statistiques quotidiennes
CREATE TABLE IF NOT EXISTS statistiques_quotidiennes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE UNIQUE NOT NULL,
    dossiers_crees INT DEFAULT 0,
    paiements INT DEFAULT 0,
    montant_paiements DECIMAL(15,2) DEFAULT 0,
    decisions INT DEFAULT 0,
    duree_moyenne_traitement DECIMAL(5,1) DEFAULT 0,
    taux_approbation DECIMAL(5,2) DEFAULT 0,
    data_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajouter colonne archived à notifications si elle n'existe pas
ALTER TABLE notifications
ADD COLUMN IF NOT EXISTS archived TINYINT DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_archived (archived);
