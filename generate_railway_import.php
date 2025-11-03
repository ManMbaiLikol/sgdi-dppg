<?php
/**
 * Génération du script SQL d'import MINEE pour Railway
 * Ce script lit le CSV MINEE et génère un fichier SQL prêt à être exécuté sur Railway
 */

require_once 'config/database.php';

set_time_limit(600);
ini_set('memory_limit', '512M');

$FICHIER_MINEE = 'F:/PROJETS DPPG/Stations_Service-1_ANALYSE.csv';
$FICHIER_SQL_OUTPUT = 'railway_import_minee.sql';

if (!file_exists($FICHIER_MINEE)) {
    die("❌ Fichier MINEE introuvable : $FICHIER_MINEE");
}

echo "🚀 Génération du script SQL pour Railway avec encodage UTF-8...\n\n";

// Forcer l'encodage UTF-8
mb_internal_encoding('UTF-8');

// Ouvrir le fichier de sortie SQL en UTF-8
$sql_output = fopen($FICHIER_SQL_OUTPUT, 'w');

// Ajouter le BOM UTF-8 pour forcer l'encodage
fwrite($sql_output, "\xEF\xBB\xBF");

// En-tête du fichier SQL
fwrite($sql_output, "-- ============================================\n");
fwrite($sql_output, "-- Import des stations historiques MINEE (UTF-8)\n");
fwrite($sql_output, "-- Généré le : " . date('Y-m-d H:i:s') . "\n");
fwrite($sql_output, "-- ============================================\n\n");
fwrite($sql_output, "SET NAMES utf8mb4;\n");
fwrite($sql_output, "SET CHARACTER SET utf8mb4;\n\n");

fwrite($sql_output, "-- Étape 1 : Vérification avant import\n");
fwrite($sql_output, "SELECT COUNT(*) as total_avant FROM dossiers;\n");
fwrite($sql_output, "SELECT COUNT(*) as historiques_avant FROM dossiers WHERE est_historique = 1;\n\n");

fwrite($sql_output, "-- Étape 2 : Suppression des stations historiques existantes\n");
fwrite($sql_output, "DELETE FROM dossiers WHERE est_historique = 1;\n\n");

fwrite($sql_output, "-- Étape 3 : Import des nouvelles stations MINEE\n");
fwrite($sql_output, "-- Total de stations à importer depuis le fichier CSV\n\n");

// Ouvrir le CSV
$handle = fopen($FICHIER_MINEE, 'r');

// Ignorer la ligne d'en-tête
$headers = fgetcsv($handle, 0, ';');

$line_num = 0;
$imported = 0;
$skipped = 0;

echo "📊 Analyse du fichier CSV...\n";
echo "Colonnes détectées : " . count($headers) . "\n";
echo "En-têtes : " . implode(' | ', $headers) . "\n\n";

