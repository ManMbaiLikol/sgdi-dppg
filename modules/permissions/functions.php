<?php
/**
 * Fonctions de gestion des permissions granulaires - SGDI
 */

// Récupérer toutes les permissions disponibles
function getAllPermissions() {
    global $pdo;

    $sql = "SELECT * FROM permissions ORDER BY module, nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les permissions groupées par module
function getPermissionsByModule() {
    global $pdo;

    $sql = "SELECT * FROM permissions ORDER BY module, nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouper par module
    $grouped = [];
    foreach ($permissions as $perm) {
        $module = $perm['module'];
        if (!isset($grouped[$module])) {
            $grouped[$module] = [];
        }
        $grouped[$module][] = $perm;
    }

    return $grouped;
}

// Récupérer les permissions d'un utilisateur
function getUserPermissions($user_id) {
    global $pdo;

    $sql = "SELECT p.*
            FROM permissions p
            INNER JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
            ORDER BY p.module, p.nom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les codes de permissions d'un utilisateur (pour vérifications rapides)
function getUserPermissionCodes($user_id) {
    global $pdo;

    $sql = "SELECT p.code
            FROM permissions p
            INNER JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Vérifier si un utilisateur a une permission spécifique
function userHasPermission($user_id, $permission_code) {
    global $pdo;

    $sql = "SELECT COUNT(*)
            FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.code = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $permission_code]);

    return $stmt->fetchColumn() > 0;
}

// Vérifier si l'utilisateur connecté a une permission
function hasPermission($permission_code) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Les admins ont toutes les permissions
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    return userHasPermission($_SESSION['user_id'], $permission_code);
}

// Vérifier si l'utilisateur a l'une des permissions listées
function hasAnyPermission($permission_codes) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Les admins ont toutes les permissions
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    foreach ($permission_codes as $code) {
        if (userHasPermission($_SESSION['user_id'], $code)) {
            return true;
        }
    }

    return false;
}

// Attribuer une permission à un utilisateur
function assignPermission($user_id, $permission_id, $assigned_by) {
    global $pdo;

    try {
        $sql = "INSERT INTO user_permissions (user_id, permission_id, accordee_par)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE accordee_par = ?, date_attribution = NOW()";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id, $permission_id, $assigned_by, $assigned_by]);
    } catch (PDOException $e) {
        return false;
    }
}

// Révoquer une permission d'un utilisateur
function revokePermission($user_id, $permission_id) {
    global $pdo;

    $sql = "DELETE FROM user_permissions
            WHERE user_id = ? AND permission_id = ?";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id, $permission_id]);
}

// Révoquer toutes les permissions d'un utilisateur
function revokeAllPermissions($user_id) {
    global $pdo;

    $sql = "DELETE FROM user_permissions WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id]);
}

