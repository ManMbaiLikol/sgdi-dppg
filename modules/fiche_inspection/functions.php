<?php
// Fonctions pour la gestion des fiches d'inspection - SGDI

/**
 * Récupérer une fiche d'inspection par dossier ID
 */
function getFicheInspectionByDossier($dossier_id) {
    global $pdo;

    $sql = "SELECT * FROM vue_fiches_inspection_completes WHERE dossier_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetch();
}

/**
 * Créer une nouvelle fiche d'inspection (pré-remplie depuis le dossier)
 */
function creerFicheInspection($dossier_id, $user_id) {
    global $pdo;

    // Vérifier si une fiche existe déjà
    $existing = getFicheInspectionByDossier($dossier_id);
    if ($existing) {
        return $existing['id'];
    }

    // Récupérer les infos du dossier
    require_once '../dossiers/functions.php';
    $dossier = getDossierById($dossier_id);

    if (!$dossier) {
        return false;
    }

    // Parser les coordonnées GPS
    require_once '../../includes/map_functions.php';
    $coords = parseGPSCoordinates($dossier['coordonnees_gps']);

    try {
        $pdo->beginTransaction();

        // Créer la fiche avec les données du dossier
        $sql = "INSERT INTO fiches_inspection (
            dossier_id, type_infrastructure, raison_sociale,
            telephone, email, ville, quartier, rue, region,
            departement, arrondissement, lieu_dit,
            latitude, longitude, inspecteur_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $dossier_id,
            getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']),
            $dossier['nom_demandeur'],
            $dossier['telephone_demandeur'],
            $dossier['email_demandeur'],
            $dossier['ville'],
            $dossier['quartier'],
            $dossier['rue'],
            $dossier['region'],
            $dossier['departement'],
            $dossier['arrondissement'],
            $dossier['lieu_dit'],
            $coords ? $coords['latitude'] : null,
            $coords ? $coords['longitude'] : null,
            $user_id
        ]);

        $fiche_id = $pdo->lastInsertId();

        // Créer les 4 lignes de distances édifices (Nord, Sud, Est, Ouest)
        $sql = "INSERT INTO fiche_inspection_distances_edifices (fiche_id, direction) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        foreach (['nord', 'sud', 'est', 'ouest'] as $direction) {
            $stmt->execute([$fiche_id, $direction]);
        }

        // Créer les 4 lignes de distances stations (Nord, Sud, Est, Ouest)
        $sql = "INSERT INTO fiche_inspection_distances_stations (fiche_id, direction) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        foreach (['nord', 'sud', 'est', 'ouest'] as $direction) {
            $stmt->execute([$fiche_id, $direction]);
        }

        $pdo->commit();
        return $fiche_id;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création fiche inspection: " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour une fiche d'inspection
 */
function mettreAJourFicheInspection($fiche_id, $data) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Mise à jour de la fiche principale
        $sql = "UPDATE fiches_inspection SET
            raison_sociale = ?, bp = ?, telephone = ?, fax = ?, email = ?,
            ville = ?, quartier = ?, rue = ?, region = ?,
            departement = ?, arrondissement = ?, lieu_dit = ?,
            latitude = ?, longitude = ?, heure_gmt = ?, heure_locale = ?,
            latitude_degres = ?, latitude_minutes = ?, latitude_secondes = ?,
            longitude_degres = ?, longitude_minutes = ?, longitude_secondes = ?,
            date_mise_service = ?, autorisation_minee = ?, autorisation_minmidt = ?,
            type_gestion = ?, type_gestion_autre = ?,
            plan_ensemble = ?, contrat_bail = ?, permis_batir = ?,
            certificat_urbanisme = ?, lettre_minepded = ?, plan_masse = ?,
            chef_piste = ?, gerant = ?,
            bouches_incendies = ?, decanteur_separateur = ?, autres_dispositions_securite = ?,
            observations_generales = ?, lieu_etablissement = ?, date_etablissement = ?
            WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['raison_sociale'],
            $data['bp'],
            $data['telephone'],
            $data['fax'],
            $data['email'],
            $data['ville'],
            $data['quartier'],
            $data['rue'],
            $data['region'],
            $data['departement'],
            $data['arrondissement'],
            $data['lieu_dit'],
            $data['latitude'],
            $data['longitude'],
            $data['heure_gmt'],
            $data['heure_locale'],
            $data['latitude_degres'],
            $data['latitude_minutes'],
            $data['latitude_secondes'],
            $data['longitude_degres'],
            $data['longitude_minutes'],
            $data['longitude_secondes'],
            $data['date_mise_service'],
            $data['autorisation_minee'],
            $data['autorisation_minmidt'],
            $data['type_gestion'],
            $data['type_gestion_autre'],
            $data['plan_ensemble'] ? 1 : 0,
            $data['contrat_bail'] ? 1 : 0,
            $data['permis_batir'] ? 1 : 0,
            $data['certificat_urbanisme'] ? 1 : 0,
            $data['lettre_minepded'] ? 1 : 0,
            $data['plan_masse'] ? 1 : 0,
            $data['chef_piste'],
            $data['gerant'],
            $data['bouches_incendies'] ? 1 : 0,
            $data['decanteur_separateur'] ? 1 : 0,
            $data['autres_dispositions_securite'],
            $data['observations_generales'],
            $data['lieu_etablissement'],
            $data['date_etablissement'],
            $fiche_id
        ]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur mise à jour fiche: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les cuves d'une fiche
 */
