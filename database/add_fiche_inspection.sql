-- Migration : Ajout de la fiche d'inspection détaillée
-- Date : 2025-10-13
-- Description : Fiche de récolte des données sur les infrastructures pétrolières

USE sgdi_mvp;

-- Table principale de la fiche d'inspection
CREATE TABLE IF NOT EXISTS fiches_inspection (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,

    -- 1. INFORMATIONS D'ORDRE GENERAL (pré-remplies depuis le dossier)
    type_infrastructure VARCHAR(200),
    raison_sociale VARCHAR(200),
    bp VARCHAR(100),
    telephone VARCHAR(50),
    fax VARCHAR(50),
    email VARCHAR(100),

    -- Localisation
    ville VARCHAR(100),
    quartier VARCHAR(100),
    rue VARCHAR(200),
    region VARCHAR(100),
    departement VARCHAR(100),
    arrondissement VARCHAR(100),
    lieu_dit VARCHAR(200),

    -- 2. INFORMATIONS DE GEO-REFERENCEMENT
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    heure_gmt TIME,
    heure_locale TIME,
    latitude_degres INT,
    latitude_minutes INT,
    latitude_secondes DECIMAL(5, 2),
    longitude_degres INT,
    longitude_minutes INT,
    longitude_secondes DECIMAL(5, 2),

    -- INFORMATIONS TECHNIQUES
    date_mise_service DATE,
    autorisation_minee VARCHAR(100),
    autorisation_minmidt VARCHAR(100),
    type_gestion ENUM('libre', 'location', 'autres') DEFAULT 'libre',
    type_gestion_autre VARCHAR(200),

    -- Documents techniques (Oui/Non)
    plan_ensemble TINYINT DEFAULT 0,
    contrat_bail TINYINT DEFAULT 0,
    permis_batir TINYINT DEFAULT 0,
    certificat_urbanisme TINYINT DEFAULT 0,
    lettre_minepded TINYINT DEFAULT 0,
    plan_masse TINYINT DEFAULT 0,

    -- Effectifs du personnel
    chef_piste VARCHAR(200),
    gerant VARCHAR(200),

    -- Sécurité et environnement
    bouches_incendies TINYINT DEFAULT 0,
    decanteur_separateur TINYINT DEFAULT 0,
    autres_dispositions_securite TEXT,

    -- Observations générales
    observations_generales TEXT,

    -- Métadonnées
    lieu_etablissement VARCHAR(200),
    date_etablissement DATE,
    inspecteur_id INT,
    statut ENUM('brouillon', 'validee', 'signee') DEFAULT 'brouillon',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (inspecteur_id) REFERENCES users(id),
    INDEX idx_dossier (dossier_id),
    INDEX idx_statut (statut)
);

-- Table des cuves
CREATE TABLE IF NOT EXISTS fiche_inspection_cuves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiche_id INT NOT NULL,
    numero INT NOT NULL,
    produit ENUM('super', 'gasoil', 'petrole', 'autre') NOT NULL,
    produit_autre VARCHAR(100),
    type_cuve ENUM('double_enveloppe', 'simple_enveloppe') DEFAULT 'double_enveloppe',
    capacite DECIMAL(10, 2),
    nombre INT DEFAULT 1,

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    INDEX idx_fiche (fiche_id)
);

-- Table des pompes
CREATE TABLE IF NOT EXISTS fiche_inspection_pompes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiche_id INT NOT NULL,
    numero INT NOT NULL,
    produit ENUM('super', 'gasoil', 'petrole', 'autre') NOT NULL,
    produit_autre VARCHAR(100),
    marque VARCHAR(100),
    debit_nominal DECIMAL(10, 2),
    nombre INT DEFAULT 1,

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    INDEX idx_fiche (fiche_id)
);

-- Table des distances aux édifices publics
CREATE TABLE IF NOT EXISTS fiche_inspection_distances_edifices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiche_id INT NOT NULL,
    direction ENUM('nord', 'sud', 'est', 'ouest') NOT NULL,
    description_edifice TEXT,
    distance_metres DECIMAL(10, 2),

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    INDEX idx_fiche (fiche_id)
);

-- Table des distances aux stations-services
CREATE TABLE IF NOT EXISTS fiche_inspection_distances_stations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fiche_id INT NOT NULL,
    direction ENUM('nord', 'sud', 'est', 'ouest') NOT NULL,
    nom_station VARCHAR(200),
    distance_metres DECIMAL(10, 2),

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    INDEX idx_fiche (fiche_id)
);

-- Vue pour obtenir une fiche complète
DROP VIEW IF EXISTS vue_fiches_inspection_completes;
CREATE VIEW vue_fiches_inspection_completes AS
SELECT
    f.*,
    d.numero as numero_dossier,
    d.nom_demandeur,
    d.type_infrastructure as type_infra_dossier,
    u.nom as inspecteur_nom,
    u.prenom as inspecteur_prenom,
    (SELECT COUNT(*) FROM fiche_inspection_cuves WHERE fiche_id = f.id) as nb_cuves,
    (SELECT COUNT(*) FROM fiche_inspection_pompes WHERE fiche_id = f.id) as nb_pompes
FROM fiches_inspection f
JOIN dossiers d ON f.dossier_id = d.id
LEFT JOIN users u ON f.inspecteur_id = u.id;

-- Message de confirmation
SELECT 'Migration de la fiche d\'inspection terminée avec succès!' as Message;
