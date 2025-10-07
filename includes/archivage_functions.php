<?php
/**
 * Fonctions d'archivage et de purge RGPD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/backup_functions.php';

/**
 * Archiver un dossier
 *
 * @param int $dossier_id ID du dossier
 * @param string $raison Raison de l'archivage
 * @param int|null $user_id ID de l'archiveur
 * @return array Résultat
 */
function archiverDossier($dossier_id, $raison = 'manuel', $user_id = null) {
    global $conn;

    try {
        $conn->beginTransaction();

        // Récupérer toutes les données du dossier
        $stmt = $conn->prepare("SELECT * FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier_id]);
        $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dossier) {
            throw new Exception("Dossier introuvable");
        }

        // Récupérer les documents associés
        $stmt = $conn->prepare("
            SELECT d.*, td.nom as type_nom
            FROM documents d
            LEFT JOIN types_document td ON d.type_document_id = td.id
            WHERE d.dossier_id = ?
        ");
        $stmt->execute([$dossier_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer l'historique
        $stmt = $conn->prepare("
            SELECT h.*, s.libelle as statut_nom, CONCAT(u.prenom, ' ', u.nom) as auteur_nom
            FROM historique_dossier h
            LEFT JOIN statuts_dossier s ON h.statut_id = s.id
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.dossier_id = ?
            ORDER BY h.created_at
        ");
        $stmt->execute([$dossier_id]);
        $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les paiements
        $stmt = $conn->prepare("SELECT * FROM paiements WHERE dossier_id = ?");
        $stmt->execute([$dossier_id]);
        $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les inspections
        $stmt = $conn->prepare("SELECT * FROM inspections WHERE dossier_id = ?");
        $stmt->execute([$dossier_id]);
        $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Préparer les données archivées
        $donnees_archivees = [
            'dossier' => $dossier,
            'historique' => $historique,
            'paiements' => $paiements,
            'inspections' => $inspections,
            'stats' => [
                'nb_documents' => count($documents),
                'nb_historique' => count($historique),
                'nb_paiements' => count($paiements),
                'nb_inspections' => count($inspections)
            ]
        ];

        // Préparer les infos des documents (sans le contenu binaire)
        $documents_archives = [];
        foreach ($documents as $doc) {
            $documents_archives[] = [
                'id' => $doc['id'],
                'nom' => $doc['nom_fichier'],
                'type' => $doc['type_nom'],
                'taille' => $doc['taille_octets'],
                'chemin' => $doc['chemin_fichier'],
                'date_upload' => $doc['created_at']
            ];
        }

        // Calculer la date de destruction prévue selon RGPD
        $retention_annees = getParametre('rgpd_retention_annees', 5);
        $date_destruction = date('Y-m-d', strtotime("+{$retention_annees} years"));

        // Créer l'archive
        $stmt = $conn->prepare("
            INSERT INTO archives_dossiers
            (dossier_id, numero_dossier, raison_archivage, donnees_archivees,
             documents_archives, archiveur_id, date_destruction_prevue)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dossier_id,
            $dossier['numero_dossier'],
            $raison,
            json_encode($donnees_archivees, JSON_UNESCAPED_UNICODE),
            json_encode($documents_archives, JSON_UNESCAPED_UNICODE),
            $user_id,
            $date_destruction
        ]);

        $archive_id = $conn->lastInsertId();

        // Marquer le dossier comme archivé
        $stmt = $conn->prepare("UPDATE dossiers SET archive = 1, date_archive = NOW() WHERE id = ?");
        $stmt->execute([$dossier_id]);

        // Logger l'action
        logActivite($user_id, 'archivage', "Dossier {$dossier['numero_dossier']} archivé", [
            'dossier_id' => $dossier_id,
            'raison' => $raison,
            'archive_id' => $archive_id
        ]);

        $conn->commit();

        return [
            'success' => true,
            'archive_id' => $archive_id,
            'numero_dossier' => $dossier['numero_dossier'],
            'date_destruction_prevue' => $date_destruction
        ];

    } catch (Exception $e) {
        $conn->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Désarchiver un dossier
 *
 * @param int $archive_id ID de l'archive
 * @param int|null $user_id ID de l'utilisateur
 * @return array Résultat
 */
function desarchiverDossier($archive_id, $user_id = null) {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM archives_dossiers WHERE id = ?");
        $stmt->execute([$archive_id]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$archive) {
            throw new Exception("Archive introuvable");
        }

        // Vérifier que le dossier existe toujours
        $stmt = $conn->prepare("SELECT id FROM dossiers WHERE id = ?");
        $stmt->execute([$archive['dossier_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Le dossier original n'existe plus");
        }

        // Réactiver le dossier
        $stmt = $conn->prepare("UPDATE dossiers SET archive = 0, date_archive = NULL WHERE id = ?");
        $stmt->execute([$archive['dossier_id']]);

        // Supprimer l'archive
        $stmt = $conn->prepare("DELETE FROM archives_dossiers WHERE id = ?");
        $stmt->execute([$archive_id]);

        // Logger
        logActivite($user_id, 'desarchivage', "Dossier {$archive['numero_dossier']} désarchivé", [
            'archive_id' => $archive_id,
            'dossier_id' => $archive['dossier_id']
        ]);

        return [
            'success' => true,
            'numero_dossier' => $archive['numero_dossier']
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Détruire définitivement une archive (RGPD)
 *
 * @param int $archive_id ID de l'archive
 * @param int|null $user_id ID de l'utilisateur
 * @return array Résultat
 */
function detruireArchive($archive_id, $user_id = null) {
    global $conn;

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * FROM archives_dossiers WHERE id = ?");
        $stmt->execute([$archive_id]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$archive) {
            throw new Exception("Archive introuvable");
        }

        // Récupérer les chemins des documents
        $documents = json_decode($archive['documents_archives'], true);

        // Supprimer les fichiers physiques
        $fichiers_supprimes = 0;
        foreach ($documents as $doc) {
            if (file_exists($doc['chemin'])) {
                unlink($doc['chemin']);
                $fichiers_supprimes++;
            }
        }

        // Supprimer le dossier de la base
        $stmt = $conn->prepare("DELETE FROM dossiers WHERE id = ?");
        $stmt->execute([$archive['dossier_id']]);

        // Marquer l'archive comme détruite (on garde la trace)
        $stmt = $conn->prepare("
            UPDATE archives_dossiers
            SET detruit = 1, date_destruction = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$archive_id]);

        // Logger
        logActivite($user_id, 'destruction_rgpd', "Archive {$archive['numero_dossier']} détruite (RGPD)", [
            'archive_id' => $archive_id,
            'fichiers_supprimes' => $fichiers_supprimes
        ]);

        $conn->commit();

        return [
            'success' => true,
            'numero_dossier' => $archive['numero_dossier'],
            'fichiers_supprimes' => $fichiers_supprimes
        ];

    } catch (Exception $e) {
        $conn->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Archivage automatique des dossiers anciens
 * Tâche CRON à exécuter périodiquement
 */
function cronAutoArchivage() {
    global $conn;

    $enabled = getParametre('archivage_auto_enabled', false);

    if (!$enabled) {
        echo "Archivage automatique désactivé\n";
        return;
    }

    $delai_mois = getParametre('archivage_delai_mois', 24);

    // Trouver les dossiers éligibles à l'archivage
    // - Statut final (approuvé ou rejeté)
    // - Date de dernière modification > délai
    $stmt = $conn->prepare("
        SELECT d.id, d.numero_dossier
        FROM dossiers d
        INNER JOIN statuts_dossier s ON d.statut_id = s.id
        WHERE d.archive = 0
            AND s.code IN ('approuve', 'rejete')
            AND d.updated_at < DATE_SUB(NOW(), INTERVAL ? MONTH)
        LIMIT 100
    ");
    $stmt->execute([$delai_mois]);
    $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Dossiers éligibles à l'archivage: " . count($dossiers) . "\n";

    $success = 0;
    $errors = 0;

    foreach ($dossiers as $dossier) {
        $result = archiverDossier($dossier['id'], 'anciennete', null);

        if ($result['success']) {
            echo "✅ {$dossier['numero_dossier']} archivé\n";
            $success++;
        } else {
            echo "❌ {$dossier['numero_dossier']}: {$result['error']}\n";
            $errors++;
        }
    }

    echo "\nRésumé: {$success} archivés, {$errors} erreurs\n";

    return [
        'total' => count($dossiers),
        'success' => $success,
        'errors' => $errors
    ];
}

/**
 * Purge RGPD automatique
 * Détruit les archives dont la date de destruction est dépassée
 */
function cronPurgeRGPD() {
    global $conn;

    $enabled = getParametre('rgpd_purge_enabled', false);

    if (!$enabled) {
        echo "Purge RGPD désactivée\n";
        return;
    }

    // Trouver les archives à détruire
    $stmt = $conn->query("
        SELECT id, numero_dossier, date_destruction_prevue
        FROM archives_dossiers
        WHERE detruit = 0
            AND date_destruction_prevue <= CURDATE()
        LIMIT 50
    ");
    $archives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Archives à détruire (RGPD): " . count($archives) . "\n";

    $success = 0;
    $errors = 0;

    foreach ($archives as $archive) {
        $result = detruireArchive($archive['id'], null);

        if ($result['success']) {
            echo "✅ {$archive['numero_dossier']} détruit\n";
            $success++;
        } else {
            echo "❌ {$archive['numero_dossier']}: {$result['error']}\n";
            $errors++;
        }
    }

    echo "\nRésumé purge RGPD: {$success} détruits, {$errors} erreurs\n";

    return [
        'total' => count($archives),
        'success' => $success,
        'errors' => $errors
    ];
}

/**
 * Lister les archives
 *
 * @param array $filters Filtres optionnels
 * @return array Liste des archives
 */
function listerArchives($filters = []) {
    global $conn;

    $where = ["1=1"];
    $params = [];

    if (isset($filters['raison'])) {
        $where[] = "raison_archivage = ?";
        $params[] = $filters['raison'];
    }

    if (isset($filters['detruit'])) {
        $where[] = "detruit = ?";
        $params[] = $filters['detruit'] ? 1 : 0;
    }

    if (isset($filters['search'])) {
        $where[] = "numero_dossier LIKE ?";
        $params[] = '%' . $filters['search'] . '%';
    }

    $sql = "
        SELECT
            a.*,
            CONCAT(u.prenom, ' ', u.nom) as archiveur_nom,
            DATEDIFF(a.date_destruction_prevue, CURDATE()) as jours_avant_destruction
        FROM archives_dossiers a
        LEFT JOIN users u ON a.archiveur_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.date_archivage DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Logger une activité système
 *
 * @param int|null $user_id ID utilisateur
 * @param string $action Type d'action
 * @param string $description Description
 * @param array $donnees Données supplémentaires
 */
function logActivite($user_id, $action, $description, $donnees = []) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO logs_activite
        (user_id, action, description, donnees_json, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $action,
        $description,
        json_encode($donnees, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
