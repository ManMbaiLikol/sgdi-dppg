<?php
// Page de détail d'un dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../daj/functions.php';
require_once '../../includes/huitaine_functions.php';
require_once '../fiche_inspection/functions.php';

requireLogin();

$dossier_id = $_GET['id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer tous les détails du dossier
$dossier = getDossierDetails($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Récupérer les informations complémentaires avec vérification des permissions
$documents_info = getDocumentsDossierWithPermissions($dossier_id, $_SESSION['user_id'], $_SESSION['user_role']);
$documents = array_merge($documents_info['documents_dossier'], $documents_info['documents_inspection']);
$historique = getHistoriqueDossier($dossier_id);
$paiement = getPaiementDossier($dossier_id);
$inspections = getInspectionsDossier($dossier_id);
$analyse_daj = getAnalyseDAJ($dossier_id);

// Récupérer la fiche d'inspection si elle existe
$fiche_inspection = getFicheInspectionByDossier($dossier_id);

// Vérifier s'il y a une huitaine active
$huitaine_active = null;
if ($dossier['statut'] === 'en_huitaine' && $dossier['huitaine_active_id']) {
    $sql = "SELECT h.*,
            DATEDIFF(h.date_limite, NOW()) as jours_restants,
            TIMESTAMPDIFF(HOUR, NOW(), h.date_limite) as heures_restantes
            FROM huitaine h
            WHERE h.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier['huitaine_active_id']]);
    $huitaine_active = $stmt->fetch();

    if ($huitaine_active && $huitaine_active['jours_restants'] < 0) {
        $huitaine_active['jours_restants'] = -$huitaine_active['jours_restants'];
        $huitaine_active['expire'] = true;
    }
}

