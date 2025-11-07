<?php
// Debug pour vérifier les IDs des dossiers - Accessible uniquement aux admins
require_once '../../includes/auth.php';
requireRole('admin');

echo "<h3>Vérification des IDs de dossiers</h3>";

// Lister les IDs de dossiers existants
try {
    $stmt = $pdo->query("SELECT id, numero, nom_demandeur FROM dossiers ORDER BY id");
    $dossiers = $stmt->fetchAll();
    echo "<h4>Dossiers existants:</h4>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Numéro</th><th>Demandeur</th></tr>";
    foreach ($dossiers as $d) {
        echo "<tr><td>{$d['id']}</td><td>{$d['numero']}</td><td>{$d['nom_demandeur']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}

// Vérifier les dossier_id dans notes_frais
echo "<h4>Dossier IDs dans notes_frais:</h4>";
try {
    $stmt = $pdo->query("SELECT id, dossier_id, description FROM notes_frais ORDER BY id");
    $notes = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Note ID</th><th>Dossier ID</th><th>Description</th></tr>";
    foreach ($notes as $n) {
        echo "<tr><td>{$n['id']}</td><td>{$n['dossier_id']}</td><td>{$n['description']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}

// Vérifier si les dossiers 13, 14, 15 existent
echo "<h4>Vérification des dossiers 13, 14, 15:</h4>";
foreach ([13, 14, 15] as $id) {
    try {
        $stmt = $pdo->prepare("SELECT id, numero, nom_demandeur FROM dossiers WHERE id = ?");
        $stmt->execute([$id]);
        $dossier = $stmt->fetch();
        if ($dossier) {
            echo "<p>Dossier ID $id: <strong>EXISTE</strong> - {$dossier['numero']} - {$dossier['nom_demandeur']}</p>";
        } else {
            echo "<p style='color: red;'>Dossier ID $id: <strong>N'EXISTE PAS</strong></p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur dossier $id: " . $e->getMessage() . "</p>";
    }
}
?>
