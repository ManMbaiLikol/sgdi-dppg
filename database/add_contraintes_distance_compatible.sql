-- Migration : Ajout des contraintes de distance pour les implantations
-- Date : 2025-10-13
-- Description : Implémentation des normes de distance de sécurité pour les stations-service
-- Version compatible MySQL 5.7+

USE sgdi_mvp;

-- Table des catégories de points d'intérêt avec leurs contraintes de distance
CREATE TABLE IF NOT EXISTS categories_poi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    distance_min_metres INT NOT NULL COMMENT 'Distance minimale en mètres',
    distance_min_rural_metres INT NOT NULL COMMENT 'Distance minimale en zone rurale (avec réduction de 20%)',
    couleur_marqueur VARCHAR(20) DEFAULT '#dc3545' COMMENT 'Couleur pour l\'affichage sur la carte',
    icone VARCHAR(50) DEFAULT 'exclamation-triangle' COMMENT 'Icône Font Awesome',
    actif TINYINT DEFAULT 1,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des points d'intérêt stratégiques
CREATE TABLE IF NOT EXISTS points_interet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categorie_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    adresse TEXT,
    ville VARCHAR(100),
    region VARCHAR(100),
    zone_type ENUM('urbaine', 'rurale') DEFAULT 'urbaine',
    actif TINYINT DEFAULT 1,
    user_id INT COMMENT 'Utilisateur qui a créé le POI',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (categorie_id) REFERENCES categories_poi(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_coords (latitude, longitude),
    INDEX idx_categorie (categorie_id),
    INDEX idx_ville (ville),
    INDEX idx_actif (actif)
);

-- Table des validations de conformité géospatiale
CREATE TABLE IF NOT EXISTS validations_geospatiales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    zone_type ENUM('urbaine', 'rurale') DEFAULT 'urbaine',
    conforme TINYINT NOT NULL COMMENT '1 = conforme, 0 = non conforme',
    nombre_violations INT DEFAULT 0,
    user_id INT NOT NULL,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_dossier (dossier_id),
    INDEX idx_conforme (conforme)
);

-- Table des violations de contraintes détectées
CREATE TABLE IF NOT EXISTS violations_contraintes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    validation_id INT NOT NULL,
    dossier_id INT NOT NULL,
    type_violation ENUM('distance_station', 'distance_poi') NOT NULL,
    poi_id INT COMMENT 'ID du POI en conflit (si applicable)',
    station_id INT COMMENT 'ID de la station en conflit (si applicable)',
    distance_mesuree DECIMAL(10, 2) NOT NULL COMMENT 'Distance mesurée en mètres',
    distance_requise DECIMAL(10, 2) NOT NULL COMMENT 'Distance minimale requise en mètres',
    ecart DECIMAL(10, 2) NOT NULL COMMENT 'Écart par rapport à la norme (mètres)',
    nom_etablissement VARCHAR(200),
    categorie_etablissement VARCHAR(200),
    severite ENUM('critique', 'majeure', 'mineure') DEFAULT 'majeure',
    coordonnees_etablissement VARCHAR(100),
    date_detection TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (validation_id) REFERENCES validations_geospatiales(id) ON DELETE CASCADE,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (poi_id) REFERENCES points_interet(id) ON DELETE SET NULL,
    FOREIGN KEY (station_id) REFERENCES dossiers(id) ON DELETE SET NULL,
    INDEX idx_validation (validation_id),
    INDEX idx_dossier (dossier_id),
    INDEX idx_type (type_violation),
    INDEX idx_severite (severite)
);

-- Table d'audit des modifications de POI
CREATE TABLE IF NOT EXISTS audit_poi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    poi_id INT NOT NULL,
    action ENUM('creation', 'modification', 'suppression', 'activation', 'desactivation') NOT NULL,
    user_id INT NOT NULL,
    anciennes_valeurs TEXT,
    nouvelles_valeurs TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (poi_id) REFERENCES points_interet(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_poi (poi_id),
    INDEX idx_action (action)
);

-- Insertion des catégories de POI selon la réglementation
-- On utilise INSERT IGNORE pour éviter les doublons si le script est réexécuté
INSERT IGNORE INTO categories_poi (code, nom, description, distance_min_metres, distance_min_rural_metres, couleur_marqueur, icone) VALUES
-- Catégorie 1 : Distance de 1000m
('presidence', 'Présidence de la République', 'Bureau du Président de la République', 1000, 800, '#8B0000', 'landmark'),
('services_pm', 'Services du Premier Ministre', 'Bureau du Premier Ministre', 1000, 800, '#8B0000', 'building'),
('assemblee_nationale', 'Assemblée Nationale', 'Parlement - Assemblée Nationale', 1000, 800, '#8B0000', 'university'),
('senat', 'Sénat', 'Parlement - Sénat', 1000, 800, '#8B0000', 'university'),

