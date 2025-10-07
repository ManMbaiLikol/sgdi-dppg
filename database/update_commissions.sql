-- Mise à jour de la structure des commissions - SGDI MVP
-- Nouvelle composition : Chef commission (chef_service/directeur) + Cadre DPPG + Cadre DAJ

USE sgdi_mvp;

-- Sauvegarder les données existantes
CREATE TEMPORARY TABLE temp_commissions AS
SELECT * FROM commissions;

-- Supprimer l'ancienne table
DROP TABLE commissions;

-- Créer la nouvelle structure
CREATE TABLE commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,

    -- Chef de commission (peut être chef_service ou directeur/sous-directeur)
    chef_commission_id INT NOT NULL,
    chef_commission_role ENUM('chef_service', 'directeur') NOT NULL,

    -- Cadre DPPG (obligatoire)
    cadre_dppg_id INT NOT NULL,

    -- Cadre DAJ (obligatoire)
    cadre_daj_id INT NOT NULL,

    -- Métadonnées
    date_constitution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('constituee', 'en_mission', 'rapport_fait') DEFAULT 'constituee',

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (chef_commission_id) REFERENCES users(id),
    FOREIGN KEY (cadre_dppg_id) REFERENCES users(id),
    FOREIGN KEY (cadre_daj_id) REFERENCES users(id),

    INDEX idx_dossier (dossier_id),
    INDEX idx_statut (statut)
);

-- Optionnel : migrer les données existantes si possible
-- (Cette partie peut être adaptée selon les données existantes)
-- INSERT INTO commissions (dossier_id, chef_commission_id, chef_commission_role, cadre_dppg_id, cadre_daj_id, date_constitution, statut)
-- SELECT dossier_id, chef_service_id, 'chef_service', cadre_dppg_id, 7, date_constitution, statut
-- FROM temp_commissions
-- WHERE chef_service_id IS NOT NULL AND cadre_dppg_id IS NOT NULL;

-- Nettoyer
DROP TEMPORARY TABLE temp_commissions;