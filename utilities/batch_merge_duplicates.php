<?php
/**
 * Script de fusion en masse des doublons haute confiance
 * Permet de traiter plusieurs fusions d'un coup avec validation
 */

require_once 'config/database.php';
session_start();

// Fonction Haversine
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLatRad = deg2rad($lat2 - $lat1);
    $deltaLonRad = deg2rad($lon2 - $lon1);
    $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLonRad / 2) * sin($deltaLonRad / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// Parser GPS
function parseGPSCoordinates($gps_string) {
    if (empty($gps_string)) return null;
    $parts = array_map('trim', explode(',', $gps_string));
    if (count($parts) === 2) {
        $lat = floatval($parts[0]);
        $lon = floatval($parts[1]);
        if ($lat >= 1.5 && $lat <= 13.5 && $lon >= 8.0 && $lon <= 16.5) {
            return ['latitude' => $lat, 'longitude' => $lon];
        }
    }
    return null;
}

// Similarité de texte
function similarityScore($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    $str1 = preg_replace('/[^a-z0-9\s]/', '', $str1);
    $str2 = preg_replace('/[^a-z0-9\s]/', '', $str2);
    similar_text($str1, $str2, $percent);
    return $percent;
}

// Fonction d'exécution de fusion
function executeMerge($pdo, $merge_id, $keep_id, $station_to_merge, $station_to_keep) {
    try {
        $pdo->beginTransaction();

        // 1. Logger la fusion
        $log_description = sprintf(
            "Fusion automatique: Station #%d (%s) fusionnée dans #%d (%s). GPS: %s -> %s",
            $merge_id,
            $station_to_merge['nom_demandeur'],
            $keep_id,
            $station_to_keep['nom_demandeur'],
            $station_to_merge['coordonnees_gps'] ?? 'N/A',
            $station_to_keep['coordonnees_gps'] ?? 'N/A'
        );

        $stmt_log = $pdo->prepare("
            INSERT INTO logs_activite (user_id, action, description, date_action)
            VALUES (?, 'fusion_masse_doublon', ?, NOW())
        ");
        $user_id = $_SESSION['user_id'] ?? 1;
        $stmt_log->execute([$user_id, $log_description]);

        // 2. Mettre à jour les références
        $pdo->prepare("UPDATE documents SET dossier_id = ? WHERE dossier_id = ?")
            ->execute([$keep_id, $merge_id]);

        try {
            $pdo->prepare("UPDATE historique SET dossier_id = ? WHERE dossier_id = ?")
                ->execute([$keep_id, $merge_id]);
        } catch (PDOException $e) {}

        try {
            $pdo->prepare("UPDATE commissions SET dossier_id = ? WHERE dossier_id = ?")
                ->execute([$keep_id, $merge_id]);
        } catch (PDOException $e) {}

        try {
            $pdo->prepare("UPDATE notifications SET dossier_id = ? WHERE dossier_id = ?")
                ->execute([$keep_id, $merge_id]);
        } catch (PDOException $e) {}

        // 3. Archiver puis supprimer
        try {
            $pdo->prepare("UPDATE dossiers SET archive = 1 WHERE id = ?")
                ->execute([$merge_id]);
        } catch (PDOException $e) {}

        $pdo->prepare("DELETE FROM dossiers WHERE id = ?")
            ->execute([$merge_id]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Fusion réussie'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Mode d'exécution
$execution_mode = $_GET['mode'] ?? 'preview';
$selected_pairs = isset($_POST['selected']) ? $_POST['selected'] : [];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fusion en Masse des Doublons - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        .warning-banner { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db; text-align: center; }
        .stat-value { font-size: 2.5em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr.selected { background: #d4edda !important; }
        .pair-row { cursor: pointer; transition: background 0.2s; }
        .pair-row:hover { background: #e8f4f8; }
        .confidence-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .high { background: #e74c3c; color: white; }
        .medium { background: #f39c12; color: white; }
        .low { background: #95a5a6; color: white; }
        .action-bar { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .btn { padding: 12px 25px; margin: 0 10px; border: none; border-radius: 4px; font-size: 1em; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .progress-section { margin: 30px 0; }
        .progress-bar-container { width: 100%; height: 40px; background: #ecf0f1; border-radius: 20px; overflow: hidden; margin: 10px 0; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); transition: width 0.5s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .result-item { padding: 10px; margin: 5px 0; border-radius: 4px; border-left: 4px solid; }
        .result-success { background: #d4edda; border-color: #28a745; }
        .result-error { background: #f8d7da; border-color: #dc3545; }
        .checkbox-cell { width: 50px; text-align: center; }
        .checkbox-cell input { width: 20px; height: 20px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔄 Fusion en Masse des Doublons</h1>

    <?php if ($execution_mode === 'preview'): ?>
        <?php
        // PHASE 1 : DÉTECTION ET SÉLECTION

        // Récupérer toutes les stations
        $stmt = $pdo->query("
            SELECT d.id, d.numero, d.nom_demandeur, d.coordonnees_gps, d.statut, d.region, d.type_infrastructure
            FROM dossiers d
            WHERE d.statut IN ('autorise', 'historique_autorise')
            ORDER BY d.nom_demandeur
        ");

        $stations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $dossier) {
            $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
            if ($coords) {
                $stations[] = array_merge($dossier, $coords);
            }
        }

        // Détecter les doublons
        $doublons = [];
        for ($i = 0; $i < count($stations); $i++) {
            for ($j = $i + 1; $j < count($stations); $j++) {
                $s1 = $stations[$i];
                $s2 = $stations[$j];

                $distance = haversineDistance(
                    $s1['latitude'], $s1['longitude'],
                    $s2['latitude'], $s2['longitude']
                );

                $similarity = similarityScore($s1['nom_demandeur'], $s2['nom_demandeur']);

                if ($distance < 100 || ($similarity > 80 && $s1['region'] === $s2['region'])) {
                    $confidence = ($distance < 50 && $similarity > 70) ? 'high' :
                                (($distance < 100 && $similarity > 60) ? 'medium' : 'low');

                    // Déterminer quelle station garder
                    $keep = $s1;
                    $merge = $s2;

                    if ($s2['statut'] === 'historique_autorise' && $s1['statut'] !== 'historique_autorise') {
                        $keep = $s2;
                        $merge = $s1;
                    }

                    $doublons[] = [
                        'merge_id' => $merge['id'],
                        'keep_id' => $keep['id'],
                        'station_merge' => $merge,
                        'station_keep' => $keep,
                        'distance' => $distance,
                        'similarity' => $similarity,
                        'confidence' => $confidence
                    ];
                }
            }
        }

        $high_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'high');
        $medium_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'medium');
        $low_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'low');
        ?>

        <div class="info-box">
            <strong>📊 Détection automatique des doublons</strong><br>
            Analyse de <?php echo count($stations); ?> stations pour identifier les doublons potentiels.<br>
            Les fusions proposées sont basées sur la proximité GPS et la similarité des noms.
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-value" style="color: #e74c3c;"><?php echo count($high_confidence); ?></div>
                <div class="stat-label">Haute confiance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #f39c12;"><?php echo count($medium_confidence); ?></div>
                <div class="stat-label">Confiance moyenne</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #95a5a6;"><?php echo count($low_confidence); ?></div>
                <div class="stat-label">Faible confiance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #3498db;"><?php echo count($doublons); ?></div>
                <div class="stat-label">Total détecté</div>
            </div>
        </div>

        <?php if (count($high_confidence) === 0): ?>
            <div class="success-box">
                <strong>✅ Aucun doublon haute confiance détecté !</strong><br>
                Les données sont propres ou ont déjà été nettoyées.
            </div>
        <?php else: ?>
            <div class="warning-banner">
                <strong>⚠️ ATTENTION - Fusion en Masse</strong><br><br>
                Vous êtes sur le point de fusionner plusieurs doublons en une seule opération.<br>
                • Sélectionnez les paires que vous souhaitez fusionner<br>
                • Vérifiez soigneusement chaque proposition<br>
                • Les fusions sont <strong>irréversibles</strong><br>
                • Un log d'audit sera créé pour chaque fusion
            </div>

            <form method="POST" action="batch_merge_duplicates.php?mode=execute" id="mergeForm">
                <div class="action-bar">
                    <button type="button" class="btn btn-primary" onclick="selectAll(true)">✅ Tout sélectionner</button>
                    <button type="button" class="btn btn-secondary" onclick="selectAll(false)">❌ Tout désélectionner</button>
                    <button type="button" class="btn btn-warning" onclick="selectByConfidence('high')">🔴 Sélectionner haute confiance</button>
                    <span style="margin: 0 20px; font-weight: bold;">
                        Sélectionnées : <span id="selectedCount">0</span> / <?php echo count($high_confidence); ?>
                    </span>
                    <button type="submit" class="btn btn-danger" id="submitBtn" disabled>
                        🚀 LANCER LES FUSIONS
                    </button>
                </div>

                <h2>🎯 Doublons Haute Confiance (Fusion Recommandée)</h2>

                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell"><input type="checkbox" id="selectAllCheckbox" onchange="selectAll(this.checked)"></th>
                            <th>❌ À Supprimer</th>
                            <th>GPS</th>
                            <th>✅ À Conserver</th>
                            <th>GPS</th>
                            <th>Distance</th>
                            <th>Similarité</th>
                            <th>Confiance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($high_confidence as $idx => $doublon): ?>
                            <tr class="pair-row" onclick="toggleRow(this, <?php echo $idx; ?>)" data-confidence="high">
                                <td class="checkbox-cell" onclick="event.stopPropagation();">
                                    <input type="checkbox"
                                           name="selected[]"
                                           value="<?php echo $doublon['merge_id'] . '|' . $doublon['keep_id']; ?>"
                                           class="pair-checkbox"
                                           onchange="updateCount()">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doublon['station_merge']['nom_demandeur']); ?></strong><br>
                                    <small>N° <?php echo $doublon['station_merge']['numero']; ?></small><br>
                                    <small style="color: #7f8c8d;"><?php echo $doublon['station_merge']['statut']; ?></small>
                                </td>
                                <td><small><?php echo htmlspecialchars($doublon['station_merge']['coordonnees_gps'] ?? 'N/A'); ?></small></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doublon['station_keep']['nom_demandeur']); ?></strong><br>
                                    <small>N° <?php echo $doublon['station_keep']['numero']; ?></small><br>
                                    <small style="color: #7f8c8d;"><?php echo $doublon['station_keep']['statut']; ?></small>
                                </td>
                                <td><small><?php echo htmlspecialchars($doublon['station_keep']['coordonnees_gps'] ?? 'N/A'); ?></small></td>
                                <td><strong><?php echo round($doublon['distance'], 1); ?> m</strong></td>
                                <td><?php echo round($doublon['similarity'], 1); ?>%</td>
                                <td><span class="confidence-badge <?php echo $doublon['confidence']; ?>">HAUTE</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (count($medium_confidence) > 0): ?>
                    <h2>⚠️ Doublons Confiance Moyenne (Vérification Recommandée)</h2>
                    <p><em>Affichage des 10 premières paires - Vérifiez manuellement avant fusion</em></p>

                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">☑️</th>
                                <th>❌ À Supprimer</th>
                                <th>✅ À Conserver</th>
                                <th>Distance</th>
                                <th>Similarité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($medium_confidence, 0, 10) as $idx => $doublon): ?>
                                <tr class="pair-row" onclick="toggleRow(this, <?php echo $idx + 1000; ?>)" data-confidence="medium">
                                    <td class="checkbox-cell" onclick="event.stopPropagation();">
                                        <input type="checkbox"
                                               name="selected[]"
                                               value="<?php echo $doublon['merge_id'] . '|' . $doublon['keep_id']; ?>"
                                               class="pair-checkbox"
                                               onchange="updateCount()">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($doublon['station_merge']['nom_demandeur']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($doublon['station_keep']['nom_demandeur']); ?></strong></td>
                                    <td><?php echo round($doublon['distance'], 1); ?> m</td>
                                    <td><?php echo round($doublon['similarity'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <script>
            function selectAll(checked) {
                document.querySelectorAll('.pair-checkbox').forEach(cb => cb.checked = checked);
                document.getElementById('selectAllCheckbox').checked = checked;
                updateCount();
            }

            function selectByConfidence(confidence) {
                document.querySelectorAll('.pair-row').forEach(row => {
                    if (row.dataset.confidence === confidence) {
                        const checkbox = row.querySelector('.pair-checkbox');
                        checkbox.checked = true;
                    }
                });
                updateCount();
            }

            function toggleRow(row, idx) {
                const checkbox = row.querySelector('.pair-checkbox');
                checkbox.checked = !checkbox.checked;
                updateCount();
            }

            function updateCount() {
                const count = document.querySelectorAll('.pair-checkbox:checked').length;
                document.getElementById('selectedCount').textContent = count;
                document.getElementById('submitBtn').disabled = count === 0;

                // Highlight selected rows
                document.querySelectorAll('.pair-row').forEach(row => {
                    const checkbox = row.querySelector('.pair-checkbox');
                    if (checkbox.checked) {
                        row.classList.add('selected');
                    } else {
                        row.classList.remove('selected');
                    }
                });
            }

            document.getElementById('mergeForm').onsubmit = function(e) {
                const count = document.querySelectorAll('.pair-checkbox:checked').length;
                if (!confirm(`⚠️ CONFIRMATION FINALE\n\nVous êtes sur le point de fusionner ${count} paire(s) de doublons.\n\nCette action est IRRÉVERSIBLE.\n\nContinuer ?`)) {
                    e.preventDefault();
                    return false;
                }
            };

            // Initial count
            updateCount();
        </script>

    <?php elseif ($execution_mode === 'execute'): ?>
        <?php
        // PHASE 2 : EXÉCUTION DES FUSIONS

        if (empty($selected_pairs)) {
            echo "<div class='error-box'><strong>❌ Erreur :</strong> Aucune paire sélectionnée.</div>";
            echo "<a href='batch_merge_duplicates.php' class='btn btn-secondary'>← Retour</a>";
            exit;
        }

        $total = count($selected_pairs);
        $success_count = 0;
        $error_count = 0;
        $results = [];
        ?>

        <div class="info-box">
            <strong>🚀 Exécution des fusions en masse</strong><br>
            Traitement de <?php echo $total; ?> fusion(s)...
        </div>

        <div class="progress-section">
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progressBar" style="width: 0%;">0%</div>
            </div>
        </div>

        <div id="resultsContainer">
            <?php
            flush();
            ob_flush();

            foreach ($selected_pairs as $idx => $pair) {
                list($merge_id, $keep_id) = explode('|', $pair);

                // Récupérer les stations
                $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id IN (?, ?)");
                $stmt->execute([$merge_id, $keep_id]);
                $stations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $station_merge = null;
                $station_keep = null;

                foreach ($stations_data as $s) {
                    if ($s['id'] == $merge_id) $station_merge = $s;
                    if ($s['id'] == $keep_id) $station_keep = $s;
                }

                if ($station_merge && $station_keep) {
                    $result = executeMerge($pdo, $merge_id, $keep_id, $station_merge, $station_keep);

                    if ($result['success']) {
                        $success_count++;
                        echo "<div class='result-item result-success'>";
                        echo "✅ <strong>Fusion réussie :</strong> Station #{$merge_id} → #{$keep_id} | ";
                        echo htmlspecialchars($station_merge['nom_demandeur']);
                        echo "</div>\n";
                    } else {
                        $error_count++;
                        echo "<div class='result-item result-error'>";
                        echo "❌ <strong>Erreur :</strong> Station #{$merge_id} → #{$keep_id} | ";
                        echo htmlspecialchars($result['message']);
                        echo "</div>\n";
                    }
                } else {
                    $error_count++;
                    echo "<div class='result-item result-error'>";
                    echo "❌ <strong>Erreur :</strong> Station(s) introuvable(s) pour #{$merge_id} → #{$keep_id}";
                    echo "</div>\n";
                }

                // Mise à jour de la progression
                $progress = round((($idx + 1) / $total) * 100);
                echo "<script>document.getElementById('progressBar').style.width = '{$progress}%'; document.getElementById('progressBar').textContent = '{$progress}%';</script>\n";

                flush();
                ob_flush();
                usleep(200000); // Pause 0.2s pour visualisation
            }
            ?>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-value" style="color: #27ae60;"><?php echo $success_count; ?></div>
                <div class="stat-label">Fusions réussies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #e74c3c;"><?php echo $error_count; ?></div>
                <div class="stat-label">Erreurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #3498db;"><?php echo $total; ?></div>
                <div class="stat-label">Total traité</div>
            </div>
        </div>

        <?php if ($success_count === $total): ?>
            <div class="success-box">
                <strong>🎉 Succès complet !</strong><br>
                Toutes les fusions ont été effectuées avec succès. Les données sont maintenant nettoyées.
            </div>
        <?php elseif ($success_count > 0): ?>
            <div class="warning-banner">
                <strong>⚠️ Succès partiel</strong><br>
                <?php echo $success_count; ?> fusion(s) réussie(s) sur <?php echo $total; ?>.
                Vérifiez les erreurs ci-dessus.
            </div>
        <?php else: ?>
            <div class="error-box">
                <strong>❌ Échec</strong><br>
                Aucune fusion n'a pu être effectuée. Vérifiez les erreurs et réessayez.
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="batch_merge_duplicates.php" class="btn btn-primary">🔄 Relancer une analyse</a>
            <a href="modules/registre_public/carte.php" class="btn btn-success">🗺️ Voir la carte</a>
            <a href="diagnostic_data_quality.php" class="btn btn-secondary">📊 Diagnostic qualité</a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>
