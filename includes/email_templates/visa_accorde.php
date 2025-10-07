<?php
$email_content = <<<HTML
<h2>Visa accordé</h2>

<p>Bonjour {prenom} {nom},</p>

<div class="alert alert-success">
    <strong>✓ Un visa a été accordé pour le dossier {numero_dossier}</strong>
</div>

<p>Le dossier a reçu le visa de <strong>{role_viseur}</strong> et passe à l'étape suivante.</p>

<div class="details">
    <p><strong>Numéro de dossier:</strong> {numero_dossier}</p>
    <p><strong>Type d'infrastructure:</strong> {type_infrastructure}</p>
    <p><strong>Demandeur:</strong> {nom_demandeur}</p>
    <p><strong>Visa accordé par:</strong> {role_viseur}</p>
    <p><strong>Date du visa:</strong> {date_visa}</p>
    <p><strong>Prochaine étape:</strong> {prochaine_etape}</p>
</div>

{observations}

<a href="{lien_dossier}" class="button">Consulter le dossier</a>

<p>Cordialement,<br>
L'équipe SGDI</p>
HTML;

ob_start();
include __DIR__ . '/base.php';
return ob_get_clean();
?>
