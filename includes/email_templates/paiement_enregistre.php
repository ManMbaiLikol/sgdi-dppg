<?php
$email_content = <<<HTML
<h2>Paiement enregistré</h2>

<p>Bonjour {prenom} {nom},</p>

<div class="alert alert-success">
    <strong>✓ Le paiement pour le dossier {numero_dossier} a été enregistré avec succès.</strong>
</div>

<p>Le dossier peut maintenant passer à l'étape suivante du traitement.</p>

<div class="details">
    <p><strong>Numéro de dossier:</strong> {numero_dossier}</p>
    <p><strong>Type d'infrastructure:</strong> {type_infrastructure}</p>
    <p><strong>Montant payé:</strong> {montant} FCFA</p>
    <p><strong>Date de paiement:</strong> {date_paiement}</p>
    <p><strong>Mode de paiement:</strong> {mode_paiement}</p>
    <p><strong>Référence:</strong> {reference_paiement}</p>
</div>

<p>Le dossier va maintenant être soumis à l'analyse juridique par la Direction des Affaires Juridiques (DAJ).</p>

<a href="{lien_dossier}" class="button">Consulter le dossier</a>

<p>Cordialement,<br>
L'équipe SGDI</p>
HTML;

// Charger le template de base
ob_start();
include __DIR__ . '/base.php';
return ob_get_clean();
?>
