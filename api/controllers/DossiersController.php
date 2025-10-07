<?php
/**
 * Contrôleur API Dossiers
 */

class DossiersController {
    private $api_key;

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Liste des dossiers
     */
    public function getAll() {
        requirePermission($this->api_key, 'dossiers.read');

        global $conn;

        // Filtres
        $statut = $_GET['statut'] ?? null;
        $type_infrastructure = $_GET['type_infrastructure'] ?? null;
        $search = $_GET['search'] ?? null;
        $archive = $_GET['archive'] ?? '0';
        $page = max(1, $_GET['page'] ?? 1);
        $per_page = min(100, $_GET['per_page'] ?? 20);

        $where = ['d.archive = ?'];
        $params = [$archive];

        if ($statut) {
            $where[] = 's.code = ?';
            $params[] = $statut;
        }

        if ($type_infrastructure) {
            $where[] = 'd.type_infrastructure_id = ?';
            $params[] = $type_infrastructure;
        }

        if ($search) {
            $where[] = '(d.numero_dossier LIKE ? OR d.nom_demandeur LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Compter le total
        $count_sql = "
            SELECT COUNT(*) as total
            FROM dossiers d
            LEFT JOIN statuts_dossier s ON d.statut_id = s.id
            WHERE " . implode(' AND ', $where);

        $stmt = $conn->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Récupérer les dossiers
        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT
                d.*,
                s.libelle as statut_libelle,
                s.code as statut_code,
                ti.nom as type_infrastructure_nom,
                CONCAT(u.prenom, ' ', u.nom) as createur_nom
            FROM dossiers d
            LEFT JOIN statuts_dossier s ON d.statut_id = s.id
            LEFT JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $per_page;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $dossiers,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $total,
                'total_pages' => ceil($total / $per_page)
            ]
        ];
    }

    /**
     * Un dossier spécifique
     */
    public function getOne($id) {
        requirePermission($this->api_key, 'dossiers.read');

        global $conn;

        $stmt = $conn->prepare("
            SELECT
                d.*,
                s.libelle as statut_libelle,
                s.code as statut_code,
                s.couleur as statut_couleur,
                ti.nom as type_infrastructure_nom,
                ti.code as type_infrastructure_code,
                CONCAT(u.prenom, ' ', u.nom) as createur_nom,
                u.email as createur_email
            FROM dossiers d
            LEFT JOIN statuts_dossier s ON d.statut_id = s.id
            LEFT JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ");

        $stmt->execute([$id]);
        $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dossier) {
            throw new ApiException('Dossier introuvable', 404, 404);
        }

        // Ajouter les infos complémentaires
        $dossier['nb_documents'] = $this->countDocuments($id);
        $dossier['nb_paiements'] = $this->countPaiements($id);
        $dossier['nb_inspections'] = $this->countInspections($id);

        return $dossier;
    }

    /**
     * Créer un dossier
     */
    public function create() {
        requirePermission($this->api_key, 'dossiers.create');

        global $conn;

        $data = getJsonBody();

        // Validation
        validateRequired($data, [
            'type_infrastructure_id',
            'nom_demandeur',
            'email_demandeur',
            'telephone_demandeur',
            'adresse_siege',
            'ville_implantation',
            'quartier_implantation'
        ]);

        try {
            $conn->beginTransaction();

            // Générer le numéro de dossier
            $numero = $this->generateNumeroDossier();

            // Statut initial
            $stmt = $conn->query("SELECT id FROM statuts_dossier WHERE code = 'brouillon' LIMIT 1");
            $statut_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];

            // Insérer le dossier
            $stmt = $conn->prepare("
                INSERT INTO dossiers
                (numero_dossier, type_infrastructure_id, nom_demandeur, email_demandeur,
                 telephone_demandeur, adresse_siege, ville_implantation, quartier_implantation,
                 statut_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $numero,
                $data['type_infrastructure_id'],
                $data['nom_demandeur'],
                $data['email_demandeur'],
                $data['telephone_demandeur'],
                $data['adresse_siege'],
                $data['ville_implantation'],
                $data['quartier_implantation'],
                $statut_id,
                $this->api_key['user_id']
            ]);

            $dossier_id = $conn->lastInsertId();

            $conn->commit();

            return [
                'id' => $dossier_id,
                'numero_dossier' => $numero,
                'message' => 'Dossier créé avec succès'
            ];

        } catch (Exception $e) {
            $conn->rollBack();
            throw new ApiException('Erreur création dossier: ' . $e->getMessage(), 500, 500);
        }
    }

    /**
     * Mettre à jour un dossier
     */
    public function update($id) {
        requirePermission($this->api_key, 'dossiers.update');

        global $conn;

        $data = getJsonBody();

        // Vérifier que le dossier existe
        $stmt = $conn->prepare("SELECT id FROM dossiers WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new ApiException('Dossier introuvable', 404, 404);
        }

        // Construire la requête UPDATE dynamiquement
        $allowed_fields = [
            'nom_demandeur', 'email_demandeur', 'telephone_demandeur',
            'adresse_siege', 'ville_implantation', 'quartier_implantation',
            'observations'
        ];

        $updates = [];
        $params = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            throw new ApiException('Aucune donnée à mettre à jour', 400, 400);
        }

        $params[] = $id;

        $sql = "UPDATE dossiers SET " . implode(', ', $updates) . " WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return [
            'message' => 'Dossier mis à jour',
            'rows_affected' => $stmt->rowCount()
        ];
    }

    /**
     * Supprimer un dossier (soft delete)
     */
    public function delete($id) {
        requirePermission($this->api_key, 'dossiers.delete');

        global $conn;

        $stmt = $conn->prepare("UPDATE dossiers SET archive = 1, date_archive = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('Dossier introuvable', 404, 404);
        }

        return [
            'message' => 'Dossier archivé'
        ];
    }

    /**
     * Documents d'un dossier
     */
    public function getDocuments($dossier_id) {
        requirePermission($this->api_key, 'documents.read');

        global $conn;

        $stmt = $conn->prepare("
            SELECT
                d.*,
                td.nom as type_nom,
                CONCAT(u.prenom, ' ', u.nom) as uploader_nom
            FROM documents d
            LEFT JOIN types_document td ON d.type_document_id = td.id
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.dossier_id = ?
            ORDER BY d.created_at DESC
        ");

        $stmt->execute([$dossier_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historique d'un dossier
     */
    public function getHistorique($dossier_id) {
        requirePermission($this->api_key, 'dossiers.read');

        global $conn;

        $stmt = $conn->prepare("
            SELECT
                h.*,
                s.libelle as statut_libelle,
                s.code as statut_code,
                CONCAT(u.prenom, ' ', u.nom) as auteur_nom
            FROM historique_dossier h
            LEFT JOIN statuts_dossier s ON h.statut_id = s.id
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.dossier_id = ?
            ORDER BY h.created_at DESC
        ");

        $stmt->execute([$dossier_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Helper methods

    private function countDocuments($dossier_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE dossier_id = ?");
        $stmt->execute([$dossier_id]);
        return $stmt->fetchColumn();
    }

    private function countPaiements($dossier_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM paiements WHERE dossier_id = ?");
        $stmt->execute([$dossier_id]);
        return $stmt->fetchColumn();
    }

    private function countInspections($dossier_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM inspections WHERE dossier_id = ?");
        $stmt->execute([$dossier_id]);
        return $stmt->fetchColumn();
    }

    private function generateNumeroDossier() {
        global $conn;

        $annee = date('Y');
        $prefix = 'SGDI-' . $annee . '-';

        $stmt = $conn->prepare("
            SELECT numero_dossier
            FROM dossiers
            WHERE numero_dossier LIKE ?
            ORDER BY numero_dossier DESC
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            $last_number = (int)substr($last['numero_dossier'], -4);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }

        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }
}
