<?php
// Fonctions pour l'import de dossiers historiques - SGDI

/**
 * Vérifie si l'utilisateur peut accéder au module d'import historique
 */
function peutImporterHistorique($role) {
    return in_array($role, ['admin', 'admin_systeme', 'chef_service', 'chef_service_sdtd']);
}

/**
 * Valide une ligne du fichier d'import
 */
function validerLigneImport($ligne, $numero_ligne) {
    $erreurs = [];

    // Champs obligatoires
    $champsObligatoires = [
        'type_infrastructure' => 'Type d\'infrastructure',
        'nom_demandeur' => 'Nom du demandeur',
        'region' => 'Région',
        'ville' => 'Ville',
        'date_autorisation' => 'Date d\'autorisation',
        'numero_decision' => 'Numéro de décision'
    ];

    foreach ($champsObligatoires as $champ => $libelle) {
        if (empty($ligne[$champ])) {
            $erreurs[] = "Ligne $numero_ligne : $libelle est obligatoire";
        }
    }

    // Validation du type d'infrastructure
    $typesValides = [
        'Implantation station-service',
        'Reprise station-service',
        'Implantation point consommateur',
        'Reprise point consommateur',
        'Implantation dépôt GPL',
        'Implantation centre emplisseur'
    ];

    if (!empty($ligne['type_infrastructure']) && !in_array($ligne['type_infrastructure'], $typesValides)) {
        $erreurs[] = "Ligne $numero_ligne : Type d'infrastructure invalide. Doit être l'un de : " . implode(', ', $typesValides);
    }

    // Validation de la région
    $regionsValides = [
        'Adamaoua', 'Centre', 'Est', 'Extrême-Nord', 'Littoral',
        'Nord', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Ouest'
    ];

    if (!empty($ligne['region']) && !in_array($ligne['region'], $regionsValides)) {
        $erreurs[] = "Ligne $numero_ligne : Région invalide. Doit être l'une de : " . implode(', ', $regionsValides);
    }

    // Validation de la date
    if (!empty($ligne['date_autorisation'])) {
        $date = DateTime::createFromFormat('d/m/Y', $ligne['date_autorisation']);
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $ligne['date_autorisation']);
        }
        if (!$date) {
            $erreurs[] = "Ligne $numero_ligne : Format de date invalide. Utilisez JJ/MM/AAAA ou AAAA-MM-JJ";
        }
    }

    // Validation des coordonnées GPS (si fournies)
    if (!empty($ligne['latitude'])) {
        if (!is_numeric($ligne['latitude']) || $ligne['latitude'] < -90 || $ligne['latitude'] > 90) {
            $erreurs[] = "Ligne $numero_ligne : Latitude invalide (doit être entre -90 et 90)";
        }
    }

    if (!empty($ligne['longitude'])) {
        if (!is_numeric($ligne['longitude']) || $ligne['longitude'] < -180 || $ligne['longitude'] > 180) {
            $erreurs[] = "Ligne $numero_ligne : Longitude invalide (doit être entre -180 et 180)";
        }
    }

    // Validation spécifique pour les points consommateurs
    if (strpos($ligne['type_infrastructure'], 'point consommateur') !== false) {
        if (empty($ligne['entreprise_beneficiaire'])) {
            $erreurs[] = "Ligne $numero_ligne : Entreprise bénéficiaire obligatoire pour les points consommateurs";
        }
    }

    return $erreurs;
}

/**
 * Génère un numéro de dossier historique unique
 */
