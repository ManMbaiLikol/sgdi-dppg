<?php
// Test d'envoi d'email - SGDI MVP
require_once '../../includes/auth.php';
require_once '../../includes/email_functions.php';

// Seuls les administrateurs peuvent tester les emails
requireRole('admin');

$page_title = 'Test d\'envoi d\'email';

// Traitement du formulaire
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $email_test = cleanInput($_POST['email_test']);
        $type_test = cleanInput($_POST['type_test']);

        if (!filter_var($email_test, FILTER_VALIDATE_EMAIL)) {
            $result = ['success' => false, 'message' => 'Adresse email invalide'];
        } else {
            try {
                switch ($type_test) {
                    case 'simple':
                        $success = testerEnvoiEmail($email_test);
                        break;

                    case 'paiement':
                        // Simuler une notification de paiement
                        $html = renderEmailTemplate('paiement_enregistre', [
                            'prenom' => 'Test',
                            'nom' => 'Utilisateur',
                            'numero_dossier' => 'SS2025000001',
                            'type_infrastructure' => 'Station-service',
                            'montant' => '150 000',
                            'date_paiement' => date('d/m/Y'),
                            'mode_paiement' => 'Espèces',
                            'reference_paiement' => 'TEST-' . date('YmdHis'),
                            'lien_dossier' => url('modules/dossiers/view.php?id=1')
                        ]);
                        $success = sendEmail($email_test, 'Test - Paiement enregistré', $html, true);
                        break;

                    case 'visa':
                        // Simuler une notification de visa
                        $html = renderEmailTemplate('visa_accorde', [
                            'prenom' => 'Test',
                            'nom' => 'Utilisateur',
                            'numero_dossier' => 'SS2025000001',
                            'type_infrastructure' => 'Station-service',
                            'nom_demandeur' => 'TOTAL CAMEROUN SA',
                            'role_viseur' => 'Chef de Service SDTD',
                            'date_visa' => date('d/m/Y à H:i'),
                            'prochaine_etape' => 'Visa du Sous-Directeur SDTD',
                            'observations' => '',
                            'lien_dossier' => url('modules/dossiers/view.php?id=1')
                        ]);
                        $success = sendEmail($email_test, 'Test - Visa accordé', $html, true);
                        break;

                    case 'huitaine':
                        // Simuler une alerte de huitaine
                        $html = renderEmailTemplate('huitaine_alerte', [
                            'prenom' => 'Test',
                            'nom' => 'Utilisateur',
                            'numero_dossier' => 'SS2025000001',
                            'type_infrastructure' => 'Station-service',
                            'nom_demandeur' => 'TOTAL CAMEROUN SA',
                            'motif_huitaine' => 'Documents manquants',
                            'date_limite' => date('d/m/Y', strtotime('+2 days')),
                            'jours_restants' => 2,
                            'action_requise' => 'Fournir les plans détaillés et les attestations',
                            'lien_dossier' => url('modules/huitaine/regulariser.php?id=1')
                        ]);
                        $success = sendEmail($email_test, '⚠ Test - Alerte Huitaine', $html, true);
                        break;

                    case 'decision':
                        // Simuler une décision ministérielle
                        $html = renderEmailTemplate('decision_ministerielle', [
                            'prenom' => 'Test',
                            'nom' => 'Utilisateur',
                            'numero_dossier' => 'SS2025000001',
                            'type_infrastructure' => 'Station-service',
                            'nom_demandeur' => 'TOTAL CAMEROUN SA',
                            'localisation' => 'Yaoundé, Centre',
                            'decision' => 'APPROUVÉ',
                            'couleur_decision' => '#4CAF50',
                            'type_alert' => 'success',
                            'icone' => '✓',
                            'reference_decision' => 'DEC-TEST-' . date('YmdHis'),
                            'date_decision' => date('d/m/Y'),
                            'observations' => '',
                            'message_supplementaire' => '<div class="alert alert-success">Infrastructure autorisée - Publié au registre public</div>',
                            'lien_dossier' => url('modules/dossiers/view.php?id=1')
                        ]);
                        $success = sendEmail($email_test, 'Test - Décision APPROUVÉE', $html, true);
                        break;

                    default:
                        $success = false;
                }

                if ($success) {
                    $result = ['success' => true, 'message' => 'Email envoyé avec succès ! Vérifiez votre boîte de réception.'];
                } else {
                    $result = ['success' => false, 'message' => 'Échec de l\'envoi. Vérifiez les logs et la configuration SMTP.'];
                }
            } catch (Exception $e) {
                $result = ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
            }
        }
    } else {
        $result = ['success' => false, 'message' => 'Token CSRF invalide'];
    }
}