// Synchroniser les permissions d'un utilisateur (remplacer toutes ses permissions)
function syncUserPermissions($user_id, $permission_ids, $assigned_by) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Supprimer toutes les permissions actuelles
        revokeAllPermissions($user_id);

        // Ajouter les nouvelles permissions
        if (!empty($permission_ids)) {
            $sql = "INSERT INTO user_permissions (user_id, permission_id, accordee_par)
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($permission_ids as $permission_id) {
                $stmt->execute([$user_id, $permission_id, $assigned_by]);
            }
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

// Obtenir le nombre de permissions par utilisateur
function getUserPermissionsCount($user_id) {
    global $pdo;

    $sql = "SELECT COUNT(*) FROM user_permissions WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    return $stmt->fetchColumn();
}

// Obtenir les statistiques des permissions
function getPermissionsStats() {
    global $pdo;

    $stats = [];

    // Total de permissions disponibles
    $sql = "SELECT COUNT(*) FROM permissions";
    $stats['total_permissions'] = $pdo->query($sql)->fetchColumn();

    // Nombre d'utilisateurs avec permissions
    $sql = "SELECT COUNT(DISTINCT user_id) FROM user_permissions";
    $stats['users_with_permissions'] = $pdo->query($sql)->fetchColumn();

    // Permissions par module
    $sql = "SELECT module, COUNT(*) as count
            FROM permissions
            GROUP BY module
            ORDER BY module";
    $stmt = $pdo->query($sql);
    $stats['permissions_by_module'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
}

// Récupérer les utilisateurs avec leurs permissions
function getUsersWithPermissions() {
    global $pdo;

    $sql = "SELECT u.id, u.username, u.nom, u.prenom, u.email, u.role, u.actif,
                   COUNT(up.id) as permissions_count
            FROM users u
            LEFT JOIN user_permissions up ON u.id = up.user_id
            GROUP BY u.id, u.username, u.nom, u.prenom, u.email, u.role, u.actif
            ORDER BY u.nom, u.prenom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtenir les détails complets des permissions d'un utilisateur
function getUserPermissionsDetails($user_id) {
    global $pdo;

    $sql = "SELECT p.*, up.date_attribution,
                   u.nom as accordee_par_nom, u.prenom as accordee_par_prenom
            FROM permissions p
            INNER JOIN user_permissions up ON p.id = up.permission_id
            LEFT JOIN users u ON up.accordee_par = u.id
            WHERE up.user_id = ?
            ORDER BY p.module, p.nom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Copier les permissions d'un utilisateur vers un autre
function copyPermissions($from_user_id, $to_user_id, $assigned_by) {
    $permissions = getUserPermissions($from_user_id);
    $permission_ids = array_column($permissions, 'id');

    return syncUserPermissions($to_user_id, $permission_ids, $assigned_by);
}

// Obtenir les permissions recommandées par rôle
function getRecommendedPermissionsByRole($role) {
    $recommendations = [
        'chef_service' => [
            'dossiers.create', 'dossiers.view', 'dossiers.edit', 'dossiers.list', 'dossiers.view_all',
            'commission.create', 'commission.view', 'commission.edit',
            'visa.chef_service', 'visa.view',
            'documents.view', 'documents.upload', 'documents.download',
            'huitaine.view', 'huitaine.create',
            'gps.view', 'gps.edit', 'gps.import', 'gps.validate',
            'carte.view', 'inspections.view'
        ],
        'billeteur' => [
            'dossiers.view', 'dossiers.list',
            'paiements.view', 'paiements.create', 'paiements.edit', 'paiements.receipt',
            'documents.view', 'documents.download'
        ],
        'cadre_daj' => [
            'dossiers.view', 'dossiers.list',
            'daj.view', 'daj.create', 'daj.edit', 'daj.validate',
            'documents.view', 'documents.download',
            'huitaine.view', 'huitaine.regularize'
        ],
        'cadre_dppg' => [
            'dossiers.view', 'dossiers.list',
            'inspections.view', 'inspections.create', 'inspections.edit', 'inspections.print',
            'documents.view', 'documents.upload', 'documents.download',
            'huitaine.view', 'huitaine.regularize',
            'gps.view', 'carte.view'
        ],
        'chef_commission' => [
            'dossiers.view', 'dossiers.list',
            'commission.view', 'commission.validate',
            'inspections.view', 'inspections.validate',
            'documents.view', 'documents.download'
        ],
        'sous_directeur' => [
            'dossiers.view', 'dossiers.list', 'dossiers.view_all',
            'visa.sous_directeur', 'visa.view',
            'documents.view', 'documents.download',
            'rapports.view'
        ],
        'directeur' => [
            'dossiers.view', 'dossiers.list', 'dossiers.view_all', 'dossiers.export',
            'visa.directeur', 'visa.view',
            'decisions.view', 'decisions.transmit',
            'documents.view', 'documents.download',
            'rapports.view', 'rapports.export_excel', 'rapports.export_pdf', 'rapports.statistics',
            'carte.view', 'carte.export'
        ],
        'ministre' => [
            'dossiers.view', 'dossiers.list', 'dossiers.view_all',
            'decisions.view', 'decisions.create',
            'documents.view', 'documents.download',
            'rapports.view'
        ],
        'admin' => [] // Les admins ont toutes les permissions automatiquement
    ];

    return $recommendations[$role] ?? [];
}

// Appliquer les permissions recommandées à un utilisateur
function applyRecommendedPermissions($user_id, $role, $assigned_by) {
    global $pdo;

    $recommended_codes = getRecommendedPermissionsByRole($role);

    if (empty($recommended_codes)) {
        return true; // Rien à faire pour les admins
    }

    // Récupérer les IDs des permissions recommandées
    $placeholders = str_repeat('?,', count($recommended_codes) - 1) . '?';
    $sql = "SELECT id FROM permissions WHERE code IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($recommended_codes);

    $permission_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return syncUserPermissions($user_id, $permission_ids, $assigned_by);
}
?>