function getCuvesFiche($fiche_id) {
    global $pdo;

    $sql = "SELECT * FROM fiche_inspection_cuves WHERE fiche_id = ? ORDER BY numero";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetchAll();
}

/**
 * Récupérer les pompes d'une fiche
 */
function getPompesFiche($fiche_id) {
    global $pdo;

    $sql = "SELECT * FROM fiche_inspection_pompes WHERE fiche_id = ? ORDER BY numero";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetchAll();
}

/**
 * Récupérer les distances aux édifices
 */
function getDistancesEdifices($fiche_id) {
    global $pdo;

    $sql = "SELECT * FROM fiche_inspection_distances_edifices
            WHERE fiche_id = ?
            ORDER BY FIELD(direction, 'nord', 'sud', 'est', 'ouest')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetchAll();
}

/**
 * Récupérer les distances aux stations
 */
function getDistancesStations($fiche_id) {
    global $pdo;

    $sql = "SELECT * FROM fiche_inspection_distances_stations
            WHERE fiche_id = ?
            ORDER BY FIELD(direction, 'nord', 'sud', 'est', 'ouest')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetchAll();
}

/**
 * Sauvegarder les cuves
 */
function sauvegarderCuves($fiche_id, $cuves) {
    global $pdo;

    try {
        // Supprimer les cuves existantes
        $sql = "DELETE FROM fiche_inspection_cuves WHERE fiche_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche_id]);

        // Insérer les nouvelles cuves
        $sql = "INSERT INTO fiche_inspection_cuves
                (fiche_id, numero, produit, produit_autre, type_cuve, capacite, nombre)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($cuves as $cuve) {
            if (!empty($cuve['produit'])) {
                $stmt->execute([
                    $fiche_id,
                    $cuve['numero'],
                    $cuve['produit'],
                    $cuve['produit_autre'] ?? null,
                    $cuve['type_cuve'] ?? 'double_enveloppe',
                    $cuve['capacite'] ?? null,
                    $cuve['nombre'] ?? 1
                ]);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Erreur sauvegarde cuves: " . $e->getMessage());
        return false;
    }
}

/**
 * Sauvegarder les pompes
 */
function sauvegarderPompes($fiche_id, $pompes) {
    global $pdo;

    try {
        // Supprimer les pompes existantes
        $sql = "DELETE FROM fiche_inspection_pompes WHERE fiche_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche_id]);

        // Insérer les nouvelles pompes
        $sql = "INSERT INTO fiche_inspection_pompes
                (fiche_id, numero, produit, produit_autre, marque, debit_nominal, nombre)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($pompes as $pompe) {
            if (!empty($pompe['produit'])) {
                $stmt->execute([
                    $fiche_id,
                    $pompe['numero'],
                    $pompe['produit'],
                    $pompe['produit_autre'] ?? null,
                    $pompe['marque'] ?? null,
                    $pompe['debit_nominal'] ?? null,
                    $pompe['nombre'] ?? 1
                ]);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Erreur sauvegarde pompes: " . $e->getMessage());
        return false;
    }
}

/**
 * Sauvegarder les distances aux édifices
 */
function sauvegarderDistancesEdifices($fiche_id, $distances) {
    global $pdo;

    try {
        $sql = "UPDATE fiche_inspection_distances_edifices
                SET description_edifice = ?, distance_metres = ?
                WHERE fiche_id = ? AND direction = ?";
        $stmt = $pdo->prepare($sql);

        foreach ($distances as $direction => $data) {
            $stmt->execute([
                $data['description'] ?? null,
                $data['distance'] ?? null,
                $fiche_id,
                $direction
            ]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Erreur sauvegarde distances édifices: " . $e->getMessage());
        return false;
    }
}

/**
 * Sauvegarder les distances aux stations
 */
function sauvegarderDistancesStations($fiche_id, $distances) {
    global $pdo;

    try {
        $sql = "UPDATE fiche_inspection_distances_stations
                SET nom_station = ?, distance_metres = ?
                WHERE fiche_id = ? AND direction = ?";
        $stmt = $pdo->prepare($sql);

        foreach ($distances as $direction => $data) {
            $stmt->execute([
                $data['nom'] ?? null,
                $data['distance'] ?? null,
                $fiche_id,
                $direction
            ]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Erreur sauvegarde distances stations: " . $e->getMessage());
        return false;
    }
}

/**
 * Valider une fiche d'inspection
 */
function validerFicheInspection($fiche_id) {
    global $pdo;

    $sql = "UPDATE fiches_inspection SET statut = 'validee' WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$fiche_id]);
}

/**
 * Obtenir le label d'une direction
 */
function getDirectionLabel($direction) {
    $labels = [
        'nord' => 'Vers le Nord',
        'sud' => 'Vers le Sud',
        'est' => 'Vers l\'Est',
        'ouest' => 'Vers l\'Ouest'
    ];

    return $labels[$direction] ?? $direction;
}
?>
