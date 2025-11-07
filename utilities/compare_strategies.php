<?php
/**
 * Comparaison des stratégies : Correction GPS vs Suppression/Recréation
 * Analyse l'état actuel et recommande la meilleure approche
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n";
echo "<title>Stratégie de Nettoyage des Données - DPPG</title>\n";
echo "<style>
body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 15px; }
h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
h3 { color: #7f8c8d; margin-top: 20px; }
.comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
.strategy-card { background: #f8f9fa; padding: 25px; border-radius: 8px; border: 2px solid #ddd; }
.strategy-card.recommended { border-color: #27ae60; background: #edfaf4; }
.strategy-card h3 { margin-top: 0; color: #2c3e50; }
.pros { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
.cons { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
.neutral { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 10px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.critical { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 4px; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-value { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
.stat-label { font-size: 0.9em; opacity: 0.9; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
th { background: #3498db; color: white; position: sticky; top: 0; }
tr:nth-child(even) { background: #f8f9fa; }
.badge { display: inline-block; padding: 5px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold; margin: 2px; }
.badge-success { background: #28a745; color: white; }
.badge-danger { background: #dc3545; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.badge-info { background: #17a2b8; color: white; }
ul { line-height: 1.8; }
strong { color: #2c3e50; }
.recommendation { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin: 30px 0; }
.recommendation h2 { color: white; border: none; }
.action-plan { background: #fff; color: #333; padding: 20px; border-radius: 8px; margin-top: 20px; }
.step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #3498db; border-radius: 4px; }
.step-number { background: #3498db; color: white; width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; }
</style>
</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🎯 Stratégie de Nettoyage des Données GPS</h1>\n";

// Analyse de l'état actuel
$stats_historiques = $pdo->query("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 END) as avec_gps,
        COUNT(CASE WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN 1 END) as sans_gps
    FROM dossiers
    WHERE statut = 'historique_autorise' OR est_historique = 1
")->fetch(PDO::FETCH_ASSOC);

$stats_nouvelles = $pdo->query("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 END) as avec_gps
    FROM dossiers
    WHERE statut = 'autorise' AND (est_historique = 0 OR est_historique IS NULL)
")->fetch(PDO::FETCH_ASSOC);

// Analyse qualité des GPS historiques
$qualite_gps = $pdo->query("
    SELECT
        coordonnees_gps,
        COUNT(*) as occurrences
    FROM dossiers
    WHERE (statut = 'historique_autorise' OR est_historique = 1)
    AND coordonnees_gps IS NOT NULL
    AND coordonnees_gps != ''
    GROUP BY coordonnees_gps
    HAVING COUNT(*) > 1
")->fetchAll(PDO::FETCH_ASSOC);

$gps_dupliques = count($qualite_gps);
$stations_doublons = array_sum(array_column($qualite_gps, 'occurrences'));

// Compter les tables liées aux historiques
// Vérifier d'abord quelles tables existent
$tables_existantes = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$has_commissions = in_array('commissions', $tables_existantes);
$has_notes_frais = in_array('notes_frais', $tables_existantes);
$has_paiements = in_array('paiements', $tables_existantes);
$has_documents = in_array('documents', $tables_existantes);

$relations = [
    'commissions' => 0,
    'notes_frais' => 0,
    'paiements' => 0,
    'documents' => 0
];

if ($has_commissions) {
    $relations['commissions'] = $pdo->query("SELECT COUNT(*) FROM commissions WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
}
if ($has_notes_frais) {
    $relations['notes_frais'] = $pdo->query("SELECT COUNT(*) FROM notes_frais WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
}
if ($has_paiements) {
    $relations['paiements'] = $pdo->query("SELECT COUNT(*) FROM paiements WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
}
if ($has_documents) {
    $relations['documents'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
}

$total_relations = array_sum($relations);

echo "<h2>📊 État Actuel des Données</h2>\n";
echo "<div class='stats'>\n";
echo "<div class='stat-card'>\n";
echo "<div class='stat-value'>{$stats_historiques['total']}</div>\n";
echo "<div class='stat-label'>Stations historiques totales</div>\n";
echo "</div>\n";

echo "<div class='stat-card green'>\n";
echo "<div class='stat-value'>{$stats_historiques['avec_gps']}</div>\n";
echo "<div class='stat-label'>Avec GPS</div>\n";
echo "</div>\n";

echo "<div class='stat-card red'>\n";
echo "<div class='stat-value'>{$stats_historiques['sans_gps']}</div>\n";
echo "<div class='stat-label'>Sans GPS</div>\n";
echo "</div>\n";

echo "<div class='stat-card orange'>\n";
echo "<div class='stat-value'>$gps_dupliques</div>\n";
echo "<div class='stat-label'>GPS dupliqués</div>\n";
echo "</div>\n";
echo "</div>\n";

$taux_gps = round(($stats_historiques['avec_gps'] / $stats_historiques['total']) * 100, 1);
$taux_doublon = $stats_historiques['avec_gps'] > 0 ? round(($stations_doublons / $stats_historiques['avec_gps']) * 100, 1) : 0;

echo "<div class='neutral'>\n";
echo "<strong>Analyse rapide :</strong><br>\n";
echo "• $taux_gps% des stations historiques ont un GPS<br>\n";
echo "• $taux_doublon% des GPS sont dupliqués (problème de qualité)<br>\n";
echo "• $total_relations enregistrements liés dans les tables relationnelles<br>\n";
echo "• {$stats_nouvelles['total']} nouvelles stations autorisées (non historiques)\n";
echo "</div>\n";

// Comparaison des deux stratégies
echo "<h2>⚖️ Comparaison des Deux Stratégies</h2>\n";

echo "<div class='comparison'>\n";

// Stratégie 1 : Correction
echo "<div class='strategy-card'>\n";
echo "<h3>📝 Stratégie 1 : Corriger les GPS existants</h3>\n";

echo "<div class='pros'>\n";
echo "<strong>✅ AVANTAGES :</strong>\n";
echo "<ul>\n";
echo "<li><strong>Conservation de l'historique</strong> : Tous les liens avec commissions, paiements, documents sont préservés</li>\n";
echo "<li><strong>Moins risqué</strong> : Modification ciblée, pas de suppression</li>\n";
echo "<li><strong>Traçabilité</strong> : L'historique des actions reste intact</li>\n";
echo "<li><strong>Progressif</strong> : Peut se faire station par station</li>\n";
echo "<li><strong>Réversible</strong> : Possibilité de rollback si erreur</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div class='cons'>\n";
echo "<strong>❌ INCONVÉNIENTS :</strong>\n";
echo "<ul>\n";
echo "<li><strong>Complexe</strong> : Nécessite scripts sophistiqués (géocodage, fusion doublons)</li>\n";
echo "<li><strong>Temps consommé</strong> : Analyse + correction + validation = plusieurs jours</li>\n";
echo "<li><strong>Risque d'erreurs</strong> : Mauvais géocodage, fusion incorrecte</li>\n";
echo "<li><strong>Données polluées</strong> : Même corrigés, les GPS restent de qualité moyenne</li>\n";
echo "<li><strong>Maintenance future</strong> : Problèmes récurrents si source OSM défaillante</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div class='neutral'>\n";
echo "<strong>⏱️ EFFORT ESTIMÉ :</strong> 3-5 jours de développement + tests\n";
echo "</div>\n";

echo "</div>\n";

// Stratégie 2 : Suppression/Recréation
echo "<div class='strategy-card recommended'>\n";
echo "<h3>🔄 Stratégie 2 : Supprimer et recréer SANS GPS</h3>\n";
echo "<span class='badge badge-success'>RECOMMANDÉE</span>\n";

echo "<div class='pros'>\n";
echo "<strong>✅ AVANTAGES :</strong>\n";
echo "<ul>\n";
echo "<li><strong>Données propres</strong> : Repartir sur une base saine</li>\n";
echo "<li><strong>Simple et rapide</strong> : 1 script SQL suffit</li>\n";
echo "<li><strong>Pas de doublons</strong> : Élimination garantie</li>\n";
echo "<li><strong>GPS optionnel</strong> : Ajout progressif des GPS réels (terrain, opérateurs)</li>\n";
echo "<li><strong>Conformité métier</strong> : GPS pas obligatoire pour stations historiques</li>\n";
echo "<li><strong>Évolutif</strong> : GPS ajoutables ultérieurement par interface admin</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div class='cons'>\n";
echo "<strong>❌ INCONVÉNIENTS :</strong>\n";
echo "<ul>\n";
echo "<li><strong>Perte liens</strong> : Supprime $total_relations enregistrements liés (commissions, paiements, etc.)</li>\n";
echo "<li><strong>Irréversible</strong> : Besoin d'un backup avant</li>\n";
echo "<li><strong>Pas de carte</strong> : Stations non visibles sur carte publique</li>\n";
echo "<li><strong>Besoin export</strong> : Sauvegarder les données métier avant suppression</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div class='neutral'>\n";
echo "<strong>⏱️ EFFORT ESTIMÉ :</strong> 1 journée (backup + script + validation)\n";
echo "</div>\n";

echo "</div>\n";

echo "</div>\n";

// Questions critiques
echo "<h2>❓ Questions Critiques à se Poser</h2>\n";

echo "<table>\n";
echo "<thead><tr><th>Question</th><th>Impact sur la décision</th></tr></thead>\n";
echo "<tbody>\n";

echo "<tr>\n";
echo "<td><strong>Les $total_relations enregistrements liés sont-ils importants ?</strong></td>\n";
echo "<td>";
if ($total_relations > 100) {
    echo "<span class='badge badge-danger'>CRITIQUE</span> Beaucoup de données liées → Privilégier correction";
} else {
    echo "<span class='badge badge-success'>OK</span> Peu de données liées → Suppression acceptable";
}
echo "</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td><strong>Les GPS historiques sont-ils utilisés pour des décisions métier ?</strong></td>\n";
echo "<td><span class='badge badge-info'>INFO</span> Si oui → Correction nécessaire. Si non → Suppression OK</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td><strong>Est-ce que la carte publique DOIT afficher les stations historiques ?</strong></td>\n";
echo "<td><span class='badge badge-warning'>MÉTIER</span> Si oui → Correction. Si non → Suppression acceptable</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td><strong>Avez-vous les moyens d'obtenir les vrais GPS (terrain/opérateurs) ?</strong></td>\n";
echo "<td><span class='badge badge-success'>OPPORTUNITÉ</span> Si oui → Suppression + ajout progressif GPS réels = meilleure solution</td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td><strong>Le temps de développement est-il un facteur limitant ?</strong></td>\n";
echo "<td><span class='badge badge-warning'>RESSOURCES</span> Si oui → Suppression (1j) vs Correction (5j)</td>\n";
echo "</tr>\n";

echo "</tbody>\n";
echo "</table>\n";

// Recommandation finale
echo "<div class='recommendation'>\n";
echo "<h2>🎯 Ma Recommandation : STRATÉGIE 2 (Suppression/Recréation)</h2>\n";

echo "<p style='font-size: 1.1em; line-height: 1.8;'>\n";
echo "Après analyse, je recommande de <strong>supprimer les stations historiques et les recréer SANS GPS</strong> pour les raisons suivantes :\n";
echo "</p>\n";

echo "<div class='action-plan'>\n";
echo "<h3 style='margin-top: 0; color: #2c3e50;'>📋 Plan d'Action Proposé</h3>\n";

echo "<div class='step'>\n";
echo "<span class='step-number'>1</span>\n";
echo "<strong>BACKUP COMPLET</strong><br>\n";
echo "Export SQL + CSV de toutes les données historiques et leurs relations\n";
echo "</div>\n";

echo "<div class='step'>\n";
echo "<span class='step-number'>2</span>\n";
echo "<strong>EXPORT DONNÉES MÉTIER</strong><br>\n";
echo "Sauvegarder : numéro, nom_demandeur, type_infrastructure, région, ville, adresse, date_autorisation<br>\n";
echo "(Exclure coordonnees_gps et score_matching_osm)\n";
echo "</div>\n";

echo "<div class='step'>\n";
echo "<span class='step-number'>3</span>\n";
echo "<strong>SUPPRESSION CASCADE</strong><br>\n";
echo "DELETE FROM dossiers WHERE est_historique = 1<br>\n";
echo "(CASCADE supprimera automatiquement les enregistrements liés)\n";
echo "</div>\n";

echo "<div class='step'>\n";
echo "<span class='step-number'>4</span>\n";
echo "<strong>RÉIMPORT PROPRE</strong><br>\n";
echo "INSERT stations historiques avec données métier uniquement (sans GPS)<br>\n";
echo "→ Statut = 'historique_autorise'<br>\n";
echo "→ coordonnees_gps = NULL<br>\n";
echo "→ Visibles dans le registre public mais pas sur la carte\n";
echo "</div>\n";

echo "<div class='step'>\n";
echo "<span class='step-number'>5</span>\n";
echo "<strong>AJOUT PROGRESSIF GPS (ULTÉRIEUR)</strong><br>\n";
echo "Créer interface admin pour ajouter GPS manuellement :<br>\n";
echo "• Via terrain (inspection physique)<br>\n";
echo "• Via opérateurs (demande GPS précis)<br>\n";
echo "• Via géocodage adresse (si disponible)<br>\n";
echo "→ GPS de QUALITÉ ajoutés progressivement\n";
echo "</div>\n";

echo "</div>\n";

echo "<div class='warning'>\n";
echo "<strong>⚠️ CONDITIONS DE VALIDITÉ :</strong><br>\n";
echo "Cette stratégie est optimale SI :<br>\n";
echo "1. ✅ Les GPS historiques ne sont PAS utilisés pour des décisions critiques<br>\n";
echo "2. ✅ La carte publique peut fonctionner sans afficher les historiques temporairement<br>\n";
echo "3. ✅ Vous avez un backup complet avant opération<br>\n";
echo "4. ✅ Vous pouvez obtenir les vrais GPS ultérieurement (terrain/opérateurs)\n";
echo "</div>\n";

echo "<div class='success'>\n";
echo "<strong>✅ BÉNÉFICES ATTENDUS :</strong><br>\n";
echo "• Base de données propre et saine<br>\n";
echo "• Pas de doublons GPS<br>\n";
echo "• Nouvelles stations non impactées<br>\n";
echo "• Ajout GPS de qualité progressivement<br>\n";
echo "• Respect règle métier : distance 500m calculée uniquement sur nouvelles stations<br>\n";
echo "• Gain de temps : 1 jour vs 5 jours\n";
echo "</div>\n";

echo "</div>\n";

// Alternative hybride
echo "<h2>🔀 Alternative Hybride (Compromis)</h2>\n";

echo "<div class='neutral'>\n";
echo "<strong>Si vous devez ABSOLUMENT garder les GPS historiques :</strong><br><br>\n";
echo "<strong>Option 3 : Suppression sélective + Correction ciblée</strong>\n";
echo "<ol>\n";
echo "<li>Supprimer uniquement les stations avec GPS dupliqués ($stations_doublons stations)</li>\n";
echo "<li>Garder les stations avec GPS uniques ({$stats_historiques['avec_gps']} - $stations_doublons stations)</li>\n";
echo "<li>Recréer les supprimées SANS GPS</li>\n";
echo "<li>Corriger manuellement les GPS uniques suspects via interface admin</li>\n";
echo "</ol>\n";
echo "→ <strong>Effort :</strong> 2-3 jours (compromis entre les deux stratégies)\n";
echo "</div>\n";

echo "<div style='text-align: center; margin: 40px 0;'>\n";
echo "<a href='execute_strategy_2.php' class='btn' style='display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 1.1em; box-shadow: 0 4px 6px rgba(0,0,0,0.2);'>🚀 Générer le Script de Stratégie 2</a>\n";
echo "</div>\n";

echo "</div>\n</body>\n</html>\n";
?>
