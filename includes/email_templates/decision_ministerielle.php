<?php
$email_content = <<<HTML
<h2>Décision ministérielle</h2>

<p>Bonjour {prenom} {nom},</p>

<div class="alert alert-{type_alert}">
    <strong>{icone} Une décision ministérielle a été prise concernant le dossier {numero_dossier}</strong>
</div>

<p>Le Cabinet du Ministre de l'Eau et de l'Énergie a rendu sa décision finale.</p>

<div class="details">
    <p><strong>Numéro de dossier:</strong> {numero_dossier}</p>
    <p><strong>Type d'infrastructure:</strong> {type_infrastructure}</p>
    <p><strong>Demandeur:</strong> {nom_demandeur}</p>
    <p><strong>Localisation:</strong> {localisation}</p>
    <p><strong>Décision:</strong> <strong style="color: {couleur_decision}">{decision}</strong></p>
    <p><strong>Référence de la décision:</strong> {reference_decision}</p>
    <p><strong>Date de la décision:</strong> {date_decision}</p>
</div>

{observations}

{message_supplementaire}

<a href="{lien_dossier}" class="button">Consulter le dossier</a>

<p>Cordialement,<br>
L'équipe SGDI</p>
HTML;

ob_start();
include __DIR__ . '/base.php';
return ob_get_clean();
?>
