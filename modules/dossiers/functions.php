<?php
// Fonctions pour la gestion des dossiers - SGDI MVP

// Créer un dossier
function createDossier($data) {
    global $pdo;

    // Générer un numéro unique
    $numero = genererNumeroDossier($data['type_infrastructure']);

    $sql = "INSERT INTO dossiers (
                numero, type_infrastructure, sous_type, nom_demandeur, contact_demandeur,
                telephone_demandeur, email_demandeur, region, departement, ville, arrondissement,
                quartier, lieu_dit, coordonnees_gps, operateur_proprietaire, entreprise_beneficiaire,
                contrat_livraison, entreprise_installatrice, operateur_gaz, entreprise_constructrice,
                capacite_enfutage, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $numero,
            $data['type_infrastructure'],
            $data['sous_type'],
            $data['nom_demandeur'],
            $data['contact_demandeur'] ?? null,
            $data['telephone_demandeur'] ?? null,
            $data['email_demandeur'] ?? null,
            $data['region'] ?? null,
            $data['departement'] ?? null,
            $data['ville'] ?? null,
            $data['arrondissement'] ?? null,
            $data['quartier'] ?? null,
            $data['lieu_dit'] ?? null,
            $data['coordonnees_gps'] ?? null,
            $data['operateur_proprietaire'] ?? null,
            $data['entreprise_beneficiaire'] ?? null,
            $data['contrat_livraison'] ?? null,
            $data['entreprise_installatrice'] ?? null,
            $data['operateur_gaz'] ?? null,
            $data['entreprise_constructrice'] ?? null,
            $data['capacite_enfutage'] ?? null,
            $data['user_id']
        ]);

        if ($result) {
            $dossier_id = $pdo->lastInsertId();

            // Logger la création
            logAction($pdo, $dossier_id, 'creation_dossier',
                     'Création du dossier ' . $numero, $data['user_id'], null, 'brouillon');

            return $dossier_id;
        }
    } catch (Exception $e) {
        return false;
    }

    return false;
}

// Modifier un dossier existant
function modifierDossier($dossier_id, $data) {
    global $pdo;

    $sql = "UPDATE dossiers SET
                type_infrastructure = ?, sous_type = ?, nom_demandeur = ?, contact_demandeur = ?,
                telephone_demandeur = ?, email_demandeur = ?, adresse_precise = ?,
                region = ?, ville = ?, arrondissement = ?, quartier = ?, lieu_dit = ?,
                coordonnees_gps = ?,
                operateur_proprietaire = ?, entreprise_beneficiaire = ?, entreprise_installatrice = ?,
                contrat_livraison = ?, operateur_gaz = ?, entreprise_constructrice = ?,
                capacite_enfutage = ?, date_modification = NOW()
            WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['type_infrastructure'],
            $data['sous_type'],
            $data['nom_demandeur'],
            $data['contact_demandeur'] ?? null,
            $data['telephone_demandeur'],
            $data['email_demandeur'],
            $data['adresse_precise'],
            $data['region'],
            $data['ville'],
            $data['arrondissement'] ?? null,
            $data['quartier'] ?? null,
            $data['lieu_dit'] ?? null,
            $data['coordonnees_gps'] ?? null,
            $data['operateur_proprietaire'],
            $data['entreprise_beneficiaire'],
            $data['entreprise_installatrice'],
            $data['contrat_livraison'],
            $data['operateur_gaz'],
            $data['entreprise_constructrice'],
            $data['capacite_enfutage'],
            $dossier_id
        ]);

        return $result;
    } catch (Exception $e) {
        error_log("Erreur modification dossier: " . $e->getMessage());
        return false;
    }
}

