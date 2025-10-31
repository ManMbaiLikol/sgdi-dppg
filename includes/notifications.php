<?php
/**
 * Système de notifications automatiques
 * Gère les emails et notifications in-app pour tout le workflow
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Envoyer une notification email
 */
function envoyerEmail($to, $subject, $body, $from_name = 'SGDI - MINEE/DPPG') {
    // Configuration email (à adapter selon votre serveur SMTP)
    $from_email = 'noreply@dppg.minee.cm';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Template HTML de base
    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>SGDI - MINEE/DPPG</h2>
                <p>Système de Gestion des Dossiers d'Implantation</p>
            </div>
            <div class='content'>
                $body
            </div>
            <div class='footer'>
                <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
                <p>&copy; " . date('Y') . " MINEE/DPPG - République du Cameroun</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return mail($to, $subject, $html_body, $headers);
}

/**
 * Créer une notification in-app
 */
function creerNotification($user_id, $type, $titre, $message, $dossier_id = null, $lien = null) {
    global $pdo;

    $sql = "INSERT INTO notifications (user_id, type, titre, message, dossier_id, lien, lu, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id, $type, $titre, $message, $dossier_id, $lien]);
}

/**
 * Notifier lors de la création d'un dossier
 */
function notifierCreationDossier($dossier_id) {
    global $pdo;

    // Récupérer le dossier
    $dossier = getDossierDetails($dossier_id);
    if (!$dossier) return false;

    // Notifier le chef service
    $chefs = getUsersByRole('chef_service');
    foreach ($chefs as $chef) {
        $message = "Un nouveau dossier <strong>{$dossier['numero']}</strong> a été créé par {$dossier['nom_demandeur']}.";

        creerNotification(
            $chef['id'],
            'nouveau_dossier',
            'Nouveau dossier créé',
            $message,
            $dossier_id,
            "modules/dossiers/view.php?id=$dossier_id"
        );

        // Email
        $email_body = "
            <h3>Nouveau dossier créé</h3>
            <p>$message</p>
            <p><strong>Type:</strong> " . getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']) . "</p>
            <p><strong>Localisation:</strong> {$dossier['ville']}</p>
            <a href='" . url("modules/dossiers/view.php?id=$dossier_id") . "' class='btn'>Consulter le dossier</a>
        ";

        envoyerEmail($chef['email'], "Nouveau dossier - {$dossier['numero']}", $email_body);
    }

    return true;
}

/**
 * Notifier lors d'un visa
 */
function notifierVisa($dossier_id, $visa_role, $action) {
    global $pdo;

    $dossier = getDossierDetails($dossier_id);
    if (!$dossier) return false;

    $role_labels = [
        'chef_service' => 'Chef Service SDTD',
        'sous_directeur' => 'Sous-Directeur SDTD',
        'directeur' => 'Directeur DPPG'
    ];

    $role_label = $role_labels[$visa_role] ?? $visa_role;

    // Déterminer le prochain rôle à notifier
    $prochain_role = null;
    if ($visa_role === 'chef_service' && $action === 'approuve') {
        $prochain_role = 'sous_directeur';
    } elseif ($visa_role === 'sous_directeur' && $action === 'approuve') {
        $prochain_role = 'directeur';
    } elseif ($visa_role === 'directeur' && $action === 'approuve') {
        $prochain_role = 'ministre';
    }

    if ($prochain_role) {
        $users = getUsersByRole($prochain_role);
        foreach ($users as $user) {
            $message = "Le dossier <strong>{$dossier['numero']}</strong> a reçu le visa du $role_label et nécessite votre action.";

            creerNotification(
                $user['id'],
                'visa_requis',
                "Visa requis - {$dossier['numero']}",
                $message,
                $dossier_id,
                getVisaPageForRole($prochain_role)
            );

            // Email
            $email_body = "
                <h3>Dossier en attente de votre visa</h3>
                <p>$message</p>
                <p><strong>Dossier:</strong> {$dossier['numero']}</p>
                <p><strong>Demandeur:</strong> {$dossier['nom_demandeur']}</p>
                <p><strong>Type:</strong> " . getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']) . "</p>
                <a href='" . url(getVisaPageForRole($prochain_role)) . "' class='btn'>Viser le dossier</a>
            ";

            envoyerEmail($user['email'], "Visa requis - {$dossier['numero']}", $email_body);
        }
    }

    return true;
}

