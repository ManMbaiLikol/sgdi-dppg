<?php
// Déconnexion - SGDI MVP
require_once 'includes/auth.php';

logoutUser();
redirect(url('index.php'), 'Vous avez été déconnecté avec succès', 'info');
?>