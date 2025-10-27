-- Migration pour l'import de dossiers historiques
-- SGDI - Système de Gestion des Dossiers d'Implantation
-- Date : Janvier 2025

-- 1. Ajouter les colonnes pour marquer les dossiers historiques
ALTER TABLE dossiers
ADD COLUMN est_historique BOOLEAN DEFAULT FALSE COMMENT 'Indique si le dossier a été importé (historique)',
ADD COLUMN importe_le DATETIME NULL COMMENT 'Date et heure de l''import',
ADD COLUMN importe_par INT NULL COMMENT 'ID de l''utilisateur ayant effectué l''import',
ADD COLUMN source_import VARCHAR(100) NULL COMMENT 'Source ou description de l''import',
ADD KEY idx_est_historique (est_historique),
ADD KEY idx_importe_par (importe_par);

-- 2. Ajouter la contrainte de clé étrangère
ALTER TABLE dossiers
ADD CONSTRAINT fk_dossiers_importe_par
FOREIGN KEY (importe_par) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Créer ou mettre à jour le statut HISTORIQUE_AUTORISE
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
    description = 'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI',
    ordre = 100;

-- 4. Créer une table pour les entreprises bénéficiaires (points consommateurs)
CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL COMMENT 'Nom de l''entreprise bénéficiaire',
    activite VARCHAR(200) NULL COMMENT 'Secteur d''activité de l''entreprise',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    INDEX idx_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Entreprises bénéficiaires pour les points consommateurs';

-- 5. Ajouter une colonne pour le numéro de décision ministérielle
ALTER TABLE dossiers
ADD COLUMN numero_decision_ministerielle VARCHAR(100) NULL COMMENT 'Numéro de la décision ministérielle',
ADD COLUMN date_decision_ministerielle DATE NULL COMMENT 'Date de la décision ministérielle',
ADD KEY idx_numero_decision (numero_decision_ministerielle);

-- 6. Créer une vue pour faciliter l'accès aux dossiers historiques
CREATE OR REPLACE VIEW v_dossiers_historiques AS
SELECT
    d.id,
    d.numero,
    d.nom_demandeur,
    ti.nom as type_infrastructure,
    d.region,
    d.ville,
    d.latitude,
    d.longitude,
    d.numero_decision_ministerielle,
    d.date_decision_ministerielle,
    d.observations,
    d.importe_le,
    d.source_import,
    CONCAT(u.prenom, ' ', u.nom) as importe_par_nom,
    s.libelle as statut,
    eb.nom as entreprise_beneficiaire,
    eb.activite as activite_entreprise,
    d.created_at
FROM dossiers d
LEFT JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
LEFT JOIN statuts_dossier s ON d.statut_id = s.id
LEFT JOIN users u ON d.importe_par = u.id
LEFT JOIN entreprises_beneficiaires eb ON d.id = eb.dossier_id
WHERE d.est_historique = TRUE
ORDER BY d.importe_le DESC, d.numero;

-- 7. Créer une table de log pour les imports
CREATE TABLE IF NOT EXISTS logs_import_historique (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    fichier_nom VARCHAR(255) NOT NULL COMMENT 'Nom du fichier importé',
    source_import VARCHAR(100) NULL COMMENT 'Description de l''import',
    nb_lignes_total INT NOT NULL COMMENT 'Nombre total de lignes dans le fichier',
    nb_success INT NOT NULL DEFAULT 0 COMMENT 'Nombre de dossiers importés avec succès',
    nb_errors INT NOT NULL DEFAULT 0 COMMENT 'Nombre d''erreurs',
    duree_secondes INT NULL COMMENT 'Durée de l''import en secondes',
    details TEXT NULL COMMENT 'Détails des erreurs éventuelles (JSON)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historique des imports de dossiers historiques';

-- 8. Ajuster les permissions pour les rôles concernés
-- Les permissions seront gérées dans le code PHP via la fonction peutImporterHistorique()

-- Fin de la migration