/**
 * Notifier lors d'une décision ministérielle
 */
function notifierDecisionMinisterielle($dossier_id, $decision, $numero_arrete) {
    global $pdo;

    $dossier = getDossierDetails($dossier_id);
    if (!$dossier) return false;

    $decision_labels = [
        'approuve' => 'APPROUVÉ',
        'refuse' => 'REFUSÉ',
        'ajourne' => 'AJOURNÉ'
    ];

    $decision_label = $decision_labels[$decision] ?? $decision;

    // Notifier le demandeur (s'il a un compte)
    $sql = "SELECT id, email FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier['email_demandeur'] ?? '']);
    $demandeur = $stmt->fetch();

    if ($demandeur) {
        $message = "Votre dossier <strong>{$dossier['numero']}</strong> a reçu une décision ministérielle : <strong>$decision_label</strong>.";

        creerNotification(
            $demandeur['id'],
            'decision_ministerielle',
            "Décision ministérielle - $decision_label",
            $message,
            $dossier_id,
            "modules/dossiers/view.php?id=$dossier_id"
        );

        // Email
        $email_body = "
            <h3>Décision ministérielle pour votre dossier</h3>
            <p>$message</p>
            <p><strong>Arrêté n°:</strong> $numero_arrete</p>
            <p><strong>Dossier:</strong> {$dossier['numero']}</p>
            <p><strong>Type:</strong> " . getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']) . "</p>
        ";

        if ($decision === 'approuve') {
            $email_body .= "<p style='color: green;'><strong>Félicitations ! Votre demande a été approuvée.</strong></p>";
            $email_body .= "<a href='" . url("modules/registre_public/index.php") . "' class='btn'>Voir au registre public</a>";
        }

        envoyerEmail($demandeur['email'], "Décision ministérielle - $decision_label", $email_body);
    }

    // Notifier tous les acteurs du circuit
    $roles = ['chef_service', 'sous_directeur', 'directeur'];
    foreach ($roles as $role) {
        $users = getUsersByRole($role);
        foreach ($users as $user) {
            creerNotification(
                $user['id'],
                'decision_ministerielle',
                "Décision ministérielle - {$dossier['numero']}",
                "Le dossier <strong>{$dossier['numero']}</strong> a reçu une décision ministérielle : <strong>$decision_label</strong>.",
                $dossier_id,
                "modules/dossiers/view.php?id=$dossier_id"
            );
        }
    }

    return true;
}

/**
 * Récupérer la page de visa selon le rôle
 */
function getVisaPageForRole($role) {
    $pages = [
        'chef_service' => 'modules/dossiers/viser_inspections.php',
        'sous_directeur' => 'modules/dossiers/viser_sous_directeur.php',
        'directeur' => 'modules/dossiers/viser_directeur.php',
        'ministre' => 'modules/dossiers/decision_ministre.php'
    ];

    return $pages[$role] ?? 'dashboard.php';
}

/**
 * Récupérer les utilisateurs par rôle
 */
function getUsersByRole($role_name) {
    global $pdo;

    $sql = "SELECT u.*
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.nom = ? AND u.actif = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$role_name]);
    return $stmt->fetchAll();
}

/**
 * Notifier paiement enregistré
 */
function notifierPaiementEnregistre($dossier_id) {
    global $pdo;

    $dossier = getDossierDetails($dossier_id);
    if (!$dossier) return false;

    // Notifier le demandeur
    if ($dossier['email_demandeur']) {
        $email_body = "
            <h3>Paiement enregistré</h3>
            <p>Votre paiement pour le dossier <strong>{$dossier['numero']}</strong> a été enregistré avec succès.</p>
            <p>Votre dossier va maintenant être analysé par la commission technique.</p>
        ";

        envoyerEmail($dossier['email_demandeur'], "Paiement enregistré - {$dossier['numero']}", $email_body);
    }

    // Notifier la DAJ et la commission
    $roles = ['cadre_daj', 'cadre_dppg', 'chef_commission'];
    foreach ($roles as $role) {
        $users = getUsersByRole($role);
        foreach ($users as $user) {
            creerNotification(
                $user['id'],
                'paiement_enregistre',
                "Nouveau dossier payé - {$dossier['numero']}",
                "Le dossier <strong>{$dossier['numero']}</strong> a été payé et nécessite votre analyse.",
                $dossier_id,
                "modules/dossiers/view.php?id=$dossier_id"
            );
        }
    }

    return true;
}
