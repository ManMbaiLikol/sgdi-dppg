-- Migration : Alignement workflow de validation (08/01/2025)
-- À exécuter sur la base de données Railway après le déploiement

-- ========================================
-- 1. Mise à jour des statuts disponibles
-- ========================================

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
    'suspendu'
) DEFAULT 'brouillon';

-- ========================================
-- 2. Modification de la table historique
-- ========================================

ALTER TABLE historique
MODIFY COLUMN ancien_statut VARCHAR(50) NULL,
MODIFY COLUMN nouveau_statut VARCHAR(50) NULL;

-- ========================================
-- 3. Ajout de la colonne motif_fermeture
-- ========================================

-- Vérifier si la colonne existe déjà avant de l'ajouter
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'motif_fermeture'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE dossiers ADD COLUMN motif_fermeture TEXT NULL AFTER statut_operationnel',
    'SELECT "La colonne motif_fermeture existe déjà" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- 4. Mise à jour des statuts existants (optionnel)
-- ========================================

-- Si vous avez des dossiers avec l'ancien statut 'validation_chef_commission',
-- les mettre à jour vers 'validation_commission'
UPDATE dossiers
SET statut = 'validation_commission'
WHERE statut = 'validation_chef_commission';

-- ========================================
-- Vérification post-migration
-- ========================================

SELECT 'Migration terminée avec succès' AS status;

-- Vérifier les statuts actuels
SELECT 'Répartition des statuts:' AS info;
SELECT statut, COUNT(*) as count
FROM dossiers
GROUP BY statut
ORDER BY count DESC;

-- Vérifier les colonnes de gestion opérationnelle
SELECT 'Colonnes de gestion opérationnelle:' AS info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'dossiers'
AND COLUMN_NAME IN ('statut_operationnel', 'motif_fermeture', 'date_fermeture', 'date_reouverture');
