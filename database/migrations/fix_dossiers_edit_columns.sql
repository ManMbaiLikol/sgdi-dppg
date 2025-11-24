-- ============================================================================
-- Migration : Correction des colonnes manquantes pour édition de dossiers
-- ============================================================================
-- Date : 2025-01-24
-- Problème : La fonction modifierDossier() tente d'UPDATE des colonnes qui
--            n'existent pas dans la table dossiers
-- Solution : Ajouter toutes les colonnes manquantes avec vérification
-- ============================================================================

-- ÉTAPE 1 : Ajouter le type 'centre_emplisseur' à l'ENUM type_infrastructure
-- ----------------------------------------------------------------------------
SET @current_enum = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'type_infrastructure'
);

-- Seulement si 'centre_emplisseur' n'existe pas encore
SET @has_centre_emplisseur = IF(@current_enum LIKE '%centre_emplisseur%', 1, 0);

SET @alter_type = IF(@has_centre_emplisseur = 0,
    "ALTER TABLE dossiers MODIFY COLUMN type_infrastructure ENUM(
        'station_service','point_consommateur','depot_gpl','centre_emplisseur'
    ) NOT NULL",
    "SELECT 'Type centre_emplisseur déjà présent' AS message"
);

PREPARE stmt FROM @alter_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 2 : Ajouter 'remodelage' au sous_type
-- ----------------------------------------------------------------------------
SET @current_sous_type_enum = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'sous_type'
);

SET @has_remodelage = IF(@current_sous_type_enum LIKE '%remodelage%', 1, 0);

SET @alter_sous_type = IF(@has_remodelage = 0,
    "ALTER TABLE dossiers MODIFY COLUMN sous_type ENUM(
        'implantation','reprise','remodelage'
    ) NOT NULL",
    "SELECT 'Sous-type remodelage déjà présent' AS message"
);

PREPARE stmt FROM @alter_sous_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 3 : Ajouter la colonne departement
-- ----------------------------------------------------------------------------
SET @col_departement = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'departement'
);

SET @add_departement = IF(@col_departement = 0,
    "ALTER TABLE dossiers ADD COLUMN departement VARCHAR(100) NULL AFTER region",
    "SELECT 'Colonne departement déjà présente' AS message"
);

PREPARE stmt FROM @add_departement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 4 : Ajouter la colonne arrondissement
-- ----------------------------------------------------------------------------
SET @col_arrondissement = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'arrondissement'
);

SET @add_arrondissement = IF(@col_arrondissement = 0,
    "ALTER TABLE dossiers ADD COLUMN arrondissement VARCHAR(100) NULL AFTER ville",
    "SELECT 'Colonne arrondissement déjà présente' AS message"
);

PREPARE stmt FROM @add_arrondissement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 5 : Ajouter la colonne quartier
-- ----------------------------------------------------------------------------
SET @col_quartier = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'quartier'
);

SET @add_quartier = IF(@col_quartier = 0,
    "ALTER TABLE dossiers ADD COLUMN quartier VARCHAR(100) NULL AFTER arrondissement",
    "SELECT 'Colonne quartier déjà présente' AS message"
);

PREPARE stmt FROM @add_quartier;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 6 : Ajouter la colonne zone_type
-- ----------------------------------------------------------------------------
SET @col_zone_type = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'zone_type'
);

SET @add_zone_type = IF(@col_zone_type = 0,
    "ALTER TABLE dossiers ADD COLUMN zone_type ENUM('urbaine','rurale') DEFAULT 'urbaine' AFTER quartier",
    "SELECT 'Colonne zone_type déjà présente' AS message"
);

PREPARE stmt FROM @add_zone_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 7 : Gérer le conflit adresse_precise / lieu_dit
-- ----------------------------------------------------------------------------
-- Vérifier si adresse_precise existe
SET @col_adresse_precise = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'adresse_precise'
);

-- Vérifier si lieu_dit existe
SET @col_lieu_dit = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'lieu_dit'
);

