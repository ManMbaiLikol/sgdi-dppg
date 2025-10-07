<?php
// Fonctions pour la gestion des notes de frais - SGDI MVP

// Obtenir toutes les notes de frais avec filtres
function getNotesAvecFiltres($filters = [], $limit = 20, $offset = 0) {
    global $pdo;

    $conditions = [];
    $params = [];

    // Base query avec jointures
    $base_sql = "SELECT nf.*, d.numero as dossier_numero, d.nom_demandeur,
                        u.nom as createur_nom, u.prenom as createur_prenom
                 FROM notes_frais nf
                 JOIN dossiers d ON nf.dossier_id = d.id
                 JOIN users u ON nf.user_id = u.id";

    // Filtrage par recherche
    if (!empty($filters['search'])) {
        $conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR nf.description LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }

    // Filtrage par statut
    if (!empty($filters['statut'])) {
        $conditions[] = "nf.statut = ?";
        $params[] = $filters['statut'];
    }

    // Filtrage par dossier
    if (!empty($filters['dossier_id'])) {
        $conditions[] = "nf.dossier_id = ?";
        $params[] = $filters['dossier_id'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "$base_sql $where_clause ORDER BY nf.date_creation DESC LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Compter les notes de frais avec filtres
function countNotesAvecFiltres($filters = []) {
    global $pdo;

    $conditions = [];
    $params = [];

    $base_sql = "SELECT COUNT(*)
                 FROM notes_frais nf
                 JOIN dossiers d ON nf.dossier_id = d.id";

    // Même logique de filtrage que getNotesAvecFiltres
    if (!empty($filters['search'])) {
        $conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR nf.description LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }

    if (!empty($filters['statut'])) {
        $conditions[] = "nf.statut = ?";
        $params[] = $filters['statut'];
    }

    if (!empty($filters['dossier_id'])) {
        $conditions[] = "nf.dossier_id = ?";
        $params[] = $filters['dossier_id'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "$base_sql $where_clause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Créer une note de frais
function creerNoteFrais($data) {
    global $pdo;

    try {
        $sql = "INSERT INTO notes_frais (dossier_id, description, montant_base,
                                       montant_frais_deplacement, montant_total,
                                       statut, user_id, date_creation)
                VALUES (?, ?, ?, ?, ?, 'en_attente', ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['dossier_id'],
            $data['description'],
            $data['montant_base'],
            $data['montant_frais_deplacement'] ?? 0,
            $data['montant_total'],
            $data['user_id']
        ]);

        if ($result) {
            return $pdo->lastInsertId();
        }
    } catch (Exception $e) {
        error_log("Erreur création note de frais: " . $e->getMessage());
        return false;
    }

    return false;
}

// Obtenir une note de frais par ID
function getNoteFreaisById($id) {
    global $pdo;

    $sql = "SELECT nf.*, d.numero as dossier_numero, d.nom_demandeur,
                   d.type_infrastructure, d.sous_type, d.region, d.ville,
                   u.nom as createur_nom, u.prenom as createur_prenom
            FROM notes_frais nf
            JOIN dossiers d ON nf.dossier_id = d.id
            JOIN users u ON nf.user_id = u.id
            WHERE nf.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Obtenir la note de frais d'un dossier
function getNoteFreaisParDossier($dossier_id) {
    global $pdo;

    try {
        $sql = "SELECT nf.*, d.numero as dossier_numero, d.nom_demandeur,
                       d.type_infrastructure, d.sous_type, d.region, d.ville,
                       u.nom as createur_nom, u.prenom as createur_prenom
                FROM notes_frais nf
                JOIN dossiers d ON nf.dossier_id = d.id
                JOIN users u ON nf.user_id = u.id
                WHERE nf.dossier_id = ?
                ORDER BY nf.date_creation DESC
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id]);
        return $stmt->fetch();

    } catch (Exception $e) {
        error_log("Erreur récupération note de frais par dossier: " . $e->getMessage());
        return null;
    }
}

// Mettre à jour une note de frais
function mettreAJourNoteFrais($id, $data) {
    global $pdo;

    try {
        $fields = [];
        $params = [];

        $allowed_fields = ['description', 'montant_base', 'montant_frais_deplacement', 'montant_total', 'statut'];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE notes_frais SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);

    } catch (Exception $e) {
        error_log("Erreur mise à jour note de frais: " . $e->getMessage());
        return false;
    }
}

// Calculer le montant total automatiquement
function calculerMontantTotal($montant_base, $montant_frais_deplacement = 0) {
    return floatval($montant_base) + floatval($montant_frais_deplacement);
}

// Obtenir les statistiques des notes de frais
function getStatistiquesNotesFrais() {
    global $pdo;

    $stats = [
        'total' => 0,
        'en_attente' => 0,
        'validee' => 0,
        'payee' => 0,
        'montant_total' => 0
    ];

    try {
        // Vérifier si la table existe
        $tables_check = $pdo->query("SHOW TABLES LIKE 'notes_frais'");
        if ($tables_check->rowCount() == 0) {
            return $stats; // Table n'existe pas encore
        }

        // Total
        $stmt = $pdo->query("SELECT COUNT(*) FROM notes_frais");
        $stats['total'] = $stmt->fetchColumn();

        // Par statut
        $stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM notes_frais GROUP BY statut");
        while ($row = $stmt->fetch()) {
            $stats[$row['statut']] = $row['count'];
        }

        // Montant total
        $stmt = $pdo->query("SELECT SUM(montant_total) FROM notes_frais WHERE statut != 'annulee'");
        $stats['montant_total'] = $stmt->fetchColumn() ?: 0;

    } catch (Exception $e) {
        error_log("Erreur récupération stats notes frais: " . $e->getMessage());
    }

    return $stats;
}

// Obtenir les dossiers éligibles pour une note de frais
function getDossiersEligiblesNoteFrais() {
    global $pdo;

    try {
        // Dossiers qui n'ont pas encore de note de frais (commission pas obligatoire)
        $sql = "SELECT d.id, d.numero, d.nom_demandeur, d.type_infrastructure, d.region, d.ville
                FROM dossiers d
                LEFT JOIN notes_frais nf ON d.id = nf.dossier_id
                WHERE nf.id IS NULL
                AND d.statut IN ('brouillon', 'en_cours')
                ORDER BY d.date_creation DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur récupération dossiers éligibles: " . $e->getMessage());
        return [];
    }
}

// Vérifier si l'utilisateur peut voir une note de frais
function peutVoirNoteFrais($note, $user_role, $user_id) {
    // Admin peut tout voir
    if ($user_role === 'admin') return true;
    // Chef service peut voir toutes les notes
    if ($user_role === 'chef_service') return true;
    // Billeteur peut voir les notes validées
    if ($user_role === 'billeteur') return true;
    // Directeur peut voir toutes les notes
    if ($user_role === 'directeur') return true;
    // L'utilisateur peut voir ses propres notes
    if ($note['user_id'] == $user_id) return true;

    return false;
}

// Vérifier si l'utilisateur peut modifier la note de frais
function peutModifierNoteFrais($note, $user_role, $user_id) {
    // Admin peut tout modifier
    if ($user_role === 'admin') return true;

    // Chef service peut modifier ses notes non payées
    if ($user_role === 'chef_service' && $note['user_id'] == $user_id && $note['statut'] !== 'payee') {
        return true;
    }

    // Billeteur peut modifier le statut pour marquer comme payée
    if ($user_role === 'billeteur' && $note['statut'] === 'validee') {
        return true;
    }

    return false;
}

// Obtenir le libellé d'un statut de note de frais
function getStatutNoteFraisLabel($statut) {
    $statuts = [
        'en_attente' => 'En attente',
        'validee' => 'Validée',
        'payee' => 'Payée',
        'annulee' => 'Annulée'
    ];

    return $statuts[$statut] ?? ucfirst($statut);
}

// Obtenir les types de frais prédéfinis
function getTypesFraisPredefinies() {
    return [
        'frais_dossier' => [
            'label' => 'Frais de dossier',
            'montant' => 25000,
            'description' => 'Frais de constitution et traitement du dossier'
        ],
        'frais_inspection' => [
            'label' => 'Frais d\'inspection',
            'montant' => 50000,
            'description' => 'Frais de déplacement et inspection sur site'
        ],
        'frais_expertise' => [
            'label' => 'Frais d\'expertise technique',
            'montant' => 75000,
            'description' => 'Expertise technique spécialisée'
        ],
        'frais_commission' => [
            'label' => 'Frais de commission',
            'montant' => 30000,
            'description' => 'Frais de fonctionnement de la commission'
        ]
    ];
}
?>