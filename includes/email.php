<?php
// Système d'envoi d'emails sans PHPMailer (utilise la fonction mail() de PHP)
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Fonction principale d'envoi d'email
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    global $pdo;
    $config = require __DIR__ . '/../config/email.php';

    // Si l'envoi d'emails est désactivé, sauvegarder en base uniquement
    if (!$config['enabled']) {
        logEmail($to, $subject, $body, 'disabled');

        if ($config['debug']) {
            error_log("EMAIL DEBUG - To: $to | Subject: $subject");
        }
        return true;
    }

    // Préparer les en-têtes
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $isHTML ? 'Content-Type: text/html; charset=UTF-8' : 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $config['from']['name'] . ' <' . $config['from']['email'] . '>';
    $headers[] = 'Reply-To: ' . $config['from']['email'];
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    // Envoyer l'email
    $success = mail($to, $subject, $body, implode("\r\n", $headers));

    // Logger l'envoi
    logEmail($to, $subject, $body, $success ? 'sent' : 'failed');

    return $success;
}

/**
 * Envoyer une notification de changement de statut
 */
function sendStatusChangeNotification($dossier_id, $ancien_statut, $nouveau_statut, $user_email) {
    global $pdo;

    // Récupérer les informations du dossier
    $sql = "SELECT d.*, u.email, u.nom, u.prenom
            FROM dossiers d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $dossier_id]);
    $dossier = $stmt->fetch();

    if (!$dossier) {
        return false;
    }

    $subject = "Changement de statut - Dossier " . $dossier['numero'];

    $body = getEmailTemplate('status_change', [
        'numero' => $dossier['numero'],
        'nom_demandeur' => $dossier['nom_demandeur'],
        'ancien_statut' => formatStatut($ancien_statut),
        'nouveau_statut' => formatStatut($nouveau_statut),
        'type_infrastructure' => formatTypeInfrastructure($dossier['type_infrastructure']),
        'url_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ]);

    // Envoyer au créateur du dossier
    if ($dossier['email']) {
        sendEmail($dossier['email'], $subject, $body);
    }

    // Envoyer à l'utilisateur qui a fait le changement si différent
    if ($user_email && $user_email !== $dossier['email']) {
        sendEmail($user_email, $subject, $body);
    }

    return true;
}

/**
 * Envoyer une notification de paiement enregistré
 */
function sendPaymentNotification($dossier_id) {
    global $pdo;

    $sql = "SELECT d.*, p.montant, p.date_paiement, p.reference_paiement,
            u.email as demandeur_email
            FROM dossiers d
            JOIN paiements p ON d.id = p.dossier_id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $dossier_id]);
    $dossier = $stmt->fetch();

    if (!$dossier) {
        return false;
    }

    $subject = "Paiement enregistré - Dossier " . $dossier['numero'];

    $body = getEmailTemplate('payment_received', [
        'numero' => $dossier['numero'],
        'nom_demandeur' => $dossier['nom_demandeur'],
        'montant' => number_format($dossier['montant'], 0, ',', ' ') . ' FCFA',
        'date_paiement' => date('d/m/Y', strtotime($dossier['date_paiement'])),
        'reference' => $dossier['reference_paiement'],
        'url_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ]);

    // Envoyer au demandeur
    if ($dossier['demandeur_email']) {
        sendEmail($dossier['demandeur_email'], $subject, $body);
    }

    // Notifier les cadres DPPG et DAJ pour le traitement
    notifyRoles(['cadre_dppg', 'cadre_daj'], $subject, $body);

    return true;
}

/**
 * Envoyer une notification de huitaine
 */
function sendHuitaineNotification($dossier_id, $jours_restants) {
    global $pdo;

    $sql = "SELECT d.*, h.date_debut_huitaine, h.date_fin_huitaine,
            u.email as user_email, u.nom, u.prenom
            FROM dossiers d
            JOIN huitaines h ON d.id = h.dossier_id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = :id AND h.statut = 'en_cours'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $dossier_id]);
    $dossier = $stmt->fetch();

    if (!$dossier) {
        return false;
    }

    $urgence = $jours_restants <= 1 ? 'URGENT' : 'Rappel';
    $subject = "[$urgence] Délai de huitaine - Dossier " . $dossier['numero'];

    $body = getEmailTemplate('huitaine_alert', [
        'numero' => $dossier['numero'],
        'nom_demandeur' => $dossier['nom_demandeur'],
        'jours_restants' => $jours_restants,
        'date_limite' => date('d/m/Y', strtotime($dossier['date_fin_huitaine'])),
        'motif_huitaine' => $dossier['motif_huitaine'] ?? 'Document manquant',
        'url_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ]);

    // Envoyer au créateur du dossier
    if ($dossier['user_email']) {
        sendEmail($dossier['user_email'], $subject, $body);
    }

    // Notifier le chef de service
    notifyRoles(['chef_service'], $subject, $body);

    return true;
}

/**
 * Envoyer une notification de décision finale
 */
function sendDecisionNotification($dossier_id) {
    global $pdo;

    $sql = "SELECT d.*, dec.decision, dec.date_decision, dec.reference_decision, dec.motif,
            u.email as user_email
            FROM dossiers d
            JOIN decisions dec ON d.id = dec.dossier_id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $dossier_id]);
    $dossier = $stmt->fetch();

    if (!$dossier) {
        return false;
    }

    $decision_libelle = $dossier['decision'] === 'approuve' ? 'APPROUVÉE' : 'REFUSÉE';
    $subject = "Décision finale $decision_libelle - Dossier " . $dossier['numero'];

    $body = getEmailTemplate('decision_finale', [
        'numero' => $dossier['numero'],
        'nom_demandeur' => $dossier['nom_demandeur'],
        'decision' => $decision_libelle,
        'date_decision' => date('d/m/Y', strtotime($dossier['date_decision'])),
        'reference' => $dossier['reference_decision'],
        'motif' => $dossier['motif'] ?? '',
        'url_dossier' => url('modules/dossiers/view.php?id=' . $dossier_id)
    ]);

    // Envoyer au créateur du dossier
    if ($dossier['user_email']) {
        sendEmail($dossier['user_email'], $subject, $body);
    }

    return true;
}

