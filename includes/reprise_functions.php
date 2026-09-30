<?php
/**
 * Reprise d'une station existante par un autre marketer
 *
 * Un dossier de nature « reprise » est lié à la station reprise (dossier historique ou autorisé).
 * Il en reprend l'emplacement ; à l'approbation ministérielle, l'ancien dossier passe au statut
 * « repris » et la station continue sous le nouveau dossier : même point sur les cartes, même ligne
 * au registre public, nouvelle dénomination (avec la mention « anciennement … »).
 */

// Statuts d'une station en activité pouvant être reprise
const REPRISE_STATUTS_REPRENABLES = ['autorise', 'historique_autorise'];

/**
 * Migration 2026_09_30_reprise_stations appliquée ?
 * (le code reste utilisable avant, sans la fonctionnalité)
 */
function repriseDisponible() {
    global $pdo;
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'dossier_repris_id'")->fetchColumn();
        } catch (Exception $e) {
            $ok = false;
        }
    }
    return $ok;
}

/**
 * Stations existantes pouvant être reprises, recherchées par numéro, nom, opérateur ou ville
 */
function repriseRechercherStations($q, $type, $limite = 15) {
    global $pdo;
    $q = trim($q);
    if (mb_strlen($q) < 2) return [];
    $marqueurs = implode(',', array_fill(0, count(REPRISE_STATUTS_REPRENABLES), '?'));
    $stmt = $pdo->prepare("SELECT id, numero, nom_demandeur, operateur_proprietaire, entreprise_beneficiaire, statut,
                                  region, departement, arrondissement, ville, quartier, lieu_dit, coordonnees_gps, source_gps
                           FROM dossiers
                           WHERE statut IN ($marqueurs) AND type_infrastructure = ?
                           AND (numero LIKE ? OR nom_demandeur LIKE ? OR operateur_proprietaire LIKE ? OR ville LIKE ? OR quartier LIKE ?)
                           ORDER BY ville, nom_demandeur
                           LIMIT " . (int) $limite);
    $motif = '%' . $q . '%';
    $stmt->execute(array_merge(REPRISE_STATUTS_REPRENABLES, [$type, $motif, $motif, $motif, $motif, $motif]));
    return $stmt->fetchAll();
}

/**
 * Station pouvant être reprise par un dossier de ce type, ou null
 */
function repriseStationValide($station_id, $type) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ? AND type_infrastructure = ?");
    $stmt->execute([(int) $station_id, $type]);
    $station = $stmt->fetch();
    return $station && in_array($station['statut'], REPRISE_STATUTS_REPRENABLES, true) ? $station : null;
}

/**
 * Reprend l'emplacement de la station si le dossier n'a pas encore de coordonnées
 */