function genererNumeroDossierHistorique($type_infrastructure, $region, $annee) {
    global $pdo;

    // Préfixe selon le type
    $prefixes = [
        'Implantation station-service' => 'HIST-SS',
        'Reprise station-service' => 'HIST-SS',
        'Implantation point consommateur' => 'HIST-PC',
        'Reprise point consommateur' => 'HIST-PC',
        'Implantation dépôt GPL' => 'HIST-GPL',
        'Implantation centre emplisseur' => 'HIST-CE'
    ];

    $prefixe = $prefixes[$type_infrastructure] ?? 'HIST';

    // Code région (2 lettres)
    $codesRegion = [
        'Adamaoua' => 'AD',
        'Centre' => 'CE',
        'Est' => 'ES',
        'Extrême-Nord' => 'EN',
        'Littoral' => 'LT',
        'Nord' => 'NO',
        'Nord-Ouest' => 'NW',
        'Ouest' => 'OU',
        'Sud' => 'SU',
        'Sud-Ouest' => 'SW'
    ];

    $codeRegion = $codesRegion[$region] ?? 'XX';

    // Trouver le prochain numéro séquentiel
    $pattern = "$prefixe-$codeRegion-$annee-%";
    $sql = "SELECT COUNT(*) FROM dossiers WHERE numero LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pattern]);
    $count = $stmt->fetchColumn();

    $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

    return "$prefixe-$codeRegion-$annee-$sequence";
}

/**
 * Lit un fichier Excel et retourne les données
 */
function lireExcel($fichier) {
    require_once '../../vendor/autoload.php'; // Pour PhpSpreadsheet si installé

    // Pour l'instant, on utilise une lecture CSV simple
    $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));

    if ($extension === 'csv') {
        return lireCSV($fichier);
    } elseif (in_array($extension, ['xlsx', 'xls'])) {
        // Si PhpSpreadsheet est disponible
        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return lireExcelAvecPhpSpreadsheet($fichier);
        } else {
            throw new Exception("L'extension PhpSpreadsheet n'est pas installée. Utilisez un fichier CSV.");
        }
    }

    throw new Exception("Format de fichier non supporté. Utilisez CSV ou Excel.");
}

/**
 * Lit un fichier CSV
 */
function lireCSV($fichier) {
    $donnees = [];
    $handle = fopen($fichier, 'r');

    if ($handle === false) {
        throw new Exception("Impossible d'ouvrir le fichier CSV");
    }

    // Lire l'en-tête
    $entetes = fgetcsv($handle, 0, ';');
    if ($entetes === false) {
        $entetes = fgetcsv($handle, 0, ',');
        rewind($handle);
        fgetcsv($handle, 0, ',');
    }

    // Nettoyer les en-têtes
    $entetes = array_map('trim', $entetes);

    // Lire les données
    $ligne_num = 1;
    while (($ligne = fgetcsv($handle, 0, ';')) !== false || ($ligne = fgetcsv($handle, 0, ',')) !== false) {
        $ligne_num++;

        if (count($ligne) === count($entetes)) {
            $donnees[] = array_combine($entetes, array_map('trim', $ligne));
        }
    }

    fclose($handle);

    return $donnees;
}

/**
 * Lit un fichier Excel avec PhpSpreadsheet
 */
function lireExcelAvecPhpSpreadsheet($fichier) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fichier);
    $worksheet = $spreadsheet->getActiveSheet();

    $donnees = [];
    $entetes = [];

    foreach ($worksheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $rowData = [];
        foreach ($cellIterator as $cell) {
            $rowData[] = $cell->getValue();
        }

        if (empty($entetes)) {
            $entetes = $rowData;
        } else {
            if (array_filter($rowData)) { // Ignorer les lignes vides
                $donnees[] = array_combine($entetes, $rowData);
            }
        }
    }

    return $donnees;
}

/**
 * Insère un dossier historique dans la base
 */
