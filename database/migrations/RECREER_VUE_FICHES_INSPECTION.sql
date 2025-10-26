-- Script pour recréer la vue avec tous les nouveaux champs
-- Date : 2025-10-25
-- Description : Recréer la vue pour inclure les champs ajoutés après sa création initiale

USE sgdi_mvp;

-- Supprimer l'ancienne vue
DROP VIEW IF EXISTS vue_fiches_inspection_completes;

-- Recréer la vue avec TOUS les champs
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

-- Vérifier que la vue contient bien tous les nouveaux champs
SELECT 'Vue recréée avec succès !' as Message;

-- Afficher les champs de la vue
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'vue_fiches_inspection_completes'
  AND COLUMN_NAME IN (
      'numero_contrat_approvisionnement',
      'societe_contractante',
      'besoins_mensuels_litres',
      'nombre_personnels',
      'superficie_site',
      'recommandations',
      'parc_engin',
      'systeme_recuperation_huiles',
      'batiments_site',
      'infra_eau',
      'infra_electricite',
      'reseau_camtel',
      'reseau_mtn',
      'reseau_orange',
      'reseau_nexttel'
  )
ORDER BY COLUMN_NAME;