// Commencer la transaction
fwrite($sql_output, "BEGIN;\n\n");

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $line_num++;

    // Extraction des données selon le mapping
    $numero = isset($row[0]) && !empty(trim($row[0])) ? trim($row[0]) : 'HIST-' . str_pad($line_num, 5, '0', STR_PAD_LEFT);
    $nom = isset($row[1]) ? trim($row[1]) : ''; // Marketer
    $region = isset($row[2]) ? trim($row[2]) : '';
    $departement = isset($row[3]) ? trim($row[3]) : '';
    $arrondissement = isset($row[4]) ? trim($row[4]) : '';
    $ville = isset($row[5]) ? trim($row[5]) : '';
    $quartier = isset($row[6]) ? trim($row[6]) : '';
    $lieu_dit = isset($row[7]) ? trim($row[7]) : '';
    $zone_implantation = isset($row[8]) ? trim($row[8]) : '';

    // Convertir TOUS les champs en UTF-8 (depuis Windows-1252 ou ISO-8859-1)
    $numero = mb_convert_encoding($numero, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $nom = mb_convert_encoding($nom, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $region = mb_convert_encoding($region, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $departement = mb_convert_encoding($departement, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $arrondissement = mb_convert_encoding($arrondissement, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $ville = mb_convert_encoding($ville, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $quartier = mb_convert_encoding($quartier, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $lieu_dit = mb_convert_encoding($lieu_dit, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    $zone_implantation = mb_convert_encoding($zone_implantation, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');

    // Si le nom est vide, ignorer cette ligne
    if (empty($nom)) {
        $skipped++;
        fwrite($sql_output, "-- Ligne $line_num ignorée : nom vide\n");
        continue;
    }

    // Construire l'adresse complète structurée
    $adresse_parts = [];
    if (!empty($lieu_dit)) $adresse_parts[] = "Lieu-dit: $lieu_dit";
    if (!empty($quartier)) $adresse_parts[] = "Quartier: $quartier";
    if (!empty($arrondissement)) $adresse_parts[] = "Arrondissement: $arrondissement";
    if (!empty($departement)) $adresse_parts[] = "Département: $departement";
    if (!empty($zone_implantation)) $adresse_parts[] = "Zone: $zone_implantation";
    $adresse = implode(', ', $adresse_parts);

    // Échapper les apostrophes pour SQL (PostgreSQL utilise '')
    $numero_escaped = str_replace("'", "''", $numero);
    $nom_escaped = str_replace("'", "''", $nom);
    $region_escaped = str_replace("'", "''", $region);
    $ville_escaped = str_replace("'", "''", $ville);
    $adresse_escaped = str_replace("'", "''", $adresse);

    // Générer l'instruction INSERT
    $sql = "INSERT INTO dossiers (numero, nom_demandeur, type_infrastructure, sous_type, region, ville, adresse_precise, statut, est_historique, coordonnees_gps, user_id, date_creation) VALUES ";
    $sql .= "('$numero_escaped', '$nom_escaped', 'station_service', 'implantation', '$region_escaped', '$ville_escaped', '$adresse_escaped', 'historique_autorise', TRUE, NULL, 1, NOW());\n";

    fwrite($sql_output, $sql);
    $imported++;

    // Afficher la progression tous les 100 enregistrements
    if ($imported % 100 == 0) {
        echo "✓ $imported stations générées...\n";
    }
}

fclose($handle);

// Finaliser la transaction
fwrite($sql_output, "\nCOMMIT;\n\n");

// Vérifications finales
fwrite($sql_output, "-- Étape 4 : Vérification après import\n");
fwrite($sql_output, "SELECT COUNT(*) as total_apres FROM dossiers;\n");
fwrite($sql_output, "SELECT COUNT(*) as historiques_apres FROM dossiers WHERE est_historique = 1;\n\n");

fwrite($sql_output, "-- Étape 5 : Statistiques par région\n");
fwrite($sql_output, "SELECT region, COUNT(*) as nb_stations FROM dossiers WHERE est_historique = 1 GROUP BY region ORDER BY nb_stations DESC;\n\n");

fwrite($sql_output, "-- Étape 6 : Top 10 opérateurs\n");
fwrite($sql_output, "SELECT nom_demandeur, COUNT(*) as nb_stations FROM dossiers WHERE est_historique = 1 GROUP BY nom_demandeur ORDER BY nb_stations DESC LIMIT 10;\n");

fclose($sql_output);

// Résumé
echo "\n✅ Génération terminée !\n\n";
echo "📊 Statistiques :\n";
echo "   • Lignes traitées : $line_num\n";
echo "   • Stations importées : $imported\n";
echo "   • Lignes ignorées : $skipped\n\n";
echo "📄 Fichier SQL généré : $FICHIER_SQL_OUTPUT\n";
echo "   Taille : " . round(filesize($FICHIER_SQL_OUTPUT) / 1024, 2) . " Ko\n\n";

echo "🚀 Prochaines étapes :\n";
echo "   1. Ouvrir Railway Dashboard\n";
echo "   2. Accéder à votre base de données PostgreSQL\n";
echo "   3. Ouvrir l'onglet 'Query'\n";
echo "   4. Copier-coller le contenu de '$FICHIER_SQL_OUTPUT'\n";
echo "   5. Exécuter le script SQL\n\n";

echo "⚠️  ATTENTION : Ce script va :\n";
echo "   • Supprimer TOUTES les stations historiques existantes\n";
echo "   • Importer $imported nouvelles stations SANS GPS\n";
echo "   • Cette opération est IRRÉVERSIBLE\n\n";
?>
