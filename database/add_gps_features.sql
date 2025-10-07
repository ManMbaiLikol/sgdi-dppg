-- Améliorations pour les fonctionnalités géographiques - SGDI

USE sgdi_mvp;

-- 1. S'assurer que le champ coordonnees_gps existe et est assez grand
ALTER TABLE dossiers
MODIFY COLUMN coordonnees_gps VARCHAR(100);

-- 2. Ajouter le champ adresse_precise s'il n'existe pas
-- Ignorer l'erreur si la colonne existe déjà
ALTER TABLE dossiers
ADD COLUMN adresse_precise TEXT AFTER coordonnees_gps;

-- 3. Ajouter un index sur coordonnees_gps pour accélérer les recherches
-- Ignorer l'erreur si l'index existe déjà
ALTER TABLE dossiers
ADD INDEX idx_coordonnees_gps (coordonnees_gps);

-- 4. Créer une table pour les zones restreintes (optionnel - pour future utilisation)
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

-- 5. Créer une table pour l'historique des localisations (audit trail)
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

-- 6. Ajouter des contraintes CHECK pour valider les coordonnées (MySQL 8.0+)
-- Note: Si vous utilisez MySQL 5.7, commentez ces lignes
-- ALTER TABLE dossiers
-- ADD CONSTRAINT chk_latitude CHECK (CAST(SUBSTRING_INDEX(coordonnees_gps, ',', 1) AS DECIMAL(10,8)) BETWEEN -90 AND 90);

-- 7. Créer une vue pour faciliter l'accès aux infrastructures géolocalisées
CREATE OR REPLACE VIEW infrastructures_geolocalisees AS
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
AND d.coordonnees_gps REGEXP '^-?[0-9]+\.?[0-9]*,[ ]?-?[0-9]+\.?[0-9]*$';

-- 8. Créer une vue pour les infrastructures publiques (autorisées uniquement)
CREATE OR REPLACE VIEW infrastructures_publiques AS
SELECT * FROM infrastructures_geolocalisees
WHERE statut = 'autorise';

-- 9. Créer des index pour optimiser les recherches géographiques
ALTER TABLE dossiers
ADD INDEX idx_region (region);

ALTER TABLE dossiers
ADD INDEX idx_ville (ville);

-- 10. Ajouter des exemples de zones restreintes (optionnel - à adapter selon les besoins réels)
-- INSERT INTO zones_restreintes (nom, type, latitude, longitude, rayon_km, description) VALUES
-- ('Base militaire Yaoundé', 'militaire', 3.8480, 11.5021, 2.0, 'Zone militaire - Interdiction d\'implantation dans un rayon de 2 km'),
-- ('Parc National de la Bénoué', 'parc_national', 8.5000, 13.8333, 10.0, 'Parc national protégé');

-- Fin du script
SELECT 'Migration des fonctionnalités géographiques terminée avec succès!' AS message;
