-- Ajouter les tables manquantes pour les tests

-- Table des rôles
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    description TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de liaison users-roles
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table huitaines (renommer huitaine en huitaines)
CREATE TABLE IF NOT EXISTS huitaines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,
    date_debut_huitaine DATE NOT NULL,
    date_fin_huitaine DATE NOT NULL,
    motif TEXT NOT NULL,
    statut ENUM('en_cours', 'regularise', 'expiree', 'annulee') DEFAULT 'en_cours',
    date_regularisation DATETIME NULL,
    observations TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    INDEX idx_statut (statut),
    INDEX idx_date_fin (date_fin_huitaine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des rôles
INSERT IGNORE INTO roles (code, libelle, description) VALUES
('admin', 'Administrateur Système', 'Gestion complète du système'),
('chef_service', 'Chef de Service SDTD', 'Création dossiers, constitution commissions, visa niveau 1'),
('cadre_dppg', 'Cadre DPPG (Inspecteur)', 'Inspections et rapports techniques'),
('cadre_daj', 'Cadre DAJ', 'Analyse juridique et conformité réglementaire'),
('chef_commission', 'Chef de Commission', 'Coordination visites et validation rapports'),
('billeteur', 'Billeteur DPPG', 'Enregistrement paiements et génération reçus'),
('sous_directeur', 'Sous-Directeur SDTD', 'Visa niveau 2'),
('directeur', 'Directeur DPPG', 'Visa niveau 3 et transmission ministre'),
('ministre', 'Cabinet/Ministre', 'Décision finale'),
('lecteur', 'Lecteur Public', 'Consultation registre public uniquement');

-- Mise à jour de la table commissions pour correspondre au schéma attendu
ALTER TABLE commissions
ADD COLUMN IF NOT EXISTS cadre_dppg_id INT AFTER dossier_id,
ADD COLUMN IF NOT EXISTS cadre_daj_id INT AFTER cadre_dppg_id,
ADD COLUMN IF NOT EXISTS chef_commission_id INT AFTER cadre_daj_id;

-- Ajouter le champ 'role' dans users s'il n'existe pas (pour compatibilité)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'lecteur' AFTER password;
