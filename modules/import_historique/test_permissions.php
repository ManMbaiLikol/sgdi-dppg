<?php
// Test des permissions - Module import historique
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

echo "<h1>Test des permissions - Module Import Historique</h1>";
echo "<hr>";

echo "<h3>Informations de session :</h3>";
echo "<ul>";
echo "<li><strong>User ID :</strong> " . ($_SESSION['user_id'] ?? 'NON DÉFINI') . "</li>";
echo "<li><strong>User Role :</strong> " . ($_SESSION['user_role'] ?? 'NON DÉFINI') . "</li>";
echo "<li><strong>User Name :</strong> " . ($_SESSION['user_name'] ?? 'NON DÉFINI') . "</li>";
echo "</ul>";

echo "<h3>Test de la fonction peutImporterHistorique() :</h3>";

$role = $_SESSION['user_role'] ?? null;

if ($role) {
    $result = peutImporterHistorique($role);

    echo "<div style='padding: 15px; border: 2px solid " . ($result ? "green" : "red") . "; background: " . ($result ? "#d4edda" : "#f8d7da") . "'>";
    echo "<strong>Résultat :</strong> " . ($result ? "✅ AUTORISÉ" : "❌ REFUSÉ") . "<br>";
    echo "<strong>Rôle testé :</strong> " . htmlspecialchars($role) . "<br>";
    echo "</div>";

    echo "<h3>Rôles autorisés pour ce module :</h3>";
    echo "<ul>";
    echo "<li>admin</li>";
    echo "<li>admin_systeme</li>";
    echo "<li>chef_service</li>";
    echo "<li>chef_service_sdtd</li>";
    echo "</ul>";

    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✅ Vous DEVRIEZ avoir accès au module d'import !</p>";
        echo "<p><a href='index.php' class='btn btn-primary'>Accéder au module d'import</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Votre rôle n'est pas autorisé.</p>";
        echo "<p>Contactez l'administrateur pour ajuster les permissions.</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Aucun rôle détecté dans la session.</p>";
}

echo "<hr>";
echo "<p><a href='../../dashboard.php'>← Retour au tableau de bord</a></p>";
?>
