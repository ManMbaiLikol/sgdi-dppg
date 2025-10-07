-- Fix workflow schema issues
-- This script ensures all necessary ENUM values and columns are present

-- 1. Update dossiers.statut ENUM to include all statuses
ALTER TABLE dossiers MODIFY COLUMN statut ENUM(
    'cree',
    'en_cours',
    'paye',
    'inspecte',
    'valide',
    'decide',
    'autorise',
    'rejete'
) DEFAULT 'cree';

-- 2. Ensure type_infrastructure includes all types
ALTER TABLE dossiers MODIFY COLUMN type_infrastructure ENUM(
    'station_service',
    'point_consommateur',
    'depot_gpl',
    'centre_emplisseur'
) NOT NULL;

-- 3. Ensure sous_type includes all subtypes
ALTER TABLE dossiers MODIFY COLUMN sous_type ENUM(
    'implantation',
    'reprise',
    'remodelage'
) NOT NULL;

-- 4. Add operational status columns (ignore errors if they already exist)
-- You can run these one by one and ignore "Duplicate column" errors

-- Add statut_operationnel column
ALTER TABLE dossiers
ADD COLUMN statut_operationnel ENUM(
    'operationnel',
    'ferme_temporaire',
    'ferme_definitif',
    'demantele'
) DEFAULT 'operationnel';

-- Add date_fermeture column
ALTER TABLE dossiers
ADD COLUMN date_fermeture DATE NULL;

-- Add date_reouverture column
ALTER TABLE dossiers
ADD COLUMN date_reouverture DATE NULL;

-- 5. Add departement column
ALTER TABLE dossiers
ADD COLUMN departement VARCHAR(100) NULL;

-- 6. Update documents.type_document ENUM to include all document types
ALTER TABLE documents MODIFY COLUMN type_document ENUM(
    'lettre_motivee',
    'rapport_delegation_regionale',
    'copie_cni',
    'contrat_bail_notarie',
    'plan_masse',
    'photos_site',
    'contrat_livraison',
    'autorisation_exploitation_miniere',
    'autorisation_prefectorale',
    'plan_installation',
    'note_calcul_structure',
    'autre'
) NOT NULL;

-- Show updated schema
SELECT 'Updated schema - dossiers table structure:' as info;
DESCRIBE dossiers;

SELECT 'Sample of recent dossiers:' as info;
SELECT id, numero, statut, type_infrastructure, sous_type, statut_operationnel, departement
FROM dossiers
ORDER BY date_creation DESC
LIMIT 5;