<?php
/**
 * Contrôleur API Users
 */

class UsersController {
    private $api_key;

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    public function getAll() {
        requirePermission($this->api_key, 'users.read');

        global $conn;

        $actif = $_GET['actif'] ?? null;
        $role = $_GET['role'] ?? null;
        $page = max(1, $_GET['page'] ?? 1);
        $per_page = min(100, $_GET['per_page'] ?? 20);

        $where = ['1=1'];
        $params = [];

        if ($actif !== null) {
            $where[] = 'u.actif = ?';
            $params[] = $actif === 'true' ? 1 : 0;
        }

        if ($role) {
            $where[] = 'r.code = ?';
            $params[] = $role;
        }

        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT
                u.id, u.nom, u.prenom, u.email, u.actif, u.created_at,
                GROUP_CONCAT(r.nom SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id
            ORDER BY u.nom, u.prenom
            LIMIT ? OFFSET ?
        ";

        $params[] = $per_page;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne($id) {
        requirePermission($this->api_key, 'users.read');

        global $conn;

        $stmt = $conn->prepare("
            SELECT
                u.id, u.nom, u.prenom, u.email, u.telephone,
                u.actif, u.created_at, u.updated_at,
                GROUP_CONCAT(r.nom SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            GROUP BY u.id
        ");

        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ApiException('Utilisateur introuvable', 404, 404);
        }

        return $user;
    }

    public function create() {
        requirePermission($this->api_key, 'users.create');

        global $conn;

        $data = getJsonBody();
        validateRequired($data, ['nom', 'prenom', 'email', 'password']);

        $stmt = $conn->prepare("
            INSERT INTO users (nom, prenom, email, password_hash, actif)
            VALUES (?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT)
        ]);

        return [
            'id' => $conn->lastInsertId(),
            'message' => 'Utilisateur créé'
        ];
    }

    public function update($id) {
        requirePermission($this->api_key, 'users.update');

        global $conn;

        $data = getJsonBody();

        $updates = [];
        $params = [];

        $allowed = ['nom', 'prenom', 'email', 'telephone', 'actif'];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            throw new ApiException('Aucune donnée à mettre à jour', 400, 400);
        }

        $params[] = $id;

        $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);

        return ['message' => 'Utilisateur mis à jour'];
    }

    public function delete($id) {
        requirePermission($this->api_key, 'users.delete');

        global $conn;

        $stmt = $conn->prepare("UPDATE users SET actif = 0 WHERE id = ?");
        $stmt->execute([$id]);

        return ['message' => 'Utilisateur désactivé'];
    }
}
