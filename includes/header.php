<?php
require_once __DIR__ . '/../config/app.php';

// Charger les fonctions huitaine si l'utilisateur est connecté
if (isLoggedIn() && file_exists(__DIR__ . '/../includes/huitaine_functions.php')) {
    require_once __DIR__ . '/../includes/huitaine_functions.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo isset($page_title) ? $page_title . ' - SGDI' : 'SGDI - Système de Gestion des Dossiers d\'Implantation'; ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="Système de Gestion des Dossiers d'Implantation pour les infrastructures pétrolières - MINEE DPPG Cameroun">
    <meta name="keywords" content="SGDI, MINEE, DPPG, implantation, station-service, GPL, Cameroun">
    <meta name="author" content="MINEE - Direction des Produits Pétroliers et du Gaz">
    <meta name="robots" content="noindex, nofollow">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SGDI">
    <meta name="application-name" content="SGDI">
    <meta name="msapplication-TileColor" content="#0d6efd">
    <meta name="msapplication-tap-highlight" content="no">

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo url('manifest.json'); ?>">

    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo asset('images/icons/icon-72x72.png'); ?>">
    <link rel="apple-touch-icon" sizes="96x96" href="<?php echo asset('images/icons/icon-96x96.png'); ?>">
    <link rel="apple-touch-icon" sizes="128x128" href="<?php echo asset('images/icons/icon-128x128.png'); ?>">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo asset('images/icons/icon-144x144.png'); ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo asset('images/icons/icon-152x152.png'); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo asset('images/icons/icon-192x192.png'); ?>">
    <link rel="apple-touch-icon" sizes="384x384" href="<?php echo asset('images/icons/icon-384x384.png'); ?>">
    <link rel="apple-touch-icon" sizes="512x512" href="<?php echo asset('images/icons/icon-512x512.png'); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('images/icons/icon-96x96.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo asset('images/icons/icon-72x72.png'); ?>">

    <!-- Microsoft Tiles -->
    <meta name="msapplication-config" content="<?php echo url('browserconfig.xml'); ?>")

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">
    <!-- Buttons System CSS -->
    <link href="<?php echo asset('css/buttons.css'); ?>" rel="stylesheet">
    <!-- Theme CSS -->
    <link href="<?php echo asset('css/theme.css'); ?>" rel="stylesheet">
    <!-- Responsive CSS -->
    <link href="<?php echo asset('css/responsive.css'); ?>" rel="stylesheet">
    <!-- Dark Mode CSS -->
    <link href="<?php echo asset('css/dark-mode.css'); ?>" rel="stylesheet">
    <!-- Accessibility CSS -->
    <link href="<?php echo asset('css/accessibility.css'); ?>" rel="stylesheet">
    <!-- Advanced Tables CSS -->
    <link href="<?php echo asset('css/advanced-tables.css'); ?>" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo url('dashboard.php'); ?>">
            <i class="fas fa-building"></i> SGDI
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('dashboard.php'); ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>

                <?php if (hasAnyRole(['chef_service', 'admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dossiersDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-folder"></i> Dossiers
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo url('modules/dossiers/create.php'); ?>">Créer un dossier</a></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/dossiers/list.php'); ?>">Liste des dossiers</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/carte/index.php'); ?>">
                            <i class="fas fa-map-marked-alt"></i> Carte des infrastructures
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasRole('billeteur')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/dossiers/list.php?statut=en_cours'); ?>">
                        <i class="fas fa-money-bill"></i> Paiements
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasRole('cadre_daj')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/daj/list.php'); ?>">
                        <i class="fas fa-gavel"></i> Analyses DAJ
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasRole('cadre_dppg')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/dossiers/list.php?statut=analyse_daj'); ?>">
                        <i class="fas fa-search"></i> Inspections
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasRole('chef_commission')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="commissionDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i> Commission
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo url('modules/chef_commission/dashboard.php'); ?>">Tableau de bord</a></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/chef_commission/list.php?statut=inspecte'); ?>">Inspections à valider</a></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/chef_commission/list.php'); ?>">Tous mes dossiers</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasRole('directeur')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/dossiers/list.php?statut=validation_commission'); ?>">
                        <i class="fas fa-check-circle"></i> Validations
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/huitaine/list.php'); ?>">
                        <i class="fas fa-clock"></i> Huitaines
                        <?php
                        // Afficher un badge si des huitaines urgentes existent
                        $stats_h = getStatistiquesHuitaine();
                        if ($stats_h['urgents'] > 0 || $stats_h['expires'] > 0):
                        ?>
                        <span class="badge bg-<?php echo $stats_h['expires'] > 0 ? 'danger' : 'warning'; ?> ms-1">
                            <?php echo $stats_h['urgents'] + $stats_h['expires']; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasAnyRole(['admin', 'chef_service'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('modules/admin_gps/index.php'); ?>">
                        <i class="fas fa-map-marked-alt"></i> Gestion GPS
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasRole('admin')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i> Administration
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo url('modules/users/list.php'); ?>">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/permissions/index.php'); ?>">
                            <i class="fas fa-shield-alt"></i> Permissions
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <!-- Bouton de changement de thème -->
                <li class="nav-item">
                    <button id="theme-toggle" class="btn btn-link nav-link" type="button" title="Changer le thème">
                        <i class="fas fa-sun"></i>
                    </button>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i>
                        <?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                        <small class="text-light opacity-75">(<?php echo getRoleLabel($_SESSION['user_role']); ?>)</small>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo url('modules/users/profile.php'); ?>">
                            <i class="fas fa-user-edit"></i> Mon profil
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo url('logout.php'); ?>">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-4">
    <?php
    // Afficher les messages flash
    $flash = getFlashMessage();
    if ($flash):
        $alert_class = $flash['type'] === 'error' ? 'alert-danger' : 'alert-' . $flash['type'];
    ?>
    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
        <?php echo sanitize($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>