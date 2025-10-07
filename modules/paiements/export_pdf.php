<?php
// Export PDF d'un paiement - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../dossiers/functions.php';
require_once '../notes_frais/functions.php';

requireLogin();

$paiement_id = intval($_GET['id'] ?? 0);
$dossier_id = intval($_GET['dossier_id'] ?? 0);

if ($paiement_id) {
    $paiement = getPaiementById($paiement_id);
} elseif ($dossier_id) {
    // Récupérer le paiement par dossier_id
    $sql = "SELECT p.*, d.numero as dossier_numero, d.nom_demandeur,
                   u.nom as billeteur_nom, u.prenom as billeteur_prenom
            FROM paiements p
            JOIN dossiers d ON p.dossier_id = d.id
            JOIN users u ON p.billeteur_id = u.id
            WHERE p.dossier_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);
    $paiement = $stmt->fetch();
} else {
    redirect(url('modules/paiements/list.php'), 'Paiement non spécifié', 'error');
}

if (!$paiement) {
    redirect(url('modules/paiements/list.php'), 'Paiement introuvable', 'error');
}

// Vérifier les permissions
if (!peutVoirPaiements($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour consulter les paiements', 'error');
}

// Récupérer la note de frais associée
$note_frais = getNoteFreaisParDossier($paiement['dossier_id']);

// Récupérer le dossier complet
$dossier = getDossierById($paiement['dossier_id']);

// Générer le nom du fichier
$filename = 'recu_paiement_' . $paiement['dossier_numero'] . '_' . date('Y-m-d', strtotime($paiement['date_paiement']));

// Headers pour HTML (sera converti en PDF par le navigateur)
header('Content-Type: text/html; charset=utf-8');

// Début du HTML
echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de paiement - ' . htmlspecialchars($paiement['dossier_numero']) . '</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.2cm 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #2c5234;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            color: #2c5234;
            margin: 0 0 5px 0;
        }

        .header .ministry {
            font-size: 11pt;
            font-weight: bold;
            color: #d4351c;
            margin: 2px 0;
        }

        .header .direction {
            font-size: 10pt;
            color: #666;
            margin: 2px 0;
        }

        .document-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: #2c5234;
            margin: 15px 0;
            text-transform: uppercase;
            border: 2px solid #2c5234;
            padding: 10px;
            background-color: #f8f9fa;
        }

        .info-section {
            margin: 12px 0;
            page-break-inside: avoid;
        }

        .info-title {
            font-size: 11pt;
            font-weight: bold;
            color: #2c5234;
            border-bottom: 1px solid #2c5234;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            width: 35%;
            font-weight: bold;
            padding: 3px 10px 3px 0;
            vertical-align: top;
            font-size: 9.5pt;
        }

        .info-value {
            display: table-cell;
            padding: 3px 0;
            vertical-align: top;
            font-size: 9.5pt;
        }

        .montant-highlight {
            background-color: #d1ecf1;
            border: 2px solid #0c5460;
            padding: 12px;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #0c5460;
            margin: 15px 0;
        }

        .signature-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .signature-grid {
            display: table;
            width: 100%;
            margin-top: 15px;
        }

        .signature-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 15px;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 35px;
            border-bottom: 1px solid #333;
            padding-bottom: 4px;
            font-size: 10pt;
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            page-break-inside: avoid;
        }

        @media print {
            .footer {
                position: fixed;
                bottom: 0.5cm;
                left: 0;
                right: 0;
                margin-top: 0;
            }
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .page-break { page-break-before: always; }
            .no-print { display: none; }

            /* Assurer que les couleurs de fond s\'impriment */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }

        /* Styles supplémentaires pour une meilleure lisibilité */
        .montant-highlight {
            border: 2px solid #0c5460 !important;
        }
    </style>
</head>
<body>';

// En-tête officiel
echo '<div class="header">
    <h1>République du Cameroun</h1>
    <div class="ministry">Ministère de l\'Eau et de l\'Energie (MINEE)</div>
    <div class="direction">Direction des Produits Pétroliers et du Gaz (DPPG)</div>
</div>';

// Titre du document
echo '<div class="document-title">
    Reçu de Paiement
</div>';

