-- Création des tables pour décisions ministérielles et registre public
-- Date: 31 octobre 2025
-- Phase: Workflow complet de visa

-- ============================================================
-- TABLE: decisions_ministerielle
-- Description: Enregistre les décisions ministérielles finales
-- ============================================================
CREATE TABLE IF NOT EXISTS decisions_ministerielle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    user_id INT NOT NULL,
    decision ENUM('approuve', 'refuse', 'ajourne') NOT NULL,
    numero_arrete VARCHAR(100) NOT NULL,
    observations TEXT,
    date_decision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),

    INDEX idx_dossier (dossier_id),
    INDEX idx_decision (decision),
    INDEX idx_date_decision (date_decision),
    UNIQUE KEY unique_decision_per_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: registre_public
-- Description: Publication automatique des dossiers approuvés
-- ============================================================
CREATE TABLE IF NOT EXISTS registre_public (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    numero_dossier VARCHAR(50) NOT NULL,
    type_infrastructure VARCHAR(50) NOT NULL,
    sous_type VARCHAR(50),
    nom_demandeur VARCHAR(200) NOT NULL,
    ville VARCHAR(100),
    quartier VARCHAR(100),
    region VARCHAR(100),
    operateur_proprietaire VARCHAR(200),
    entreprise_beneficiaire VARCHAR(200),
    decision ENUM('approuve') NOT NULL DEFAULT 'approuve',
    numero_arrete VARCHAR(100) NOT NULL,
    observations TEXT,
    date_decision DATETIME NOT NULL,
    date_publication DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,

    INDEX idx_dossier (dossier_id),
    INDEX idx_numero (numero_dossier),
    INDEX idx_type (type_infrastructure),
    INDEX idx_ville (ville),
    INDEX idx_region (region),
    INDEX idx_date_decision (date_decision),
    INDEX idx_date_publication (date_publication),
    INDEX idx_numero_arrete (numero_arrete),
    UNIQUE KEY unique_dossier_publication (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Ajout des nouveaux statuts au workflow
-- ============================================================
-- Note: Si la colonne statut est déjà définie comme ENUM,
-- il faudra l'altérer pour ajouter les nouveaux statuts

-- ALTER TABLE dossiers MODIFY COLUMN statut ENUM(
--     'brouillon',
--     'soumis',
--     'en_attente_paiement',
--     'paye',
--     'analyse_daj',
--     'controle_completude',
--     'a_inspecter',
--     'inspecte',
--     'visa_chef_service',
--     'visa_sous_directeur',
--     'visa_directeur',
--     'approuve',
--     'refuse',
--     'ajourne',
--     'rejete',
--     'ferme',
--     'historique_autorise',
--     'autorise'
-- ) NOT NULL DEFAULT 'brouillon';

-- ============================================================
-- Insertion de commentaires pour documentation
-- ============================================================
ALTER TABLE decisions_ministerielle COMMENT = 'Décisions ministérielles finales pour les dossiers d\'implantation';
ALTER TABLE registre_public COMMENT = 'Registre public des infrastructures approuvées - accessible sans authentification';

-- ============================================================
-- COMMENTAIRES DES COLONNES
-- ============================================================

-- Table decisions_ministerielle
ALTER TABLE decisions_ministerielle
    MODIFY COLUMN decision ENUM('approuve', 'refuse', 'ajourne') NOT NULL
    COMMENT 'Decision: approuve=autorisation accordée, refuse=rejet, ajourne=complément requis';

ALTER TABLE decisions_ministerielle
    MODIFY COLUMN numero_arrete VARCHAR(100) NOT NULL
    COMMENT 'Numéro officiel de l\'arrêté ministériel';

ALTER TABLE decisions_ministerielle
    MODIFY COLUMN observations TEXT
    COMMENT 'Observations et motifs de la décision (obligatoire pour refuse/ajourne)';

-- Table registre_public
ALTER TABLE registre_public
    MODIFY COLUMN date_publication DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    COMMENT 'Date de publication automatique au registre public';

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