/**
 * Notifier tous les utilisateurs d'un ou plusieurs rôles
 */
function notifyRoles($roles, $subject, $body) {
    global $pdo;

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));

    $sql = "SELECT DISTINCT u.email
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.code IN ($placeholders) AND u.actif = 1 AND u.email IS NOT NULL";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($roles);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($emails as $email) {
        sendEmail($email, $subject, $body);
    }

    return count($emails);
}

/**
 * Logger l'envoi d'email en base de données
 */
function logEmail($to, $subject, $body, $status = 'sent') {
    global $pdo;

    try {
        $sql = "INSERT INTO email_logs (destinataire, sujet, corps, statut, date_envoi)
                VALUES (:to, :subject, :body, :status, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'status' => $status
        ]);
    } catch (Exception $e) {
        error_log("Erreur log email: " . $e->getMessage());
    }
}

/**
 * Récupérer un template d'email
 */
function getEmailTemplate($template_name, $vars = []) {
    $templates = [
        'status_change' => '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                    <div style="background: #1e3a8a; color: white; padding: 20px; text-align: center;">
                        <h2>SGDI - MINEE/DPPG</h2>
                    </div>
                    <div style="padding: 20px;">
                        <h3>Changement de statut de dossier</h3>
                        <p><strong>Dossier N°:</strong> {numero}</p>
                        <p><strong>Infrastructure:</strong> {nom_demandeur}</p>
                        <p><strong>Type:</strong> {type_infrastructure}</p>
                        <hr>
                        <p><strong>Ancien statut:</strong> <span style="color: #999;">{ancien_statut}</span></p>
                        <p><strong>Nouveau statut:</strong> <span style="color: #059669; font-weight: bold;">{nouveau_statut}</span></p>
                        <hr>
                        <p style="text-align: center; margin-top: 30px;">
                            <a href="{url_dossier}" style="background: #1e3a8a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                                Voir le dossier
                            </a>
                        </p>
                    </div>
                    <div style="background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #666;">
                        <p>Cet email a été généré automatiquement par le SGDI - Merci de ne pas répondre</p>
                    </div>
                </div>
            </body>
            </html>
        ',

        'payment_received' => '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                    <div style="background: #059669; color: white; padding: 20px; text-align: center;">
                        <h2>✓ Paiement enregistré</h2>
                    </div>
                    <div style="padding: 20px;">
                        <p>Bonjour,</p>
                        <p>Le paiement pour votre dossier d\'implantation a été enregistré avec succès.</p>
                        <hr>
                        <p><strong>Dossier N°:</strong> {numero}</p>
                        <p><strong>Infrastructure:</strong> {nom_demandeur}</p>
                        <p><strong>Montant payé:</strong> <span style="font-size: 18px; color: #059669; font-weight: bold;">{montant}</span></p>
                        <p><strong>Date de paiement:</strong> {date_paiement}</p>
                        <p><strong>Référence:</strong> {reference}</p>
                        <hr>
                        <p>Votre dossier va maintenant passer à l\'étape d\'analyse juridique et d\'inspection.</p>
                        <p style="text-align: center; margin-top: 30px;">
                            <a href="{url_dossier}" style="background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                                Suivre mon dossier
                            </a>
                        </p>
                    </div>
                    <div style="background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #666;">
                        <p>SGDI - MINEE/DPPG - République du Cameroun</p>
                    </div>
                </div>
            </body>
            </html>
        ',

        'huitaine_alert' => '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                    <div style="background: #dc2626; color: white; padding: 20px; text-align: center;">
                        <h2>⚠ ALERTE DÉLAI DE HUITAINE</h2>
                    </div>
                    <div style="padding: 20px;">
                        <p><strong style="color: #dc2626;">Attention! Il vous reste {jours_restants} jour(s) pour régulariser votre dossier.</strong></p>
                        <hr>
                        <p><strong>Dossier N°:</strong> {numero}</p>
                        <p><strong>Infrastructure:</strong> {nom_demandeur}</p>
                        <p><strong>Date limite:</strong> <span style="color: #dc2626; font-weight: bold;">{date_limite}</span></p>
                        <p><strong>Motif:</strong> {motif_huitaine}</p>
                        <hr>
                        <p style="background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b;">
                            <strong>Important:</strong> Si vous ne régularisez pas votre dossier avant la date limite,
                            celui-ci sera automatiquement rejeté conformément aux dispositions réglementaires.
                        </p>
                        <p style="text-align: center; margin-top: 30px;">
                            <a href="{url_dossier}" style="background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                                Régulariser mon dossier
                            </a>
                        </p>
                    </div>
                    <div style="background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #666;">
                        <p>SGDI - MINEE/DPPG</p>
                    </div>
                </div>
            </body>
            </html>
        ',

        'decision_finale' => '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                    <div style="background: #1e3a8a; color: white; padding: 20px; text-align: center;">
                        <h2>DÉCISION ADMINISTRATIVE FINALE</h2>
                    </div>
                    <div style="padding: 20px;">
                        <p>Madame, Monsieur,</p>
                        <p>Nous avons le plaisir de vous informer que votre dossier a fait l\'objet d\'une décision administrative.</p>
                        <hr>
                        <p><strong>Dossier N°:</strong> {numero}</p>
                        <p><strong>Infrastructure:</strong> {nom_demandeur}</p>
                        <p><strong>Décision:</strong> <span style="font-size: 20px; font-weight: bold; color: {decision_color};">{decision}</span></p>
                        <p><strong>Date de décision:</strong> {date_decision}</p>
                        <p><strong>Référence:</strong> {reference}</p>
                        {motif_section}
                        <hr>
                        <p style="text-align: center; margin-top: 30px;">
                            <a href="{url_dossier}" style="background: #1e3a8a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                                Consulter la décision
                            </a>
                        </p>
                    </div>
                    <div style="background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #666;">
                        <p>MINEE/DPPG - Direction du Pétrole, du Produit Pétrolier et du Gaz</p>
                        <p>République du Cameroun</p>
                    </div>
                </div>
            </body>
            </html>
        '
    ];

    if (!isset($templates[$template_name])) {
        return '';
    }

    $template = $templates[$template_name];

    // Remplacer les variables
    foreach ($vars as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }

    // Variables spéciales pour decision_finale
    if ($template_name === 'decision_finale') {
        $decision_color = $vars['decision'] === 'APPROUVÉE' ? '#059669' : '#dc2626';
        $motif_section = !empty($vars['motif']) ? '<p><strong>Motif:</strong> ' . $vars['motif'] . '</p>' : '';

        $template = str_replace('{decision_color}', $decision_color, $template);
        $template = str_replace('{motif_section}', $motif_section, $template);
    }

    return $template;
}

// Créer la table email_logs si elle n'existe pas
function createEmailLogsTable() {
    global $pdo;

    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        destinataire VARCHAR(255) NOT NULL,
        sujet VARCHAR(500) NOT NULL,
        corps TEXT,
        statut ENUM('sent', 'failed', 'disabled') DEFAULT 'sent',
        date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_destinataire (destinataire),
        INDEX idx_date (date_envoi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log("Erreur création table email_logs: " . $e->getMessage());
    }
}

// Créer la table au chargement
createEmailLogsTable();