function insererDossierHistorique($data, $user_id) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Convertir la date
        $date = DateTime::createFromFormat('d/m/Y', $data['date_autorisation']);
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $data['date_autorisation']);
        }
        $date_autorisation = $date->format('Y-m-d');
        $annee = $date->format('Y');

        // Générer le numéro si absent
        if (empty($data['numero_dossier'])) {
            $numero = genererNumeroDossierHistorique(
                $data['type_infrastructure'],
                $data['region'],
                $annee
            );
        } else {
            $numero = $data['numero_dossier'];
        }

        // Mapper le type d'infrastructure au format ENUM
        $type_map = [
            'Implantation station-service' => ['type' => 'station_service', 'sous_type' => 'implantation'],
            'Reprise station-service' => ['type' => 'station_service', 'sous_type' => 'reprise'],
            'Implantation point consommateur' => ['type' => 'point_consommateur', 'sous_type' => 'implantation'],
            'Reprise point consommateur' => ['type' => 'point_consommateur', 'sous_type' => 'reprise'],
            'Implantation dépôt GPL' => ['type' => 'depot_gpl', 'sous_type' => 'implantation'],
            'Implantation centre emplisseur' => ['type' => 'centre_emplisseur', 'sous_type' => 'implantation']
        ];

        if (!isset($type_map[$data['type_infrastructure']])) {
            throw new Exception("Type d'infrastructure non mappé : " . $data['type_infrastructure']);
        }

        $type_infra = $type_map[$data['type_infrastructure']]['type'];
        $sous_type = $type_map[$data['type_infrastructure']]['sous_type'];

        // Insérer le dossier
        $sql = "INSERT INTO dossiers (
                    numero, type_infrastructure, sous_type, statut,
                    nom_demandeur, region, ville,
                    coordonnees_gps,
                    numero_decision_ministerielle,
                    date_decision_ministerielle,
                    lieu_dit,
                    est_historique,
                    importe_par,
                    importe_le,
                    source_import,
                    user_id,
                    date_creation
                ) VALUES (?, ?, ?, 'historique_autorise', ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, ?, NOW())";

        $coords_gps = null;
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $coords_gps = $data['latitude'] . ',' . $data['longitude'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numero,
            $type_infra,
            $sous_type,
            $data['nom_demandeur'],
            $data['region'],
            $data['ville'],
            $coords_gps,
            $data['numero_decision'],
            $date_autorisation,
            $data['observations'] ?? null,
            $user_id,
            $data['source_import'] ?? 'Import manuel',
            $user_id
        ]);

        $dossier_id = $pdo->lastInsertId();

        // Ajouter l'entrée dans l'historique
        $sql = "INSERT INTO historique (dossier_id, action, description, user_id, date_action)
                VALUES (?, 'import', ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $dossier_id,
            "Dossier historique importé - Décision N° " . $data['numero_decision'],
            $user_id
        ]);

        // Si point consommateur, ajouter l'entreprise bénéficiaire
        if (strpos($data['type_infrastructure'], 'point consommateur') !== false && !empty($data['entreprise_beneficiaire'])) {
            // Remplir aussi le champ entreprise_beneficiaire de la table dossiers
            $sql = "UPDATE dossiers SET entreprise_beneficiaire = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['entreprise_beneficiaire'],
                $dossier_id
            ]);

            // Et créer l'entrée dans la table dédiée
            $sql = "INSERT INTO entreprises_beneficiaires (dossier_id, nom, activite)
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dossier_id,
                $data['entreprise_beneficiaire'],
                $data['activite_entreprise'] ?? null
            ]);
        }

        $pdo->commit();

        return [
            'success' => true,
            'dossier_id' => $dossier_id,
            'numero' => $numero
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obtient les statistiques d'import
 */
function getStatistiquesImport() {
    global $pdo;

    $sql = "SELECT
                COUNT(*) as total,
                COUNT(DISTINCT importe_par) as nb_importeurs,
                MIN(importe_le) as premier_import,
                MAX(importe_le) as dernier_import
            FROM dossiers
            WHERE est_historique = 1";

    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtient l'historique des imports
 */
function getHistoriqueImports($limit = 50) {
    global $pdo;

    $sql = "SELECT
                d.importe_le,
                d.importe_par,
                u.nom,
                u.prenom,
                COUNT(*) as nb_dossiers
            FROM dossiers d
            LEFT JOIN users u ON d.importe_par = u.id
            WHERE d.est_historique = 1
            GROUP BY DATE(d.importe_le), d.importe_par
            ORDER BY d.importe_le DESC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
