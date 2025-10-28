-- ============================================================================
-- Migration CORRIGÉE : Module d'import de dossiers historiques
-- ============================================================================
-- Date : 2025-10-28
-- Compatible avec : Railway + Structure actuelle de la base
-- Résout : Problèmes de statut, structure de table, vues SQL
-- ============================================================================

-- ÉTAPE 1 : Vérifier et ajouter 'historique_autorise' à l'ENUM statut de dossiers
-- ----------------------------------------------------------------------------
SET @current_enum = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'statut'
);

-- Seulement si 'historique_autorise' n'existe pas encore
SET @has_historique = IF(@current_enum LIKE '%historique_autorise%', 1, 0);

SET @alter_statut = IF(@has_historique = 0,
    "ALTER TABLE dossiers MODIFY COLUMN statut ENUM(
        'brouillon','cree','en_cours','note_transmise','paye','en_huitaine',
        'analyse_daj','inspecte','validation_commission','visa_chef_service',
        'visa_sous_directeur','visa_directeur','valide','decide','autorise',
        'rejete','ferme','suspendu','historique_autorise'
    ) DEFAULT 'brouillon'",
    "SELECT 'Statut historique_autorise déjà présent' AS message"
);

PREPARE stmt FROM @alter_statut;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 2 : Ajouter les colonnes pour les dossiers historiques
-- ----------------------------------------------------------------------------
-- Vérifier si les colonnes existent déjà pour éviter les erreurs
SET @col_historique = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'est_historique'
);

-- Ajouter est_historique
SET @add_historique = IF(@col_historique = 0,
    "ALTER TABLE dossiers ADD COLUMN est_historique BOOLEAN DEFAULT FALSE COMMENT 'Dossier importé (historique)'",
    "SELECT 'Colonne est_historique déjà présente' AS message"
);
PREPARE stmt FROM @add_historique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter importe_le
SET @col_importe_le = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'importe_le');
SET @add_importe_le = IF(@col_importe_le = 0,
    "ALTER TABLE dossiers ADD COLUMN importe_le DATETIME NULL COMMENT 'Date et heure de l''import'",
    "SELECT 'Colonne importe_le déjà présente' AS message"
);
PREPARE stmt FROM @add_importe_le;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter importe_par
SET @col_importe_par = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'importe_par');
SET @add_importe_par = IF(@col_importe_par = 0,
    "ALTER TABLE dossiers ADD COLUMN importe_par INT NULL COMMENT 'Utilisateur ayant effectué l''import'",
    "SELECT 'Colonne importe_par déjà présente' AS message"
);
PREPARE stmt FROM @add_importe_par;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter source_import
SET @col_source = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'source_import');
SET @add_source = IF(@col_source = 0,
    "ALTER TABLE dossiers ADD COLUMN source_import VARCHAR(100) NULL COMMENT 'Description de l''import'",
    "SELECT 'Colonne source_import déjà présente' AS message"
);
PREPARE stmt FROM @add_source;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter numero_decision_ministerielle
SET @col_decision = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'numero_decision_ministerielle');
SET @add_decision = IF(@col_decision = 0,
    "ALTER TABLE dossiers ADD COLUMN numero_decision_ministerielle VARCHAR(100) NULL COMMENT 'Numéro de décision ministérielle'",
    "SELECT 'Colonne numero_decision_ministerielle déjà présente' AS message"
);
PREPARE stmt FROM @add_decision;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter date_decision_ministerielle
SET @col_date_decision = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'date_decision_ministerielle');
SET @add_date_decision = IF(@col_date_decision = 0,
    "ALTER TABLE dossiers ADD COLUMN date_decision_ministerielle DATE NULL COMMENT 'Date de décision ministérielle'",
    "SELECT 'Colonne date_decision_ministerielle déjà présente' AS message"
);
PREPARE stmt FROM @add_date_decision;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter lieu_dit si manquant
SET @col_lieu_dit = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'lieu_dit');
SET @add_lieu_dit = IF(@col_lieu_dit = 0,
    "ALTER TABLE dossiers ADD COLUMN lieu_dit VARCHAR(200) NULL COMMENT 'Lieu-dit/Observations'",
    "SELECT 'Colonne lieu_dit déjà présente' AS message"
);
PREPARE stmt FROM @add_lieu_dit;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 3 : Ajouter les index (ignorer si existent déjà)
-- ----------------------------------------------------------------------------
SET @idx_historique = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND INDEX_NAME = 'idx_est_historique');
SET @add_idx_historique = IF(@idx_historique = 0,
    "ALTER TABLE dossiers ADD INDEX idx_est_historique (est_historique)",
    "SELECT 'Index idx_est_historique déjà présent' AS message"
);
PREPARE stmt FROM @add_idx_historique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_importe = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND INDEX_NAME = 'idx_importe_par');
SET @add_idx_importe = IF(@idx_importe = 0,
    "ALTER TABLE dossiers ADD INDEX idx_importe_par (importe_par)",
    "SELECT 'Index idx_importe_par déjà présent' AS message"
);
PREPARE stmt FROM @add_idx_importe;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_decision = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND INDEX_NAME = 'idx_numero_decision');
SET @add_idx_decision = IF(@idx_decision = 0,
    "ALTER TABLE dossiers ADD INDEX idx_numero_decision (numero_decision_ministerielle)",
    "SELECT 'Index idx_numero_decision déjà présent' AS message"
);
PREPARE stmt FROM @add_idx_decision;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 4 : Créer la table entreprises_beneficiaires
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL COMMENT 'Nom de l''entreprise bénéficiaire',
    activite VARCHAR(200) NULL COMMENT 'Secteur d''activité',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Entreprises bénéficiaires pour les points consommateurs';

