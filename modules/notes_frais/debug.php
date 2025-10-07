<?php
// Debug pour vérifier les notes de frais
require_once '../../includes/auth.php';
require_once 'functions.php';

echo "<h3>Debug Notes de Frais</h3>";

// Vérifier la table
try {
    $tables_check = $pdo->query("SHOW TABLES LIKE 'notes_frais'");
    echo "<p>Table existe: " . ($tables_check->rowCount() > 0 ? "OUI" : "NON") . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}

// Compter les notes
try {
    $count = $pdo->query("SELECT COUNT(*) FROM notes_frais")->fetchColumn();
    echo "<p>Nombre total de notes dans la table: <strong>$count</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur count: " . $e->getMessage() . "</p>";
}

// Afficher toutes les notes brutes
try {
    $stmt = $pdo->query("SELECT * FROM notes_frais ORDER BY date_creation DESC");
    $notes = $stmt->fetchAll();
    echo "<p>Notes récupérées (requête simple): <strong>" . count($notes) . "</strong></p>";
    echo "<pre>";
    print_r($notes);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur requête simple: " . $e->getMessage() . "</p>";
}

// Tester la fonction avec jointures
try {
    $filters = [];
    $notes_avec_filtres = getNotesAvecFiltres($filters, 20, 0);
    echo "<h4>Notes avec fonction getNotesAvecFiltres():</h4>";
    echo "<p>Nombre: <strong>" . count($notes_avec_filtres) . "</strong></p>";
    echo "<pre>";
    print_r($notes_avec_filtres);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur getNotesAvecFiltres: " . $e->getMessage() . "</p>";
}

// Tester la requête avec JOIN manuellement
try {
    $sql = "SELECT nf.*, d.numero as dossier_numero, d.nom_demandeur,
                   u.nom as createur_nom, u.prenom as createur_prenom
            FROM notes_frais nf
            JOIN dossiers d ON nf.dossier_id = d.id
            JOIN users u ON nf.user_id = u.id
            ORDER BY nf.date_creation DESC";

    $stmt = $pdo->query($sql);
    $notes_join = $stmt->fetchAll();
    echo "<h4>Notes avec JOIN manuel:</h4>";
    echo "<p>Nombre: <strong>" . count($notes_join) . "</strong></p>";
    echo "<pre>";
    print_r($notes_join);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur JOIN: " . $e->getMessage() . "</p>";
}

// Vérifier les dossiers
try {
    $count_dossiers = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
    echo "<p>Nombre de dossiers: <strong>$count_dossiers</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur dossiers: " . $e->getMessage() . "</p>";
}

// Vérifier les users
try {
    $count_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p>Nombre d'utilisateurs: <strong>$count_users</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur users: " . $e->getMessage() . "</p>";
}
?>
