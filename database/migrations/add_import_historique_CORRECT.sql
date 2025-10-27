-- Migration corrigée pour le module import historique
-- Adapté à la structure réelle de la base SGDI

-- ÉTAPE 1 : Ajouter 'historique_autorise' à l'ENUM statut
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM(
    'brouillon',
    'cree',
    'en_cours',
    'note_transmise',
    'paye',
    'en_huitaine',
    'analyse_daj',
    'inspecte',
    'validation_commission',
    'visa_chef_service',
    'visa_sous_directeur',
    'visa_directeur',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'ferme',
    'suspendu',
    'historique_autorise'
) DEFAULT 'brouillon';

-- ÉTAPE 2 : Créer la table entreprises_beneficiaires
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

-- ÉTAPE 3 : Créer la table logs_import_historique
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

-- ÉTAPE 4 : Créer la vue pour les dossiers historiques
CREATE OR REPLACE VIEW v_dossiers_historiques AS
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
    d.importe_le,
    d.source_import,
    d.statut,
    CONCAT(u.nom, ' ', u.prenom) as importe_par_nom,
    d.date_creation
FROM dossiers d
LEFT JOIN users u ON d.importe_par = u.id
WHERE d.est_historique = 1
ORDER BY d.importe_le DESC, d.numero;

-- FIN
SELECT 'Migration exécutée avec succès !' AS message;
