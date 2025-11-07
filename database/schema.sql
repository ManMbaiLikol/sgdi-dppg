-- SGDI MVP Database Schema
-- Système de Gestion des Dossiers d'Implantation - Version MVP

CREATE DATABASE IF NOT EXISTS sgdi_mvp;
USE sgdi_mvp;

-- Table des utilisateurs
-- NOTE: Le schéma de base définit 5 rôles initiaux.
-- Les 4 rôles additionnels (cadre_daj, chef_commission, sous_directeur, ministre)
-- sont ajoutés via les migrations pour atteindre le total de 9 rôles du système.
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('chef_service', 'cadre_dppg', 'billeteur', 'directeur', 'admin') NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    actif TINYINT DEFAULT 1,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des dossiers
CREATE TABLE dossiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(20) UNIQUE NOT NULL,
    type_infrastructure ENUM('station_service', 'point_consommateur', 'depot_gpl') NOT NULL,
    sous_type ENUM('implantation', 'reprise') NOT NULL,
    statut ENUM('cree', 'en_cours', 'paye', 'inspecte', 'valide', 'decide') DEFAULT 'cree',

    -- Informations du demandeur
    nom_demandeur VARCHAR(200) NOT NULL,
    contact_demandeur VARCHAR(100),
    telephone_demandeur VARCHAR(20),
    email_demandeur VARCHAR(100),

    -- Localisation
    region VARCHAR(100),
    ville VARCHAR(100),
    adresse_precise TEXT,
    coordonnees_gps VARCHAR(50),

    -- Informations spécifiques selon le type
    operateur_proprietaire VARCHAR(200), -- Pour station-service
    entreprise_beneficiaire VARCHAR(200), -- Pour point consommateur
    contrat_livraison TEXT, -- Pour point consommateur
    entreprise_installatrice VARCHAR(200), -- Pour dépôt GPL

    -- Métadonnées
    user_id INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_statut (statut),
    INDEX idx_type (type_infrastructure),
    INDEX idx_numero (numero)
);

-- Table des documents
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    nom_original VARCHAR(255) NOT NULL,
    type_document ENUM('piece_identite', 'plan_implantation', 'autorisation_terrain', 'etude_impact', 'autres') NOT NULL,
    taille_fichier INT,
    extension VARCHAR(10),
    chemin_fichier VARCHAR(500) NOT NULL,
    user_id INT NOT NULL,
    date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table de l'historique des actions
CREATE TABLE historique (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ancien_statut ENUM('cree', 'en_cours', 'paye', 'inspecte', 'valide', 'decide'),
    nouveau_statut ENUM('cree', 'en_cours', 'paye', 'inspecte', 'valide', 'decide'),
    user_id INT NOT NULL,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des commissions
CREATE TABLE commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,
    chef_service_id INT NOT NULL,
    cadre_dppg_id INT NOT NULL,
    membre_externe_nom VARCHAR(200) NOT NULL,
    membre_externe_fonction VARCHAR(100),
    membre_externe_contact VARCHAR(100),
    date_constitution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('constituee', 'en_mission', 'rapport_fait') DEFAULT 'constituee',

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (chef_service_id) REFERENCES users(id),
    FOREIGN KEY (cadre_dppg_id) REFERENCES users(id)
);

-- Table des paiements
CREATE TABLE paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,
    montant DECIMAL(15,2) NOT NULL,
    devise VARCHAR(10) DEFAULT 'FCFA',
    mode_paiement ENUM('especes', 'cheque', 'virement') NOT NULL,
    reference_paiement VARCHAR(100),
    date_paiement DATE NOT NULL,
    billeteur_id INT NOT NULL,
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observations TEXT,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (billeteur_id) REFERENCES users(id)
);

-- Table des inspections
CREATE TABLE inspections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,
    cadre_dppg_id INT NOT NULL,
    date_inspection DATE NOT NULL,
    rapport TEXT NOT NULL,
    recommandations TEXT,
    conforme ENUM('oui', 'non', 'sous_reserve') NOT NULL,
    observations TEXT,
    date_redaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valide_par_directeur TINYINT DEFAULT 0,
    directeur_id INT,
    date_validation TIMESTAMP NULL,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (cadre_dppg_id) REFERENCES users(id),
    FOREIGN KEY (directeur_id) REFERENCES users(id)
);

-- Table des décisions finales
CREATE TABLE decisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL UNIQUE,
    decision ENUM('approuve', 'refuse') NOT NULL,
    motif TEXT,
    date_decision DATE NOT NULL,
    autorite_decisionnaire VARCHAR(200) DEFAULT 'Directeur DPPG',
    reference_decision VARCHAR(100),
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE
);