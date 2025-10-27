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
 * Note: Cette fonction doit être appelée dans une transaction gérée par l'appelant
 */
function mettreAJourFicheInspection($fiche_id, $data) {
    global $pdo;

    try {
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
            certificat_urbanisme = ?, lettre_minepded = ?, plan_masse = ?, lettre_desistement = ?,
            chef_piste = ?, gerant = ?,
            bouches_incendies = ?, decanteur_separateur = ?, autres_dispositions_securite = ?,
            observations_generales = ?, recommandations = ?, lieu_etablissement = ?, date_etablissement = ?,
            numero_contrat_approvisionnement = ?, societe_contractante = ?,
            besoins_mensuels_litres = ?, parc_engin = ?, systeme_recuperation_huiles = ?,
            nombre_personnels = ?, superficie_site = ?, batiments_site = ?,
            infra_eau = ?, infra_electricite = ?,
            reseau_camtel = ?, reseau_mtn = ?, reseau_orange = ?, reseau_nexttel = ?
            WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
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
            // Convertir les chaînes vides en NULL pour les champs numériques GPS
            $data['latitude_degres'] !== '' ? $data['latitude_degres'] : null,
            $data['latitude_minutes'] !== '' ? $data['latitude_minutes'] : null,
            $data['latitude_secondes'] !== '' ? $data['latitude_secondes'] : null,
            $data['longitude_degres'] !== '' ? $data['longitude_degres'] : null,
            $data['longitude_minutes'] !== '' ? $data['longitude_minutes'] : null,
            $data['longitude_secondes'] !== '' ? $data['longitude_secondes'] : null,
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
            $data['lettre_desistement'] ? 1 : 0,
            $data['chef_piste'],
            $data['gerant'],
            $data['bouches_incendies'] ? 1 : 0,
            $data['decanteur_separateur'] ? 1 : 0,
            $data['autres_dispositions_securite'],
            $data['observations_generales'],
            $data['recommandations'] ?? '',
            $data['lieu_etablissement'],
            $data['date_etablissement'],
            // Champs spécifiques aux points consommateurs
            $data['numero_contrat_approvisionnement'] ?? null,
            $data['societe_contractante'] ?? null,
            // Convertir les chaînes vides en NULL pour les champs numériques
            !empty($data['besoins_mensuels_litres']) ? $data['besoins_mensuels_litres'] : null,
            $data['parc_engin'] ?? null,
            $data['systeme_recuperation_huiles'] ?? null,
            !empty($data['nombre_personnels']) ? $data['nombre_personnels'] : null,
            !empty($data['superficie_site']) ? $data['superficie_site'] : null,
            $data['batiments_site'] ?? null,
            $data['infra_eau'] ?? 0,
            $data['infra_electricite'] ?? 0,
            $data['reseau_camtel'] ?? 0,
            $data['reseau_mtn'] ?? 0,
            $data['reseau_orange'] ?? 0,
            $data['reseau_nexttel'] ?? 0,
            $fiche_id
        ]);

        return $result;

    } catch (Exception $e) {
        error_log("Erreur mise à jour fiche: " . $e->getMessage());
        // DEBUG: Afficher l'erreur SQL complète
        if (isset($_GET['debug'])) {
            echo "<pre style='background: #ffebee; padding: 10px; border: 1px solid red;'>";
            echo "ERREUR SQL:\n";
            echo $e->getMessage() . "\n\n";
            echo "Stack trace:\n";
            echo $e->getTraceAsString();
            echo "</pre>";
        }
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
                !empty($data['distance']) ? $data['distance'] : null,
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
                !empty($data['distance']) ? $data['distance'] : null,
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
 * Vérifier la complétude d'une fiche d'inspection
 * Retourne un tableau d'erreurs (vide si tout est OK)
 */
function validerCompletudeFiche($fiche_id) {
    global $pdo;

    $erreurs = [];

    // Récupérer la fiche
    $sql = "SELECT * FROM fiches_inspection WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    $fiche = $stmt->fetch();

    if (!$fiche) {
        return ["Fiche introuvable"];
    }

    // Champs obligatoires section 1
    if (empty($fiche['raison_sociale'])) {
        $erreurs[] = "Raison sociale manquante";
    }
    if (empty($fiche['ville'])) {
        $erreurs[] = "Ville manquante";
    }

    // Coordonnées GPS obligatoires
    if (empty($fiche['latitude']) || empty($fiche['longitude'])) {
        $erreurs[] = "Coordonnées GPS (latitude/longitude) manquantes";
    }

    // Au moins une cuve
    $sql = "SELECT COUNT(*) FROM fiche_inspection_cuves WHERE fiche_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    if ($stmt->fetchColumn() == 0) {
        $erreurs[] = "Aucune cuve renseignée (minimum 1 requise)";
    }

    // Au moins une pompe
    $sql = "SELECT COUNT(*) FROM fiche_inspection_pompes WHERE fiche_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    if ($stmt->fetchColumn() == 0) {
        $erreurs[] = "Aucune pompe renseignée (minimum 1 requise)";
    }

    // Date d'établissement
    if (empty($fiche['date_etablissement'])) {
        $erreurs[] = "Date d'établissement de la fiche manquante";
    }

    return $erreurs;
}