-- Catégorie 2 : Distance de 500m
('services_gouverneur', 'Services du Gouverneur', 'Bureaux du Gouverneur de région', 500, 400, '#DC143C', 'building'),
('prefecture', 'Préfecture', 'Bureaux de la préfecture', 500, 400, '#DC143C', 'building'),
('sous_prefecture', 'Sous-préfecture', 'Bureaux de la sous-préfecture', 500, 400, '#DC143C', 'building'),
('mairie', 'Mairie', 'Hôtel de ville / Mairie', 500, 400, '#DC143C', 'building'),

-- Catégorie 3 : Distance de 100m
('etablissement_enseignement', 'Établissement d\'enseignement', 'École, collège, lycée, université', 100, 80, '#FF6B6B', 'school'),
('infrastructure_sanitaire', 'Infrastructure sanitaire', 'Hôpital, centre de santé, dispensaire', 100, 80, '#FF6B6B', 'hospital'),
('lieu_culte', 'Lieu de culte', 'Église, mosquée, temple', 100, 80, '#FF6B6B', 'place-of-worship'),
('terrain_sport', 'Terrain de sport', 'Stade, terrain de football, complexe sportif', 100, 80, '#FF6B6B', 'futbol'),
('place_marche', 'Place de marché', 'Marché public', 100, 80, '#FF6B6B', 'shopping-basket'),
('batiment_administratif', 'Bâtiment administratif', 'Administration publique', 100, 80, '#FF6B6B', 'building');

-- Procédure pour ajouter des colonnes de manière sécurisée
DELIMITER $$

DROP PROCEDURE IF EXISTS AddColumnIfNotExists$$
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDefinition VARCHAR(500)
)
BEGIN
    DECLARE columnExists INT;

    SELECT COUNT(*) INTO columnExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;

    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD COLUMN ', columnName, ' ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Ajouter les colonnes à la table dossiers
CALL AddColumnIfNotExists('dossiers', 'zone_type', 'ENUM(\'urbaine\', \'rurale\') DEFAULT \'urbaine\' AFTER coordonnees_gps');
CALL AddColumnIfNotExists('dossiers', 'validation_geospatiale_faite', 'TINYINT DEFAULT 0 AFTER zone_type');
CALL AddColumnIfNotExists('dossiers', 'conformite_geospatiale', 'ENUM(\'conforme\', \'non_conforme\', \'en_attente\') DEFAULT \'en_attente\' AFTER validation_geospatiale_faite');

-- Nettoyer la procédure temporaire
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

-- Vue pour obtenir les statistiques de conformité
DROP VIEW IF EXISTS vue_statistiques_conformite;
CREATE VIEW vue_statistiques_conformite AS
SELECT
    d.region,
    d.ville,
    d.type_infrastructure,
    d.zone_type,
    COUNT(*) as total_dossiers,
    SUM(CASE WHEN d.conformite_geospatiale = 'conforme' THEN 1 ELSE 0 END) as conformes,
    SUM(CASE WHEN d.conformite_geospatiale = 'non_conforme' THEN 1 ELSE 0 END) as non_conformes,
    SUM(CASE WHEN d.conformite_geospatiale = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    ROUND(SUM(CASE WHEN d.conformite_geospatiale = 'conforme' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taux_conformite
FROM dossiers d
WHERE d.coordonnees_gps IS NOT NULL AND d.coordonnees_gps != ''
GROUP BY d.region, d.ville, d.type_infrastructure, d.zone_type;

-- Vue pour les violations critiques en cours
DROP VIEW IF EXISTS vue_violations_critiques;
CREATE VIEW vue_violations_critiques AS
SELECT
    v.id,
    v.dossier_id,
    d.numero as numero_dossier,
    d.nom_demandeur,
    d.ville,
    v.type_violation,
    v.nom_etablissement,
    v.categorie_etablissement,
    v.distance_mesuree,
    v.distance_requise,
    v.ecart,
    v.severite,
    v.date_detection,
    d.statut as statut_dossier
FROM violations_contraintes v
JOIN dossiers d ON v.dossier_id = d.id
WHERE v.severite IN ('critique', 'majeure')
AND d.statut NOT IN ('rejete', 'abandonne')
ORDER BY v.severite DESC, v.ecart DESC;

-- Procédure pour ajouter des index de manière sécurisée
DELIMITER $$

DROP PROCEDURE IF EXISTS AddIndexIfNotExists$$
CREATE PROCEDURE AddIndexIfNotExists(
    IN tableName VARCHAR(128),
    IN indexName VARCHAR(128),
    IN indexDefinition VARCHAR(500)
)
BEGIN
    DECLARE indexExists INT;

    SELECT COUNT(*) INTO indexExists
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND INDEX_NAME = indexName;

    IF indexExists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX ', indexName, ' ON ', tableName, ' ', indexDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Index supplémentaires pour optimiser les recherches géospatiales
CALL AddIndexIfNotExists('dossiers', 'idx_dossiers_coords', '(coordonnees_gps)');
CALL AddIndexIfNotExists('dossiers', 'idx_dossiers_zone_type', '(zone_type)');
CALL AddIndexIfNotExists('dossiers', 'idx_dossiers_conformite', '(conformite_geospatiale)');

-- Nettoyer la procédure temporaire
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;

-- Message de confirmation
SELECT 'Migration des contraintes de distance terminée avec succès!' as Message;
