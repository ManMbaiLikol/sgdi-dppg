<?php
// Fonctions avancées d'envoi d'emails avec templates
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

/**
 * Rendre un template d'email avec des variables
 */
function renderEmailTemplate($template_name, $variables = []) {
    $template_path = __DIR__ . '/email_templates/' . $template_name . '.php';

    if (!file_exists($template_path)) {
        error_log("Template email non trouvé: $template_name");
        return false;
    }

    // Extraire les variables
    extract($variables);

    // Capturer le rendu du template
    ob_start();
    $html = include $template_path;
    if ($html === false) {
        $html = ob_get_clean();
    }

    // Remplacer les variables dans le template
    foreach ($variables as $key => $value) {
        $html = str_replace('{' . $key . '}', $value, $html);
    }

    return $html;
}

/**
 * Notifier après paiement enregistré
 */
function notifierPaiementEnregistre($dossier_id) {
    global $pdo;

    // Récupérer les infos du dossier et du paiement
    $sql = "SELECT d.*, u.email, u.nom, u.prenom, p.montant, p.reference, p.mode_paiement,
            DATE_FORMAT(p.date_paiement, '%d/%m/%Y') as date_paiement_format
            FROM dossiers d
            INNER JOIN users u ON d.user_id = u.id
            INNER JOIN paiements p ON d.id = p.dossier_id
            WHERE d.id = ?
            ORDER BY p.date_paiement DESC
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    $data = $stmt->fetch();

    if (!$data) return false;

    $variables = [
        'prenom' => $data['prenom'],
        'nom' => $data['nom'],
        'numero_dossier' => $data['numero'],
        'type_infrastructure' => getTypeInfrastructureLabel($data['type_infrastructure']),
        'montant' => number_format($data['montant'], 0, ',', ' '),
        'date_paiement' => $data['date_paiement_format'],
        'mode_paiement' => ucfirst($data['mode_paiement']),
        'reference_paiement' => $data['reference'],
        'lien_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ];

    $html = renderEmailTemplate('paiement_enregistre', $variables);
    $subject = 'Paiement enregistré - Dossier ' . $data['numero'];

    return sendEmail($data['email'], $subject, $html, true);
}

/**
 * Notifier après visa accordé
 */
function notifierVisaAccorde($dossier_id, $role_viseur, $prochaine_etape, $observations = '') {
    global $pdo;

    // Récupérer les infos du dossier
    $sql = "SELECT d.*, u.email, u.nom, u.prenom
            FROM dossiers d
            INNER JOIN users u ON d.user_id = u.id
            WHERE d.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    $data = $stmt->fetch();

    if (!$data) return false;

    $obs_html = '';
    if ($observations) {
        $obs_html = '<div class="alert alert-info">
            <strong>Observations:</strong><br>' . nl2br(htmlspecialchars($observations)) . '
        </div>';
    }

    $variables = [
        'prenom' => $data['prenom'],
        'nom' => $data['nom'],
        'numero_dossier' => $data['numero'],
        'type_infrastructure' => getTypeInfrastructureLabel($data['type_infrastructure']),
        'nom_demandeur' => $data['nom_demandeur'],
        'role_viseur' => getRoleLabel($role_viseur),
        'date_visa' => date('d/m/Y à H:i'),
        'prochaine_etape' => $prochaine_etape,
        'observations' => $obs_html,
        'lien_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ];

    $html = renderEmailTemplate('visa_accorde', $variables);
    $subject = 'Visa accordé - Dossier ' . $data['numero'];

    return sendEmail($data['email'], $subject, $html, true);
}

/**
 * Notifier décision ministérielle
 */
function notifierDecisionMinisterielle($dossier_id) {
    global $pdo;

    // Récupérer les infos du dossier et de la décision
    $sql = "SELECT d.*, u.email, u.nom, u.prenom, dec.decision, dec.reference_decision, dec.observations,
            DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format
            FROM dossiers d
            INNER JOIN users u ON d.user_id = u.id
            INNER JOIN decisions dec ON d.id = dec.dossier_id
            WHERE d.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    $data = $stmt->fetch();

    if (!$data) return false;

    $est_approuve = ($data['decision'] === 'approuve');

    $obs_html = '';
    if ($data['observations']) {
        $obs_html = '<div class="alert alert-info">
            <strong>Observations:</strong><br>' . nl2br(htmlspecialchars($data['observations'])) . '
        </div>';
    }

    $msg_supplementaire = '';
    if ($est_approuve) {
        $msg_supplementaire = '<div class="alert alert-success">
            <strong>✓ Félicitations !</strong><br>
            L\'implantation est autorisée. Le dossier a été publié dans le registre public des infrastructures pétrolières.
            Vous pouvez maintenant procéder aux démarches de mise en service.
        </div>';
    } else {
        $msg_supplementaire = '<div class="alert alert-danger">
            <strong>Information importante:</strong><br>
            Le dossier a été refusé. Pour toute question, veuillez contacter la Direction des Produits Pétroliers et Gaziers.
        </div>';
    }

    $variables = [
        'prenom' => $data['prenom'],
        'nom' => $data['nom'],
        'numero_dossier' => $data['numero'],
        'type_infrastructure' => getTypeInfrastructureLabel($data['type_infrastructure']),
        'nom_demandeur' => $data['nom_demandeur'],
        'localisation' => ($data['ville'] ?? '') . ', ' . ($data['region'] ?? ''),
        'decision' => $est_approuve ? 'APPROUVÉ' : 'REFUSÉ',
        'couleur_decision' => $est_approuve ? '#4CAF50' : '#f44336',
        'type_alert' => $est_approuve ? 'success' : 'danger',
        'icone' => $est_approuve ? '✓' : '✗',
        'reference_decision' => $data['reference_decision'],
        'date_decision' => $data['date_decision_format'],
        'observations' => $obs_html,
        'message_supplementaire' => $msg_supplementaire,
        'lien_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ];

    $html = renderEmailTemplate('decision_ministerielle', $variables);
    $subject = 'Décision ministérielle - Dossier ' . $data['numero'] . ' - ' . ($est_approuve ? 'APPROUVÉ' : 'REFUSÉ');

    return sendEmail($data['email'], $subject, $html, true);
}

/**
 * Notifier alerte huitaine
 */
function notifierAlerteHuitaine($dossier_id, $jours_restants) {
    global $pdo;

    // Récupérer les infos
    $sql = "SELECT d.*, u.email, u.nom, u.prenom, h.motif, h.action_requise,
            DATE_FORMAT(h.date_limite, '%d/%m/%Y') as date_limite_format
            FROM dossiers d
            INNER JOIN users u ON d.user_id = u.id
            INNER JOIN huitaines h ON d.id = h.dossier_id
            WHERE d.id = ? AND h.statut = 'active'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    $data = $stmt->fetch();

    if (!$data) return false;

    $variables = [
        'prenom' => $data['prenom'],
        'nom' => $data['nom'],
        'numero_dossier' => $data['numero'],
        'type_infrastructure' => getTypeInfrastructureLabel($data['type_infrastructure']),
        'nom_demandeur' => $data['nom_demandeur'],
        'motif_huitaine' => $data['motif'],
        'date_limite' => $data['date_limite_format'],
        'jours_restants' => $jours_restants,
        'action_requise' => $data['action_requise'],
        'lien_dossier' => url('modules/huitaine/regulariser.php?id=' . $dossier_id)
    ];

    $html = renderEmailTemplate('huitaine_alerte', $variables);
    $subject = '⚠ Alerte Huitaine - ' . $jours_restants . ' jour(s) restant(s) - Dossier ' . $data['numero'];

    return sendEmail($data['email'], $subject, $html, true);
}

/**
 * Tester l'envoi d'emails
 */
function testerEnvoiEmail($email_test = 'test@example.com') {
    $subject = 'Test SGDI - ' . date('d/m/Y H:i:s');

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Test d'envoi d'email - SGDI</h2>
    <p class="success">✓ Si vous recevez cet email, la configuration fonctionne correctement !</p>
    <p><strong>Date/Heure:</strong> {date}</p>
    <p><strong>Serveur:</strong> {serveur}</p>
    <p>Cordialement,<br>Système SGDI</p>
</body>
</html>
HTML;

    $html = str_replace('{date}', date('d/m/Y à H:i:s'), $html);
    $html = str_replace('{serveur}', php_uname('n'), $html);

    return sendEmail($email_test, $subject, $html, true);
}
?>