// Informations du dossier
echo '<div class="info-section">
    <div class="info-title">Informations du dossier</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Numéro de dossier :</div>
            <div class="info-value">' . htmlspecialchars($paiement['dossier_numero']) . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Type d\'infrastructure :</div>
            <div class="info-value">' . htmlspecialchars($dossier['type_infrastructure']) . '</div>
        </div>';

if (!empty($dossier['sous_type'])) {
    echo '<div class="info-row">
            <div class="info-label">Sous-type :</div>
            <div class="info-value">' . htmlspecialchars($dossier['sous_type']) . '</div>
          </div>';
}

echo '<div class="info-row">
        <div class="info-label">Demandeur :</div>
        <div class="info-value">' . htmlspecialchars($paiement['nom_demandeur']) . '</div>
      </div>
      <div class="info-row">
        <div class="info-label">Localisation :</div>
        <div class="info-value">' . htmlspecialchars($dossier['region'] . ' - ' . $dossier['ville']) . '</div>
      </div>
    </div>
</div>';

// Détails de la note de frais (si disponible)
if (!empty($note_frais)) {
    echo '<div class="info-section">
        <div class="info-title">Détail des frais</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Montant de base :</div>
                <div class="info-value">' . formatMontantPaiement($note_frais['montant_base']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Frais de déplacement :</div>
                <div class="info-value">' . formatMontantPaiement($note_frais['montant_frais_deplacement']) . '</div>
            </div>';

    if (!empty($note_frais['description'])) {
        echo '<div class="info-row">
                <div class="info-label">Description :</div>
                <div class="info-value">' . htmlspecialchars($note_frais['description']) . '</div>
              </div>';
    }

    echo '</div>
    </div>';
}

// Informations du paiement
echo '<div class="info-section">
    <div class="info-title">Détails du paiement</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Date de paiement :</div>
            <div class="info-value">' . formatDateTime($paiement['date_paiement'], 'd/m/Y') . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Mode de paiement :</div>
            <div class="info-value">' . ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])) . '</div>
        </div>';

if (!empty($paiement['reference_paiement'])) {
    echo '<div class="info-row">
            <div class="info-label">Référence :</div>
            <div class="info-value">' . htmlspecialchars($paiement['reference_paiement']) . '</div>
          </div>';
}

if (!empty($paiement['billeteur_nom'])) {
    echo '<div class="info-row">
            <div class="info-label">Enregistré par :</div>
            <div class="info-value">' . htmlspecialchars($paiement['billeteur_prenom'] . ' ' . $paiement['billeteur_nom']) . '</div>
          </div>';
}

echo '<div class="info-row">
        <div class="info-label">Date d\'enregistrement :</div>
        <div class="info-value">' . formatDateTime($paiement['date_enregistrement'], 'd/m/Y H:i') . '</div>
      </div>';

if (!empty($paiement['observations'])) {
    echo '<div class="info-row">
            <div class="info-label">Observations :</div>
            <div class="info-value">' . htmlspecialchars($paiement['observations']) . '</div>
          </div>';
}

echo '</div>
</div>';

// Montant payé en évidence
echo '<div class="montant-highlight">
    MONTANT PAYÉ : ' . formatMontantPaiement($paiement['montant'], $paiement['devise']) . '
</div>';

// Section signatures
echo '<div class="signature-section">
    <div class="signature-grid">
        <div class="signature-col">
            <div class="signature-title">Le Billeteur</div>
            <p>Nom et signature</p>
        </div>
        <div class="signature-col">
            <div class="signature-title">Le Demandeur</div>
            <p>Nom et signature</p>
        </div>
    </div>
</div>';

// Pied de page
echo '<div class="footer">
    <p>Document généré le ' . date('d/m/Y à H:i') . ' | SGDI - Système de Gestion des Dossiers d\'Implantation - Direction des Produits Pétroliers et du Gaz (DPPG) - Ministère de l\'Eau et de l\'Energie</p>
</div>';

echo '<script>
// Configuration pour l\'impression automatique en PDF
window.onload = function() {
    // Changer le titre de la page pour le nom du fichier
    document.title = "' . $filename . '";

    // Lancer l\'impression automatiquement
    setTimeout(function() {
        window.print();
    }, 500);
};

// Fermer la fenêtre après impression (optionnel)
window.onafterprint = function() {
    // window.close(); // Décommenté si vous voulez fermer auto
};
</script>';

echo '</body></html>';
?>