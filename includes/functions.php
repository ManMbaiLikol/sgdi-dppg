<?php
// Fonctions utilitaires - SGDI MVP

/**
 * Nettoyer les entrées utilisateur (pour traitement/stockage)
 * À utiliser AVANT insertion en base de données
 * NE PAS utiliser pour l'affichage
 */
function cleanInput($data) {
    if ($data === null) {
        return '';
    }
    // Seulement trim - pas de htmlspecialchars car les prepared statements gèrent la sécurité SQL
    return trim($data);
}

/**
 * Sécuriser les données pour l'affichage HTML
 * À utiliser UNIQUEMENT pour l'affichage
 * NE PAS utiliser avant insertion en base
 */
function sanitize($data) {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Alias de cleanInput pour la compatibilité
 */
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Générer un numéro de dossier unique
function genererNumeroDossier($type_infrastructure) {
    $prefixes = [
        'station_service' => 'SS',
        'point_consommateur' => 'PC',
        'depot_gpl' => 'DG',
        'centre_emplisseur' => 'CE'
    ];

    $prefix = $prefixes[$type_infrastructure] ?? 'XX';
    $year = date('Y');
    $timestamp = date('mdHis');

    return $prefix . $year . $timestamp;
}

// Formater le statut pour l'affichage
function getStatutLabel($statut) {
    $labels = [
        'brouillon' => 'Brouillon',
        'cree' => 'Créé',
        'en_cours' => 'En cours',
        'note_transmise' => 'Note transmise',
        'paye' => 'Payé',
        'en_huitaine' => 'En huitaine',
        'analyse_daj' => 'Analysé',
        'inspecte' => 'Inspecté',
        'validation_commission' => 'Validé par la commission',
        'visa_chef_service' => 'Visa Chef Service',
        'visa_sous_directeur' => 'Visa Sous-Directeur',
        'visa_directeur' => 'Visa Directeur',
        'valide' => 'Validé',
        'decide' => 'Décidé',
        'autorise' => 'Autorisé',
        'rejete' => 'Rejeté',
        'ferme' => 'Fermé',
        'suspendu' => 'Suspendu'
    ];

    return $labels[$statut] ?? $statut;
}

// Obtenir la classe CSS pour le statut
function getStatutClass($statut) {
    $classes = [
        'brouillon' => 'secondary',
        'cree' => 'info',
        'en_cours' => 'warning',
        'note_transmise' => 'info',
        'paye' => 'primary',
        'en_huitaine' => 'danger',
        'analyse_daj' => 'info',
        'inspecte' => 'primary',
        'validation_commission' => 'success',
        'visa_chef_service' => 'info',
        'visa_sous_directeur' => 'info',
        'visa_directeur' => 'info',
        'valide' => 'primary',
        'decide' => 'dark',
        'autorise' => 'success',
        'rejete' => 'danger',
        'ferme' => 'secondary',
        'suspendu' => 'warning'
    ];

    return $classes[$statut] ?? 'secondary';
}

// Formater le statut opérationnel pour l'affichage
function getStatutOperationnelLabel($statut_operationnel) {
    $labels = [
        'operationnel' => 'Opérationnel',
        'ferme_temporaire' => 'Fermé temporairement',
        'ferme_definitif' => 'Fermé définitivement',
        'demantele' => 'Démantelé'
    ];

    return $labels[$statut_operationnel] ?? $statut_operationnel;
}

// Obtenir la classe CSS pour le statut opérationnel
function getStatutOperationnelClass($statut_operationnel) {
    $classes = [
        'operationnel' => 'success',
        'ferme_temporaire' => 'warning',
        'ferme_definitif' => 'danger',
        'demantele' => 'dark'
    ];

    return $classes[$statut_operationnel] ?? 'secondary';
}

// Obtenir l'icône pour le statut opérationnel
function getStatutOperationnelIcon($statut_operationnel) {
    $icons = [
        'operationnel' => 'fas fa-check-circle',
        'ferme_temporaire' => 'fas fa-pause-circle',
        'ferme_definitif' => 'fas fa-times-circle',
        'demantele' => 'fas fa-trash-alt'
    ];

    return $icons[$statut_operationnel] ?? 'fas fa-question-circle';
}

// Formater le type d'infrastructure
function getTypeLabel($type, $sous_type = null) {
    $types = [
        'station_service' => 'Station-service',
        'point_consommateur' => 'Point consommateur',
        'depot_gpl' => 'Dépôt GPL',
        'centre_emplisseur' => 'Centre emplisseur'
    ];

    $sous_types = [
        'implantation' => 'Implantation',
        'reprise' => 'Reprise',
        'remodelage' => 'Remodelage'
    ];

    $label = $types[$type] ?? $type;
    if ($sous_type) {
        $label = $sous_types[$sous_type] . ' ' . strtolower($label);
    }

    return $label;
}

// Obtenir le nom du rôle
function getRoleLabel($role) {
    $roles = [
        'chef_service' => 'Chef Service SDTD',
        'cadre_dppg' => 'Cadre DPPG',
        'cadre_daj' => 'Cadre DAJ',
        'billeteur' => 'Billeteur',
        'directeur' => 'Directeur DPPG',
        'chef_commission' => 'Chef de Commission',
        'admin' => 'Administrateur'
    ];

    return $roles[$role] ?? $role;
}

// Vérifier les permissions selon le rôle
function peutAcceder($action, $role, $statut_dossier = null) {
    $permissions = [
        'chef_service' => ['creer_dossier', 'voir_tous_dossiers', 'constituer_commission', 'modifier_dossier'],
        'cadre_dppg' => ['voir_dossiers_assigne', 'faire_inspection', 'rediger_rapport'],
        'cadre_daj' => ['voir_dossiers_assigne', 'analyser_dossier'],
        'billeteur' => ['enregistrer_paiement', 'voir_dossiers_paiement'],
        'chef_commission' => ['voir_dossiers_commission', 'valider_inspection'],
        'directeur' => ['valider_rapport', 'voir_tous_dossiers', 'prendre_decision'],
        'admin' => ['gerer_utilisateurs', 'voir_tous_dossiers', 'admin_system']
    ];

    return in_array($action, $permissions[$role] ?? []);
}

// Logger une action dans l'historique
function logAction($pdo, $dossier_id, $action, $description, $user_id, $ancien_statut = null, $nouveau_statut = null) {
    $sql = "INSERT INTO historique (dossier_id, action, description, ancien_statut, nouveau_statut, user_id)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$dossier_id, $action, $description, $ancien_statut, $nouveau_statut, $user_id]);
}

// Formater une date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Formater une date avec heure
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Vérifier un token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rediriger avec message flash
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    header("Location: $url");
    exit;
}

