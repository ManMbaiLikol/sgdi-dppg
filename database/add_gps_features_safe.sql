-- Améliorations pour les fonctionnalités géographiques - SGDI
-- Version sécurisée - Exécuter ligne par ligne si nécessaire

USE sgdi_mvp;

-- 1. S'assurer que le champ coordonnees_gps existe et est assez grand
ALTER TABLE dossiers
MODIFY COLUMN coordonnees_gps VARCHAR(100);

-- 2. Ajouter le champ adresse_precise
-- Si erreur "Duplicate column name", ignorer - la colonne existe déjà
ALTER TABLE dossiers
ADD COLUMN adresse_precise TEXT AFTER coordonnees_gps;

-- 3. Ajouter un index sur coordonnees_gps
-- Si erreur "Duplicate key name", ignorer - l'index existe déjà
ALTER TABLE dossiers
ADD INDEX idx_coordonnees_gps (coordonnees_gps);

-- 4. Créer une table pour les zones restreintes
CREATE TABLE IF NOT EXISTS zones_restreintes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    type ENUM('militaire', 'parc_national', 'reserve', 'zone_protegee', 'autre') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    rayon_km DECIMAL(10, 2) NOT NULL COMMENT 'Rayon de la zone restreinte en km',
    description TEXT,
    actif TINYINT DEFAULT 1,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Créer une table pour l'historique des localisations
CREATE TABLE IF NOT EXISTS historique_localisation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    anciennes_coordonnees VARCHAR(100),
    nouvelles_coordonnees VARCHAR(100),
    user_id INT NOT NULL,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 6. Ajouter des index pour optimiser les recherches géographiques
ALTER TABLE dossiers
ADD INDEX idx_region (region);

ALTER TABLE dossiers
ADD INDEX idx_ville (ville);

-- 7. Créer une vue pour faciliter l'accès aux infrastructures géolocalisées
-- DROP VIEW si elle existe déjà
DROP VIEW IF EXISTS infrastructures_geolocalisees;

CREATE VIEW infrastructures_geolocalisees AS
SELECT
    d.id,
    d.numero,
    d.type_infrastructure,
    d.sous_type,
    d.nom_demandeur,
    d.ville,
    d.region,
    d.coordonnees_gps,
    d.adresse_precise,
    d.statut,
    d.date_creation,
    CAST(SUBSTRING_INDEX(d.coordonnees_gps, ',', 1) AS DECIMAL(10,8)) as latitude,
    CAST(SUBSTRING_INDEX(d.coordonnees_gps, ',', -1) AS DECIMAL(11,8)) as longitude
FROM dossiers d
WHERE d.coordonnees_gps IS NOT NULL
AND d.coordonnees_gps != ''
AND d.coordonnees_gps REGEXP '^-?[0-9]+\\.?[0-9]*,[ ]?-?[0-9]+\\.?[0-9]*$';

-- 8. Créer une vue pour les infrastructures publiques
DROP VIEW IF EXISTS infrastructures_publiques;

CREATE VIEW infrastructures_publiques AS
SELECT * FROM infrastructures_geolocalisees
WHERE statut = 'autorise';

-- Fin du script
SELECT 'Migration des fonctionnalités géographiques terminée avec succès!' AS message;
