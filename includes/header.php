<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/ui.php';

// Charger les fonctions huitaine si l'utilisateur est connecté
if (isLoggedIn() && file_exists(__DIR__ . '/../includes/huitaine_functions.php')) {
    require_once __DIR__ . '/../includes/huitaine_functions.php';
}

/**
 * Menu de navigation latérale selon le rôle et les permissions.
 * Chaque lien : [icône, libellé, chemin, badge (int), badge d'alerte (bool)]
 */
function sgdiNavigation() {
    global $pdo;
    $role = $_SESSION['user_role'] ?? '';
    $compter = function ($sql) use ($pdo) {
        try { return (int) $pdo->query($sql)->fetchColumn(); } catch (Exception $e) { return 0; }
    };

    $principal = [['fa-gauge-high', 'Tableau de bord', 'dashboard.php']];
    if (hasAnyPermission(['dossiers.list', 'dossiers.view_all'])) {
        $principal[] = ['fa-folder-open', 'Dossiers', 'modules/dossiers/list.php'];
    }
    if (hasPermission('dossiers.create')) {
        $principal[] = ['fa-folder-plus', 'Nouveau dossier', 'modules/dossiers/create.php'];
    }
    if (hasPermission('carte.view')) {
        $principal[] = ['fa-map-location-dot', 'Carte des infrastructures', 'modules/carte/index.php'];
    }
    $sections = [['Principal', $principal]];

    $huitaines = null;
    if (in_array($role, ['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'], true) && function_exists('getStatistiquesHuitaine')) {
        $h = getStatistiquesHuitaine();
        $huitaines = ['fa-hourglass-half', 'Huitaines', 'modules/huitaine/list.php', $h['urgents'] + $h['expires'], $h['expires'] > 0];
    }

    switch ($role) {
        case 'chef_service':
            $sections[] = ['Instruction', array_filter([
                $huitaines,
                ['fa-file-invoice-dollar', 'Notes de frais', 'modules/notes_frais/list.php'],
                ['fa-money-check-dollar', 'Paiements', 'modules/paiements/list.php'],
            ])];
            $sections[] = ['Circuit de décision', [
                ['fa-stamp', 'Visas à apposer', 'modules/dossiers/viser_inspections.php', $compter("SELECT COUNT(*) FROM dossiers WHERE statut = 'inspecte'")],
            ]];
            $sections[] = ['Gestion', [
                ['fa-gears', 'Gestion opérationnelle', 'modules/dossiers/list.php?statut=autorise'],
                ['fa-location-crosshairs', 'Gestion GPS', 'modules/admin_gps/index.php'],
                ['fa-chart-line', 'Tableau de bord avancé', 'modules/chef_service/dashboard_avance.php'],
            ]];
            break;
        case 'billeteur':
            $sections[] = ['Paiements', [
                ['fa-money-bill', 'Enregistrer un paiement', 'modules/dossiers/list.php?statut=en_cours'],
                ['fa-money-check-dollar', 'Paiements enregistrés', 'modules/paiements/list.php'],
                ['fa-bell', 'Retards de paiement', 'modules/paiements/retards.php'],
            ]];
            break;
        case 'cadre_daj':
            $sections[] = ['Instruction', array_filter([
                ['fa-gavel', 'Analyses juridiques', 'modules/daj/list.php'],
                $huitaines,
            ])];
            break;
        case 'cadre_dppg':
            $sections[] = ['Instruction', array_filter([
                ['fa-clipboard-check', 'Inspections', 'modules/fiche_inspection/list_dossiers.php'],
                $huitaines,
            ])];
            break;
        case 'chef_commission':
            $sections[] = ['Commission', [
                ['fa-clipboard-list', 'Mon espace commission', 'modules/chef_commission/dashboard.php'],
                ['fa-check-double', 'Inspections à valider', 'modules/chef_commission/list.php?statut=inspecte', $compter("SELECT COUNT(*) FROM dossiers WHERE statut = 'inspecte'")],
                ['fa-users', 'Tous mes dossiers', 'modules/chef_commission/list.php'],
            ]];
            break;
        case 'sous_directeur':
            $sections[] = ['Circuit de décision', [
                ['fa-stamp', 'Visas à apposer', 'modules/sous_directeur/dashboard.php', $compter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_chef_service'")],
                ['fa-check-double', 'Mes dossiers visés', 'modules/sous_directeur/mes_dossiers_vises.php'],
            ]];
            break;
        case 'directeur':
            $sections[] = ['Circuit de décision', [
                ['fa-stamp', 'Visas à apposer', 'modules/directeur/dashboard.php', $compter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_sous_directeur'")],
            ]];
            break;
        case 'cabinet':
            $sections[] = ['Décision', [
                ['fa-gavel', 'Décisions à prendre', 'modules/ministre/dashboard.php', $compter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'")],
            ]];
            break;
        case 'admin':
            $sections[] = ['Instruction', array_filter([$huitaines])];
            $sections[] = ['Administration', [
                ['fa-users', 'Utilisateurs', 'modules/users/list.php'],
                ['fa-shield-halved', 'Permissions', 'modules/permissions/index.php'],
                ['fa-chart-line', 'Tableau de bord avancé', 'modules/admin/dashboard_avance.php'],
                ['fa-envelope-open-text', 'Journal des e-mails', 'modules/admin/email_logs.php'],
                ['fa-file-import', 'Import historique', 'modules/import_historique/index.php'],
                ['fa-globe-africa', 'Extraction OSM', 'modules/osm_extraction/index.php'],
                ['fa-location-crosshairs', 'Gestion GPS', 'modules/admin_gps/index.php'],
            ]];
            break;
    }

    $sections[] = ['Référentiels', [['fa-book-open', 'Registre public', 'modules/registre_public/index.php']]];
    return array_values(array_filter($sections, function ($s) { return !empty($s[1]); }));
}

// Lien actif : même script que la page courante (les paramètres d'URL départagent les doublons)
function sgdiNavActive($chemin) {
    $script = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $cible = parse_url(url($chemin));
    if (!$script || $script !== ($cible['path'] ?? '')) {
        return false;
    }
    if (empty($cible['query'])) {
        return empty($_SERVER['QUERY_STRING']);
    }
    parse_str($cible['query'], $attendus);
    foreach ($attendus as $k => $v) {
        if (($_GET[$k] ?? null) !== $v) return false;
    }
    return true;
}

// Dernières notifications de l'utilisateur
function sgdiNotifications($limite = 5) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT titre, message, dossier_id, lue, date_creation FROM notifications
                               WHERE user_id = ? ORDER BY date_creation DESC LIMIT " . (int) $limite);
        $stmt->execute([$_SESSION['user_id']]);
        $liste = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lue = 0");
        $stmt->execute([$_SESSION['user_id']]);
        return [$liste, (int) $stmt->fetchColumn()];
    } catch (Exception $e) {
        return [[], 0];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo isset($page_title) ? sanitize($page_title) . ' - SGDI' : 'SGDI - Système de Gestion des Dossiers d\'Implantation'; ?></title>
    <?php echo uiThemeInitScript(); ?>

    <meta name="description" content="Système de Gestion des Dossiers d'Implantation pour les infrastructures pétrolières - MINEE DPPG Cameroun">
    <meta name="robots" content="noindex, nofollow">

    <!-- PWA -->
    <meta name="theme-color" content="#2c3e50">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SGDI">
    <meta name="application-name" content="SGDI">
    <link rel="manifest" href="<?php echo url('manifest.json'); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo asset('images/icons/icon-192x192.png'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo asset('images/favicon.svg'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo asset('images/icons/icon-96x96.png'); ?>">
    <meta name="msapplication-config" content="<?php echo url('browserconfig.xml'); ?>">

    <!-- Ouvrir les connexions aux CDN au plus tôt -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin><!-- polices Font Awesome -->
    <link rel="preconnect" href="https://cdn.datatables.net">
    <link rel="preconnect" href="https://code.jquery.com">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <!-- Anciennes feuilles de style : conservées tant que tous les modules ne sont pas migrés -->
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/buttons.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/theme.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/responsive.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/dark-mode.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/accessibility.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/advanced-tables.css'); ?>" rel="stylesheet">
    <!-- Socle de design v2 (en dernier : il a la priorité) -->
    <link href="<?php echo asset('css/sgdi-ui.css'); ?>" rel="stylesheet">
    <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body class="sgdi">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<?php if (isLoggedIn()): ?>
<?php
    $nav_sections = sgdiNavigation();
    list($notifs, $notifs_non_lues) = sgdiNotifications();
?>
<div class="app-shell">
    <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Navigation principale">
        <a class="app-brand" href="<?php echo url('dashboard.php'); ?>">
            <span class="app-brand-mark"><i class="fas fa-gas-pump" aria-hidden="true"></i></span>
            <span class="app-brand-name">SGDI<span class="app-brand-sub">MINEE · DPPG</span></span>
        </a>
        <nav class="app-nav">
            <?php foreach ($nav_sections as $section): ?>
            <div class="app-nav-title"><?php echo sanitize($section[0]); ?></div>
            <?php foreach ($section[1] as $lien): ?>
            <a class="app-nav-link<?php echo sgdiNavActive($lien[2]) ? ' active' : ''; ?>" href="<?php echo url($lien[2]); ?>"<?php echo sgdiNavActive($lien[2]) ? ' aria-current="page"' : ''; ?>>
                <i class="fas <?php echo $lien[0]; ?>" aria-hidden="true"></i> <?php echo sanitize($lien[1]); ?>
                <?php if (!empty($lien[3])): ?>
                <span class="app-nav-badge<?php echo !empty($lien[4]) ? ' is-alert' : ''; ?>"><?php echo (int) $lien[3]; ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="app-sidebar-footer">Système de Gestion des Dossiers d'Implantation · <?php echo date('Y'); ?></div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="app-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Ouvrir le menu">
                <i class="fas fa-bars"></i>
            </button>
            <?php if (hasAnyPermission(['dossiers.list', 'dossiers.view_all'])): ?>
            <form class="app-search d-none d-md-block" action="<?php echo url('modules/dossiers/list.php'); ?>" method="get" role="search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input id="recherche-globale" class="form-control" type="search" name="search" placeholder="Rechercher un dossier, un demandeur, une ville…" aria-label="Rechercher un dossier" value="<?php echo sanitize($_GET['search'] ?? ''); ?>">
                <kbd>/</kbd>
            </form>
            <?php endif; ?>

            <div class="ms-auto d-flex align-items-center gap-1">
                <button id="theme-toggle" class="app-icon-btn" type="button" aria-label="Basculer le thème clair / sombre" title="Thème clair / sombre">
                    <i class="fas fa-circle-half-stroke"></i>
                </button>

                <div class="dropdown">
                    <button class="app-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="Notifications<?php echo $notifs_non_lues ? ' (' . $notifs_non_lues . ' non lues)' : ''; ?>">
                        <i class="fas fa-bell"></i><?php if ($notifs_non_lues): ?><span class="dot"></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: min(22rem, 90vw)">
                        <div class="px-3 py-2 border-bottom fw-semibold small">Notifications<?php echo $notifs_non_lues ? ' · ' . $notifs_non_lues . ' non lue(s)' : ''; ?></div>
                        <?php if (!$notifs): ?>
                        <div class="px-3 py-4 text-center text-muted-sgdi small">Aucune notification</div>
                        <?php else: foreach ($notifs as $n): ?>
                        <a class="dropdown-item py-2 text-wrap<?php echo $n['lue'] ? '' : ' fw-semibold'; ?>" href="<?php echo $n['dossier_id'] ? url('modules/dossiers/view.php?id=' . (int) $n['dossier_id']) : '#'; ?>">
                            <span class="d-block small"><?php echo sanitize($n['titre'] ?: $n['message']); ?></span>
                            <span class="d-block text-muted-sgdi" style="font-size: .72rem"><?php echo formatDateTime($n['date_creation']); ?></span>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="dropdown">
                    <a class="app-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="app-avatar"><?php echo sanitize(uiInitiales($_SESSION['user_prenom'] ?? '', $_SESSION['user_nom'] ?? '')); ?></span>
                        <span class="d-none d-sm-block">
                            <span class="app-user-name d-block"><?php echo sanitize(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '')); ?></span>
                            <span class="app-user-role"><?php echo sanitize(getRoleLabel($_SESSION['user_role'])); ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo url('modules/users/profile.php'); ?>"><i class="fas fa-user-pen me-2"></i>Mon profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo url('logout.php'); ?>"><i class="fas fa-right-from-bracket me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content" id="contenu"><div class="container-fluid px-0">
<?php else: ?>
<div class="app-main ms-0">
    <header class="app-topbar">
        <a class="app-brand p-0 border-0" href="<?php echo url('index.php'); ?>" style="color: var(--sgdi-text)">
            <span class="app-brand-mark"><i class="fas fa-gas-pump" aria-hidden="true"></i></span>
            <span class="app-brand-name">SGDI<span class="app-brand-sub" style="color: var(--sgdi-muted)">MINEE · DPPG</span></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button id="theme-toggle" class="app-icon-btn" type="button" aria-label="Basculer le thème clair / sombre"><i class="fas fa-circle-half-stroke"></i></button>
            <a class="btn btn-primary btn-sm" href="<?php echo url('index.php'); ?>">Se connecter</a>
        </div>
    </header>
    <main class="app-content mx-auto" id="contenu"><div class="container-fluid px-0">
<?php endif; ?>

    <?php
    // Messages flash
    $flash = getFlashMessage();
    if ($flash):
        $alert_class = $flash['type'] === 'error' ? 'alert-danger' : 'alert-' . $flash['type'];
    ?>
    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
        <?php echo sanitize($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
    <?php endif; ?>