// Récupérer la configuration actuelle
$config = require '../../config/email.php';

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3"><?php echo $page_title; ?></h1>
            <p class="text-muted">Testez l'envoi d'emails avec différents templates</p>
        </div>
    </div>

    <!-- Configuration actuelle -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> Configuration actuelle</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Envoi activé :</strong>
                                <?php if ($config['enabled']): ?>
                                    <span class="badge bg-success">✓ OUI</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">✗ NON (mode log uniquement)</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Serveur SMTP :</strong> <?php echo htmlspecialchars(SMTP_HOST); ?>:<?php echo SMTP_PORT; ?></p>
                            <p><strong>Sécurité :</strong> <?php echo strtoupper(SMTP_SECURE); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Expéditeur :</strong> <?php echo htmlspecialchars(EMAIL_FROM_NAME); ?> &lt;<?php echo htmlspecialchars(EMAIL_FROM); ?>&gt;</p>
                            <p><strong>Username SMTP :</strong> <?php echo htmlspecialchars(SMTP_USERNAME); ?></p>
                            <p><strong>Mode debug :</strong> <?php echo $config['debug'] ? 'Activé' : 'Désactivé'; ?></p>
                        </div>
                    </div>

                    <?php if (!$config['enabled']): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong><i class="fas fa-exclamation-triangle"></i> Mode désactivé</strong><br>
                        Les emails seront loggés dans la table <code>email_logs</code> mais pas envoyés.<br>
                        Pour activer l'envoi réel, définissez <code>EMAIL_ENABLED=true</code> dans les variables d'environnement Railway.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle"></i> Variables d'environnement</h6>
                    <p class="small mb-2">Pour activer les emails sur Railway, définissez :</p>
                    <ul class="small mb-0">
                        <li><code>EMAIL_ENABLED=true</code></li>
                        <li><code>SMTP_HOST</code></li>
                        <li><code>SMTP_USERNAME</code></li>
                        <li><code>SMTP_PASSWORD</code></li>
                        <li><code>EMAIL_FROM</code></li>
                    </ul>
                    <p class="small mt-2 mb-0">
                        <a href="https://docs.railway.app/develop/variables" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Documentation Railway
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Résultat du test -->
    <?php if ($result): ?>
    <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <strong><?php echo $result['success'] ? '✓ Succès' : '✗ Erreur'; ?></strong><br>
        <?php echo htmlspecialchars($result['message']); ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire de test -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Envoyer un email de test</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label for="email_test" class="form-label">Adresse email de destination</label>
                            <input type="email" class="form-control" id="email_test" name="email_test"
                                   value="<?php echo htmlspecialchars($_POST['email_test'] ?? $_SESSION['user_email'] ?? ''); ?>"
                                   placeholder="test@example.com" required>
                            <small class="text-muted">L'email sera envoyé à cette adresse</small>
                        </div>

                        <div class="mb-3">
                            <label for="type_test" class="form-label">Type d'email à tester</label>
                            <select class="form-select" id="type_test" name="type_test" required>
                                <option value="simple">Email simple (test basique)</option>
                                <option value="paiement">Notification de paiement enregistré</option>
                                <option value="visa">Notification de visa accordé</option>
                                <option value="huitaine">Alerte de huitaine (2 jours restants)</option>
                                <option value="decision">Décision ministérielle (APPROUVÉE)</option>
                            </select>
                            <small class="text-muted">Choisissez le template d'email à tester</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Envoyer l'email de test
                        </button>
                        <a href="<?php echo url('modules/admin/email_logs.php'); ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-list"></i> Voir les logs d'envoi
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Conseils</h6>
                </div>
                <div class="card-body">
                    <h6>Pour Gmail :</h6>
                    <ul class="small">
                        <li>Activez l'authentification à 2 facteurs</li>
                        <li>Créez un "mot de passe d'application"</li>
                        <li>Utilisez ce mot de passe dans <code>SMTP_PASSWORD</code></li>
                    </ul>

                    <h6 class="mt-3">Serveurs SMTP recommandés :</h6>
                    <ul class="small mb-0">
                        <li><strong>Gmail :</strong> smtp.gmail.com:587</li>
                        <li><strong>SendGrid :</strong> smtp.sendgrid.net:587</li>
                        <li><strong>Mailgun :</strong> smtp.mailgun.org:587</li>
                        <li><strong>Office365 :</strong> smtp.office365.com:587</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