-- ÉTAPE 5 : Créer la table logs_import_historique
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs_import_historique (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    fichier_nom VARCHAR(255) NOT NULL COMMENT 'Nom du fichier importé',
    source_import VARCHAR(100) NULL COMMENT 'Description de l''import',
    nb_lignes_total INT NOT NULL COMMENT 'Nombre total de lignes',
    nb_success INT NOT NULL DEFAULT 0 COMMENT 'Nombre de succès',
    nb_errors INT NOT NULL DEFAULT 0 COMMENT 'Nombre d''erreurs',
    duree_secondes INT NULL COMMENT 'Durée en secondes',
    details TEXT NULL COMMENT 'Détails des erreurs (JSON)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des imports de dossiers historiques';

-- ÉTAPE 6 : Créer une vue simplifiée pour les dossiers historiques
-- ----------------------------------------------------------------------------
-- Supprimer l'ancienne vue si elle existe
DROP VIEW IF EXISTS v_dossiers_historiques;

-- Créer la nouvelle vue compatible avec la structure actuelle
CREATE VIEW v_dossiers_historiques AS
SELECT
    d.id,
    d.numero,
    d.nom_demandeur,
    d.type_infrastructure,
    d.sous_type,
    d.region,
    d.ville,
    d.coordonnees_gps,
    d.numero_decision_ministerielle,
    d.date_decision_ministerielle,
    d.lieu_dit as observations,
    d.importe_le,
    d.source_import,
    CONCAT(u.prenom, ' ', u.nom) as importe_par_nom,
    d.statut,
    d.entreprise_beneficiaire,
    eb.nom as entreprise_beneficiaire_detail,
    eb.activite as activite_entreprise,
    d.date_creation
FROM dossiers d
LEFT JOIN users u ON d.importe_par = u.id
LEFT JOIN entreprises_beneficiaires eb ON d.id = eb.dossier_id
WHERE d.est_historique = TRUE
ORDER BY d.importe_le DESC, d.numero;

-- ============================================================================
-- VÉRIFICATIONS FINALES
-- ============================================================================

-- Vérifier que toutes les colonnes sont présentes
SELECT
    'Colonnes ajoutées avec succès' AS message,
    COUNT(*) as nb_colonnes
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dossiers'
AND COLUMN_NAME IN (
    'est_historique', 'importe_le', 'importe_par', 'source_import',
    'numero_decision_ministerielle', 'date_decision_ministerielle'
);

-- Vérifier que les tables sont créées
SELECT
    'Tables créées avec succès' AS message,
    COUNT(*) as nb_tables
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('entreprises_beneficiaires', 'logs_import_historique');

-- Vérifier que la vue est créée
SELECT
    'Vue créée avec succès' AS message,
    COUNT(*) as nb_vues
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'v_dossiers_historiques';

-- Message final
SELECT '✅ Migration terminée avec succès !' AS message;
