<?php
// Fonctions pour la gestion des utilisateurs - SGDI MVP

// Récupérer les utilisateurs avec filtres
function getUsersWithFilters($filters = [], $limit = 20, $offset = 0) {
    global $pdo;

    $conditions = [];
    $params = [];

    // Filtrage par recherche
    if (!empty($filters['search'])) {
        $conditions[] = "(nom LIKE ? OR prenom LIKE ? OR username LIKE ? OR email LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    // Filtrage par rôle
    if (!empty($filters['role'])) {
        $conditions[] = "role = ?";
        $params[] = $filters['role'];
    }

    // Filtrage par statut actif
    if ($filters['actif'] !== '') {
        $conditions[] = "actif = ?";
        $params[] = $filters['actif'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT id, username, email, nom, prenom, telephone, role, actif,
                   date_creation, derniere_connexion
            FROM users
            $where_clause
            ORDER BY nom, prenom
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Compter les utilisateurs avec filtres
function countUsersWithFilters($filters = []) {
    global $pdo;

    $conditions = [];
    $params = [];

    // Filtrage par recherche
    if (!empty($filters['search'])) {
        $conditions[] = "(nom LIKE ? OR prenom LIKE ? OR username LIKE ? OR email LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    // Filtrage par rôle
    if (!empty($filters['role'])) {
        $conditions[] = "role = ?";
        $params[] = $filters['role'];
    }

    // Filtrage par statut actif
    if ($filters['actif'] !== '') {
        $conditions[] = "actif = ?";
        $params[] = $filters['actif'];
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT COUNT(*) FROM users $where_clause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Obtenir les statistiques des utilisateurs
function getUserStats() {
    global $pdo;

    $stats = [
        'total' => 0,
        'actifs' => 0,
        'inactifs' => 0,
        'admins' => 0
    ];

    // Total
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total'] = $stmt->fetchColumn();

    // Actifs
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE actif = 1");
    $stats['actifs'] = $stmt->fetchColumn();

    // Inactifs
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE actif = 0");
    $stats['inactifs'] = $stmt->fetchColumn();

    // Administrateurs
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stats['admins'] = $stmt->fetchColumn();

    return $stats;
}

// Obtenir un utilisateur par ID
function getUserById($user_id) {
    global $pdo;

    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Obtenir la couleur CSS pour un rôle
function getRoleColor($role) {
    $colors = [
        'admin' => 'danger',
        'chef_service' => 'primary',
        'billeteur' => 'success',
        'chef_commission' => 'info',
        'cadre_daj' => 'warning',
        'cadre_dppg' => 'secondary',
        'sous_directeur' => 'dark',
        'directeur' => 'primary',
        'cabinet' => 'danger',
        'lecteur_public' => 'light'
    ];

    return $colors[$role] ?? 'secondary';
}

// Mettre à jour un utilisateur
function updateUser($user_id, $data) {
    global $pdo;

    try {
        $fields = [];
        $params = [];

        // Champs modifiables
        $allowed_fields = ['username', 'email', 'nom', 'prenom', 'telephone', 'role', 'actif'];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);

    } catch (Exception $e) {
        error_log("Erreur mise à jour utilisateur: " . $e->getMessage());
        return false;
    }
}

// Vérifier si un username existe déjà
function usernameExists($username, $exclude_user_id = null) {
    global $pdo;

    $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
    $params = [$username];

    if ($exclude_user_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

// Vérifier si un email existe déjà
function emailExists($email, $exclude_user_id = null) {
    global $pdo;

    if (empty($email)) return false;

    $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
    $params = [$email];

    if ($exclude_user_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

// Créer un nouvel utilisateur avec validation
function createUserWithValidation($data) {
    global $pdo;

    $errors = [];

    // Validation des champs requis
    $required_fields = ['username', 'email', 'password', 'nom', 'prenom', 'role'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ $field est requis";
        }
    }

    // Validation username unique
    if (!empty($data['username']) && usernameExists($data['username'])) {
        $errors[] = "Ce nom d'utilisateur existe déjà";
    }

    // Validation email unique
    if (!empty($data['email']) && emailExists($data['email'])) {
        $errors[] = "Cette adresse email existe déjà";
    }

    // Validation email format
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }

    // Validation mot de passe
    if (!empty($data['password']) && !isStrongPassword($data['password'])) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres";
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Créer l'utilisateur
    try {
        $sql = "INSERT INTO users (username, email, password, nom, prenom, telephone, role, actif)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['username'],
            $data['email'],
            $hashed_password,
            $data['nom'],
            $data['prenom'],
            $data['telephone'] ?? null,
            $data['role']
        ]);

        if ($success) {
            return ['success' => true, 'user_id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'errors' => ['Erreur lors de la création de l\'utilisateur']];
        }

    } catch (Exception $e) {
        error_log("Erreur création utilisateur: " . $e->getMessage());
        return ['success' => false, 'errors' => ['Erreur technique lors de la création']];
    }
}

// Mettre à jour la dernière connexion
function updateLastLogin($user_id) {
    global $pdo;

    try {
        $sql = "UPDATE users SET derniere_connexion = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Erreur mise à jour dernière connexion: " . $e->getMessage());
        return false;
    }
}

// Supprimer un utilisateur (soft delete - désactivation)
function deleteUser($user_id) {
    global $pdo;

    try {
        // On ne supprime pas vraiment, on désactive
        $sql = "UPDATE users SET actif = 0 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Erreur suppression utilisateur: " . $e->getMessage());
        return false;
    }
}

// Obtenir la liste des rôles disponibles
function getAvailableRoles() {
    return [
        'admin' => 'Administrateur Système',
        'chef_service' => 'Chef de Service SDTD',
        'billeteur' => 'Billeteur DPPG',
        'chef_commission' => 'Chef de Commission',
        'cadre_daj' => 'Cadre DAJ',
        'cadre_dppg' => 'Cadre DPPG (Inspecteur)',
        'sous_directeur' => 'Sous-Directeur SDTD',
        'directeur' => 'Directeur DPPG',
        'cabinet' => 'Cabinet/Secrétariat Ministre',
        'lecteur_public' => 'Lecteur Public'
    ];
}

// Obtenir les statistiques d'activité d'un utilisateur
function getUserActivityStats($user_id) {
    global $pdo;

    $stats = [
        'dossiers_crees' => 0,
        'actions_effectuees' => 0
    ];

    try {
        // Compter les dossiers créés par cet utilisateur
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['dossiers_crees'] = $stmt->fetchColumn();

        // Compter les actions dans l'historique
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM historique WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['actions_effectuees'] = $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Erreur récupération stats utilisateur: " . $e->getMessage());
    }

    return $stats;
}

// Obtenir l'activité récente d'un utilisateur
function getUserRecentActivity($user_id, $limit = 10) {
    global $pdo;

    try {
        $sql = "SELECT h.*, d.numero as dossier_numero
                FROM historique h
                LEFT JOIN dossiers d ON h.dossier_id = d.id
                WHERE h.user_id = ?
                ORDER BY h.date_action DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur récupération activité utilisateur: " . $e->getMessage());
        return [];
    }
}

// Obtenir les permissions d'un rôle
function getRolePermissions($role) {
    $permissions = [
        'admin' => [
            'Gestion complète des utilisateurs',
            'Accès à tous les dossiers',
            'Configuration du système',
            'Consultation des logs',
            'Gestion des statistiques'
        ],
        'chef_service' => [
            'Création de dossiers',
            'Constitution des commissions',
            'Premier niveau de visa',
            'Gestion des assignations',
            'Consultation de tous les dossiers'
        ],
        'billeteur' => [
            'Enregistrement des paiements',
            'Génération des reçus',
            'Consultation des notes de frais',
            'Validation financière'
        ],
        'chef_commission' => [
            'Coordination des visites',
            'Validation des rapports d\'inspection',
            'Gestion de l\'équipe de commission'
        ],
        'cadre_daj' => [
            'Analyse juridique des dossiers',
            'Validation de la conformité réglementaire',
            'Avis juridiques'
        ],
        'cadre_dppg' => [
            'Inspections d\'infrastructure',
            'Rédaction de rapports techniques',
            'Contrôle de conformité',
            'Évaluation technique'
        ],
        'sous_directeur' => [
            'Deuxième niveau de visa',
            'Supervision des processus',
            'Validation intermédiaire'
        ],
        'directeur' => [
            'Troisième niveau de visa',
            'Transmission ministérielle',
            'Décisions de direction',
            'Supervision générale'
        ],
        'cabinet' => [
            'Décision ministérielle finale',
            'Autorité d\'approbation/refus',
            'Publication au registre public'
        ],
        'lecteur_public' => [
            'Consultation du registre public',
            'Recherche de dossiers publics',
            'Téléchargement des décisions'
        ]
    ];

    return $permissions[$role] ?? [];
}

// Obtenir la couleur pour un type d'activité
function getActivityTypeColor($type) {
    $colors = [
        'creation' => 'primary',
        'modification' => 'warning',
        'validation' => 'success',
        'rejection' => 'danger',
        'commission' => 'info',
        'paiement' => 'success',
        'inspection' => 'secondary',
        'decision' => 'dark',
        'login' => 'light'
    ];

    return $colors[$type] ?? 'secondary';
}

// Obtenir l'icône pour un type d'activité
function getActivityTypeIcon($type) {
    $icons = [
        'creation' => 'plus-circle',
        'modification' => 'edit',
        'validation' => 'check-circle',
        'rejection' => 'times-circle',
        'commission' => 'users',
        'paiement' => 'money-bill',
        'inspection' => 'search',
        'decision' => 'gavel',
        'login' => 'sign-in-alt'
    ];

    return $icons[$type] ?? 'circle';
}

// Vérifier si un utilisateur doit changer son mot de passe
function mustChangePassword($user_id) {
    global $pdo;

    try {
        // Vérifier si la colonne existe
        $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
        $has_column = $columns_check->rowCount() > 0;

        if ($has_column) {
            $sql = "SELECT force_password_change FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetchColumn();
            return $result == 1;
        }

        return false; // Si la colonne n'existe pas, pas de changement forcé
    } catch (Exception $e) {
        error_log("Erreur vérification changement mot de passe: " . $e->getMessage());
        return false;
    }
}

// Marquer le changement de mot de passe comme effectué
function clearPasswordChangeFlag($user_id) {
    global $pdo;

    try {
        // Vérifier si la colonne existe
        $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
        $has_column = $columns_check->rowCount() > 0;

        if ($has_column) {
            $sql = "UPDATE users SET force_password_change = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$user_id]);
        }

        return true; // Si la colonne n'existe pas, considérer comme réussi
    } catch (Exception $e) {
        error_log("Erreur clear flag changement mot de passe: " . $e->getMessage());
        return false;
    }
}

// Obtenir l'historique des réinitialisations de mot de passe d'un utilisateur
function getPasswordResetHistory($user_id, $limit = 5) {
    global $pdo;

    try {
        $sql = "SELECT la.*, u_admin.nom as admin_nom, u_admin.prenom as admin_prenom
                FROM logs_activite la
                JOIN users u_admin ON la.user_id = u_admin.id
                WHERE la.action = 'password_reset'
                AND la.description LIKE CONCAT('%', ?, '%')
                ORDER BY la.date_action DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Erreur récupération historique réinitialisation: " . $e->getMessage());
        return [];
    }
}

// Invalider toutes les sessions actives d'un utilisateur (simulation)
function invalidateUserSessions($user_id) {
    // Note: En production, ceci nécessiterait un système de gestion des sessions centralisé
    // Pour le MVP, on log l'action
    global $pdo;

    try {
        // Vérifier si la table logs_activite existe
        $tables_check = $pdo->query("SHOW TABLES LIKE 'logs_activite'");
        $table_exists = $tables_check->rowCount() > 0;

        if ($table_exists) {
            $sql = "INSERT INTO logs_activite (user_id, action, description, date_action, ip_address)
                    VALUES (?, 'session_invalidation', 'Sessions invalidées après réinitialisation mot de passe', NOW(), ?)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? '']);
        }

        return true; // Si la table n'existe pas, considérer comme réussi
    } catch (Exception $e) {
        error_log("Erreur invalidation sessions: " . $e->getMessage());
        return false;
    }
}

// Fonction pour envoyer une notification email (simulation)
function sendPasswordResetNotification($user_id, $admin_name) {
    global $pdo;

    try {
        // Récupérer les informations de l'utilisateur
        $sql = "SELECT nom, prenom, email FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Dans un vrai système, on utiliserait PHPMailer ou un service d'email
            // Pour le MVP, on log l'action
            $description = sprintf(
                "Notification email envoyée à %s %s (%s) - Mot de passe réinitialisé par %s",
                $user['nom'],
                $user['prenom'],
                $user['email'],
                $admin_name
            );

            // Vérifier si la table logs_activite existe
            $tables_check = $pdo->query("SHOW TABLES LIKE 'logs_activite'");
            $table_exists = $tables_check->rowCount() > 0;

            if ($table_exists) {
                $sql = "INSERT INTO logs_activite (user_id, action, description, date_action, ip_address)
                        VALUES (?, 'email_notification', ?, NOW(), ?)";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([$user_id, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            return true; // Si la table n'existe pas, considérer comme réussi
        }

        return false;
    } catch (Exception $e) {
        error_log("Erreur envoi notification email: " . $e->getMessage());
        return false;
    }
}

// Obtenir les statistiques de sécurité des mots de passe
function getPasswordSecurityStats() {
    global $pdo;

    $stats = [
        'users_must_change' => 0,
        'recent_resets' => 0,
        'never_changed' => 0
    ];

    try {
        // Utilisateurs qui doivent changer leur mot de passe
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE force_password_change = 1 AND actif = 1");
        $stats['users_must_change'] = $stmt->fetchColumn();

        // Réinitialisations récentes (7 derniers jours)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM logs_activite
            WHERE action = 'password_reset'
            AND date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['recent_resets'] = $stmt->fetchColumn();

        // Utilisateurs n'ayant jamais changé leur mot de passe
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM users
            WHERE password_reset_date IS NULL
            AND date_creation < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND actif = 1
        ");
        $stats['never_changed'] = $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Erreur récupération stats sécurité: " . $e->getMessage());
    }

    return $stats;
}
?>