// Supprimer un dossier et toutes ses données associées
function supprimerDossier($dossier_id) {
    global $pdo;

    try {
        // Démarrer une transaction
        $pdo->beginTransaction();

        // Fonction helper pour vérifier si une table existe
        function tableExists($pdo, $tableName) {
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$tableName]);
                return $stmt->rowCount() > 0;
            } catch (Exception $e) {
                return false;
            }
        }

        // Supprimer dans l'ordre inverse des dépendances, en vérifiant l'existence des tables

        // 1. Notes de frais
        if (tableExists($pdo, 'notes_frais')) {
            $stmt = $pdo->prepare("DELETE FROM notes_frais WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 2. Paiements
        if (tableExists($pdo, 'paiements')) {
            $stmt = $pdo->prepare("DELETE FROM paiements WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 3. Documents et leurs versions
        if (tableExists($pdo, 'documents')) {
            // Supprimer les versions si la table existe
            if (tableExists($pdo, 'versions_document')) {
                $stmt = $pdo->prepare("SELECT id FROM documents WHERE dossier_id = ?");
                $stmt->execute([$dossier_id]);
                $documents = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($documents as $doc_id) {
                    $stmt = $pdo->prepare("DELETE FROM versions_document WHERE document_id = ?");
                    $stmt->execute([$doc_id]);
                }
            }

            // Supprimer les documents
            $stmt = $pdo->prepare("DELETE FROM documents WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 4. Commissions et membres
        if (tableExists($pdo, 'commissions')) {
            if (tableExists($pdo, 'membres_commission')) {
                $stmt = $pdo->prepare("SELECT id FROM commissions WHERE dossier_id = ?");
                $stmt->execute([$dossier_id]);
                $commissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($commissions as $commission_id) {
                    $stmt = $pdo->prepare("DELETE FROM membres_commission WHERE commission_id = ?");
                    $stmt->execute([$commission_id]);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM commissions WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 5. Inspections et rapports
        if (tableExists($pdo, 'inspections')) {
            if (tableExists($pdo, 'rapports_inspection')) {
                $stmt = $pdo->prepare("SELECT id FROM inspections WHERE dossier_id = ?");
                $stmt->execute([$dossier_id]);
                $inspections = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($inspections as $inspection_id) {
                    $stmt = $pdo->prepare("DELETE FROM rapports_inspection WHERE inspection_id = ?");
                    $stmt->execute([$inspection_id]);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM inspections WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 6. Analyses DAJ
        if (tableExists($pdo, 'analyses_daj')) {
            $stmt = $pdo->prepare("DELETE FROM analyses_daj WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 7. Historique
        if (tableExists($pdo, 'historique_dossier')) {
            $stmt = $pdo->prepare("DELETE FROM historique_dossier WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 8. Notifications
        if (tableExists($pdo, 'notifications')) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 9. Logs d'activité
        if (tableExists($pdo, 'logs_activite')) {
            $stmt = $pdo->prepare("DELETE FROM logs_activite WHERE dossier_id = ?");
            $stmt->execute([$dossier_id]);
        }

        // 10. Enfin, supprimer le dossier lui-même
        $stmt = $pdo->prepare("DELETE FROM dossiers WHERE id = ?");
        $result = $stmt->execute([$dossier_id]);

        if ($result) {
            // Valider la transaction
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $pdo->rollBack();
        error_log("Erreur suppression dossier: " . $e->getMessage());
        return false;
    }
}

// Obtenir les statistiques géographiques par type d'infrastructure
function getStatistiquesGeographiques($type_infrastructure = '', $type_localisation = '') {
    global $pdo;

    $stats = [
        'par_region' => [],
        'par_arrondissement' => [],
        'par_ville' => []
    ];

    try {
        // Condition pour filtrer par type si spécifié
        $where_conditions = [];
        $params = [];

        if (!empty($type_infrastructure)) {
            $where_conditions[] = 'type_infrastructure = ?';
            $params[] = $type_infrastructure;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Si un type de localisation spécifique est demandé, ne retourner que celui-ci
        if (!empty($type_localisation)) {
            switch ($type_localisation) {
                case 'region':
                    $where_region = !empty($where_clause) ? $where_clause . ' AND' : 'WHERE';
                    $sql = "SELECT region as localisation, COUNT(*) as count
                            FROM dossiers
                            $where_region region IS NOT NULL AND region != ''
                            GROUP BY region
                            ORDER BY count DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()) {
                        $stats['par_region'][$row['localisation']] = $row['count'];
                    }
                    break;

                case 'arrondissement':
                    $where_arrondissement = !empty($where_clause) ? $where_clause . ' AND' : 'WHERE';
                    $sql = "SELECT arrondissement as localisation, COUNT(*) as count
                            FROM dossiers
                            $where_arrondissement arrondissement IS NOT NULL AND arrondissement != ''
                            GROUP BY arrondissement
                            ORDER BY count DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()) {
                        $stats['par_arrondissement'][$row['localisation']] = $row['count'];
                    }
                    break;

                case 'ville':
                    $where_ville = !empty($where_clause) ? $where_clause . ' AND' : 'WHERE';
                    $sql = "SELECT ville as localisation, COUNT(*) as count
                            FROM dossiers
                            $where_ville ville IS NOT NULL AND ville != ''
                            GROUP BY ville
                            ORDER BY count DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()) {
                        $stats['par_ville'][$row['localisation']] = $row['count'];
                    }
                    break;
            }
        } else {
            // Comportement par défaut : afficher toutes les répartitions
            $where_default = !empty($where_clause) ? $where_clause . ' AND' : 'WHERE';

            // Répartition par région
            $sql = "SELECT region, COUNT(*) as count
                    FROM dossiers
                    $where_default region IS NOT NULL AND region != ''
                    GROUP BY region
                    ORDER BY count DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $stats['par_region'][$row['region']] = $row['count'];
            }

            // Répartition par arrondissement
            $sql = "SELECT arrondissement, COUNT(*) as count
                    FROM dossiers
                    $where_default arrondissement IS NOT NULL AND arrondissement != ''
                    GROUP BY arrondissement
                    ORDER BY count DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $stats['par_arrondissement'][$row['arrondissement']] = $row['count'];
            }

            // Répartition par ville
            $sql = "SELECT ville, COUNT(*) as count
                    FROM dossiers
                    $where_default ville IS NOT NULL AND ville != ''
                    GROUP BY ville
                    ORDER BY count DESC
                    LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                $stats['par_ville'][$row['ville']] = $row['count'];
            }
        }

    } catch (Exception $e) {
        error_log("Erreur récupération stats géographiques: " . $e->getMessage());
    }

    return $stats;
}

// Obtenir la liste des types d'infrastructure disponibles
function getTypesInfrastructureDisponibles() {
    global $pdo;

    try {
        $sql = "SELECT DISTINCT type_infrastructure, COUNT(*) as count
                FROM dossiers
                WHERE type_infrastructure IS NOT NULL AND type_infrastructure != ''
                GROUP BY type_infrastructure
                ORDER BY type_infrastructure";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $types = [];
        while ($row = $stmt->fetch()) {
            $types[$row['type_infrastructure']] = $row['count'];
        }

        return $types;

    } catch (Exception $e) {
        error_log("Erreur récupération types infrastructure: " . $e->getMessage());
        return [];
    }
}

// Obtenir un dossier par ID
function getDossierById($id) {
    global $pdo;

    $sql = "SELECT d.*, u.nom as createur_nom, u.prenom as createur_prenom
            FROM dossiers d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Obtenir tous les dossiers avec filtres
function getDossiers($filters = [], $limit = 20, $offset = 0) {
    global $pdo;

    $where_conditions = [];
    $params = [];

    // Filtres
    if (!empty($filters['statut'])) {
        $where_conditions[] = "d.statut = ?";
        $params[] = $filters['statut'];
    }

    if (!empty($filters['type_infrastructure'])) {
        $where_conditions[] = "d.type_infrastructure = ?";
        $params[] = $filters['type_infrastructure'];
    }

    if (!empty($filters['sous_type'])) {
        $where_conditions[] = "d.sous_type = ?";
        $params[] = $filters['sous_type'];
    }

    if (!empty($filters['search'])) {
        $where_conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR d.contact_demandeur LIKE ? OR d.region LIKE ? OR d.arrondissement LIKE ? OR d.ville LIKE ? OR d.quartier LIKE ? OR d.lieu_dit LIKE ? OR d.operateur_proprietaire LIKE ? OR d.entreprise_beneficiaire LIKE ? OR d.entreprise_installatrice LIKE ? OR d.operateur_gaz LIKE ? OR d.entreprise_constructrice LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search; // numero
        $params[] = $search; // nom_demandeur
        $params[] = $search; // contact_demandeur
        $params[] = $search; // region
        $params[] = $search; // arrondissement
        $params[] = $search; // ville
        $params[] = $search; // quartier
        $params[] = $search; // lieu_dit
        $params[] = $search; // operateur_proprietaire
        $params[] = $search; // entreprise_beneficiaire
        $params[] = $search; // entreprise_installatrice
        $params[] = $search; // operateur_gaz
        $params[] = $search; // entreprise_constructrice
    }

    // Permissions selon le rôle
    if (!empty($filters['user_role'])) {
        switch ($filters['user_role']) {
            case 'chef_service':
            case 'admin':
                // Voir tous les dossiers
                break;

            case 'sous_directeur':
                // Voir seulement les dossiers qu'il a visés
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM visas v
                    WHERE v.dossier_id = d.id
                    AND v.role = 'sous_directeur'
                )";
                break;

            case 'directeur':
                // Voir seulement les dossiers qu'il a visés
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM visas v
                    WHERE v.dossier_id = d.id
                    AND v.role = 'directeur'
                )";
                break;

            case 'ministre':
                // Voir seulement les dossiers qui ont une décision OU qui sont en attente de décision
                $where_conditions[] = "(d.statut IN ('visa_directeur', 'decide', 'autorise', 'rejete'))";
                break;

            case 'cadre_dppg':
                // Voir SEULEMENT les dossiers dont il est membre de la commission
                // Règle stricte: accès uniquement aux membres de la commission (cadre_dppg, cadre_daj, chef_commission)
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND (c.cadre_dppg_id = ? OR c.cadre_daj_id = ? OR c.chef_commission_id = ?)
                )";
                $params[] = $_SESSION['user_id'];
                $params[] = $_SESSION['user_id'];
                $params[] = $_SESSION['user_id'];
                break;

            case 'cadre_daj':
                // Voir seulement les dossiers dont il est membre de la commission
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND c.cadre_daj_id = ?
                )";
                $params[] = $_SESSION['user_id'];
                $where_conditions[] = "d.statut IN ('paye', 'en_cours', 'inspecte', 'valide', 'decide', 'autorise')";
                break;

            case 'chef_commission':
                // Voir seulement les dossiers dont il est chef de commission
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND c.chef_commission_id = ?
                )";
                $params[] = $_SESSION['user_id'];
                break;

            case 'billeteur':
                // Voir les dossiers en cours (pour paiement)
                $where_conditions[] = "d.statut = 'en_cours'";
                break;
        }
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    $sql = "SELECT d.*, u.nom as createur_nom, u.prenom as createur_prenom
            FROM dossiers d
            LEFT JOIN users u ON d.user_id = u.id
            $where_sql
            ORDER BY d.date_creation DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Compter les dossiers avec filtres
function countDossiers($filters = []) {
    global $pdo;

    $where_conditions = [];
    $params = [];

    // Même logique de filtres que getDossiers
    if (!empty($filters['statut'])) {
        $where_conditions[] = "d.statut = ?";
        $params[] = $filters['statut'];
    }

    if (!empty($filters['type_infrastructure'])) {
        $where_conditions[] = "d.type_infrastructure = ?";
        $params[] = $filters['type_infrastructure'];
    }

    if (!empty($filters['sous_type'])) {
        $where_conditions[] = "d.sous_type = ?";
        $params[] = $filters['sous_type'];
    }

    if (!empty($filters['search'])) {
        $where_conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR d.contact_demandeur LIKE ? OR d.region LIKE ? OR d.arrondissement LIKE ? OR d.ville LIKE ? OR d.quartier LIKE ? OR d.lieu_dit LIKE ? OR d.operateur_proprietaire LIKE ? OR d.entreprise_beneficiaire LIKE ? OR d.entreprise_installatrice LIKE ? OR d.operateur_gaz LIKE ? OR d.entreprise_constructrice LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search; // numero
        $params[] = $search; // nom_demandeur
        $params[] = $search; // contact_demandeur
        $params[] = $search; // region
        $params[] = $search; // arrondissement
        $params[] = $search; // ville
        $params[] = $search; // quartier
        $params[] = $search; // lieu_dit
        $params[] = $search; // operateur_proprietaire
        $params[] = $search; // entreprise_beneficiaire
        $params[] = $search; // entreprise_installatrice
        $params[] = $search; // operateur_gaz
        $params[] = $search; // entreprise_constructrice
    }

    // Permissions selon le rôle
    if (!empty($filters['user_role'])) {
        switch ($filters['user_role']) {
            case 'cadre_dppg':
                // Voir SEULEMENT les dossiers dont il est membre de la commission
                // Règle stricte: accès uniquement aux membres de la commission (cadre_dppg, cadre_daj, chef_commission)
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND (c.cadre_dppg_id = ? OR c.cadre_daj_id = ? OR c.chef_commission_id = ?)
                )";
                $params[] = $_SESSION['user_id'];
                $params[] = $_SESSION['user_id'];
                $params[] = $_SESSION['user_id'];
                break;

            case 'cadre_daj':
                // Voir seulement les dossiers dont il est membre de la commission
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND c.cadre_daj_id = ?
                )";
                $params[] = $_SESSION['user_id'];
                $where_conditions[] = "d.statut IN ('paye', 'en_cours', 'inspecte', 'valide', 'decide', 'autorise')";
                break;

            case 'chef_commission':
                // Voir seulement les dossiers dont il est chef de commission
                $where_conditions[] = "EXISTS (
                    SELECT 1 FROM commissions c
                    WHERE c.dossier_id = d.id
                    AND c.chef_commission_id = ?
                )";
                $params[] = $_SESSION['user_id'];
                break;

            case 'billeteur':
                $where_conditions[] = "d.statut = 'en_cours'";
                break;
        }
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    $sql = "SELECT COUNT(*) FROM dossiers d $where_sql";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Changer le statut d'un dossier
function changerStatutDossier($dossier_id, $nouveau_statut, $user_id, $description = '', $use_transaction = true) {
    global $pdo;

    try {
        $transaction_started = false;

        // Démarrer une transaction seulement si aucune n'est active et si demandé
        if ($use_transaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $transaction_started = true;
        }

        // Obtenir l'ancien statut
        $sql = "SELECT statut FROM dossiers WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id]);
        $ancien_statut = $stmt->fetchColumn();

        // Mettre à jour le statut
        $sql = "UPDATE dossiers SET statut = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nouveau_statut, $dossier_id]);

        // Logger l'action
        $action = "changement_statut_" . $nouveau_statut;
        $desc = $description ?: "Changement de statut vers " . getStatutLabel($nouveau_statut);

        logAction($pdo, $dossier_id, $action, $desc, $user_id, $ancien_statut, $nouveau_statut);

        // Committer seulement si on a démarré la transaction
        if ($transaction_started) {
            $pdo->commit();
        }

        return true;

    } catch (Exception $e) {
        // Log l'erreur pour debug
        error_log("Erreur dans changerStatutDossier: " . $e->getMessage());

        // Rollback seulement si on a démarré la transaction
        if ($transaction_started && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        return false;
    }
}

// Obtenir l'historique d'un dossier
function getHistoriqueDossier($dossier_id) {
    global $pdo;

    $sql = "SELECT h.*, u.nom, u.prenom, u.role
            FROM historique h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.dossier_id = ?
            ORDER BY h.date_action DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    return $stmt->fetchAll();
}

// Obtenir les statistiques des dossiers
function getStatistiquesDossiers($user_role = null) {
    global $pdo;

    $stats = [];

    // Statistiques par statut
    $where_sql = '';
    $params = [];

    if ($user_role) {
        switch ($user_role) {
            case 'cadre_dppg':
                $where_sql = "WHERE statut IN ('paye', 'inspecte', 'valide', 'decide', 'autorise')";
                break;
            case 'cadre_daj':
                $where_sql = "WHERE statut IN ('paye', 'en_cours', 'inspecte', 'valide', 'decide', 'autorise')";
                break;
            case 'chef_commission':
                $where_sql = "WHERE statut IN ('paye', 'inspecte', 'valide', 'decide', 'autorise')";
                break;
            case 'billeteur':
                $where_sql = "WHERE statut IN ('en_cours', 'paye', 'rejete')";
                break;
        }
    }

    $sql = "SELECT statut, COUNT(*) as nombre
            FROM dossiers $where_sql
            GROUP BY statut";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['par_statut'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Statistiques spéciales pour le billeteur
    if ($user_role === 'billeteur') {
        // Total encaissé ce mois
        $sql = "SELECT COALESCE(SUM(p.montant), 0) as total_encaisse
                FROM paiements p
                JOIN dossiers d ON p.dossier_id = d.id
                WHERE MONTH(p.date_enregistrement) = MONTH(CURRENT_DATE())
                AND YEAR(p.date_enregistrement) = YEAR(CURRENT_DATE())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['par_statut']['total_encaisse'] = $stmt->fetchColumn();

        // Compter les dossiers rejetés faute de paiement (simulé pour l'instant)
        $stats['par_statut']['rejete'] = 0; // À implémenter selon la logique métier
    }

    // Statistiques par type
    $sql = "SELECT type_infrastructure, COUNT(*) as nombre
            FROM dossiers $where_sql
            GROUP BY type_infrastructure";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['par_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Total
    $sql = "SELECT COUNT(*) FROM dossiers $where_sql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total'] = $stmt->fetchColumn();

    return $stats;
}

// Vérifier si l'utilisateur peut modifier le dossier
function peutModifierDossier($dossier, $user_role, $user_id) {
    // Admin peut tout modifier
    if ($user_role === 'admin') return true;

    // Chef service peut modifier ses dossiers non décidés
    if ($user_role === 'chef_service' && $dossier['user_id'] == $user_id && $dossier['statut'] !== 'decide') {
        return true;
    }

    return false;
}

// Obtenir les actions possibles selon le rôle et le statut
function getActionsPossibles($dossier, $user_role) {
    $actions = [];

    switch ($user_role) {
        case 'chef_service':
            // Upload de documents possible tant que le dossier n'est pas finalisé
            if (in_array($dossier['statut'], ['brouillon', 'en_cours'])) {
                $actions[] = ['action' => 'upload_documents', 'label' => 'Uploader documents', 'class' => 'info'];
            }

            // Constituer la commission si pas encore créée
            if (in_array($dossier['statut'], ['brouillon', 'en_cours'])) {
                // Vérifier si une commission existe déjà
                global $pdo;
                $sql_check_comm = "SELECT id FROM commissions WHERE dossier_id = ?";
                $stmt_check = $pdo->prepare($sql_check_comm);
                $stmt_check->execute([$dossier['id']]);
                $commission_existante = $stmt_check->fetch();

                if (!$commission_existante) {
                    $actions[] = ['action' => 'constituer_commission', 'label' => 'Constituer la commission', 'class' => 'primary'];
                }
            }

            // Créer note de frais si pas encore créée
            if (in_array($dossier['statut'], ['brouillon', 'en_cours'])) {
                // Vérifier si une note de frais existe déjà
                require_once __DIR__ . '/../notes_frais/functions.php';
                $note_existante = getNoteFreaisParDossier($dossier['id']);
                if (!$note_existante) {
                    $actions[] = ['action' => 'creer_note_frais', 'label' => 'Créer note de frais', 'class' => 'warning'];
                }
            }
            if ($dossier['statut'] === 'decide') {
                $actions[] = ['action' => 'marquer_autorise', 'label' => 'Marquer comme autorisé', 'class' => 'success'];
            }
            if ($dossier['statut'] === 'autorise') {
                $actions[] = ['action' => 'gestion_operationnelle', 'label' => 'Gestion opérationnelle', 'class' => 'warning'];
            }
            break;

        case 'admin':
            // L'admin n'a pas d'actions spécifiques sur les dossiers (seulement supprimer via l'interface)
            break;

        case 'billeteur':
            if ($dossier['statut'] === 'en_cours') {
                $actions[] = ['action' => 'enregistrer_paiement', 'label' => 'Enregistrer le paiement', 'class' => 'success'];
            }
            break;

        case 'cadre_dppg':
            if ($dossier['statut'] === 'paye') {
                $actions[] = ['action' => 'faire_inspection', 'label' => 'Faire l\'inspection', 'class' => 'warning'];
            }
            break;

        case 'cadre_daj':
            if (in_array($dossier['statut'], ['paye', 'en_cours'])) {
                $actions[] = ['action' => 'analyser_dossier', 'label' => 'Analyser le dossier', 'class' => 'info'];
            }
            break;

        case 'chef_commission':
            if ($dossier['statut'] === 'inspecte') {
                $actions[] = ['action' => 'valider_rapport', 'label' => 'Valider le rapport', 'class' => 'success'];
            }
            break;

        case 'directeur':
            if ($dossier['statut'] === 'inspecte') {
                $actions[] = ['action' => 'valider_rapport', 'label' => 'Valider le rapport', 'class' => 'info'];
            }
            if ($dossier['statut'] === 'valide') {
                $actions[] = ['action' => 'prendre_decision', 'label' => 'Prendre la décision', 'class' => 'dark'];
            }
            break;
    }

    // Action commune de visualisation (sauf si une action spécifique existe déjà)
    $has_specific_action = false;
    foreach ($actions as $action) {
        if (in_array($action['action'], ['analyser_dossier', 'faire_inspection', 'enregistrer_paiement', 'constituer_commission'])) {
            $has_specific_action = true;
            break;
        }
    }

    if (!$has_specific_action) {
        $actions[] = ['action' => 'voir_details', 'label' => 'Voir les détails', 'class' => 'outline-secondary'];
    }

    return $actions;
}

/**
 * Obtenir tous les détails complets d'un dossier
 */
function getDossierDetails($dossier_id) {
    global $pdo;

    $sql = "
        SELECT d.*,
               u.nom as createur_nom,
               u.prenom as createur_prenom,
               c.chef_commission_id,
               c.chef_commission_role,
               c.cadre_dppg_id,
               c.cadre_daj_id,
               c.date_constitution as commission_date,
               c.statut as commission_statut,
               chef.nom as chef_nom,
               chef.prenom as chef_prenom,
               dppg.nom as dppg_nom,
               dppg.prenom as dppg_prenom,
               daj.nom as daj_nom,
               daj.prenom as daj_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN users chef ON c.chef_commission_id = chef.id
        LEFT JOIN users dppg ON c.cadre_dppg_id = dppg.id
        LEFT JOIN users daj ON c.cadre_daj_id = daj.id
        WHERE d.id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Vérifier si l'utilisateur a le droit d'accéder à un dossier
 * Selon la règle stricte: seuls les membres de la commission peuvent voir un dossier
 */
function canAccessDossier($dossier_id, $user_id, $user_role) {
    global $pdo;

    // Admin et chef de service peuvent tout voir
    if (in_array($user_role, ['admin', 'chef_service'])) {
        return true;
    }

    // Sous-directeur: peut voir les dossiers qu'il a visés OU où il est chef de commission
    if ($user_role === 'sous_directeur') {
        // Vérifier si visé
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visas WHERE dossier_id = ? AND role = 'sous_directeur'");
        $stmt->execute([$dossier_id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }

        // Vérifier si chef de commission
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM commissions WHERE dossier_id = ? AND chef_commission_id = ?");
        $stmt->execute([$dossier_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }

    // Directeur: peut voir les dossiers qu'il a visés
    if ($user_role === 'directeur') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visas WHERE dossier_id = ? AND role = 'directeur'");
        $stmt->execute([$dossier_id]);
        return $stmt->fetchColumn() > 0;
    }

    // Ministre: peut voir les dossiers en attente de décision ou décidés
    if ($user_role === 'ministre') {
        $stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier_id]);
        $statut = $stmt->fetchColumn();
        return in_array($statut, ['visa_directeur', 'decide', 'autorise', 'rejete']);
    }

    // Cadre DPPG, Cadre DAJ, Chef Commission: seulement si membre de la commission
    if (in_array($user_role, ['cadre_dppg', 'cadre_daj', 'chef_commission'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM commissions
                              WHERE dossier_id = ?
                              AND (cadre_dppg_id = ? OR cadre_daj_id = ? OR chef_commission_id = ?)");
        $stmt->execute([$dossier_id, $user_id, $user_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }

    // Billeteur: peut voir les dossiers en cours (pour paiement)
    if ($user_role === 'billeteur') {
        $stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier_id]);
        $statut = $stmt->fetchColumn();
        return $statut === 'en_cours';
    }

    // Par défaut: pas d'accès
    return false;
}

/**
 * Obtenir les documents d'un dossier
 */
function getDocumentsDossier($dossier_id) {
    global $pdo;

    $sql = "
        SELECT d.*, u.nom as uploader_nom, u.prenom as uploader_prenom
        FROM documents d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.dossier_id = ?
        ORDER BY d.date_upload DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtenir les informations de paiement d'un dossier
 */
function getPaiementDossier($dossier_id) {
    global $pdo;

    $sql = "
        SELECT p.*, u.nom as billeteur_nom, u.prenom as billeteur_prenom
        FROM paiements p
        LEFT JOIN users u ON p.billeteur_id = u.id
        WHERE p.dossier_id = ?
        ORDER BY p.date_paiement DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtenir les inspections d'un dossier
 */
function getInspectionsDossier($dossier_id) {
    global $pdo;

    $sql = "
        SELECT i.*, u.nom as inspecteur_nom, u.prenom as inspecteur_prenom
        FROM inspections i
        LEFT JOIN users u ON i.cadre_dppg_id = u.id
        WHERE i.dossier_id = ?
        ORDER BY i.date_inspection DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtenir le nombre d'utilisateurs actifs sur les 30 derniers jours
 */
function getUtilisateursActifs30j() {
    global $pdo;

    try {
        // Vérifier si la table logs_activite existe
        $sql = "SHOW TABLES LIKE 'logs_activite'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Si la table existe, compter les utilisateurs avec activité récente
            $sql = "SELECT COUNT(DISTINCT user_id)
                    FROM logs_activite
                    WHERE date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } else {
            // Sinon, utiliser la table users avec derniere_connexion si elle existe
            $sql = "SELECT COUNT(*)
                    FROM users
                    WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Erreur récupération utilisateurs actifs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtenir les statistiques d'infrastructures autorisées (TOUTES, y compris fermées)
 */
function getStatistiquesInfrastructuresAutorisees() {
    global $pdo;

    try {
        $stats = [];

        // Stations-service autorisées (toutes)
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'station_service'
                AND statut = 'autorise'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['stations'] = (int) $stmt->fetchColumn();

        // Points consommateurs autorisés (tous)
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'point_consommateur'
                AND statut = 'autorise'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['points_consommateurs'] = (int) $stmt->fetchColumn();

        // Dépôts GPL autorisés (tous)
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'depot_gpl'
                AND statut = 'autorise'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['depots'] = (int) $stmt->fetchColumn();

        // Centres emplisseurs autorisés (tous)
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'centre_emplisseur'
                AND statut = 'autorise'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['centres_emplisseurs'] = (int) $stmt->fetchColumn();

        return $stats;

    } catch (Exception $e) {
        error_log("Erreur récupération stats infrastructures: " . $e->getMessage());
        return ['stations' => 0, 'points_consommateurs' => 0, 'depots' => 0, 'centres_emplisseurs' => 0];
    }
}

/**
 * Obtenir les opérateurs les plus actifs
 */
function getOperateursPlusActifs($limit = 5) {
    global $pdo;

    try {
        $sql = "SELECT
                    COALESCE(operateur_proprietaire, nom_demandeur) as operateur,
                    COUNT(*) as nb_dossiers,
                    SUM(CASE WHEN statut = 'autorise' THEN 1 ELSE 0 END) as nb_autorises,
                    SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) as nb_rejetes
                FROM dossiers
                WHERE COALESCE(operateur_proprietaire, nom_demandeur) IS NOT NULL
                    AND COALESCE(operateur_proprietaire, nom_demandeur) != ''
                GROUP BY operateur
                ORDER BY nb_dossiers DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Erreur récupération opérateurs actifs: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir l'évolution mensuelle des paiements (12 derniers mois)
 */
function getEvolutionMensuellesPaiements() {
    global $pdo;

    try {
        $sql = "SELECT
                    DATE_FORMAT(p.date_paiement, '%Y-%m') as mois,
                    COUNT(*) as nombre_paiements,
                    SUM(p.montant) as montant_total
                FROM paiements p
                WHERE p.date_paiement >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(p.date_paiement, '%Y-%m')
                ORDER BY mois ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Erreur récupération évolution paiements: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir le top 5 des motifs de rejet/irrégularité
 */
function getTop5MotifsRejet($limit = 5) {
    global $pdo;

    try {
        // Essayer d'abord depuis la table decisions
        $sql = "SELECT
                    LEFT(motif, 100) as motif_court,
                    motif,
                    COUNT(*) as occurrences
                FROM decisions
                WHERE decision = 'refuse'
                    AND motif IS NOT NULL
                    AND motif != ''
                GROUP BY motif
                ORDER BY occurrences DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $motifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si pas de motifs dans decisions, retourner des exemples génériques
        if (empty($motifs)) {
            return [
                ['motif_court' => 'Documents incomplets', 'motif' => 'Documents incomplets', 'occurrences' => 0],
                ['motif_court' => 'Localisation non conforme', 'motif' => 'Localisation non conforme', 'occurrences' => 0],
                ['motif_court' => 'Non-conformité technique', 'motif' => 'Non-conformité technique', 'occurrences' => 0],
                ['motif_court' => 'Normes sécurité non respectées', 'motif' => 'Normes sécurité non respectées', 'occurrences' => 0],
                ['motif_court' => 'Irrégularités administratives', 'motif' => 'Irrégularités administratives', 'occurrences' => 0]
            ];
        }

        return $motifs;

    } catch (Exception $e) {
        error_log("Erreur récupération motifs rejet: " . $e->getMessage());
        return [
            ['motif_court' => 'Documents incomplets', 'motif' => 'Documents incomplets', 'occurrences' => 0],
            ['motif_court' => 'Localisation non conforme', 'motif' => 'Localisation non conforme', 'occurrences' => 0],
            ['motif_court' => 'Non-conformité technique', 'motif' => 'Non-conformité technique', 'occurrences' => 0],
            ['motif_court' => 'Normes sécurité non respectées', 'motif' => 'Normes sécurité non respectées', 'occurrences' => 0],
            ['motif_court' => 'Irrégularités administratives', 'motif' => 'Irrégularités administratives', 'occurrences' => 0]
        ];
    }
}

/**
 * Changer le statut opérationnel d'un dossier
 */
function changerStatutOperationnel($dossier_id, $nouveau_statut, $motif, $user_id, $date_fermeture = null, $date_reouverture = null) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Récupérer l'ancien statut
        $stmt = $pdo->prepare("SELECT statut_operationnel FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier_id]);
        $ancien_statut = $stmt->fetchColumn() ?: 'operationnel';

        // Mettre à jour le statut opérationnel
        $sql = "UPDATE dossiers SET
                statut_operationnel = ?,
                motif_fermeture = ?,
                date_fermeture = ?,
                date_reouverture = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nouveau_statut,
            $motif,
            $date_fermeture,
            $date_reouverture,
            $dossier_id
        ]);

        // Ajouter à l'historique
        addHistoriqueDossier(
            $dossier_id,
            $user_id,
            'changement_statut_operationnel',
            "Changement statut opérationnel: {$ancien_statut} → {$nouveau_statut}. Motif: {$motif}",
            $ancien_statut,
            $nouveau_statut
        );

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Erreur changement statut opérationnel: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques d'infrastructures opérationnelles (excluant les fermées)
 */
function getStatistiquesInfrastructuresOperationnelles() {
    global $pdo;

    try {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN statut_operationnel IS NULL OR statut_operationnel = 'operationnel' THEN 1 ELSE 0 END) as operationnels,
                    SUM(CASE WHEN statut_operationnel = 'ferme_temporaire' THEN 1 ELSE 0 END) as fermes_temporaires,
                    SUM(CASE WHEN statut_operationnel = 'ferme_definitif' THEN 1 ELSE 0 END) as fermes_definitifs,
                    SUM(CASE WHEN statut_operationnel = 'demantele' THEN 1 ELSE 0 END) as demanteles
                FROM dossiers
                WHERE statut = 'autorise'";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) $result['total'],
            'operationnels' => (int) $result['operationnels'],
            'fermes_temporaires' => (int) $result['fermes_temporaires'],
            'fermes_definitifs' => (int) $result['fermes_definitifs'],
            'demanteles' => (int) $result['demanteles']
        ];

    } catch (Exception $e) {
        error_log("Erreur récupération stats infrastructures opérationnelles: " . $e->getMessage());
        return [
            'total' => 0,
            'operationnels' => 0,
            'fermes_temporaires' => 0,
            'fermes_definitifs' => 0,
            'demanteles' => 0
        ];
    }
}

/**
 * Obtenir les statistiques d'infrastructures par type (pour dashboard admin)
 */
function getStatistiquesInfrastructuresParType() {
    global $pdo;

    try {
        $stats = [];

        // Stations-service opérationnelles
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'station_service'
                AND statut = 'autorise'
                AND (statut_operationnel IS NULL OR statut_operationnel = 'operationnel')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['stations'] = (int) $stmt->fetchColumn();

        // Points consommateurs opérationnels
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'point_consommateur'
                AND statut = 'autorise'
                AND (statut_operationnel IS NULL OR statut_operationnel = 'operationnel')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['points_consommateurs'] = (int) $stmt->fetchColumn();

        // Dépôts GPL opérationnels
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'depot_gpl'
                AND statut = 'autorise'
                AND (statut_operationnel IS NULL OR statut_operationnel = 'operationnel')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['depots'] = (int) $stmt->fetchColumn();

        // Centres emplisseurs opérationnels
        $sql = "SELECT COUNT(*) FROM dossiers
                WHERE type_infrastructure = 'centre_emplisseur'
                AND statut = 'autorise'
                AND (statut_operationnel IS NULL OR statut_operationnel = 'operationnel')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['centres_emplisseurs'] = (int) $stmt->fetchColumn();

        return $stats;

    } catch (Exception $e) {
        error_log("Erreur récupération stats infrastructures par type: " . $e->getMessage());
        return ['stations' => 0, 'points_consommateurs' => 0, 'depots' => 0, 'centres_emplisseurs' => 0];
    }
}

/**
 * Obtenir les statistiques des infrastructures fermées (pour dashboard stats avancées)
 */
function getStatistiquesInfrastructuresFermees() {
    global $pdo;

    try {
        // Compter par type et statut opérationnel
        $sql = "SELECT
                    type_infrastructure,
                    statut_operationnel,
                    COUNT(*) as count
                FROM dossiers
                WHERE statut = 'autorise'
                AND statut_operationnel IN ('ferme_temporaire', 'ferme_definitif', 'demantele')
                GROUP BY type_infrastructure, statut_operationnel
                ORDER BY count DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Erreur récupération stats infrastructures fermées: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les statistiques des infrastructures fermées par statut (pour dashboard admin)
 */
function getStatistiquesInfrastructuresParStatut() {
    global $pdo;

    try {
        $stats = [];

        // Compter par statut opérationnel
        $sql = "SELECT
                    statut_operationnel,
                    COUNT(*) as nombre
                FROM dossiers
                WHERE statut = 'autorise'
                AND statut_operationnel IN ('ferme_temporaire', 'ferme_definitif', 'demantele')
                GROUP BY statut_operationnel";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $result) {
            $stats[$result['statut_operationnel']] = (int) $result['nombre'];
        }

        // S'assurer que toutes les clés existent
        $stats['ferme_temporaire'] = $stats['ferme_temporaire'] ?? 0;
        $stats['ferme_definitif'] = $stats['ferme_definitif'] ?? 0;
        $stats['demantele'] = $stats['demantele'] ?? 0;

        return $stats;

    } catch (Exception $e) {
        error_log("Erreur récupération stats infrastructures par statut: " . $e->getMessage());
        return ['ferme_temporaire' => 0, 'ferme_definitif' => 0, 'demantele' => 0];
    }
}
/**
 * Obtenir les types de documents requis selon le type d'infrastructure
 */
function getDocumentsRequis($type_infrastructure, $sous_type = null) {
    // Documents communs à tous les types
    $documents_communs = [
        'lettre_motivee' => [
            'label' => 'Lettre motivée',
            'description' => 'Lettre motivée de la demande',
            'requis' => true
        ],
        'rapport_delegation_regionale' => [
            'label' => 'Rapport de la délégation régionale',
            'description' => 'Rapport de la délégation régionale du lieu du site objet de la demande',
            'requis' => true
        ],
        'formulaire_infrastructure' => [
            'label' => 'Formulaire de présentation détaillée',
            'description' => 'Formulaire de présentation détaillée de l\'infrastructure',
            'requis' => true
        ],
        'lettre_ministre_environnement' => [
            'label' => 'Lettre du Ministre de l\'Environnement',
            'description' => 'Lettre du Ministre de l\'Environnement validant les Termes de Référence de l\'Etude d\'Impact Environnementale et Social',
            'requis' => true
        ],
        'plan_masse_200' => [
            'label' => 'Plan de masse 1/200',
            'description' => 'Plan de masse à l\'échelle 1/200',
            'requis' => true
        ],
        'plan_ensemble_1000_2000' => [
            'label' => 'Plan d\'ensemble 1/1000 ou 1/2000',
            'description' => 'Plan d\'ensemble au 1/1000 ou 1/2000',
            'requis' => true
        ],
        'copie_agrement_d1' => [
            'label' => 'Copie de l\'agrément D1',
            'description' => 'Copie de l\'agrément D1',
            'requis' => true
        ]
    ];

    // Documents spécifiques selon le type d'infrastructure
    $documents_specifiques = [];

    switch ($type_infrastructure) {
        case 'station_service':
            $documents_specifiques = [
                'contrat_bail_notarie' => [
                    'label' => 'Contrat de bail notarié',
                    'description' => 'Contrat de bail notarié pour station-service',
                    'requis' => true
                ],
                'permis_implanter' => [
                    'label' => 'Permis d\'implanter',
                    'description' => 'Permis d\'implanter pour station-service',
                    'requis' => true
                ]
            ];
            break;

        case 'point_consommateur':
            $documents_specifiques = [
                'contrat_livraison' => [
                    'label' => 'Contrat de livraison',
                    'description' => 'Contrat de livraison pour point consommateur',
                    'requis' => true
                ]
            ];
            break;

        case 'depot_gpl':
            $documents_specifiques = [
                'contrat_livraison' => [
                    'label' => 'Contrat de livraison',
                    'description' => 'Contrat de livraison pour dépôt GPL',
                    'requis' => true
                ]
            ];
            break;

        case 'centre_emplisseur':
            $documents_specifiques = [
                'contrat_bail_notarie' => [
                    'label' => 'Contrat de bail notarié',
                    'description' => 'Contrat de bail notarié pour centre emplisseur',
                    'requis' => true
                ],
                'permis_implanter' => [
                    'label' => 'Permis d\'implanter',
                    'description' => 'Permis d\'implanter pour centre emplisseur',
                    'requis' => true
                ]
            ];
            break;
    }

    return array_merge($documents_communs, $documents_specifiques);
}

/**
 * Vérifier les documents uploadés pour un dossier
 */
function getDocumentsUploadedByType($dossier_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT type_document, nom_original, id, taille_fichier, date_upload
            FROM documents
            WHERE dossier_id = ?
            AND type_document NOT IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
            ORDER BY date_upload DESC
        ");
        $stmt->execute([$dossier_id]);
        $documents = $stmt->fetchAll();

        $documents_by_type = [];
        foreach ($documents as $doc) {
            $documents_by_type[$doc['type_document']] = $doc;
        }

        return $documents_by_type;
    } catch (Exception $e) {
        error_log("Erreur récupération documents: " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifier si un utilisateur est membre de la commission d'un dossier
 */
function isMembreCommission($dossier_id, $user_id, $user_role) {
    global $pdo;

    try {
        // Récupérer la commission pour ce dossier
        $stmt = $pdo->prepare("
            SELECT chef_commission_id, cadre_dppg_id, cadre_daj_id, chef_commission_role
            FROM commissions
            WHERE dossier_id = ?
        ");
        $stmt->execute([$dossier_id]);
        $commission = $stmt->fetch();

        if (!$commission) {
            return false; // Pas de commission constituée
        }

        // Vérifier si l'utilisateur est membre
        // Si l'utilisateur est chef de commission (peu importe son rôle)
        if ($commission['chef_commission_id'] == $user_id) {
            return true;
        }

        // Si l'utilisateur est cadre DPPG désigné
        if ($commission['cadre_dppg_id'] == $user_id && $user_role === 'cadre_dppg') {
            return true;
        }

        // Si l'utilisateur est cadre DAJ désigné
        if ($commission['cadre_daj_id'] == $user_id && $user_role === 'cadre_daj') {
            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("Erreur vérification membre commission: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les documents d'un dossier (avec vérification des permissions)
 */
function getDocumentsDossierWithPermissions($dossier_id, $user_id, $user_role) {
    global $pdo;

    try {
        // Documents d'inspection - toujours visibles pour les rôles autorisés
        $documents_inspection = [];
        if (in_array($user_role, ['chef_service', 'directeur', 'cadre_dppg', 'cadre_daj', 'chef_commission', 'admin'])) {
            $stmt = $pdo->prepare("
                SELECT d.*, u.prenom as uploader_prenom, u.nom as uploader_nom
                FROM documents d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.dossier_id = ?
                AND d.type_document IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
                ORDER BY d.date_upload DESC
            ");
            $stmt->execute([$dossier_id]);
            $documents_inspection = $stmt->fetchAll();
        }

        // Documents du dossier initial - visibles seulement pour les membres de la commission
        $documents_dossier = [];
        if (isMembreCommission($dossier_id, $user_id, $user_role) || $user_role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT d.*, u.prenom as uploader_prenom, u.nom as uploader_nom
                FROM documents d
                LEFT JOIN users u ON d.user_id = u.id
                WHERE d.dossier_id = ?
                AND d.type_document NOT IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
                ORDER BY d.date_upload DESC
            ");
            $stmt->execute([$dossier_id]);
            $documents_dossier = $stmt->fetchAll();
        }

        return [
            'documents_dossier' => $documents_dossier,
            'documents_inspection' => $documents_inspection,
            'peut_voir_documents_dossier' => isMembreCommission($dossier_id, $user_id, $user_role) || $user_role === 'admin'
        ];

    } catch (Exception $e) {
        error_log("Erreur récupération documents avec permissions: " . $e->getMessage());
        return [
            'documents_dossier' => [],
            'documents_inspection' => [],
            'peut_voir_documents_dossier' => false
        ];
    }
}

/**
 * Obtenir le label lisible pour un type d'infrastructure
 */
function getTypeInfrastructureLabel($type) {
    if ($type === null || $type === '') {
        return 'N/A';
    }

    $labels = [
        'station_service' => 'Station-Service',
        'point_consommateur' => 'Point Consommateur',
        'depot_gpl' => 'Dépôt GPL',
        'centre_emplisseur' => 'Centre Emplisseur',
        // Anciens types avec sous-types combinés
        'implantation_station' => 'Implantation Station-Service',
        'reprise_station' => 'Reprise Station-Service',
        'implantation_point_conso' => 'Implantation Point Consommateur',
        'reprise_point_conso' => 'Reprise Point Consommateur',
        'implantation_depot_gpl' => 'Implantation Dépôt GPL',
        'implantation_centre_emplisseur' => 'Implantation Centre Emplisseur'
    ];

    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

?>