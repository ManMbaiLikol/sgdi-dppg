<?php
/**
 * Contrôleur API Statistics
 */

class StatisticsController {
    private $api_key;

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    public function getGlobal() {
        global $conn;

        $stats = [];

        // Dossiers par statut
        $stmt = $conn->query("
            SELECT
                s.libelle,
                s.code,
                s.couleur,
                COUNT(*) as nb
            FROM dossiers d
            INNER JOIN statuts_dossier s ON d.statut_id = s.id
            WHERE d.archive = 0
            GROUP BY s.id, s.libelle, s.code, s.couleur
        ");
        $stats['par_statut'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dossiers par type infrastructure
        $stmt = $conn->query("
            SELECT
                ti.nom,
                COUNT(*) as nb
            FROM dossiers d
            INNER JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
            WHERE d.archive = 0
            GROUP BY ti.id, ti.nom
        ");
        $stats['par_type_infrastructure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats globales
        $stmt = $conn->query("
            SELECT
                (SELECT COUNT(*) FROM dossiers WHERE archive = 0) as total_dossiers,
                (SELECT COUNT(*) FROM users WHERE actif = 1) as total_users,
                (SELECT COUNT(*) FROM notifications WHERE lu = 0) as notifications_non_lues,
                (SELECT COUNT(*) FROM v_huitaines_actives WHERE jours_restants < 0) as huitaines_expirees
        ");
        $stats['global'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    public function getDashboard() {
        global $conn;

        $dashboard = [];

        // Dossiers récents
        $stmt = $conn->query("
            SELECT
                d.id,
                d.numero_dossier,
                d.nom_demandeur,
                s.libelle as statut,
                s.couleur as statut_couleur,
                d.created_at
            FROM dossiers d
            INNER JOIN statuts_dossier s ON d.statut_id = s.id
            WHERE d.archive = 0
            ORDER BY d.created_at DESC
            LIMIT 10
        ");
        $dashboard['dossiers_recents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Alertes
        $dashboard['alertes'] = [
            'huitaines_urgentes' => $this->getHuitainesUrgentes(),
            'paiements_attente' => $this->getPaiementsAttente()
        ];

        // Stats mensuelles
        $dashboard['stats_mensuelles'] = $this->getStatsMensuelles();

        return $dashboard;
    }

    private function getHuitainesUrgentes() {
        global $conn;

        $stmt = $conn->query("
            SELECT COUNT(*) as nb
            FROM v_huitaines_actives
            WHERE jours_restants <= 2
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC)['nb'];
    }

    private function getPaiementsAttente() {
        global $conn;

        $stmt = $conn->query("
            SELECT COUNT(*) as nb
            FROM dossiers d
            INNER JOIN statuts_dossier s ON d.statut_id = s.id
            WHERE s.code = 'attente_paiement'
                AND d.archive = 0
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC)['nb'];
    }

    private function getStatsMensuelles() {
        global $conn;

        $stmt = $conn->query("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as mois,
                COUNT(*) as nb_dossiers
            FROM dossiers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mois
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