/**
 * Valider une fiche d'inspection (workflow complet)
 */
function validerFicheInspection($fiche_id, $user_id) {
    global $pdo;

    try {
        // Note: La transaction est gérée par l'appelant (edit.php)

        // 1. Vérifier complétude
        $erreurs = validerCompletudeFiche($fiche_id);
        if (!empty($erreurs)) {
            return [
                'success' => false,
                'erreurs' => $erreurs
            ];
        }

        // 2. Récupérer la fiche avec infos dossier
        $sql = "SELECT fi.*, d.numero as dossier_numero, d.id as dossier_id
                FROM fiches_inspection fi
                INNER JOIN dossiers d ON fi.dossier_id = d.id
                WHERE fi.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche_id]);
        $fiche = $stmt->fetch();

        if (!$fiche) {
            return ['success' => false, 'erreurs' => ["Fiche introuvable"]];
        }

        // 3. Mettre à jour statut fiche
        $sql = "UPDATE fiches_inspection
                SET statut = 'validee',
                    date_validation = NOW(),
                    valideur_id = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $fiche_id]);

        // 4. Mettre à jour statut dossier
        $sql = "UPDATE dossiers
                SET statut = 'inspecte'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 5. Créer notification pour le chef de commission
        $sql = "SELECT c.chef_commission_id, u.nom, u.prenom
                FROM commissions c
                LEFT JOIN users u ON c.chef_commission_id = u.id
                WHERE c.dossier_id = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);
        $commission = $stmt->fetch();

        if ($commission && $commission['chef_commission_id']) {
            // Créer notification (si table notifications existe)
            // Note: Vérifier d'abord si la colonne 'lien' existe
            try {
                $sql = "INSERT INTO notifications (user_id, type, message, date_creation)
                        VALUES (?, 'inspection_validee', ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $message = "Nouvelle inspection à valider pour le dossier N° " . $fiche['dossier_numero'];
                $stmt->execute([$commission['chef_commission_id'], $message]);
            } catch (Exception $e) {
                // Table notifications n'existe peut-être pas encore ou structure différente
                error_log("Notification non envoyée: " . $e->getMessage());
            }
        }

        // 6. Historique dossier
        logAction(
            $pdo,
            $fiche['dossier_id'],
            'inspection_validee',
            "Fiche d'inspection validée par l'inspecteur",
            $user_id
        );

        return [
            'success' => true,
            'message' => "Fiche d'inspection validée avec succès"
        ];

    } catch (Exception $e) {
        error_log("Erreur validation fiche: " . $e->getMessage());
        return [
            'success' => false,
            'erreurs' => ["Erreur lors de la validation: " . $e->getMessage()]
        ];
    }
}

/**
 * Récupérer une fiche avec toutes ses informations
 */
function getFicheInspectionById($fiche_id) {
    global $pdo;

    $sql = "SELECT fi.*, d.numero as dossier_numero, d.id as dossier_id
            FROM fiches_inspection fi
            INNER JOIN dossiers d ON fi.dossier_id = d.id
            WHERE fi.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetch();
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