-- Si lieu_dit n'existe pas mais adresse_precise existe, on garde adresse_precise
-- et on ajoute lieu_dit séparément
SET @add_lieu_dit = IF(@col_lieu_dit = 0,
    "ALTER TABLE dossiers ADD COLUMN lieu_dit VARCHAR(200) NULL AFTER zone_type",
    "SELECT 'Colonne lieu_dit déjà présente' AS message"
);

PREPARE stmt FROM @add_lieu_dit;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Si adresse_precise n'existe pas, la recréer (au cas où elle aurait été renommée)
SET @add_adresse_precise = IF(@col_adresse_precise = 0,
    "ALTER TABLE dossiers ADD COLUMN adresse_precise TEXT NULL AFTER email_demandeur",
    "SELECT 'Colonne adresse_precise déjà présente' AS message"
);

PREPARE stmt FROM @add_adresse_precise;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 8 : Ajouter la colonne annee_mise_en_service
-- ----------------------------------------------------------------------------
SET @col_annee = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'annee_mise_en_service'
);

SET @add_annee = IF(@col_annee = 0,
    "ALTER TABLE dossiers ADD COLUMN annee_mise_en_service YEAR NULL AFTER coordonnees_gps",
    "SELECT 'Colonne annee_mise_en_service déjà présente' AS message"
);

PREPARE stmt FROM @add_annee;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ÉTAPE 9 : Ajouter les colonnes pour centre_emplisseur
-- ----------------------------------------------------------------------------
-- Colonne operateur_gaz
SET @col_operateur_gaz = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'operateur_gaz'
);

SET @add_operateur_gaz = IF(@col_operateur_gaz = 0,
    "ALTER TABLE dossiers ADD COLUMN operateur_gaz VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
    "SELECT 'Colonne operateur_gaz déjà présente' AS message"
);

PREPARE stmt FROM @add_operateur_gaz;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonne entreprise_constructrice
SET @col_constructrice = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'entreprise_constructrice'
);

SET @add_constructrice = IF(@col_constructrice = 0,
    "ALTER TABLE dossiers ADD COLUMN entreprise_constructrice VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
    "SELECT 'Colonne entreprise_constructrice déjà présente' AS message"
);

PREPARE stmt FROM @add_constructrice;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonne capacite_enfutage
SET @col_capacite = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'capacite_enfutage'
);

SET @add_capacite = IF(@col_capacite = 0,
    "ALTER TABLE dossiers ADD COLUMN capacite_enfutage VARCHAR(100) NULL COMMENT 'Capacité d''enfûtage (bouteilles/jour)'",
    "SELECT 'Colonne capacite_enfutage déjà présente' AS message"
);

PREPARE stmt FROM @add_capacite;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VÉRIFICATIONS FINALES
-- ============================================================================

-- Vérifier que toutes les colonnes sont présentes
SELECT
    'Vérification des colonnes ajoutées' AS message,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'departement') as departement,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'arrondissement') as arrondissement,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'quartier') as quartier,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'zone_type') as zone_type,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'lieu_dit') as lieu_dit,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'adresse_precise') as adresse_precise,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'annee_mise_en_service') as annee_mise_en_service,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'operateur_gaz') as operateur_gaz,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'entreprise_constructrice') as entreprise_constructrice,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'capacite_enfutage') as capacite_enfutage;

-- Vérifier l'ENUM type_infrastructure
SELECT
    'Vérification ENUM type_infrastructure' AS message,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dossiers'
AND COLUMN_NAME = 'type_infrastructure';

-- Vérifier l'ENUM sous_type
SELECT
    'Vérification ENUM sous_type' AS message,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dossiers'
AND COLUMN_NAME = 'sous_type';

-- Message final
SELECT '✅ Migration terminée avec succès !' AS message,
       'Toutes les colonnes nécessaires pour edit.php sont maintenant disponibles' AS details;
