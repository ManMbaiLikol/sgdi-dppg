<?php
// Fonctions pour la gestion des paiements - SGDI MVP

// Obtenir tous les paiements avec filtres
function getPaiementsAvecFiltres($filters = [], $limit = 20, $offset = 0) {
    global $pdo;

    $conditions = [];
    $params = [];

    // Base query avec jointures
    $base_sql = "SELECT p.*, d.numero as dossier_numero, d.nom_demandeur,
                        d.type_infrastructure, d.sous_type, d.region, d.ville,
                        u.nom as billeteur_nom, u.prenom as billeteur_prenom
                 FROM paiements p
                 JOIN dossiers d ON p.dossier_id = d.id
                 LEFT JOIN users u ON p.billeteur_id = u.id";

    // Filtrage par recherche
    if (!empty($filters['search'])) {
        $conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR p.reference_paiement LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }

    // Filtrage par mode de paiement
    if (!empty($filters['mode_paiement'])) {
        $conditions[] = "p.mode_paiement = ?";
        $params[] = $filters['mode_paiement'];
    }

    // Filtrage par période
    if (!empty($filters['date_debut'])) {
        $conditions[] = "p.date_paiement >= ?";
        $params[] = $filters['date_debut'];
    }

    if (!empty($filters['date_fin'])) {
        $conditions[] = "p.date_paiement <= ?";
        $params[] = $filters['date_fin'];
    }

    // Filtrage par dossier
    if (!empty($filters['dossier_id'])) {
        $conditions[] = "p.dossier_id = ?";
        $params[] = $filters['dossier_id'];
    }

    // Filtrage par billeteur (pour historique personnel)
    if (!empty($filters['billeteur_id'])) {
        $conditions[] = "p.billeteur_id = ?";
        $params[] = $filters['billeteur_id'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "$base_sql $where_clause ORDER BY p.date_paiement DESC, p.date_enregistrement DESC LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Compter les paiements avec filtres
function countPaiementsAvecFiltres($filters = []) {
    global $pdo;

    $conditions = [];
    $params = [];

    $base_sql = "SELECT COUNT(*)
                 FROM paiements p
                 JOIN dossiers d ON p.dossier_id = d.id";

    // Même logique de filtrage que getPaiementsAvecFiltres
    if (!empty($filters['search'])) {
        $conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR p.reference_paiement LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }

    if (!empty($filters['mode_paiement'])) {
        $conditions[] = "p.mode_paiement = ?";
        $params[] = $filters['mode_paiement'];
    }

    if (!empty($filters['date_debut'])) {
        $conditions[] = "p.date_paiement >= ?";
        $params[] = $filters['date_debut'];
    }

    if (!empty($filters['date_fin'])) {
        $conditions[] = "p.date_paiement <= ?";
        $params[] = $filters['date_fin'];
    }

    if (!empty($filters['dossier_id'])) {
        $conditions[] = "p.dossier_id = ?";
        $params[] = $filters['dossier_id'];
    }

    if (!empty($filters['billeteur_id'])) {
        $conditions[] = "p.billeteur_id = ?";
        $params[] = $filters['billeteur_id'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "$base_sql $where_clause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Obtenir un paiement par ID
function getPaiementById($id) {
    global $pdo;

    $sql = "SELECT p.*, d.numero as dossier_numero, d.nom_demandeur,
                   d.type_infrastructure, d.sous_type, d.region, d.ville, d.statut as dossier_statut,
                   u.nom as billeteur_nom, u.prenom as billeteur_prenom
            FROM paiements p
            JOIN dossiers d ON p.dossier_id = d.id
            LEFT JOIN users u ON p.billeteur_id = u.id
            WHERE p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Obtenir les statistiques des paiements
function getStatistiquesPaiements($filters = []) {
    global $pdo;

    $stats = [
        'paiements_valides' => 0,
        'paiements_attente' => 0,
        'dossiers_rejetes' => 0,
        'par_mode' => [],
        'par_mois' => [],
        'par_type_dossier' => []
    ];

    try {
        // 1. Paiements validés (avec date de paiement)
        $sql = "SELECT COUNT(*) as count FROM paiements WHERE date_paiement IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['paiements_valides'] = $result['count'] ?? 0;

        // 2. Dossiers en attente de paiement (statut en_cours)
        $sql = "SELECT COUNT(*) as count FROM dossiers WHERE statut = 'en_cours'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['paiements_attente'] = $result['count'] ?? 0;

        // 3. Dossiers rejetés faute de paiement (statut rejete avec raison paiement)
        $sql = "SELECT COUNT(*) as count FROM dossiers
                WHERE statut = 'rejete'
                AND (description LIKE '%paiement%' OR observations LIKE '%paiement%')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['dossiers_rejetes'] = $result['count'] ?? 0;

        // 4. Évolution des paiements par mois (derniers 12 mois)
        $sql = "SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois,
                       COUNT(*) as count, SUM(montant) as montant
                FROM paiements
                WHERE date_paiement >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                AND date_paiement IS NOT NULL
                GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
                ORDER BY mois ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $stats['par_mois'][$row['mois']] = [
                'count' => $row['count'],
                'montant' => $row['montant']
            ];
        }

        // 5. Montants par type de dossier
        $sql = "SELECT d.type_infrastructure, COUNT(p.id) as count, SUM(p.montant) as montant
                FROM dossiers d
                LEFT JOIN paiements p ON d.id = p.dossier_id AND p.date_paiement IS NOT NULL
                WHERE p.id IS NOT NULL
                GROUP BY d.type_infrastructure
                ORDER BY montant DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $stats['par_type_dossier'][$row['type_infrastructure']] = [
                'count' => $row['count'],
                'montant' => $row['montant']
            ];
        }

        // 6. Par mode de paiement
        $sql = "SELECT mode_paiement, COUNT(*) as count, SUM(montant) as montant
                FROM paiements
                WHERE date_paiement IS NOT NULL
                GROUP BY mode_paiement";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $stats['par_mode'][$row['mode_paiement']] = [
                'count' => $row['count'],
                'montant' => $row['montant']
            ];
        }

    } catch (Exception $e) {
        error_log("Erreur récupération stats paiements: " . $e->getMessage());
    }

    return $stats;
}

// Obtenir les modes de paiement disponibles
function getModesPaiement() {
    return [
        'especes' => [
            'label' => 'Espèces',
            'icon' => 'fas fa-money-bill-wave',
            'color' => 'success'
        ],
        'cheque' => [
            'label' => 'Chèque',
            'icon' => 'fas fa-money-check',
            'color' => 'primary'
        ],
        'virement' => [
            'label' => 'Virement bancaire',
            'icon' => 'fas fa-university',
            'color' => 'info'
        ]
    ];
}

// Vérifier si l'utilisateur peut voir les paiements
function peutVoirPaiements($user_role) {
    return in_array($user_role, ['admin', 'chef_service', 'billeteur', 'directeur']);
}

// Obtenir les paiements récents
function getPaiementsRecents($limit = 10) {
    global $pdo;

    try {
        $sql = "SELECT p.*, d.numero as dossier_numero, d.nom_demandeur,
                       u.nom as billeteur_nom, u.prenom as billeteur_prenom
                FROM paiements p
                JOIN dossiers d ON p.dossier_id = d.id
                LEFT JOIN users u ON p.billeteur_id = u.id
                ORDER BY p.date_enregistrement DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur récupération paiements récents: " . $e->getMessage());
        return [];
    }
}

// Formater le montant avec devise
function formatMontantPaiement($montant, $devise = 'XAF') {
    if ($devise === 'XAF' || $devise === 'FCFA') {
        return number_format($montant, 0, ',', ' ') . ' F CFA';
    }
    return number_format($montant, 2, ',', ' ') . ' ' . $devise;
}

// Obtenir la couleur CSS pour un mode de paiement
function getModePaiementColor($mode) {
    $colors = [
        'especes' => 'success',
        'cheque' => 'primary',
        'virement' => 'info'
    ];

    return $colors[$mode] ?? 'secondary';
}

// Obtenir l'icône pour un mode de paiement
function getModePaiementIcon($mode) {
    $icons = [
        'especes' => 'fas fa-money-bill-wave',
        'cheque' => 'fas fa-money-check',
        'virement' => 'fas fa-university'
    ];

    return $icons[$mode] ?? 'fas fa-credit-card';
}

// Obtenir les dossiers en attente de paiement depuis plus de 30 jours
function getDossiersEnRetardPaiement() {
    global $pdo;

    try {
        $sql = "SELECT d.*, DATEDIFF(CURDATE(), d.date_creation) as jours_attente
                FROM dossiers d
                WHERE d.statut = 'en_cours'
                AND DATEDIFF(CURDATE(), d.date_creation) > 30
                AND NOT EXISTS (
                    SELECT 1 FROM paiements p
                    WHERE p.dossier_id = d.id
                    AND p.date_paiement IS NOT NULL
                )
                ORDER BY d.date_creation ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur récupération dossiers en retard: " . $e->getMessage());
        return [];
    }
}

// Compter les dossiers en attente de paiement depuis plus de 30 jours
function countDossiersEnRetardPaiement() {
    global $pdo;

    try {
        $sql = "SELECT COUNT(*) as count
                FROM dossiers d
                WHERE d.statut = 'en_cours'
                AND DATEDIFF(CURDATE(), d.date_creation) > 30
                AND NOT EXISTS (
                    SELECT 1 FROM paiements p
                    WHERE p.dossier_id = d.id
                    AND p.date_paiement IS NOT NULL
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;

    } catch (Exception $e) {
        error_log("Erreur comptage dossiers en retard: " . $e->getMessage());
        return 0;
    }
}

// Rechercher des paiements par référence ou dossier
function rechercherPaiements($terme_recherche) {
    global $pdo;

    try {
        $search = '%' . $terme_recherche . '%';

        $sql = "SELECT p.*, d.numero as dossier_numero, d.nom_demandeur
                FROM paiements p
                JOIN dossiers d ON p.dossier_id = d.id
                WHERE d.numero LIKE ?
                OR d.nom_demandeur LIKE ?
                OR p.reference_paiement LIKE ?
                ORDER BY p.date_paiement DESC
                LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$search, $search, $search]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur recherche paiements: " . $e->getMessage());
        return [];
    }
}

// Exporter les paiements (données pour CSV/Excel)
function exporterPaiements($filters = []) {
    global $pdo;

    try {
        $conditions = [];
        $params = [];

        if (!empty($filters['date_debut'])) {
            $conditions[] = "p.date_paiement >= ?";
            $params[] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $conditions[] = "p.date_paiement <= ?";
            $params[] = $filters['date_fin'];
        }

        $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    d.numero as 'Numéro dossier',
                    d.nom_demandeur as 'Demandeur',
                    d.type_infrastructure as 'Type infrastructure',
                    p.montant as 'Montant',
                    p.devise as 'Devise',
                    p.mode_paiement as 'Mode paiement',
                    p.reference_paiement as 'Référence',
                    p.date_paiement as 'Date paiement',
                    CONCAT(u.prenom, ' ', u.nom) as 'Billeteur',
                    p.observations as 'Observations'
                FROM paiements p
                JOIN dossiers d ON p.dossier_id = d.id
                LEFT JOIN users u ON p.billeteur_id = u.id
                $where_clause
                ORDER BY p.date_paiement DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Erreur export paiements: " . $e->getMessage());
        return [];
    }
}
?>