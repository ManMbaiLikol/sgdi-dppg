-- Migration pour le système de workflow "Huitaine" (8 jours) - SGDI
-- Ce système gère le compte à rebours de 8 jours pour la régularisation des dossiers

USE sgdi_mvp;

-- 1. Créer la table huitaine pour suivre les délais de 8 jours
CREATE TABLE IF NOT EXISTS huitaine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    type_irregularite ENUM('document_manquant', 'info_incomplete', 'non_conformite', 'paiement_partiel', 'autre') NOT NULL,
    description TEXT NOT NULL,
    date_debut TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_limite TIMESTAMP NOT NULL COMMENT 'Date limite (J+8)',
    date_regularisation TIMESTAMP NULL,
    statut ENUM('en_cours', 'regularise', 'rejete', 'annule') DEFAULT 'en_cours',
    regularise_par INT NULL COMMENT 'ID de l\'utilisateur qui a régularisé',
    commentaire_regularisation TEXT NULL,
    demandeur_notifie TINYINT DEFAULT 0 COMMENT 'Le demandeur a été notifié',
    alerte_j2_envoyee TINYINT DEFAULT 0,
    alerte_j1_envoyee TINYINT DEFAULT 0,
    alerte_j_envoyee TINYINT DEFAULT 0,
    created_by INT NOT NULL,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (regularise_par) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_statut (statut),
    INDEX idx_date_limite (date_limite),
    INDEX idx_dossier (dossier_id)
);

-- 2. Créer la table historique_huitaine pour tracer toutes les actions
CREATE TABLE IF NOT EXISTS historique_huitaine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    huitaine_id INT NOT NULL,
    action ENUM('creation', 'alerte_j2', 'alerte_j1', 'alerte_j', 'regularisation', 'rejet_auto', 'annulation') NOT NULL,
    description TEXT,
    user_id INT NULL,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (huitaine_id) REFERENCES huitaine(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_huitaine (huitaine_id),
    INDEX idx_action (action)
);

-- 3. Créer la table alertes_huitaine pour gérer les notifications
CREATE TABLE IF NOT EXISTS alertes_huitaine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    huitaine_id INT NOT NULL,
    type_alerte ENUM('j-2', 'j-1', 'j', 'regularise', 'rejete') NOT NULL,
    destinataire_user_id INT NULL COMMENT 'Utilisateur SGDI',
    destinataire_email VARCHAR(255) NULL COMMENT 'Email du demandeur',
    canal ENUM('email', 'sms', 'in_app') NOT NULL,
    statut_envoi ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente',
    date_envoi TIMESTAMP NULL,
    erreur_envoi TEXT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (huitaine_id) REFERENCES huitaine(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_user_id) REFERENCES users(id),
    INDEX idx_statut (statut_envoi),
    INDEX idx_type (type_alerte)
);

-- 4. Ajouter des colonnes au dossier pour référencer la huitaine active
ALTER TABLE dossiers
ADD COLUMN huitaine_active_id INT NULL COMMENT 'Référence à la huitaine en cours',
ADD COLUMN nombre_huitaines INT DEFAULT 0 COMMENT 'Nombre total de huitaines pour ce dossier',
ADD COLUMN derniere_regularisation TIMESTAMP NULL,
ADD FOREIGN KEY (huitaine_active_id) REFERENCES huitaine(id) ON DELETE SET NULL;

-- 5. Créer une vue pour les huitaines actives avec compte à rebours
CREATE OR REPLACE VIEW huitaines_actives AS
SELECT
    h.id,
    h.dossier_id,
    d.numero as numero_dossier,
    d.nom_demandeur,
    d.statut as statut_dossier,
    h.type_irregularite,
    h.description,
    h.date_debut,
    h.date_limite,
    h.statut,
    DATEDIFF(h.date_limite, NOW()) as jours_restants,
    TIMESTAMPDIFF(HOUR, NOW(), h.date_limite) as heures_restantes,
    h.alerte_j2_envoyee,
    h.alerte_j1_envoyee,
    h.alerte_j_envoyee,
    h.demandeur_notifie,
    h.created_by,
    CONCAT(u.prenom, ' ', u.nom) as cree_par
FROM huitaine h
INNER JOIN dossiers d ON h.dossier_id = d.id
LEFT JOIN users u ON h.created_by = u.id
WHERE h.statut = 'en_cours'
ORDER BY h.date_limite ASC;

-- 6. Créer une vue pour les statistiques huitaine
CREATE OR REPLACE VIEW statistiques_huitaine AS
SELECT
    COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) as en_cours,
    COUNT(CASE WHEN statut = 'regularise' THEN 1 END) as regularises,
    COUNT(CASE WHEN statut = 'rejete' THEN 1 END) as rejetes,
    COUNT(CASE WHEN statut = 'annule' THEN 1 END) as annules,
    COUNT(CASE WHEN statut = 'en_cours' AND DATEDIFF(date_limite, NOW()) <= 2 THEN 1 END) as urgents,
    COUNT(CASE WHEN statut = 'en_cours' AND date_limite < NOW() THEN 1 END) as expires,
    AVG(CASE WHEN statut = 'regularise' THEN TIMESTAMPDIFF(DAY, date_debut, date_regularisation) END) as duree_moyenne_regularisation
FROM huitaine;

-- 7. Ajouter un nouveau statut pour les dossiers en huitaine
ALTER TABLE dossiers
MODIFY COLUMN statut ENUM(
    'brouillon',
    'en_cours',
    'paye',
    'analyse_daj',
    'inspecte',
    'validation_chef_commission',
    'valide',
    'decide',
    'autorise',
    'rejete',
    'en_huitaine'
) NOT NULL;

-- 8. Créer des triggers pour automatiser certaines actions
DELIMITER //

-- Trigger pour incrémenter le nombre de huitaines sur un dossier
CREATE TRIGGER after_huitaine_insert
AFTER INSERT ON huitaine
FOR EACH ROW
BEGIN
    UPDATE dossiers
    SET nombre_huitaines = nombre_huitaines + 1,
        huitaine_active_id = NEW.id
    WHERE id = NEW.dossier_id;
END//

-- Trigger pour mettre à jour le dossier après régularisation
CREATE TRIGGER after_huitaine_regularisation
AFTER UPDATE ON huitaine
FOR EACH ROW
BEGIN
    IF NEW.statut = 'regularise' AND OLD.statut = 'en_cours' THEN
        UPDATE dossiers
        SET huitaine_active_id = NULL,
            derniere_regularisation = NEW.date_regularisation
        WHERE id = NEW.dossier_id;

        -- Enregistrer dans l'historique
        INSERT INTO historique_huitaine (huitaine_id, action, description, user_id)
        VALUES (NEW.id, 'regularisation', NEW.commentaire_regularisation, NEW.regularise_par);
    END IF;

    IF NEW.statut = 'rejete' AND OLD.statut = 'en_cours' THEN
        UPDATE dossiers
        SET huitaine_active_id = NULL,
            statut = 'rejete'
        WHERE id = NEW.dossier_id;

        -- Enregistrer dans l'historique
        INSERT INTO historique_huitaine (huitaine_id, action, description)
        VALUES (NEW.id, 'rejet_auto', 'Rejet automatique : délai de huitaine expiré');
    END IF;
END//

DELIMITER ;

-- Fin de la migration
SELECT 'Migration du système Huitaine terminée avec succès!' AS message;
