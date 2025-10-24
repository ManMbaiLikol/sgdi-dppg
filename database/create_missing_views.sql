-- Script pour créer les vues SQL manquantes après import Railway
-- Ces vues n'ont pas été exportées correctement par le script PDO

-- 1. Vue statistiques_huitaine
DROP VIEW IF EXISTS statistiques_huitaine;
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

-- 2. Vue infrastructures_geolocalisees
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

-- 3. Vue infrastructures_publiques
DROP VIEW IF EXISTS infrastructures_publiques;
CREATE VIEW infrastructures_publiques AS
SELECT * FROM infrastructures_geolocalisees
WHERE statut = 'autorise';

-- 4. Vue vue_statistiques_conformite
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

-- 5. Vue vue_violations_critiques
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

-- 6. Vue vue_fiches_inspection_completes
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

SELECT 'Vues SQL créées avec succès!' as Message;
