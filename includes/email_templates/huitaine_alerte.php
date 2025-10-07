<?php
$email_content = <<<HTML
<h2>Alerte Huitaine</h2>

<p>Bonjour {prenom} {nom},</p>

<div class="alert alert-warning">
    <strong>⚠ Alerte: Délai de huitaine en cours pour le dossier {numero_dossier}</strong>
</div>

<p>Un délai de huitaine a été déclenché pour ce dossier. Vous disposez de 8 jours pour régulariser la situation.</p>

<div class="details">
    <p><strong>Numéro de dossier:</strong> {numero_dossier}</p>
    <p><strong>Type d'infrastructure:</strong> {type_infrastructure}</p>
    <p><strong>Demandeur:</strong> {nom_demandeur}</p>
    <p><strong>Motif:</strong> {motif_huitaine}</p>
    <p><strong>Date limite:</strong> <span style="color: #e65100; font-weight: bold;">{date_limite}</span></p>
    <p><strong>Jours restants:</strong> <span style="color: #e65100; font-weight: bold;">{jours_restants} jour(s)</span></p>
</div>

<div class="alert alert-danger">
    <strong>Important:</strong> Si aucune action n'est entreprise avant la date limite, le dossier sera automatiquement rejeté.
</div>

<p><strong>Action requise:</strong> {action_requise}</p>

<a href="{lien_dossier}" class="button">Régulariser le dossier</a>

<p>Cordialement,<br>
L'équipe SGDI</p>
HTML;

ob_start();
include __DIR__ . '/base.php';
return ob_get_clean();
?>