$page_title = 'Détail du dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- En-tête avec navigation -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-folder-open"></i>
                        Dossier <?php echo htmlspecialchars($dossier['numero']); ?>
                    </h1>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-lg badge-<?php echo getStatutClass($dossier['statut']); ?> mr-2">
                            <i class="fas fa-info-circle"></i>
                            <?php echo getStatutLabel($dossier['statut']); ?>
                        </span>
                        <?php if ($dossier['statut'] === 'autorise'): ?>
                        <?php
                        $statut_op = $dossier['statut_operationnel'] ?: 'operationnel';
                        ?>
                        <span class="badge badge-lg badge-<?php echo getStatutOperationnelClass($statut_op); ?> mr-2">
                            <i class="<?php echo getStatutOperationnelIcon($statut_op); ?>"></i>
                            <?php echo getStatutOperationnelLabel($statut_op); ?>
                        </span>
                        <?php endif; ?>
                        <small class="text-muted">
                            Créé le <?php echo date('d/m/Y H:i', strtotime($dossier['date_creation'])); ?>
                        </small>
                    </div>
                </div>
                <div>
                    <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                    <?php
                    $actions_possibles = getActionsPossibles($dossier, $_SESSION['user_role']);
                    if (!empty($actions_possibles) || $_SESSION['user_role'] === 'chef_service'):
                    ?>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Actions
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($_SESSION['user_role'] === 'chef_service'): ?>
                            <a class="dropdown-item" href="<?php echo url('modules/dossiers/edit.php?id=' . $dossier_id); ?>">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            <a class="dropdown-item" href="<?php echo url('modules/dossiers/localisation.php?id=' . $dossier_id); ?>">
                                <i class="fas fa-map-marker-alt"></i> Localisation GPS
                            </a>
                            <?php if ($dossier['coordonnees_gps'] && in_array($dossier['type_infrastructure'], ['station_service', 'reprise_station_service'])): ?>
                            <a class="dropdown-item" href="<?php echo url('modules/dossiers/validation_geospatiale.php?id=' . $dossier_id); ?>">
                                <i class="fas fa-ruler-combined"></i> Validation géospatiale
                                <?php if ($dossier['conformite_geospatiale'] === 'non_conforme'): ?>
                                    <span class="badge badge-sm bg-danger">Non conforme</span>
                                <?php elseif ($dossier['conformite_geospatiale'] === 'conforme'): ?>
                                    <span class="badge badge-sm bg-success">Conforme</span>
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <?php endif; ?>

                            <?php if (hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj']) && $dossier['statut'] !== 'en_huitaine'): ?>
                            <a class="dropdown-item text-warning" href="<?php echo url('modules/huitaine/creer.php?id=' . $dossier_id); ?>">
                                <i class="fas fa-clock"></i> Créer une huitaine
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php endif; ?>

                            <?php foreach ($actions_possibles as $action): ?>
                                <?php if ($action['action'] === 'upload_documents'): ?>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/dossiers/upload_documents.php?id=' . $dossier_id); ?>">
                                    <i class="fas fa-upload"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php elseif ($action['action'] === 'constituer_commission'): ?>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/dossiers/commission.php?id=' . $dossier_id); ?>">
                                    <i class="fas fa-users"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php elseif ($action['action'] === 'creer_note_frais'): ?>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/notes_frais/create.php?dossier_id=' . $dossier_id); ?>">
                                    <i class="fas fa-file-invoice-dollar"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php elseif ($action['action'] === 'marquer_autorise'): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/dossiers/marquer_autorise.php?id=' . $dossier_id); ?>">
                                    <i class="fas fa-check-circle"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php elseif ($action['action'] === 'gestion_operationnelle'): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/dossiers/gestion_operationnelle.php?id=' . $dossier_id); ?>">
                                    <i class="fas fa-cogs"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php elseif ($action['action'] === 'analyser_dossier'): ?>
                                <a class="dropdown-item text-<?php echo $action['class']; ?>" href="<?php echo url('modules/dossiers/analyse_daj.php?id=' . $dossier_id); ?>">
                                    <i class="fas fa-search"></i> <?php echo $action['label']; ?>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-tools"></i> Administration
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-danger" href="<?php echo url('modules/dossiers/delete.php?id=' . $dossier_id); ?>"
                               onclick="return confirm('ATTENTION: Êtes-vous sûr de vouloir supprimer définitivement ce dossier ? Cette action est irréversible et supprimera aussi tous les documents, paiements et historiques associés.')">
                                <i class="fas fa-trash"></i> Supprimer le dossier
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerte huitaine active -->
            <?php if ($huitaine_active): ?>
            <div class="alert alert-<?php echo getHuitaineBadgeClass($huitaine_active['jours_restants']); ?> mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="alert-heading mb-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            Huitaine de régularisation en cours
                        </h5>
                        <p class="mb-1">
                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $huitaine_active['type_irregularite'])); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Description:</strong> <?php echo sanitize($huitaine_active['description']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="display-4 mb-2 text-<?php echo getHuitaineBadgeClass($huitaine_active['jours_restants']); ?>">
                            <?php if (!isset($huitaine_active['expire'])): ?>
                                <?php echo $huitaine_active['jours_restants']; ?>
                            <?php else: ?>
                                <i class="fas fa-times-circle"></i>
                            <?php endif; ?>
                        </div>
                        <p class="mb-2">
                            <strong><?php echo formatCompteARebours($huitaine_active['jours_restants'], $huitaine_active['heures_restantes']); ?></strong>
                        </p>
                        <p class="mb-0">
                            <small>Date limite: <?php echo formatDateTime($huitaine_active['date_limite']); ?></small>
                        </p>
                        <?php if (hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])): ?>
                        <a href="<?php echo url('modules/huitaine/regulariser.php?id=' . $huitaine_active['id']); ?>"
                           class="btn btn-success btn-sm mt-2">
                            <i class="fas fa-check"></i> Régulariser
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Informations principales -->
                <div class="col-lg-8">
                    <!-- Informations générales -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i>
                                Informations générales
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Numéro :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['numero']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type :</strong></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($dossier['type_infrastructure']); ?>
                                                </span>
                                                <span class="badge badge-secondary">
                                                    <?php echo htmlspecialchars($dossier['sous_type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Statut :</strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo getStatutClass($dossier['statut']); ?>">
                                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Créé par :</strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?>
                                            </td>
                                        </tr>
                                        <?php if ($dossier['validation_geospatiale_faite'] && $dossier['conformite_geospatiale']): ?>
                                        <tr>
                                            <td><strong>Conformité géospatiale :</strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $dossier['conformite_geospatiale'] === 'conforme' ? 'success' : 'danger'; ?>">
                                                    <i class="fas fa-<?php echo $dossier['conformite_geospatiale'] === 'conforme' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                                    <?php echo strtoupper($dossier['conformite_geospatiale']); ?>
                                                </span>
                                                <br>
                                                <a href="<?php echo url('modules/dossiers/validation_geospatiale.php?id=' . $dossier_id); ?>"
                                                   class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-eye"></i> Voir les détails
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Date création :</strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($dossier['date_creation'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Dernière modification :</strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($dossier['date_modification'])); ?></td>
                                        </tr>
                                        <?php if ($dossier['coordonnees_gps']): ?>
                                        <?php
                                        require_once '../../includes/map_functions.php';
                                        $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
                                        ?>
                                        <tr>
                                            <td><strong>Coordonnées GPS :</strong></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($dossier['coordonnees_gps']); ?></code>
                                                <?php if ($coords): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marked-alt"></i>
                                                    <?php echo formatGPSCoordinates($coords['latitude'], $coords['longitude'], 'dms'); ?>
                                                </small>
                                                <br>
                                                <a href="<?php echo getGoogleMapsLink($coords['latitude'], $coords['longitude']); ?>"
                                                   target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-map"></i> Voir sur Google Maps
                                                </a>
                                                <a href="<?php echo url('modules/dossiers/localisation.php?id=' . $dossier_id); ?>"
                                                   class="btn btn-sm btn-outline-secondary mt-2">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations du demandeur -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user"></i>
                                Informations du demandeur
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Nom :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Contact :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['contact_demandeur'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Téléphone :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['telephone_demandeur'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['email_demandeur'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Localisation -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-map-marker-alt"></i>
                                Localisation
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Région :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['region'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Département :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['departement'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Arrondissement :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['arrondissement'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ville :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['ville'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Quartier :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['quartier'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Lieu-dit :</strong></td>
                                            <td><?php echo htmlspecialchars($dossier['lieu_dit'] ?? 'Non précisé'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations spécifiques selon le type -->
                    <?php if ($dossier['operateur_proprietaire'] || $dossier['entreprise_beneficiaire'] || $dossier['entreprise_installatrice']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building"></i>
                                Informations spécifiques
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <?php if ($dossier['operateur_proprietaire']): ?>
                                <tr>
                                    <td><strong>Opérateur propriétaire :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['operateur_proprietaire']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($dossier['entreprise_beneficiaire']): ?>
                                <tr>
                                    <td><strong>Entreprise bénéficiaire :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['entreprise_beneficiaire']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($dossier['entreprise_installatrice']): ?>
                                <tr>
                                    <td><strong>Entreprise installatrice :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['entreprise_installatrice']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($dossier['contrat_livraison']): ?>
                                <tr>
                                    <td><strong>Contrat de livraison :</strong></td>
                                    <td><?php echo nl2br(htmlspecialchars($dossier['contrat_livraison'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Analyse DAJ si disponible -->
                    <?php if ($analyse_daj): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-gavel"></i>
                                Analyse juridique DAJ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Statut d'analyse :</strong>
                                    <?php
                                    $badge_class = 'secondary';
                                    switch ($analyse_daj['statut_analyse']) {
                                        case 'conforme': $badge_class = 'success'; break;
                                        case 'conforme_avec_reserves': $badge_class = 'warning'; break;
                                        case 'non_conforme': $badge_class = 'danger'; break;
                                        case 'en_cours': $badge_class = 'info'; break;
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $analyse_daj['statut_analyse'])); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Date d'analyse :</strong>
                                    <?php echo date('d/m/Y H:i', strtotime($analyse_daj['date_analyse'])); ?>
                                </div>
                            </div>
                            <?php if ($analyse_daj['observations']): ?>
                            <div class="mb-3">
                                <strong>Observations :</strong>
                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($analyse_daj['observations'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($analyse_daj['documents_manquants']): ?>
                            <div class="mb-3">
                                <strong>Documents manquants :</strong>
                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($analyse_daj['documents_manquants'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($analyse_daj['recommandations']): ?>
                            <div class="mb-3">
                                <strong>Recommandations :</strong>
                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($analyse_daj['recommandations'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar avec infos complémentaires -->
                <div class="col-lg-4">
                    <!-- Commission -->
                    <?php if ($dossier['commission_date']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-users"></i>
                                Commission
                            </h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Constituée le :</strong><br>
                            <?php echo date('d/m/Y', strtotime($dossier['commission_date'])); ?></p>

                            <p><strong>Statut :</strong><br>
                            <span class="badge badge-info"><?php echo htmlspecialchars($dossier['commission_statut']); ?></span></p>

                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Chef de commission :</strong><br>
                                    <?php if ($dossier['chef_nom'] && $dossier['chef_prenom']): ?>
                                        <?php echo htmlspecialchars($dossier['chef_prenom'] . ' ' . $dossier['chef_nom']); ?>
                                        <br><small class="text-muted"><?php echo ucfirst($dossier['chef_commission_role']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Cadre DPPG :</strong><br>
                                    <?php if ($dossier['dppg_nom'] && $dossier['dppg_prenom']): ?>
                                        <?php echo htmlspecialchars($dossier['dppg_prenom'] . ' ' . $dossier['dppg_nom']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Cadre DAJ :</strong><br>
                                    <?php if ($dossier['daj_nom'] && $dossier['daj_prenom']): ?>
                                        <?php echo htmlspecialchars($dossier['daj_prenom'] . ' ' . $dossier['daj_nom']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Paiement -->
                    <?php if ($paiement): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-money-bill"></i>
                                Paiement
                            </h6>
                            <a href="<?php echo url('modules/paiements/export_pdf.php?id=' . $paiement['id']); ?>"
                               class="btn btn-success btn-sm" target="_blank"
                               title="Télécharger le reçu de paiement">
                                <i class="fas fa-file-pdf"></i> Reçu PDF
                            </a>
                        </div>
                        <div class="card-body">
                            <p><strong>Montant :</strong><br>
                            <?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FCFA</p>

                            <p><strong>Date :</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($paiement['date_paiement'])); ?></p>

                            <p><strong>Mode :</strong><br>
                            <?php echo htmlspecialchars($paiement['mode_paiement']); ?></p>

                            <?php if (!empty($paiement['reference_paiement'])): ?>
                            <p><strong>Référence :</strong><br>
                            <code><?php echo htmlspecialchars($paiement['reference_paiement']); ?></code></p>
                            <?php endif; ?>

                            <p><strong>Enregistré par :</strong><br>
                            <small><?php echo htmlspecialchars(($paiement['billeteur_prenom'] ?? '') . ' ' . ($paiement['billeteur_nom'] ?? '')); ?></small></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Documents -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-file-alt"></i>
                                Documents du dossier
                                <span class="badge badge-secondary"><?php echo count($documents); ?></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Utiliser les documents séparés par permissions
                            $documents_dossier = $documents_info['documents_dossier'];
                            $documents_inspection = $documents_info['documents_inspection'];
                            $peut_voir_documents_dossier = $documents_info['peut_voir_documents_dossier'];
                            ?>

                            <!-- Documents du dossier initial -->
                            <?php if (!$peut_voir_documents_dossier): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle"></i> Accès restreint</h6>
                                <p class="mb-0">
                                    Les documents du dossier ne sont visibles que par les membres de la commission désignée
                                    pour traiter ce dossier (chef de commission, cadre DPPG et cadre DAJ).
                                </p>
                            </div>
                            <?php elseif (!empty($documents_dossier)): ?>
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-folder"></i> Documents initiaux
                                <span class="badge bg-primary"><?php echo count($documents_dossier); ?></span>
                            </h6>

                            <!-- Présentation améliorée pour DAJ et cadre DPPG -->
                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle">
                                    <thead class="table-primary">
                                        <tr>
                                            <th width="5%" class="text-center">Type</th>
                                            <th width="30%">Document</th>
                                            <th width="15%">Catégorie</th>
                                            <th width="15%">Informations</th>
                                            <th width="20%">Métadonnées</th>
                                            <th width="10%" class="text-center">Statut</th>
                                            <th width="10%" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Labels et icônes pour les types de documents
                                        $types_config = [
                                            'lettre_motivee' => ['label' => 'Lettre motivée', 'icon' => 'fas fa-envelope', 'color' => 'primary'],
                                            'rapport_delegation_regionale' => ['label' => 'Rapport délégation', 'icon' => 'fas fa-building', 'color' => 'info'],
                                            'copie_cni' => ['label' => 'Copie CNI', 'icon' => 'fas fa-id-card', 'color' => 'secondary'],
                                            'contrat_bail_notarie' => ['label' => 'Contrat notarié', 'icon' => 'fas fa-file-contract', 'color' => 'success'],
                                            'plan_masse' => ['label' => 'Plan de masse', 'icon' => 'fas fa-drafting-compass', 'color' => 'warning'],
                                            'photos_site' => ['label' => 'Photos du site', 'icon' => 'fas fa-camera', 'color' => 'danger'],
                                            'contrat_livraison' => ['label' => 'Contrat livraison', 'icon' => 'fas fa-truck', 'color' => 'dark'],
                                            'autorisation_exploitation_miniere' => ['label' => 'Auth. exploitation', 'icon' => 'fas fa-hard-hat', 'color' => 'primary'],
                                            'autorisation_prefectorale' => ['label' => 'Auth. préfectorale', 'icon' => 'fas fa-stamp', 'color' => 'info'],
                                            'plan_installation' => ['label' => 'Plan installation', 'icon' => 'fas fa-blueprint', 'color' => 'success'],
                                            'note_calcul_structure' => ['label' => 'Note calcul', 'icon' => 'fas fa-calculator', 'color' => 'warning'],
                                            'autre' => ['label' => 'Autre document', 'icon' => 'fas fa-file', 'color' => 'secondary']
                                        ];

                                        foreach ($documents_dossier as $doc):
                                            $type_config = $types_config[$doc['type_document']] ?? $types_config['autre'];
                                            $taille_mb = round($doc['taille_fichier'] / (1024 * 1024), 2);
                                            $taille_display = $taille_mb > 0 ? $taille_mb . ' MB' : round($doc['taille_fichier'] / 1024, 0) . ' KB';
                                        ?>
                                        <tr class="table-light">
                                            <td class="text-center">
                                                <i class="<?php echo $type_config['icon']; ?> fa-lg text-<?php echo $type_config['color']; ?>"></i>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($doc['nom_original']); ?>">
                                                    <?php echo htmlspecialchars($doc['nom_original']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-tag"></i>
                                                    ID: <?php echo $doc['id']; ?> | Uploadé le <?php echo date('d/m/Y', strtotime($doc['date_upload'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $type_config['color']; ?>">
                                                    <?php echo $type_config['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <i class="fas fa-weight text-muted"></i> <?php echo $taille_display; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="fas fa-file-code"></i> <?php echo strtoupper(pathinfo($doc['nom_original'], PATHINFO_EXTENSION)); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <i class="fas fa-calendar text-muted"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($doc['date_upload'])); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars(($doc['uploader_prenom'] ?? '') . ' ' . ($doc['uploader_nom'] ?? '')); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check"></i> Disponible
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?php echo url('modules/documents/download.php?id=' . $doc['id']); ?>"
                                                       class="btn btn-outline-primary"
                                                       title="Télécharger le document">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <?php if (in_array($_SESSION['user_role'], ['cadre_daj', 'cadre_dppg'])): ?>
                                                    <button type="button"
                                                            class="btn btn-outline-info"
                                                            title="Prévisualiser"
                                                            onclick="previewDocument(<?php echo $doc['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Statistiques des documents pour DAJ/DPPG -->
                            <?php if (in_array($_SESSION['user_role'], ['cadre_daj', 'cadre_dppg'])): ?>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center py-2">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-file-alt"></i>
                                                Total documents
                                            </h6>
                                            <h4 class="mb-0"><?php echo count($documents_dossier); ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center py-2">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-weight"></i>
                                                Taille totale
                                            </h6>
                                            <h4 class="mb-0">
                                                <?php
                                                $taille_totale = array_sum(array_column($documents_dossier, 'taille_fichier'));
                                                echo round($taille_totale / (1024 * 1024), 1) . ' MB';
                                                ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center py-2">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-calendar"></i>
                                                Dernier upload
                                            </h6>
                                            <h6 class="mb-0">
                                                <?php
                                                if (!empty($documents_dossier)) {
                                                    $derniere_date = max(array_column($documents_dossier, 'date_upload'));
                                                    echo date('d/m/Y', strtotime($derniere_date));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body text-center py-2">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-list"></i>
                                                Types uniques
                                            </h6>
                                            <h4 class="mb-0">
                                                <?php
                                                $types_uniques = array_unique(array_column($documents_dossier, 'type_document'));
                                                echo count($types_uniques);
                                                ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php elseif ($peut_voir_documents_dossier): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Aucun document initial uploadé</strong>
                            </div>
                            <?php endif; ?>

                            <!-- Documents d'inspection -->
                            <?php if (!empty($documents_inspection)): ?>
                            <h6 class="text-warning mb-3">
                                <i class="fas fa-search"></i> Documents d'inspection
                                <span class="badge bg-warning text-dark"><?php echo count($documents_inspection); ?></span>
                            </h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="60" class="text-center">Type</th>
                                            <th width="30%">Document</th>
                                            <th width="20%">Informations</th>
                                            <th width="25%">Détails</th>
                                            <th width="15%" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $types_labels = [
                                            'note_inspection' => ['label' => 'Note d\'inspection', 'icon' => 'fas fa-sticky-note', 'color' => 'primary', 'desc' => 'Document préliminaire'],
                                            'lettre_inspection' => ['label' => 'Lettre d\'inspection', 'icon' => 'fas fa-envelope', 'color' => 'info', 'desc' => 'Convocation officielle'],
                                            'rapport_inspection' => ['label' => 'Rapport d\'inspection', 'icon' => 'fas fa-file-alt', 'color' => 'success', 'desc' => 'Rapport technique'],
                                            'decision_motivee' => ['label' => 'Décision motivée', 'icon' => 'fas fa-gavel', 'color' => 'warning', 'desc' => 'Décision finale']
                                        ];

                                        foreach ($types_labels as $type => $config):
                                            $doc_trouve = null;
                                            foreach ($documents_inspection as $doc) {
                                                if ($doc['type_document'] === $type) {
                                                    $doc_trouve = $doc;
                                                    break;
                                                }
                                            }
                                        ?>
                                        <tr <?php echo $doc_trouve ? 'class="table-success-subtle"' : 'class="table-light"'; ?>>
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="<?php echo $config['icon']; ?> fa-lg text-<?php echo $config['color']; ?> mb-1"></i>
                                                    <span class="badge bg-<?php echo $config['color']; ?> px-2 py-1" style="font-size: 0.7rem;">
                                                        <?php echo strtoupper(substr($config['label'], 0, 4)); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-<?php echo $config['color']; ?> mb-1" style="font-size: 0.9rem;">
                                                    <?php echo $config['label']; ?>
                                                </div>
                                                <?php if ($doc_trouve): ?>
                                                <div class="text-dark" style="font-size: 0.85rem; line-height: 1.3;">
                                                    <?php echo htmlspecialchars($doc_trouve['nom_original']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo $config['desc']; ?></small>
                                                <?php else: ?>
                                                <div class="text-muted fst-italic">Document non disponible</div>
                                                <small class="text-danger">En attente d'upload</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($doc_trouve): ?>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <i class="fas fa-weight text-muted me-1"></i>
                                                        <strong><?php echo number_format($doc_trouve['taille_fichier'] / 1024, 0); ?> KB</strong>
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-calendar text-muted me-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($doc_trouve['date_upload'])); ?>
                                                        <br><small class="text-muted"><?php echo date('H:i', strtotime($doc_trouve['date_upload'])); ?></small>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($doc_trouve): ?>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <i class="fas fa-user-shield text-muted me-1"></i>
                                                        <span class="text-success fw-bold">Équipe DPPG</span>
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <span class="text-success">Disponible</span>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <i class="fas fa-clock text-warning me-1"></i>
                                                        <span class="text-warning">En attente</span>
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-exclamation-circle text-danger me-1"></i>
                                                        <span class="text-danger">Non uploadé</span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($doc_trouve): ?>
                                                <a href="<?php echo url('modules/documents/download.php?id=' . $doc_trouve['id']); ?>"
                                                   class="btn btn-<?php echo $config['color']; ?> btn-sm"
                                                   title="Télécharger <?php echo $config['label']; ?>">
                                                    <i class="fas fa-download"></i>
                                                    <span class="d-none d-lg-inline ms-1">Télécharger</span>
                                                </a>
                                                <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm" disabled title="Document non disponible">
                                                    <i class="fas fa-ban"></i>
                                                    <span class="d-none d-lg-inline ms-1">Indisponible</span>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php elseif ($dossier['statut'] === 'inspecte' || in_array($dossier['statut'], ['valide', 'decide'])): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Documents d'inspection</h6>
                                <p class="mb-0">Les documents d'inspection ne sont pas encore disponibles ou ont été supprimés.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Inspections -->
                    <?php if ($inspections): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-search"></i>
                                Inspections
                                <span class="badge badge-secondary"><?php echo count($inspections); ?></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($inspections as $inspection): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <p class="mb-1">
                                        <strong><?php echo date('d/m/Y', strtotime($inspection['date_inspection'])); ?></strong><br>
                                        <small class="text-muted">
                                            par <?php echo htmlspecialchars(($inspection['inspecteur_prenom'] ?? '') . ' ' . ($inspection['inspecteur_nom'] ?? '')); ?>
                                        </small>
                                    </p>
                                    <?php if ($inspection['observations']): ?>
                                    <p class="small mb-0"><?php echo htmlspecialchars(substr($inspection['observations'], 0, 100)) . '...'; ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Fiche d'inspection -->
                    <?php if (in_array($_SESSION['user_role'], ['cadre_dppg', 'admin', 'chef_service', 'chef_commission'])): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-clipboard-list"></i>
                                Fiche d'inspection
                            </h6>
                            <?php if ($fiche_inspection): ?>
                                <span class="badge badge-<?php
                                    echo $fiche_inspection['statut'] === 'validee' ? 'success' :
                                        ($fiche_inspection['statut'] === 'signee' ? 'primary' : 'secondary');
                                ?>">
                                    <?php
                                        switch($fiche_inspection['statut']) {
                                            case 'brouillon': echo 'Brouillon'; break;
                                            case 'validee': echo 'Validée'; break;
                                            case 'signee': echo 'Signée'; break;
                                            default: echo ucfirst($fiche_inspection['statut']);
                                        }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($fiche_inspection): ?>
                                <p class="mb-2">
                                    <strong>Créée le :</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($fiche_inspection['date_creation'])); ?>
                                </p>
                                <?php if ($fiche_inspection['date_etablissement']): ?>
                                <p class="mb-2">
                                    <strong>Établie le :</strong><br>
                                    <?php echo date('d/m/Y', strtotime($fiche_inspection['date_etablissement'])); ?>
                                    <?php if ($fiche_inspection['lieu_etablissement']): ?>
                                        à <?php echo htmlspecialchars($fiche_inspection['lieu_etablissement']); ?>
                                    <?php endif; ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($fiche_inspection['inspecteur_nom'] || $fiche_inspection['inspecteur_prenom']): ?>
                                <p class="mb-3">
                                    <strong>Inspecteur :</strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($fiche_inspection['inspecteur_prenom'] . ' ' . $fiche_inspection['inspecteur_nom']); ?>
                                    </small>
                                </p>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <?php if (in_array($_SESSION['user_role'], ['cadre_dppg', 'admin']) && $fiche_inspection['statut'] === 'brouillon'): ?>
                                    <a href="<?php echo url('modules/fiche_inspection/edit.php?dossier_id=' . $dossier_id); ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Modifier la fiche
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php echo url('modules/fiche_inspection/edit.php?dossier_id=' . $dossier_id); ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Voir la fiche
                                    </a>
                                    <?php endif; ?>

                                    <a href="<?php echo url('modules/fiche_inspection/print_filled.php?dossier_id=' . $dossier_id); ?>"
                                       class="btn btn-sm btn-outline-success" target="_blank">
                                        <i class="fas fa-print"></i> Imprimer (remplie)
                                    </a>

                                    <a href="<?php echo url('modules/fiche_inspection/print_blank.php'); ?>"
                                       class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fas fa-file-alt"></i> Imprimer (vierge)
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle"></i>
                                    Aucune fiche d'inspection créée pour ce dossier
                                </div>
                                <?php if (in_array($_SESSION['user_role'], ['cadre_dppg', 'admin'])): ?>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo url('modules/fiche_inspection/edit.php?dossier_id=' . $dossier_id); ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Créer une fiche
                                    </a>

                                    <a href="<?php echo url('modules/fiche_inspection/print_blank.php'); ?>"
                                       class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fas fa-file-alt"></i> Imprimer formulaire vierge
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history"></i>
                                Historique des actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($historique): ?>
                                <div class="timeline">
                                    <?php foreach ($historique as $action): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title"><?php echo htmlspecialchars($action['action']); ?></h6>
                                                <p class="timeline-text"><?php echo htmlspecialchars($action['description']); ?></p>
                                                <div class="timeline-time">
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($action['date_action'])); ?>
                                                        par <?php echo htmlspecialchars(($action['prenom'] ?? '') . ' ' . ($action['nom'] ?? '')); ?>
                                                        <?php if ($action['ancien_statut'] && $action['nouveau_statut']): ?>
                                                        <br>
                                                        <span class="badge badge-sm badge-<?php echo getStatutClass($action['ancien_statut']); ?>">
                                                            <?php echo getStatutLabel($action['ancien_statut']); ?>
                                                        </span>
                                                        →
                                                        <span class="badge badge-sm badge-<?php echo getStatutClass($action['nouveau_statut']); ?>">
                                                            <?php echo getStatutLabel($action['nouveau_statut']); ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Aucun historique disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -34px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #007bff;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -28px;
    top: 12px;
    bottom: -20px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-title {
    margin-bottom: 5px;
    font-size: 1rem;
}

.timeline-text {
    margin-bottom: 10px;
    color: #6c757d;
}

.timeline-time {
    font-size: 0.875rem;
}

.badge-lg {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

/* Améliorations pour la présentation des documents DAJ/DPPG */
.table-responsive {
    border-radius: 0.375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.table-primary th {
    font-weight: 600;
    font-size: 0.9rem;
}

.document-stats-card {
    transition: transform 0.2s;
}

.document-stats-card:hover {
    transform: translateY(-2px);
}
</style>

<script>
// Fonction pour prévisualiser un document (DAJ/DPPG)
function previewDocument(documentId) {
    // Créer une modal pour prévisualiser le document
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye"></i> Prévisualisation du document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement de la prévisualisation...</p>
                </div>
                <div class="modal-footer">
                    <a href="<?php echo url('modules/documents/download.php?id='); ?>${documentId}"
                       class="btn btn-primary">
                        <i class="fas fa-download"></i> Télécharger
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();

    // Supprimer la modal quand elle est fermée
    modal.addEventListener('hidden.bs.modal', function () {
        document.body.removeChild(modal);
    });

    // Pour l'instant, on affiche juste un message
    setTimeout(() => {
        modal.querySelector('.modal-body').innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Prévisualisation en cours de développement</strong><br>
                Cette fonctionnalité sera bientôt disponible. Utilisez le bouton "Télécharger" pour accéder au document.
            </div>
        `;
    }, 1000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>