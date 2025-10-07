-- Ajouter les statuts pour le circuit de visa à 3 niveaux
-- Circuit: validation_chef_commission → visa_chef_service → visa_sous_directeur → visa_directeur → decide

ALTER TABLE dossiers
MODIFY COLUMN statut ENUM(
    'brouillon',
    'en_cours',
    'paye',
    'analyse_daj',
    'inspecte',
    'validation_chef_commission',
    'visa_chef_service',
    'visa_sous_directeur',
    'visa_directeur',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu',
    'en_huitaine'
) DEFAULT 'brouillon';

-- Créer table pour suivre les visas
CREATE TABLE IF NOT EXISTS visas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    action ENUM('approuve', 'rejete', 'demande_modification') NOT NULL,
    observations TEXT,
    date_visa TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_dossier (dossier_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