// Afficher un message flash
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Valider un fichier uploadé
function validateFile($file, $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], $max_size = 5242880) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'upload du fichier";
        return $errors;
    }

    if ($file['size'] > $max_size) {
        $errors[] = "Le fichier est trop volumineux (max 5MB)";
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        $errors[] = "Type de fichier non autorisé. Types acceptés: " . implode(', ', $allowed_types);
    }

    // Vérifier le MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = "Type MIME non autorisé";
    }

    return $errors;
}

/**
 * Ajouter une entrée dans l'historique des dossiers
 */
function addHistoriqueDossier($dossier_id, $user_id, $action, $description, $ancien_statut = null, $nouveau_statut = null) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO historique (dossier_id, user_id, action, description, ancien_statut, nouveau_statut)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $dossier_id,
            $user_id,
            $action,
            $description,
            $ancien_statut,
            $nouveau_statut
        ]);

    } catch (Exception $e) {
        error_log("Erreur ajout historique: " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour le statut d'un dossier avec historique
 */
function updateStatutDossier($dossier_id, $nouveau_statut, $description = '') {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Récupérer l'ancien statut
        $stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier_id]);
        $ancien_statut = $stmt->fetchColumn();

        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE dossiers SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $dossier_id]);

        // Ajouter à l'historique
        addHistoriqueDossier($dossier_id, $_SESSION['user_id'], 'changement_statut',
                           $description ?: "Changement de statut: $ancien_statut → $nouveau_statut",
                           $ancien_statut, $nouveau_statut);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Erreur mise à jour statut: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une notification pour un utilisateur ou un rôle
 */
function createNotification($dossier_id, $user_role, $message, $type = 'info') {
    global $pdo;

    try {
        // Pour l'instant, on peut simplement logger ou ignorer
        // La table notifications pourra être créée plus tard
        error_log("Notification ($type) pour $user_role: $message (dossier $dossier_id)");
        return true;

    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir la liste des régions du Cameroun
 */
function getRegions() {
    return [
        'Adamaoua',
        'Centre',
        'Est',
        'Extrême-Nord',
        'Littoral',
        'Nord',
        'Nord-Ouest',
        'Ouest',
        'Sud',
        'Sud-Ouest'
    ];
}
?>