function repriseCopierPosition($dossier_id, array $station) {
    global $pdo;
    if (empty($station['coordonnees_gps'])) return false;
    $stmt = $pdo->prepare("UPDATE dossiers SET coordonnees_gps = ?, latitude = ?, longitude = ?, source_gps = ?
                           WHERE id = ? AND (coordonnees_gps IS NULL OR coordonnees_gps = '')");
    $coords = array_map('trim', explode(',', $station['coordonnees_gps']));
    $source = mb_substr('Station reprise (' . $station['numero'] . ')'
            . (($station['source_gps'] ?? '') === 'Centre de la localité (approximatif)' ? ' – approximatif' : ''), 0, 100);
    $stmt->execute([$station['coordonnees_gps'], $coords[0] ?? null, $coords[1] ?? null, $source, $dossier_id]);
    return $stmt->rowCount() === 1;
}

/**
 * Lie un dossier de reprise à la station reprise (création ou correction depuis la fiche)
 */
function repriseLier($dossier_id, $station_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, numero, type_infrastructure, sous_type FROM dossiers WHERE id = ?");
    $stmt->execute([$dossier_id]);
    $dossier = $stmt->fetch();
    if (!$dossier || $dossier['sous_type'] !== 'reprise' || (int) $station_id === (int) $dossier_id) return false;

    $station = repriseStationValide($station_id, $dossier['type_infrastructure']);
    if (!$station) return false;

    $pdo->prepare("UPDATE dossiers SET dossier_repris_id = ? WHERE id = ?")->execute([$station['id'], $dossier_id]);
    $position = repriseCopierPosition($dossier_id, $station);
    addHistoriqueDossier($dossier_id, $user_id, 'lien_reprise',
        'Reprise de la station ' . $station['numero'] . ' (' . $station['nom_demandeur'] . ', ' . $station['ville'] . ')'
        . ($position ? ' ; emplacement GPS repris de la station' : ''));
    return $station;
}

/**
 * Retire le lien (erreur de saisie), tant que la décision n'est pas prise
 */
function repriseDelier($dossier_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE dossiers SET dossier_repris_id = NULL WHERE id = ? AND dossier_repris_id IS NOT NULL AND statut NOT IN ('autorise', 'rejete')");
    $stmt->execute([$dossier_id]);
    if ($stmt->rowCount() !== 1) return false;
    addHistoriqueDossier($dossier_id, $user_id, 'lien_reprise', 'Lien avec la station reprise retiré');
    return true;
}

/**
 * Approbation ministérielle d'une reprise : l'ancienne station passe au statut « repris »
 * (appelée dans la transaction de la décision)
 */
function repriseAppliquerApprobation(array $dossier, $numero_arrete, $user_id) {
    global $pdo;
    if (!repriseDisponible() || $dossier['sous_type'] !== 'reprise' || empty($dossier['dossier_repris_id'])) return false;

    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
    $stmt->execute([$dossier['dossier_repris_id']]);
    $station = $stmt->fetch();
    if (!$station) return false;

    repriseCopierPosition($dossier['id'], $station);

    $marqueurs = implode(',', array_fill(0, count(REPRISE_STATUTS_REPRENABLES), '?'));
    $stmt = $pdo->prepare("UPDATE dossiers SET statut = 'repris', date_modification = NOW() WHERE id = ? AND statut IN ($marqueurs)");
    $stmt->execute(array_merge([$station['id']], REPRISE_STATUTS_REPRENABLES));
    if ($stmt->rowCount() !== 1) return false;

    $nouveau = $dossier['operateur_proprietaire'] ?: $dossier['nom_demandeur'];
    addHistoriqueDossier($station['id'], $user_id, 'reprise_station',
        'Station reprise par ' . $nouveau . ' (dossier ' . $dossier['numero'] . ', arrêté n° ' . $numero_arrete . ')');
    addHistoriqueDossier($dossier['id'], $user_id, 'reprise_station',
        'Reprise approuvée : la station ' . $station['numero'] . ' (' . $station['nom_demandeur'] . ') continue sous ce dossier');
    return true;
}

/**
 * Liens de reprise d'un dossier : station reprise et dossiers de reprise qui le visent
 */
function repriseLiens($dossier) {
    global $pdo;
    $liens = ['station' => null, 'reprises' => []];
    if (!repriseDisponible()) return $liens;
    if (!empty($dossier['dossier_repris_id'])) {
        $stmt = $pdo->prepare("SELECT id, numero, nom_demandeur, operateur_proprietaire, ville, quartier, statut FROM dossiers WHERE id = ?");
        $stmt->execute([$dossier['dossier_repris_id']]);
        $liens['station'] = $stmt->fetch() ?: null;
    }
    $stmt = $pdo->prepare("SELECT id, numero, nom_demandeur, operateur_proprietaire, statut, date_creation FROM dossiers WHERE dossier_repris_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$dossier['id']]);
    $liens['reprises'] = $stmt->fetchAll();
    return $liens;
}

/**
 * Jointure SQL « anciennement » : dénomination de la station reprise
 * @param string $alias alias de la table dossiers dans la requête
 */
function repriseJointureAncien($alias = 'd') {
    if (!repriseDisponible()) return ['select' => ", NULL AS ancien_nom, NULL AS ancien_operateur, 0 AS est_reprise_station", 'join' => ''];
    return [
        'select' => ", anc.nom_demandeur AS ancien_nom, anc.operateur_proprietaire AS ancien_operateur, ($alias.dossier_repris_id IS NOT NULL) AS est_reprise_station",
        'join' => " LEFT JOIN dossiers anc ON anc.id = $alias.dossier_repris_id AND $alias.statut IN ('autorise', 'historique_autorise')",
    ];
}
