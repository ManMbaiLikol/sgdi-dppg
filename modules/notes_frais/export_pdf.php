<?php
// Export PDF d'une note de frais - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$note_id = intval($_GET['id'] ?? 0);
if (!$note_id) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais non spécifiée', 'error');
}

$note = getNoteFreaisById($note_id);
if (!$note) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais introuvable', 'error');
}

// Vérifier les permissions
if (!peutVoirNoteFrais($note, $_SESSION['user_role'], $_SESSION['user_id'])) {
    redirect(url('modules/notes_frais/list.php'), 'Vous n\'avez pas les permissions pour voir cette note', 'error');
}

// Headers pour le PDF
header('Content-Type: text/html; charset=utf-8');

// Si le paramètre download est présent, on force le téléchargement
$download = isset($_GET['download']);
if ($download) {
    $filename = 'note_frais_' . $note['id'] . '_' . date('Y-m-d') . '.pdf';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

// Style CSS pour l'impression/PDF
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Note de frais #<?php echo $note['id']; ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 2cm 2cm 1.5cm 2cm;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 11px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1e4a72;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header h1 {
            color: #1e4a72;
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header .ministry {
            color: #2c5282;
            margin: 2px 0;
            font-size: 11px;
            font-weight: 600;
        }

        .header .direction {
            color: #2c5282;
            margin: 3px 0 6px 0;
            font-size: 10px;
            font-weight: bold;
            text-decoration: underline;
        }

        .header h2 {
            color: #1e4a72;
            margin: 8px 0 2px 0;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .header .note-number {
            color: #1e4a72;
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0;
        }

        .info-section {
            margin-bottom: 10px;
        }

        .info-section h3 {
            color: #1e4a72;
            border-bottom: 1px solid #1e4a72;
            padding-bottom: 2px;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 12px 3px 0;
            width: 35%;
            vertical-align: top;
            font-size: 10.5px;
        }

        .info-value {
            display: table-cell;
            padding: 3px 0;
            vertical-align: top;
            font-size: 10.5px;
        }

        .status-badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-en_attente { background-color: #fff3cd; color: #856404; }
        .status-validee { background-color: #d1ecf1; color: #0c5460; }
        .status-payee { background-color: #d4edda; color: #155724; }
        .status-annulee { background-color: #f8d7da; color: #721c24; }

        .montant-total {
            background-color: #f7fafc;
            border: 2px solid #1e4a72;
            padding: 8px;
            text-align: center;
            margin: 8px 0;
            border-radius: 4px;
        }

        .montant-total .label {
            font-size: 9.5px;
            font-weight: bold;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .montant-total .amount {
            font-size: 16px;
            font-weight: bold;
            color: #1e4a72;
        }

        .description-box {
            background-color: #f7fafc;
            border-left: 3px solid #1e4a72;
            padding: 6px 8px;
            margin: 6px 0;
            border-radius: 0 3px 3px 0;
            font-size: 10.5px;
            line-height: 1.4;
        }

        .footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid #dee2e6;
            font-size: 8px;
            color: #6c757d;
            line-height: 1.1;
        }

        .footer p {
            margin: 2px 0;
        }

        .no-print {
            margin: 20px 0;
            text-align: center;
        }

        @media print {
            .no-print { display: none; }
            body {
                font-size: 9.5px;
                line-height: 1.2;
            }
            .info-section {
                margin-bottom: 10px;
            }
            .montant-total {
                margin: 10px 0;
                padding: 8px;
            }
            .header {
                margin-bottom: 12px;
                padding-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="<?php echo url('modules/notes_frais/view.php?id=' . $note['id']); ?>" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
            Retour
        </a>
    </div>

    <div class="header">
        <h1>République du Cameroun</h1>
        <div class="ministry">Ministère de l'Eau et de l'Energie (MINEE)</div>
        <div class="direction">Direction des Produits Pétroliers et du Gaz (DPPG)</div>
        <h2>Note de Frais</h2>
        <div class="note-number">N° <?php echo str_pad($note['id'], 6, '0', STR_PAD_LEFT); ?></div>
    </div>

    <div class="info-section">
        <h3>Informations générales</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Date de création :</div>
                <div class="info-value"><?php echo formatDateTime($note['date_creation'], 'd/m/Y à H:i'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Créée par :</div>
                <div class="info-value"><?php echo sanitize($note['createur_prenom'] . ' ' . $note['createur_nom']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut :</div>
                <div class="info-value">
                    <span class="status-badge status-<?php echo $note['statut']; ?>">
                        <?php echo getStatutNoteFraisLabel($note['statut']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Dossier concerné</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Numéro dossier :</div>
                <div class="info-value"><strong><?php echo sanitize($note['dossier_numero']); ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Demandeur :</div>
                <div class="info-value"><?php echo sanitize($note['nom_demandeur']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Type d'infrastructure :</div>
                <div class="info-value"><?php echo sanitize($note['type_infrastructure']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Localisation :</div>
                <div class="info-value"><?php echo sanitize($note['region'] . ' - ' . $note['ville']); ?></div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Description des frais</h3>
        <div class="description-box">
            <?php echo nl2br(sanitize($note['description'])); ?>
        </div>
    </div>

    <div class="info-section">
        <h3>Détail financier</h3>
        <div class="montant-total">
            <div class="label">Montant Total</div>
            <div class="amount"><?php echo number_format($note['montant_total'], 0, ',', ' '); ?> F CFA</div>
        </div>
    </div>

    <?php if (!empty($note['date_validation'])): ?>
    <div class="info-section">
        <h3>Validation</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Date de validation :</div>
                <div class="info-value"><?php echo formatDateTime($note['date_validation'], 'd/m/Y à H:i'); ?></div>
            </div>
            <?php if (!empty($note['validateur_nom'])): ?>
            <div class="info-row">
                <div class="info-label">Validée par :</div>
                <div class="info-value"><?php echo sanitize($note['validateur_prenom'] . ' ' . $note['validateur_nom']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($note['date_paiement'])): ?>
    <div class="info-section">
        <h3>Paiement</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Date de paiement :</div>
                <div class="info-value"><?php echo formatDateTime($note['date_paiement'], 'd/m/Y à H:i'); ?></div>
            </div>
            <?php if (!empty($note['payeur_nom'])): ?>
            <div class="info-row">
                <div class="info-label">Payée par :</div>
                <div class="info-value"><?php echo sanitize($note['payeur_prenom'] . ' ' . $note['payeur_nom']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Espace signature -->
    <div class="info-section" style="margin-top: 20px;">
        <h3>Signature et cachet</h3>
        <div style="margin-top: 15px;">
            <div style="text-align: right; margin-right: 60px;">
                <p style="margin-bottom: 35px; font-weight: bold; font-size: 11px;">Le Directeur des Produits Pétroliers et du Gaz (DPPG)</p>
                <div style="border-top: 1px solid #333; width: 200px; display: inline-block; margin-top: 35px;"></div>
                <p style="margin-top: 5px; margin-bottom: 5px; font-size: 10px; font-style: italic;">(Signature et cachet)</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>Document généré automatiquement par le SGDI - <?php echo formatDateTime(date('Y-m-d H:i:s'), 'd/m/Y à H:i'); ?></strong></p>
        <p>Direction des Produits Pétroliers et du Gaz (DPPG) - Ministère de l'Eau et de l'Energie (MINEE)</p>
    </div>

    <script>
        // Auto-print si paramètre download présent
        <?php if ($download): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    </script>
</body>
</html>